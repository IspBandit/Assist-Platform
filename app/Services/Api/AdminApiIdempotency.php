<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Idempotency key storage for Admin API bulk and package mutations (CORE-011).
 */
final class AdminApiIdempotency
{
    public static function requireKey(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['Idempotency-Key' => ['Idempotency-Key header is required.']]
            );
        }
        if (strlen($key) > 128) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['Idempotency-Key' => ['Idempotency-Key must be 128 characters or fewer.']]
            );
        }

        return $key;
    }

    /**
     * @param callable():array<string,mixed> $execute
     * @return array{replay:bool,result:array<string,mixed>}
     */
    public static function execute(string $scopeKey, string $idempotencyKey, callable $execute): array
    {
        $brandId = AdminApiBrandScope::brandId();
        $existing = Database::selectOne(
            'SELECT response_json FROM api_idempotency_keys '
            . 'WHERE brand_id = ? AND scope_key = ? AND idempotency_key = ?',
            [$brandId, $scopeKey, $idempotencyKey]
        );

        if ($existing !== null) {
            $decoded = json_decode((string) $existing['response_json'], true);

            return [
                'replay' => true,
                'result' => is_array($decoded) ? $decoded : [],
            ];
        }

        $result = $execute();
        Database::query(
            'INSERT INTO api_idempotency_keys (id, brand_id, scope_key, idempotency_key, response_json, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [
                AdminApiToken::uuid(),
                $brandId,
                $scopeKey,
                $idempotencyKey,
                json_encode($result, JSON_THROW_ON_ERROR),
            ]
        );

        return [
            'replay' => false,
            'result' => $result,
        ];
    }
}
