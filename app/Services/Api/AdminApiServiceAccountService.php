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
 * Service account lifecycle and machine token issuance (CORE-011 Increment 3).
 */
final class AdminApiServiceAccountService
{
    /** @var list<string> */
    public const MANAGER_ROLES = [
        'administrator',
        'super-administrator',
        'platform-administrator',
    ];

    /** @return list<array<string,mixed>> */
    public function list(): array
    {
        $this->assertCredentialStoreReady();
        $rows = Database::select(
            'SELECT * FROM api_oauth_clients WHERE status != ? ORDER BY created_at DESC',
            ['revoked']
        );

        return array_map(fn (array $row): array => $this->publicClient($row), $rows);
    }

    /** @return array<string,mixed> */
    public function get(string $id): array
    {
        $this->assertCredentialStoreReady();
        $row = $this->findClientOrFail($id);
        if (($row['status'] ?? '') === 'revoked') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return $this->publicClient($row);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $this->assertCredentialStoreReady();
        $this->assertManager();

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || strlen($name) > 120) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['name' => ['Name is required and must be at most 120 characters.']]
            );
        }

        $scopes = AdminApiScopes::normalize($input['scopes'] ?? []);
        if ($scopes === []) {
            $scopes = AdminApiScopes::DEFAULT_SERVICE;
        }
        AdminApiScopes::rejectForbiddenForService($scopes);

        $status = strtolower(trim((string) ($input['status'] ?? 'active')));
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['status' => ['Status must be active or disabled.']]
            );
        }

        $tokenTtl = $this->resolveTokenTtl($input['token_ttl_seconds'] ?? null);
        $expiresAt = $this->parseOptionalExpiry($input['expires_at'] ?? null);
        $secret = AdminApiToken::generate();
        $id = AdminApiToken::uuid();
        $clientKey = $this->generateClientKey();
        $now = date('Y-m-d H:i:s');
        $createdBy = AdminApiContext::userId();

        Database::query(
            'INSERT INTO api_oauth_clients (
                id, name, client_key, secret_hash, status, scopes_json, token_ttl_seconds,
                expires_at, last_used_at, created_by, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)',
            [
                $id,
                $name,
                $clientKey,
                password_hash($secret, PASSWORD_DEFAULT),
                $status,
                json_encode($scopes, JSON_THROW_ON_ERROR),
                $tokenTtl,
                $expiresAt,
                $createdBy,
                $now,
                $now,
            ]
        );

        $row = $this->findClientOrFail($id);
        $this->securityEvent('service_account_created', 'user', $createdBy, $id, $request, [
            'client_key' => $clientKey,
        ]);

        return array_merge($this->publicClient($row), [
            'client_secret' => $secret,
        ]);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(string $id, array $input, Request $request): array
    {
        $this->assertCredentialStoreReady();
        $this->assertManager();
        $row = $this->findClientOrFail($id);
        if (($row['status'] ?? '') === 'revoked') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        $updates = [];
        $params = [];

        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '' || strlen($name) > 120) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['name' => ['Name is required and must be at most 120 characters.']]
                );
            }
            $updates[] = 'name = ?';
            $params[] = $name;
        }

        if (array_key_exists('scopes', $input)) {
            $scopes = AdminApiScopes::normalize($input['scopes']);
            if ($scopes === []) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['scopes' => ['At least one valid scope is required.']]
                );
            }
            AdminApiScopes::rejectForbiddenForService($scopes);
            $updates[] = 'scopes_json = ?';
            $params[] = json_encode($scopes, JSON_THROW_ON_ERROR);
        }

        if (array_key_exists('status', $input)) {
            $status = strtolower(trim((string) $input['status']));
            if (!in_array($status, ['active', 'disabled'], true)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['status' => ['Status must be active or disabled.']]
                );
            }
            $updates[] = 'status = ?';
            $params[] = $status;
            if ($status === 'disabled') {
                $this->revokeClientAccessTokens($id, 'client_disabled');
            }
        }

        if (array_key_exists('token_ttl_seconds', $input)) {
            $updates[] = 'token_ttl_seconds = ?';
            $params[] = $this->resolveTokenTtl($input['token_ttl_seconds']);
        }

        if (array_key_exists('expires_at', $input)) {
            $updates[] = 'expires_at = ?';
            $params[] = $this->parseOptionalExpiry($input['expires_at']);
        }

        if ($updates === []) {
            return $this->publicClient($row);
        }

        $updates[] = 'updated_at = ?';
        $params[] = date('Y-m-d H:i:s');
        $params[] = $id;

        Database::query(
            'UPDATE api_oauth_clients SET ' . implode(', ', $updates) . ' WHERE id = ?',
            $params
        );

        $this->securityEvent('service_account_updated', 'user', AdminApiContext::userId(), $id, $request);

        return $this->publicClient($this->findClientOrFail($id));
    }

    /** @return array<string,mixed> */
    public function rotate(string $id, Request $request): array
    {
        $this->assertCredentialStoreReady();
        $this->assertManager();
        $row = $this->findClientOrFail($id);
        if (($row['status'] ?? '') === 'revoked') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        $secret = AdminApiToken::generate();
        Database::query(
            'UPDATE api_oauth_clients SET secret_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($secret, PASSWORD_DEFAULT), date('Y-m-d H:i:s'), $id]
        );
        $this->revokeClientAccessTokens($id, 'secret_rotated');
        $this->securityEvent('service_account_rotated', 'user', AdminApiContext::userId(), $id, $request);

        return array_merge($this->publicClient($this->findClientOrFail($id)), [
            'client_secret' => $secret,
        ]);
    }

    /** @return array<string,mixed> */
    public function revoke(string $id, Request $request): array
    {
        $this->assertCredentialStoreReady();
        $this->assertManager();
        $row = $this->findClientOrFail($id);
        if (($row['status'] ?? '') === 'revoked') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        Database::query(
            'UPDATE api_oauth_clients SET status = ?, updated_at = ? WHERE id = ?',
            ['revoked', date('Y-m-d H:i:s'), $id]
        );
        $this->revokeClientAccessTokens($id, 'client_revoked');
        $this->securityEvent('service_account_revoked', 'user', AdminApiContext::userId(), $id, $request);

        return $this->publicClient($this->findClientOrFail($id));
    }

    /**
     * @return array{
     *   access_token:string,
     *   token_type:string,
     *   expires_in:int,
     *   scopes:list<string>,
     *   actor_type:string,
     *   client:array<string,mixed>
     * }
     */
    public function issueAccessToken(string $clientKey, string $secret, Request $request): array
    {
        $clientKey = trim($clientKey);
        $secret = trim($secret);
        if ($clientKey === '' || $secret === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                array_filter([
                    'client_key' => $clientKey === '' ? ['Client key is required.'] : null,
                    'client_secret' => $secret === '' ? ['Client secret is required.'] : null,
                ])
            );
        }

        $this->assertCredentialStoreReady();

        $row = Database::selectOne(
            'SELECT * FROM api_oauth_clients WHERE client_key = ? LIMIT 1',
            [$clientKey]
        );
        if ($row === null || !password_verify($secret, (string) ($row['secret_hash'] ?? ''))) {
            $this->securityEvent('service_token_failed', 'anonymous', null, null, $request, [
                'client_key' => $clientKey,
            ]);
            throw new AdminApiException(401, 'unauthenticated', 'Invalid client credentials.');
        }

        if (($row['status'] ?? '') !== 'active') {
            throw new AdminApiException(403, 'forbidden', 'Service account is not active.');
        }

        if (!empty($row['expires_at']) && strtotime((string) $row['expires_at']) < time()) {
            throw new AdminApiException(403, 'forbidden', 'Service account has expired.');
        }

        $scopes = AdminApiScopes::normalize($row['scopes_json'] ?? []);
        AdminApiScopes::rejectForbiddenForService($scopes);
        $ttl = min(
            max(60, (int) ($row['token_ttl_seconds'] ?? 0)),
            $this->defaultServiceTokenTtl()
        );
        if ($ttl <= 0) {
            $ttl = $this->defaultServiceTokenTtl();
        }

        $accessId = AdminApiToken::uuid();
        $accessToken = AdminApiToken::generate();
        $now = date('Y-m-d H:i:s');
        $accessExpiry = date('Y-m-d H:i:s', time() + $ttl);
        $requestId = RequestContext::hasRequestId() ? RequestContext::requestId() : null;

        Database::query(
            'INSERT INTO api_access_tokens (
                id, token_hash, actor_type, user_id, client_id, scopes_json, expires_at,
                request_id_created, ip_address, user_agent, created_at
            ) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?)',
            [
                $accessId,
                AdminApiToken::hash($accessToken),
                'service',
                (string) $row['id'],
                json_encode($scopes, JSON_THROW_ON_ERROR),
                $accessExpiry,
                $requestId,
                $request->ip(),
                $request->userAgent(),
                $now,
            ]
        );

        Database::query(
            'UPDATE api_oauth_clients SET last_used_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $row['id']]
        );

        $this->securityEvent('service_token_issued', 'service', null, (string) $row['id'], $request, [
            'access_token_id' => $accessId,
        ]);

        return [
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'scopes' => $scopes,
            'actor_type' => 'service',
            'client' => $this->publicClient($row),
        ];
    }

    /** @param array<string,mixed> $row */
    public function publicClient(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'client_key' => (string) ($row['client_key'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'scopes' => AdminApiScopes::normalize($row['scopes_json'] ?? []),
            'token_ttl_seconds' => (int) ($row['token_ttl_seconds'] ?? 0),
            'expires_at' => !empty($row['expires_at']) ? $this->iso((string) $row['expires_at']) : null,
            'last_used_at' => !empty($row['last_used_at']) ? $this->iso((string) $row['last_used_at']) : null,
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'created_at' => $this->iso((string) ($row['created_at'] ?? '')),
            'updated_at' => !empty($row['updated_at']) ? $this->iso((string) $row['updated_at']) : null,
        ];
    }

    private function assertManager(): void
    {
        $userId = AdminApiContext::userId();
        if ($userId === null) {
            throw new AdminApiException(401, 'unauthenticated', 'Bearer access token required.');
        }

        if (array_intersect(User::roleSlugs($userId), self::MANAGER_ROLES) === []) {
            throw new AdminApiException(
                403,
                'forbidden',
                'Administrator role required to manage service accounts.'
            );
        }
    }

    /** @return array<string,mixed> */
    private function findClientOrFail(string $id): array
    {
        $row = Database::selectOne(
            'SELECT * FROM api_oauth_clients WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return $row;
    }

    private function generateClientKey(): string
    {
        return 'va_sa_' . bin2hex(random_bytes(16));
    }

    private function defaultServiceTokenTtl(): int
    {
        return min(3600, max(60, (int) Config::get('admin_api.service_token_ttl_seconds', 3600)));
    }

    private function resolveTokenTtl(mixed $value): int
    {
        if ($value === null || $value === '') {
            return $this->defaultServiceTokenTtl();
        }

        $ttl = (int) $value;

        return min(3600, max(60, $ttl));
    }

    /** @return string|null */
    private function parseOptionalExpiry(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['expires_at' => ['Expiry must be an ISO-8601 datetime string.']]
            );
        }
        $ts = strtotime($value);
        if ($ts === false) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['expires_at' => ['Expiry must be a valid datetime.']]
            );
        }

        return date('Y-m-d H:i:s', $ts);
    }

    private function revokeClientAccessTokens(string $clientId, string $reason): void
    {
        Database::query(
            'UPDATE api_access_tokens SET revoked_at = NOW() '
            . 'WHERE client_id = ? AND revoked_at IS NULL',
            [$clientId]
        );
        unset($reason);
    }

    private function assertCredentialStoreReady(): void
    {
        foreach (['api_oauth_clients', 'api_access_tokens'] as $table) {
            if (!Database::tableExists($table)) {
                throw new AdminApiException(
                    503,
                    'api_unavailable',
                    'Admin API credential store is not migrated on this deployment.'
                );
            }
        }
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

    private function iso(string $datetime): string
    {
        $ts = strtotime($datetime);

        return $ts === false ? $datetime : date('c', $ts);
    }
}
