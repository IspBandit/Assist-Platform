<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use RuntimeException;

/**
 * Persist guided-match preference profiles and shareable comparison sets.
 */
final class BuyerStateService
{
    public function savePreference(?int $userId, ?string $sessionKey, PreferenceProfile $profile): int
    {
        $json = json_encode($profile->toArray(), JSON_THROW_ON_ERROR);
        if ($userId !== null && $userId > 0) {
            $existing = Database::selectOne(
                'SELECT id FROM polaris_preference_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1',
                [$userId]
            );
            if ($existing !== null) {
                Database::affecting(
                    'UPDATE polaris_preference_profiles SET profile_json = ?, last_score_version = ?, updated_at = NOW() WHERE id = ?',
                    [$json, PreferenceProfile::SCORE_VERSION, (int) $existing['id']]
                );
                return (int) $existing['id'];
            }
            return Database::insert(
                'INSERT INTO polaris_preference_profiles (user_id, session_key, profile_json, last_score_version, created_at)
                 VALUES (?, NULL, ?, ?, NOW())',
                [$userId, $json, PreferenceProfile::SCORE_VERSION]
            );
        }
        $sessionKey = $sessionKey !== null && $sessionKey !== '' ? $sessionKey : bin2hex(random_bytes(16));
        return Database::insert(
            'INSERT INTO polaris_preference_profiles (user_id, session_key, profile_json, last_score_version, created_at)
             VALUES (NULL, ?, ?, ?, NOW())',
            [$sessionKey, $json, PreferenceProfile::SCORE_VERSION]
        );
    }

    public function loadPreferenceForUser(int $userId): ?PreferenceProfile
    {
        $row = Database::selectOne(
            'SELECT profile_json FROM polaris_preference_profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1',
            [$userId]
        );
        if ($row === null) {
            return null;
        }
        $decoded = json_decode((string) $row['profile_json'], true);
        return is_array($decoded) ? PreferenceProfile::fromArray($decoded) : null;
    }

    /**
     * @param list<int> $modelIds
     */
    public function saveComparison(int $brandId, array $modelIds, ?int $userId, ?string $title = null): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $modelIds), static fn (int $id): bool => $id > 0)));
        $ids = array_slice($ids, 0, ComparisonService::MAX_MODELS);
        if ($ids === []) {
            throw new RuntimeException('Select at least one model to share a comparison.');
        }
        $token = substr(bin2hex(random_bytes(12)), 0, 16);
        Database::insert(
            'INSERT INTO polaris_comparisons (brand_id, public_token, model_ids_json, user_id, title, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [
                $brandId,
                $token,
                json_encode($ids, JSON_THROW_ON_ERROR),
                $userId,
                $title,
            ]
        );
        return $token;
    }

    /** @return list<int>|null */
    public function loadComparisonModelIds(int $brandId, string $token): ?array
    {
        $token = strtolower(trim($token));
        if (!preg_match('/^[a-f0-9]{16}$/', $token)) {
            return null;
        }
        $row = Database::selectOne(
            'SELECT model_ids_json FROM polaris_comparisons WHERE brand_id = ? AND public_token = ? LIMIT 1',
            [$brandId, $token]
        );
        if ($row === null) {
            return null;
        }
        $ids = json_decode((string) $row['model_ids_json'], true);
        if (!is_array($ids)) {
            return null;
        }
        return array_values(array_map('intval', $ids));
    }

    /**
     * Shareable comparisons created while signed in (brand-scoped).
     *
     * @return list<array{token: string, title: string, model_ids: list<int>, model_count: int, created_at: string}>
     */
    public function listComparisonsForUser(int $brandId, int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $rows = Database::select(
            'SELECT public_token, title, model_ids_json, created_at
             FROM polaris_comparisons
             WHERE brand_id = ? AND user_id = ?
             ORDER BY created_at DESC, id DESC
             LIMIT ' . $limit,
            [$brandId, $userId]
        );
        $out = [];
        foreach ($rows as $row) {
            $ids = json_decode((string) ($row['model_ids_json'] ?? '[]'), true);
            if (!is_array($ids)) {
                $ids = [];
            }
            $modelIds = array_values(array_map('intval', $ids));
            $title = trim((string) ($row['title'] ?? ''));
            $count = count($modelIds);
            if ($title === '') {
                $title = $count === 1
                    ? 'Comparison (1 model)'
                    : sprintf('Comparison (%d models)', $count);
            }
            $out[] = [
                'token' => (string) $row['public_token'],
                'title' => $title,
                'model_ids' => $modelIds,
                'model_count' => $count,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return $out;
    }
}
