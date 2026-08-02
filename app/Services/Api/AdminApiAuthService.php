<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Models\User;
use App\Platform\Support\RequestContext;

/**
 * Human Admin API authentication: login, refresh rotation, logout, sessions.
 */
final class AdminApiAuthService
{
    /** @var list<string> */
    public const ADMIN_ROLES = [
        'moderator',
        'administrator',
        'super-administrator',
        'platform-administrator',
        'brand-administrator',
        'editor',
        'support',
        'finance',
        'marketing',
    ];

    /** @var list<string> */
    public const DEFAULT_HUMAN_SCOPES = [
        'providers:read',
        'providers:write',
        'stays:read',
        'stays:write',
        'drafts:read',
        'drafts:write',
        'drafts:approve',
        'imports:read',
        'imports:write',
        'sync:read',
        'analytics:read',
        'audit:read',
        'lifecycle:write',
        'recycle_bin:restore',
        'service_accounts:admin',
    ];

    /**
     * @return (
     *   array{
     *     access_token:string,
     *     refresh_token:string,
     *     token_type:string,
     *     expires_in:int,
     *     scopes:list<string>,
     *     user:array<string,mixed>
     *   }|
     *   array{
     *     mfa_required:true,
     *     mfa_token:string,
     *     token_type:string,
     *     expires_in:int,
     *     scopes:list<string>,
     *     user:array<string,mixed>,
     *     message:string
     *   }
     * )
     */
    public function login(string $email, string $password, Request $request, ?string $sessionLabel = null): array
    {
        $this->assertCredentialStoreReady();
        $email = strtolower(trim($email));
        $ip = $request->ip();
        $this->assertNotThrottled($email, $ip);

        $user = User::findByEmail($email);
        if (
            $user === null
            || ($user['status'] ?? '') === 'suspended'
            || ($user['deleted_at'] ?? null) !== null
            || !password_verify($password, (string) ($user['password_hash'] ?? ''))
        ) {
            $this->recordFailedLogin($email, $ip, $request);
            throw new AdminApiException(401, 'unauthenticated', 'Invalid email or password.');
        }

        if (!$this->userHasAdminRole((int) $user['id'])) {
            $this->recordFailedLogin($email, $ip, $request, 'role_denied');
            throw new AdminApiException(403, 'forbidden', 'This account is not permitted to use the Admin API.');
        }

        if (!$this->userAllowedInRestrictedMode((int) $user['id'], $user)) {
            $this->securityEvent('login_restricted_denied', 'user', (int) $user['id'], null, $request);
            throw new AdminApiException(
                403,
                'forbidden',
                'Admin API access is restricted on this deployment.'
            );
        }

        if ($this->mfaChallengeRequired((int) $user['id'])) {
            if (!$this->userHasEnabledMfa((int) $user['id'])) {
                $this->securityEvent('login_mfa_enrollment_required', 'user', (int) $user['id'], null, $request);
                throw new AdminApiException(
                    403,
                    'mfa_enrollment_required',
                    'Enroll MFA while ADMIN_API_MFA_REQUIRED is false, then enable the flag.',
                    [],
                    ['mfa_enforced' => true, 'enrolled' => false]
                );
            }

            $this->clearThrottle($email, $ip);
            $challenge = $this->issueMfaChallengeToken($user, $request);
            $this->securityEvent('login_mfa_required', 'user', (int) $user['id'], null, $request, [
                'access_token_id' => $challenge['access_token_id'],
            ]);

            return [
                'mfa_required' => true,
                'mfa_token' => $challenge['access_token'],
                'token_type' => 'Bearer',
                'expires_in' => $challenge['expires_in'],
                'scopes' => $challenge['scopes'],
                'user' => $this->publicUser($user),
                'message' => 'Complete MFA with POST /auth/mfa/verify using this mfa_token as Bearer.',
            ];
        }

        $this->clearThrottle($email, $ip);
        $bundle = $this->issueTokenBundle($user, $request, $sessionLabel);
        $this->securityEvent('login_succeeded', 'user', (int) $user['id'], null, $request, [
            'access_token_id' => $bundle['access_token_id'],
        ]);

        return $this->publicTokenResponse($bundle, $user);
    }

