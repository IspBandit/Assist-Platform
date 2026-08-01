<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\SecretCipher;

/**
 * Admin API MFA enrollment and TOTP verification (OPS-010).
 */
final class AdminApiMfaService
{
    /** @return array<string,mixed> */
    public function challenge(int $userId): array
    {
        $methods = $this->listMethods($userId);
        $enrolled = $methods !== [];

        return [
            'mfa_required' => (bool) Config::get('admin_api.mfa_required', false),
            'enrolled' => $enrolled,
            'methods' => $methods,
            'verify_status' => 'active',
            'message' => $enrolled
                ? 'Submit a current authenticator code to POST /auth/mfa/verify.'
                : 'No verified MFA method enrolled. Use POST /auth/mfa/enroll/begin while authenticated.',
        ];
    }

    /** @return array<string,mixed> */
    public function beginEnrollment(int $userId, string $accountLabel, ?string $label = null): array
    {
        $this->assertTable();
        if ($this->userHasEnabledMfa($userId)) {
            throw new AdminApiException(
                409,
                'conflict',
                'An MFA method is already enrolled for this account.'
            );
        }

        $secret = AdminApiTotp::generateSecret();
        $now = date('Y-m-d H:i:s');
        $existing = Database::selectOne(
            'SELECT id FROM user_mfa_methods WHERE user_id = ? AND method = ? LIMIT 1',
            [$userId, 'totp']
        );

        if ($existing !== null) {
            Database::query(
                'UPDATE user_mfa_methods SET secret_encrypted = ?, label = ?, enabled_at = NULL, '
                . 'verified_at = NULL, updated_at = ? WHERE id = ?',
                [SecretCipher::encrypt($secret), $label, $now, $existing['id']]
            );
        } else {
            Database::query(
                'INSERT INTO user_mfa_methods (user_id, method, secret_encrypted, label, enabled_at, verified_at, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, NULL, NULL, ?, ?)',
                [$userId, 'totp', SecretCipher::encrypt($secret), $label, $now, $now]
            );
        }

        return [
            'method' => 'totp',
            'secret' => $secret,
            'otpauth_uri' => AdminApiTotp::provisioningUri($secret, $accountLabel),
            'label' => $label,
            'message' => 'Scan the otpauth URI or enter the secret in an authenticator, then confirm with a code.',
        ];
    }

    /** @return array<string,mixed> */
    public function confirmEnrollment(int $userId, string $code): array
    {
        $this->assertTable();
        $row = Database::selectOne(
            'SELECT id, secret_encrypted FROM user_mfa_methods WHERE user_id = ? AND method = ? LIMIT 1',
            [$userId, 'totp']
        );
        if ($row === null || empty($row['secret_encrypted'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['code' => ['Begin MFA enrollment before confirming.']]
            );
        }

        $secret = SecretCipher::decrypt((string) $row['secret_encrypted']);
        if (!AdminApiTotp::verify($code, $secret)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['code' => ['Authenticator code is invalid or expired.']]
            );
        }

        $now = date('Y-m-d H:i:s');
        Database::query(
            'UPDATE user_mfa_methods SET enabled_at = ?, verified_at = ?, updated_at = ? WHERE id = ?',
            [$now, $now, $now, $row['id']]
        );

        return [
            'enrolled' => true,
            'method' => 'totp',
            'verified_at' => $now,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function verify(int $userId, string $code, Request $request): array
    {
        $this->assertTable();
        $code = trim($code);
        if ($code === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['code' => ['Verification code is required.']]
            );
        }

        $row = Database::selectOne(
            'SELECT id, method, secret_encrypted, label FROM user_mfa_methods '
            . 'WHERE user_id = ? AND enabled_at IS NOT NULL AND verified_at IS NOT NULL LIMIT 1',
            [$userId]
        );
        if ($row === null || empty($row['secret_encrypted'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'No verified MFA method is enrolled for this account.',
                ['code' => ['Enroll MFA before attempting verification.']]
            );
        }

        $secret = SecretCipher::decrypt((string) $row['secret_encrypted']);
        if (!AdminApiTotp::verify($code, $secret)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['code' => ['Authenticator code is invalid or expired.']]
            );
        }

        $auth = new AdminApiAuthService();
        if ($auth->tokenIsMfaChallenge()) {
            $bundle = $auth->completeMfaChallenge($request);

            return array_merge($bundle, [
                'mfa_verified' => true,
                'method' => (string) $row['method'],
            ]);
        }

        return [
            'mfa_verified' => true,
            'method' => (string) $row['method'],
            'verify_status' => 'ok',
        ];
    }

    public function userHasEnabledMfa(int $userId): bool
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

    /** @return list<array<string,mixed>> */
    private function listMethods(int $userId): array
    {
        if (!Database::tableExists('user_mfa_methods')) {
            return [];
        }

        $rows = Database::select(
            'SELECT method, label, enabled_at, verified_at FROM user_mfa_methods WHERE user_id = ? ORDER BY id ASC',
            [$userId]
        );

        $methods = [];
        foreach ($rows as $row) {
            $enabled = $row['enabled_at'] !== null && $row['verified_at'] !== null;
            if (!$enabled) {
                continue;
            }
            $methods[] = [
                'method' => (string) $row['method'],
                'label' => $row['label'] !== null ? (string) $row['label'] : null,
                'enabled' => true,
            ];
        }

        return $methods;
    }

    private function assertTable(): void
    {
        if (!Database::tableExists('user_mfa_methods')) {
            throw new AdminApiException(
                503,
                'api_unavailable',
                'MFA storage is not migrated on this deployment.'
            );
        }
    }
}
