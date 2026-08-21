<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Exceptions\AdminApiException;

/**
 * Cursor pagination helpers for Admin API collections (docs/API.md).
 */
final class AdminApiCursor
{
    public const DEFAULT_LIMIT = 25;
    public const MAX_LIMIT = 100;

    public static function limit(mixed $value): int
    {
        if ($value === null || $value === '') {
            return self::DEFAULT_LIMIT;
        }

        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['limit' => ['Limit must be a positive integer.']]
            );
        }

        $limit = (int) $value;
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['limit' => ['Limit must be between 1 and ' . self::MAX_LIMIT . '.']]
            );
        }

        return $limit;
    }

    public static function decode(?string $cursor): ?int
    {
        $cursor = trim((string) $cursor);
        if ($cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        $payload = json_decode($decoded, true);
        if (
            !is_array($payload)
            || !isset($payload['id'])
            || (!is_int($payload['id']) && !ctype_digit((string) $payload['id']))
        ) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        $id = (int) $payload['id'];
        if ($id < 1) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return $id;
    }

    public static function encode(int $id): string
    {
        $json = json_encode(['id' => $id], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array{
     *   items:list<array<string,mixed>>,
     *   has_more:bool,
     *   next_cursor:?string,
     *   count:int
     * }
     */
    public static function page(array $rows, int $limit, callable $idExtractor): array
    {
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[array_key_last($rows)];
            $nextCursor = self::encode((int) $idExtractor($last));
        }

        return [
            'items' => $rows,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
            'count' => count($rows),
        ];
    }
}