    /**
     * @return array{
     *   access_token:string,
     *   refresh_token:string,
     *   token_type:string,
     *   expires_in:int,
     *   scopes:list<string>,
     *   user:array<string,mixed>
     * }
     */
    public function refresh(string $refreshToken, Request $request): array
    {
        $this->assertCredentialStoreReady();
        $hash = AdminApiToken::hash($refreshToken);
        $row = Database::selectOne(
            'SELECT * FROM api_refresh_tokens WHERE token_hash = ? LIMIT 1',
            [$hash]
        );
        if ($row === null) {
            throw new AdminApiException(401, 'unauthenticated', 'Refresh token is invalid.');
        }
        if ($row['revoked_at'] !== null) {
            $this->revokeRefreshFamily((string) $row['family_id'], 'refresh_reuse');
            $this->securityEvent('refresh_reuse_detected', 'user', (int) $row['user_id'], null, $request, [
                'family_id' => $row['family_id'],
            ]);
            throw new AdminApiException(401, 'unauthenticated', 'Refresh token has been revoked.');
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            throw new AdminApiException(401, 'unauthenticated', 'Refresh token has expired.');
        }

        $user = User::find((int) $row['user_id']);
        if (
            $user === null
            || ($user['status'] ?? '') === 'suspended'
            || ($user['deleted_at'] ?? null) !== null
            || !$this->userHasAdminRole((int) $user['id'])
            || !$this->userAllowedInRestrictedMode((int) $user['id'], $user)
        ) {
            $this->revokeRefreshFamily((string) $row['family_id'], 'user_ineligible');
            throw new AdminApiException(401, 'unauthenticated', 'Refresh token is no longer valid.');
        }

        Database::query(
            'UPDATE api_refresh_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
            [$row['id']]
        );
        if (!empty($row['access_token_id'])) {
            Database::query(
                'UPDATE api_access_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
                [$row['access_token_id']]
            );
        }

        $bundle = $this->issueTokenBundle(
            $user,
            $request,
            is_string($row['session_label'] ?? null) ? (string) $row['session_label'] : null,
            (string) $row['family_id']
        );
        Database::query(
            'UPDATE api_refresh_tokens SET replaced_by = ? WHERE id = ?',
            [$bundle['refresh_token_id'], $row['id']]
        );
        $this->securityEvent('token_refreshed', 'user', (int) $user['id'], null, $request, [
            'family_id' => $row['family_id'],
        ]);

        return $this->publicTokenResponse($bundle, $user);
    }

