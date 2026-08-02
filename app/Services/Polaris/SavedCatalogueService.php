<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use RuntimeException;

final class SavedCatalogueService
{
    public function saveModel(int $userId, int $modelId, ?string $notes = null): void
    {
        $model = Database::selectOne(
            'SELECT id FROM polaris_rv_models WHERE id = ? AND publication_status = \'published\' AND deleted_at IS NULL LIMIT 1',
            [$modelId]
        );
        if ($model === null) {
            throw new RuntimeException('Model not available to save.');
        }
        $existing = Database::selectOne(
            'SELECT id FROM polaris_saved_models WHERE user_id = ? AND model_id = ? LIMIT 1',
            [$userId, $modelId]
        );
        if ($existing !== null) {
            return;
        }
        Database::insert(
            'INSERT INTO polaris_saved_models (user_id, model_id, notes, created_at) VALUES (?, ?, ?, NOW())',
            [$userId, $modelId, $notes]
        );
    }

    public function removeModel(int $userId, int $modelId): void
    {
        Database::affecting(
            'DELETE FROM polaris_saved_models WHERE user_id = ? AND model_id = ?',
            [$userId, $modelId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listModels(int $userId): array
    {
        return Database::select(
            'SELECT s.id AS saved_id, s.notes, s.created_at AS saved_at, m.*, mf.trading_name AS manufacturer_name, mf.slug AS manufacturer_slug
             FROM polaris_saved_models s
             INNER JOIN polaris_rv_models m ON m.id = s.model_id
             INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id
             WHERE s.user_id = ? AND m.deleted_at IS NULL
             ORDER BY s.created_at DESC',
            [$userId]
        );
    }

    /**
     * Persist browse filters for later reuse. alert_enabled is stored only;
     * notification delivery is not implemented yet.
     *
     * @param array<string,mixed> $query
     */
    public function saveSearch(int $userId, string $name, array $query, bool $alert = false): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Sign in to save a search.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Give this search a short name.');
        }
        $normalised = self::normaliseBrowseQuery($query);
        if ($normalised === []) {
            throw new RuntimeException('Apply at least one browse filter before saving a search.');
        }
        return Database::insert(
            'INSERT INTO polaris_saved_searches (user_id, name, query_json, alert_enabled, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$userId, substr($name, 0, 120), json_encode($normalised, JSON_THROW_ON_ERROR), $alert ? 1 : 0]
        );
    }

    public function removeSearch(int $userId, int $searchId): void
    {
        Database::affecting(
            'DELETE FROM polaris_saved_searches WHERE user_id = ? AND id = ?',
            [$userId, $searchId]
        );
    }

    /**
     * @return list<array{id: int, name: string, query: array<string,string>, browse_path: string, alert_enabled: bool, created_at: string}>
     */
    public function listSearches(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        $rows = Database::select(
            'SELECT id, name, query_json, alert_enabled, created_at
             FROM polaris_saved_searches
             WHERE user_id = ?
             ORDER BY created_at DESC, id DESC',
            [$userId]
        );
        $out = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['query_json'] ?? '{}'), true);
            $query = is_array($decoded) ? self::normaliseBrowseQuery($decoded) : [];
            $out[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'query' => $query,
                'browse_path' => self::browsePathFromQuery($query),
                'alert_enabled' => (int) ($row['alert_enabled'] ?? 0) === 1,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }
        return $out;
    }

    /**
     * @param array<string,mixed> $query
     * @return array<string,string>
     */
    public static function normaliseBrowseQuery(array $query): array
    {
        $keys = [
            'q',
            'category',
            'production_status',
            'min_sleeps',
            'max_atm_kg',
            'max_length_m',
            'max_budget_aud',
            'sort',
        ];
        $out = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $query)) {
                continue;
            }
            $value = trim((string) $query[$key]);
            if ($value === '') {
                continue;
            }
            if ($key === 'sort' && $value === 'name') {
                continue;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * @param array<string,string> $query
     */
    public static function browsePathFromQuery(array $query): string
    {
        $query = self::normaliseBrowseQuery($query);
        if ($query === []) {
            return '/rvs';
        }
        return '/rvs?' . http_build_query($query);
    }

    /**
     * @param array<string,mixed> $filters
     */
    public static function suggestSearchName(array $filters): string
    {
        $parts = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $parts[] = $q;
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $parts[] = $category;
        }
        $sleeps = trim((string) ($filters['min_sleeps'] ?? ''));
        if ($sleeps !== '') {
            $parts[] = $sleeps . '+ sleeps';
        }
        $atm = trim((string) ($filters['max_atm_kg'] ?? ''));
        if ($atm !== '') {
            $parts[] = '≤' . $atm . 'kg ATM';
        }
        $budget = trim((string) ($filters['max_budget_aud'] ?? ''));
        if ($budget !== '') {
            $parts[] = '≤$' . $budget;
        }
        if ($parts === []) {
            return 'Browse filters';
        }
        return substr(implode(' · ', $parts), 0, 120);
    }
}
