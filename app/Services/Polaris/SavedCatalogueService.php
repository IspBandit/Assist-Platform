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
     * @param array<string,mixed> $query
     */
    public function saveSearch(int $userId, string $name, array $query, bool $alert = false): int
    {
        return Database::insert(
            'INSERT INTO polaris_saved_searches (user_id, name, query_json, alert_enabled, created_at)
             VALUES (?, ?, ?, ?, NOW())',
            [$userId, substr($name, 0, 120), json_encode($query, JSON_THROW_ON_ERROR), $alert ? 1 : 0]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function listSearches(int $userId): array
    {
        return Database::select(
            'SELECT * FROM polaris_saved_searches WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }
}
