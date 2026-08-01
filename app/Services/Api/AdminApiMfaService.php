<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;

/**
 * MFA challenge/verify scaffold for Admin API (OPS-010 / CORE-011 Increment 8b).
 *
 * Enrollment is stored in `user_mfa_methods` (migration 081). Full TOTP validation
 * is not shipped in Phase 1; verify returns 501 until a vetted OTP library lands.
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
            'verify_status' => 'scaffolded',
            'message' => $enrolled
                ? 'MFA method enrolled; verify endpoint remains scaffolded until TOTP validation ships.'
                : 'No verified MFA method enrolled for this account.',
        ];
    }

    public function verify(int $userId, string $code): never
    {
        $code = trim($code);
        if ($code === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['code' => ['Verification code is required.']]
            );
        }

        if (!Database::tableExists('user_mfa_methods')) {
            throw new AdminApiException(
                501,
                'not_implemented',
                'MFA verification is not available on this deployment.'
            );
        }

        $method = Database::selectOne(
            'SELECT id, method, label FROM user_mfa_methods '
            . 'WHERE user_id = ? AND enabled_at IS NOT NULL AND verified_at IS NOT NULL LIMIT 1',
            [$userId]
        );

        if ($method === null) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'No verified MFA method is enrolled for this account.',
                ['code' => ['Enroll MFA before attempting verification.']]
            );
        }

        throw new AdminApiException(
            501,
            'not_implemented',
            'TOTP verification is scaffolded only. Full validation ships before ADMIN_API_MFA_REQUIRED=true in production.',
            [],
            [
                'method' => (string) $method['method'],
                'verify_status' => 'scaffolded',
            ]
        );
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
}