    public function logout(?string $refreshToken, ?string $accessTokenId, Request $request, bool $allSessions = false): void
    {
        $userId = AdminApiContext::userId();
        if ($userId === null && $refreshToken !== null) {
            $row = Database::selectOne(
                'SELECT user_id, family_id FROM api_refresh_tokens WHERE token_hash = ? LIMIT 1',
                [AdminApiToken::hash($refreshToken)]
            );
            $userId = $row !== null ? (int) $row['user_id'] : null;
            if ($allSessions && $userId !== null) {
                $this->revokeAllUserSessions($userId);
            } elseif ($row !== null) {
                $this->revokeRefreshFamily((string) $row['family_id'], 'logout');
            }
        } elseif ($allSessions && $userId !== null) {
            $this->revokeAllUserSessions($userId);
        } else {
            if ($accessTokenId !== null) {
                Database::query(
                    'UPDATE api_access_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
                    [$accessTokenId]
                );
                $refresh = Database::selectOne(
                    'SELECT family_id FROM api_refresh_tokens WHERE access_token_id = ? LIMIT 1',
                    [$accessTokenId]
                );
                if ($refresh !== null) {
                    $this->revokeRefreshFamily((string) $refresh['family_id'], 'logout');
                }
            }
            if ($refreshToken !== null) {
                $row = Database::selectOne(
                    'SELECT family_id FROM api_refresh_tokens WHERE token_hash = ? LIMIT 1',
                    [AdminApiToken::hash($refreshToken)]
                );
                if ($row !== null) {
                    $this->revokeRefreshFamily((string) $row['family_id'], 'logout');
                }
            }
        }

        $this->securityEvent('logout', $userId !== null ? 'user' : 'anonymous', $userId, null, $request, [
            'all_sessions' => $allSessions,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function listSessions(int $userId): array
    {
        $rows = Database::select(
            'SELECT id, family_id, session_label, ip_address, user_agent, created_at, expires_at, revoked_at '
            . 'FROM api_refresh_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 100',
            [$userId]
        );

        $sessions = [];
        $seenFamilies = [];
        foreach ($rows as $row) {
            $family = (string) $row['family_id'];
            if (isset($seenFamilies[$family])) {
                continue;
            }
            $seenFamilies[$family] = true;
            $sessions[] = [
                'id' => $family,
                'label' => $row['session_label'],
                'ip_address' => $row['ip_address'],
                'user_agent' => $row['user_agent'],
                'created_at' => $this->iso((string) $row['created_at']),
                'expires_at' => $this->iso((string) $row['expires_at']),
                'active' => $row['revoked_at'] === null && strtotime((string) $row['expires_at']) >= time(),
            ];
        }

        return $sessions;
    }

    public function revokeSession(int $userId, string $familyId): void
    {
        $row = Database::selectOne(
            'SELECT id FROM api_refresh_tokens WHERE user_id = ? AND family_id = ? LIMIT 1',
            [$userId, $familyId]
        );
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Session not found.');
        }
        $this->revokeRefreshFamily($familyId, 'session_revoked');
    }

    public function authenticateAccessToken(string $rawToken, Request $request): bool
    {
        if (!Database::tableExists('api_access_tokens')) {
            return false;
        }

        $row = Database::selectOne(
            'SELECT * FROM api_access_tokens WHERE token_hash = ? LIMIT 1',
            [AdminApiToken::hash($rawToken)]
        );
        if ($row === null || $row['revoked_at'] !== null) {
            return false;
        }
        if (strtotime((string) $row['expires_at']) < time()) {
            return false;
        }

        $scopes = AdminApiScopes::normalize($row['scopes_json'] ?? []);
        $actorType = (string) ($row['actor_type'] ?? '');

        if ($actorType === 'user') {
            if (empty($row['user_id'])) {
                return false;
            }

            $user = User::find((int) $row['user_id']);
            if (
                $user === null
                || ($user['status'] ?? '') === 'suspended'
                || ($user['deleted_at'] ?? null) !== null
                || !$this->userHasAdminRole((int) $user['id'])
                || !$this->userAllowedInRestrictedMode((int) $user['id'], $user)
            ) {
                return false;
            }

            AdminApiContext::setUser($user, $scopes, (string) $row['id']);

            return true;
        }

        if ($actorType === 'service') {
            if (empty($row['client_id']) || !Database::tableExists('api_oauth_clients')) {
                return false;
            }

            $client = Database::selectOne(
                'SELECT * FROM api_oauth_clients WHERE id = ? LIMIT 1',
                [$row['client_id']]
            );
            if (
                $client === null
                || ($client['status'] ?? '') !== 'active'
                || (!empty($client['expires_at']) && strtotime((string) $client['expires_at']) < time())
            ) {
                return false;
            }

            try {
                AdminApiScopes::rejectForbiddenForService($scopes);
            } catch (AdminApiException) {
                return false;
            }

            AdminApiContext::setService($client, $scopes, (string) $row['id']);

            return true;
        }

        return false;
    }

    /** @return list<string> */
    public function scopesForUser(array $user): array
    {
        $scopes = self::DEFAULT_HUMAN_SCOPES;
        $roles = User::roleSlugs((int) $user['id']);
        if (in_array('super-administrator', $roles, true) || in_array('platform-administrator', $roles, true)) {
            $scopes[] = 'recycle_bin:purge';
        }

        return array_values(array_unique($scopes));
    }

    /**
     * @param array<string,mixed> $user
     * @return array{
     *   access_token:string,
     *   refresh_token:string,
     *   access_token_id:string,
     *   refresh_token_id:string,
     *   expires_in:int,
     *   scopes:list<string>
     * }
     */
    private function issueTokenBundle(
        array $user,
        Request $request,
        ?string $sessionLabel = null,
        ?string $familyId = null
    ): array {
        $scopes = $this->scopesForUser($user);
        $accessTtl = (int) Config::get('admin_api.access_token_ttl_seconds', 900);
        $refreshTtl = (int) Config::get('admin_api.refresh_token_ttl_seconds', 604800);
        $accessId = AdminApiToken::uuid();
        $refreshId = AdminApiToken::uuid();
        $familyId = $familyId ?? AdminApiToken::uuid();
        $accessToken = AdminApiToken::generate();
        $refreshToken = AdminApiToken::generate();
        $now = date('Y-m-d H:i:s');
        $accessExpiry = date('Y-m-d H:i:s', time() + $accessTtl);
        $refreshExpiry = date('Y-m-d H:i:s', time() + $refreshTtl);
        $requestId = RequestContext::hasRequestId() ? RequestContext::requestId() : null;

        Database::query(
            'INSERT INTO api_access_tokens (
                id, token_hash, actor_type, user_id, client_id, scopes_json, expires_at,
                request_id_created, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)',
            [
                $accessId,
                AdminApiToken::hash($accessToken),
                'user',
                (int) $user['id'],
                json_encode($scopes, JSON_THROW_ON_ERROR),
                $accessExpiry,
                $requestId,
                $request->ip(),
                $request->userAgent(),
                $now,
            ]
        );

        Database::query(
            'INSERT INTO api_refresh_tokens (
                id, token_hash, user_id, family_id, access_token_id, expires_at,
                session_label, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $refreshId,
                AdminApiToken::hash($refreshToken),
                (int) $user['id'],
                $familyId,
                $accessId,
                $refreshExpiry,
                $sessionLabel,
                $request->ip(),
                $request->userAgent(),
                $now,
            ]
        );

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'access_token_id' => $accessId,
            'refresh_token_id' => $refreshId,
            'expires_in' => $accessTtl,
            'scopes' => $scopes,
        ];
    }

    /**
     * @param array{
     *   access_token:string,
     *   refresh_token:string,
     *   expires_in:int,
     *   scopes:list<string>
     * } $bundle
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function publicTokenResponse(array $bundle, array $user): array
    {
        return [
            'access_token' => $bundle['access_token'],
            'refresh_token' => $bundle['refresh_token'],
            'token_type' => 'Bearer',
            'expires_in' => $bundle['expires_in'],
            'scopes' => $bundle['scopes'],
            'user' => $this->publicUser($user),
        ];
    }

    /** @param array<string,mixed> $user */
    public function publicUser(array $user): array
    {
        return [
            'id' => (int) $user['id'],
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'roles' => User::roleSlugs((int) $user['id']),
            'mfa_enabled' => $this->userHasEnabledMfa((int) $user['id']),
        ];
    }

    private function userHasAdminRole(int $userId): bool
    {
        return array_intersect(User::roleSlugs($userId), self::ADMIN_ROLES) !== [];
    }

    /** @param array<string,mixed> $user */
    private function userAllowedInRestrictedMode(int $userId, array $user): bool
    {
        if (!(bool) Config::get('admin_api.restricted', true)) {
            return true;
        }

        $allowed = Config::get('admin_api.allowed_user_ids', []);
        if (is_array($allowed) && $allowed !== []) {
            $ids = array_map('intval', $allowed);

            return in_array($userId, $ids, true);
        }

        return in_array('super-administrator', User::roleSlugs($userId), true);
    }

    private function mfaChallengeRequired(int $userId): bool
    {
        unset($userId);

        return (bool) Config::get('admin_api.mfa_required', false);
    }

    /**
     * @param array<string,mixed> $user
     * @return array{access_token:string,access_token_id:string,expires_in:int,scopes:list<string>}
     */
    private function issueMfaChallengeToken(array $user, Request $request): array
    {
        $ttl = (int) Config::get('admin_api.mfa_challenge_ttl_seconds', 300);
        $accessId = AdminApiToken::uuid();
        $accessToken = AdminApiToken::generate();
        $scopes = ['mfa:verify'];
        $now = date('Y-m-d H:i:s');
        $accessExpiry = date('Y-m-d H:i:s', time() + $ttl);
        $requestId = RequestContext::hasRequestId() ? RequestContext::requestId() : null;

        Database::query(
            'INSERT INTO api_access_tokens (
                id, token_hash, actor_type, user_id, client_id, scopes_json, expires_at,
                request_id_created, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)',
            [
                $accessId,
                AdminApiToken::hash($accessToken),
                'user',
                (int) $user['id'],
                json_encode($scopes, JSON_THROW_ON_ERROR),
                $accessExpiry,
                $requestId,
                $request->ip(),
                $request->userAgent(),
                $now,
            ]
        );

        return [
            'access_token' => $accessToken,
            'access_token_id' => $accessId,
            'expires_in' => $ttl,
            'scopes' => $scopes,
        ];
    }

    public function tokenIsMfaChallenge(): bool
    {
        $scopes = AdminApiContext::scopes();

        return AdminApiContext::isHuman()
            && $scopes === ['mfa:verify'];
    }

    /**
     * Exchange a successful MFA verify for a full human token bundle.
     *
     * @return array<string,mixed>
     */
    public function completeMfaChallenge(Request $request): array
    {
        $user = AdminApiContext::user();
        $challengeTokenId = AdminApiContext::accessTokenId();
        if ($user === null || $challengeTokenId === null || !$this->tokenIsMfaChallenge()) {
            throw new AdminApiException(
                403,
                'forbidden',
                'MFA login completion requires a valid MFA challenge token.'
            );
        }

        Database::query(
            'UPDATE api_access_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
            [$challengeTokenId]
        );

        $bundle = $this->issueTokenBundle($user, $request, 'mfa-verified');
        $this->securityEvent('login_mfa_verified', 'user', (int) $user['id'], null, $request, [
            'access_token_id' => $bundle['access_token_id'],
            'challenge_token_id' => $challengeTokenId,
        ]);

        return $this->publicTokenResponse($bundle, $user);
    }

    public function assertNotMfaChallengeOnly(string $action = 'this action'): void
    {
        if ($this->tokenIsMfaChallenge()) {
            throw new AdminApiException(
                403,
                'forbidden',
                'MFA challenge tokens may only call MFA verify. Complete MFA before ' . $action . '.'
            );
        }
    }

    private function assertCredentialStoreReady(): void
    {
        foreach (['api_access_tokens', 'api_refresh_tokens', 'api_login_throttle'] as $table) {
            if (!Database::tableExists($table)) {
                throw new AdminApiException(
                    503,
                    'api_unavailable',
                    'Admin API credential store is not migrated on this deployment.'
                );
            }
        }
    }

    private function userHasEnabledMfa(int $userId): bool
    {
        if (!Database::tableExists('user_mfa_methods')) {
            return false;
        }

        $row = Database::selectOne(
            'SELECT id FROM user_mfa_methods WHERE user_id = ? AND enabled_at IS NOT NULL AND verified_at IS NOT NULL LIMIT 1',
            [$userId]
        );

        return $row !== null;
    }

    private function assertNotThrottled(string $email, string $ip): void
    {
        $row = Database::selectOne(
            'SELECT * FROM api_login_throttle WHERE email_hash = ? AND ip_address = ? LIMIT 1',
            [$this->emailHash($email), $ip]
        );
        if ($row === null) {
            return;
        }
        if (!empty($row['locked_until']) && strtotime((string) $row['locked_until']) > time()) {
            throw new AdminApiException(
                429,
                'rate_limited',
                'Too many failed login attempts. Try again later.'
            );
        }
    }

    private function recordFailedLogin(string $email, string $ip, Request $request, string $reason = 'bad_credentials'): void
    {
        $max = max(1, (int) Config::get('security.login.max_attempts', 5));
        $lockMinutes = max(1, (int) Config::get('security.login.lockout_minutes', 15));
        $hash = $this->emailHash($email);
        $row = Database::selectOne(
            'SELECT * FROM api_login_throttle WHERE email_hash = ? AND ip_address = ? LIMIT 1',
            [$hash, $ip]
        );
        $now = date('Y-m-d H:i:s');
        if ($row === null) {
            Database::query(
                'INSERT INTO api_login_throttle (email_hash, ip_address, attempts, window_start, locked_until, updated_at)
                 VALUES (?, ?, 1, ?, NULL, ?)',
                [$hash, $ip, $now, $now]
            );
            $attempts = 1;
        } else {
            $windowStart = strtotime((string) $row['window_start']);
            $attempts = ($windowStart < time() - 3600) ? 1 : ((int) $row['attempts'] + 1);
            $lockedUntil = $attempts >= $max
                ? date('Y-m-d H:i:s', time() + ($lockMinutes * 60))
                : null;
            Database::query(
                'UPDATE api_login_throttle SET attempts = ?, window_start = ?, locked_until = ?, updated_at = ? '
                . 'WHERE email_hash = ? AND ip_address = ?',
                [
                    $attempts,
                    $attempts === 1 ? $now : $row['window_start'],
                    $lockedUntil,
                    $now,
                    $hash,
                    $ip,
                ]
            );
        }

        $this->securityEvent('login_failed', 'anonymous', null, null, $request, [
            'reason' => $reason,
            'attempts' => $attempts,
        ]);
    }

    private function clearThrottle(string $email, string $ip): void
    {
        Database::query(
            'DELETE FROM api_login_throttle WHERE email_hash = ? AND ip_address = ?',
            [$this->emailHash($email), $ip]
        );
    }

    private function revokeRefreshFamily(string $familyId, string $reason): void
    {
        $tokens = Database::select(
            'SELECT id, access_token_id FROM api_refresh_tokens WHERE family_id = ? AND revoked_at IS NULL',
            [$familyId]
        );
        Database::query(
            'UPDATE api_refresh_tokens SET revoked_at = NOW() WHERE family_id = ? AND revoked_at IS NULL',
            [$familyId]
        );
        foreach ($tokens as $token) {
            if (!empty($token['access_token_id'])) {
                Database::query(
                    'UPDATE api_access_tokens SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL',
                    [$token['access_token_id']]
                );
            }
        }
        unset($reason);
    }

    private function revokeAllUserSessions(int $userId): void
    {
        Database::query(
            'UPDATE api_refresh_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
        Database::query(
            'UPDATE api_access_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId]
        );
    }

    /**
     * @param array<string,mixed>|null $meta
     */
    private function securityEvent(
        string $type,
        string $actorType,
        ?int $userId,
        ?string $clientId,
        Request $request,
        ?array $meta = null
    ): void {
        if (!Database::tableExists('api_security_events')) {
            return;
        }
        Database::query(
            'INSERT INTO api_security_events (
                event_type, actor_type, user_id, client_id, request_id, ip_address, user_agent, meta_json, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $type,
                $actorType,
                $userId,
                $clientId,
                RequestContext::hasRequestId() ? RequestContext::requestId() : null,
                $request->ip(),
                $request->userAgent(),
                $meta !== null ? json_encode($meta, JSON_THROW_ON_ERROR) : null,
            ]
        );
    }

    private function emailHash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }

    /** @param mixed $scopes */
    private function decodeScopes(mixed $scopes): array
    {
        if (is_string($scopes)) {
            $decoded = json_decode($scopes, true);
            $scopes = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($scopes)) {
            return [];
        }

        return array_values(array_filter($scopes, static fn ($scope): bool => is_string($scope) && $scope !== ''));
    }

    private function iso(string $datetime): string
    {
        $ts = strtotime($datetime);

        return $ts === false ? $datetime : date('c', $ts);
    }
}
