<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use Throwable;

/**
 * Catalogue queries scoped to the Polaris brand database ID.
 */
final class CatalogueRepository
{
    /** @return array<int,array<string,mixed>> */
    public function publishedModels(int $brandId, array $filters = [], int $limit = 48): array
    {
        $where = [
            'm.brand_id = ?',
            "m.publication_status = 'published'",
            "m.lifecycle_status = 'active'",
            'm.deleted_at IS NULL',
            "mf.publication_status = 'published'",
            "mf.lifecycle_status = 'active'",
            'mf.deleted_at IS NULL',
        ];
        $params = [$brandId];

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '' && isset(CatalogueService::categoryLabels()[$category])) {
            $where[] = 'm.category = ?';
            $params[] = $category;
        }

        $production = trim((string) ($filters['production_status'] ?? ''));
        if (in_array($production, ['current', 'upcoming', 'superseded', 'discontinued'], true)) {
            $where[] = 'm.production_status = ?';
            $params[] = $production;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(m.name LIKE ? OR mf.trading_name LIKE ? OR mf.legal_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }

        $having = [];
        $minSleeps = isset($filters['min_sleeps']) && is_numeric($filters['min_sleeps']) ? (int) $filters['min_sleeps'] : null;
        if ($minSleeps !== null && $minSleeps > 0) {
            $having[] = 'sleeps IS NOT NULL AND sleeps >= ' . $minSleeps;
        }
        $maxAtm = isset($filters['max_atm_kg']) && is_numeric($filters['max_atm_kg']) ? (int) $filters['max_atm_kg'] : null;
        if ($maxAtm !== null && $maxAtm > 0) {
            $having[] = 'atm_kg IS NOT NULL AND atm_kg <= ' . $maxAtm;
        }
        $maxLength = isset($filters['max_length_m']) && is_numeric($filters['max_length_m']) ? (float) $filters['max_length_m'] : null;
        if ($maxLength !== null && $maxLength > 0) {
            $having[] = 'body_length_m IS NOT NULL AND body_length_m <= ' . $maxLength;
        }
        $maxBudgetCents = isset($filters['max_budget_aud']) && is_numeric($filters['max_budget_aud'])
            ? (int) ((float) $filters['max_budget_aud'] * 100)
            : null;
        if ($maxBudgetCents !== null && $maxBudgetCents > 0) {
            $having[] = 'price_aud_cents IS NOT NULL AND price_aud_cents <= ' . $maxBudgetCents;
        }

        $sort = trim((string) ($filters['sort'] ?? 'name'));
        $orderBy = match ($sort) {
            'price_asc' => 'price_aud_cents IS NULL ASC, price_aud_cents ASC, m.name ASC',
            'price_desc' => 'price_aud_cents IS NULL ASC, price_aud_cents DESC, m.name ASC',
            'tare_asc' => 'tare_kg IS NULL ASC, tare_kg ASC, m.name ASC',
            'payload_desc' => '(atm_kg - tare_kg) IS NULL ASC, (atm_kg - tare_kg) DESC, m.name ASC',
            'length_asc' => 'body_length_m IS NULL ASC, body_length_m ASC, m.name ASC',
            'length_desc' => 'body_length_m IS NULL ASC, body_length_m DESC, m.name ASC',
            'newest' => 'm.first_model_year DESC, m.name ASC',
            'verified' => 'm.last_reviewed_at IS NULL ASC, m.last_reviewed_at DESC, m.name ASC',
            default => 'm.is_demo ASC, mf.trading_name ASC, m.name ASC',
        };

        $sql = 'SELECT m.*, mf.trading_name AS manufacturer_name, mf.slug AS manufacturer_slug, mf.verification_status AS manufacturer_verification,'
            . ' (SELECT MIN(v.tare_kg) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS tare_kg,'
            . ' (SELECT MIN(v.atm_kg) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS atm_kg,'
            . ' (SELECT MIN(v.sleeps) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS sleeps,'
            . ' (SELECT MIN(v.body_length_m) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS body_length_m,'
            . ' (SELECT MAX(v.solar_w) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS solar_w,'
            . ' (SELECT MAX(v.fresh_water_l) FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\') AS fresh_water_l,'
            . ' (SELECT v.bathroom_type FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\' ORDER BY v.id ASC LIMIT 1) AS bathroom_type,'
            . ' (SELECT v.price_status FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\' ORDER BY v.price_aud_cents IS NULL, v.price_aud_cents ASC LIMIT 1) AS price_status,'
            . ' (SELECT v.price_aud_cents FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\' ORDER BY v.price_aud_cents IS NULL, v.price_aud_cents ASC LIMIT 1) AS price_aud_cents,'
            . ' (SELECT v.price_effective_on FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\' ORDER BY v.price_aud_cents IS NULL, v.price_aud_cents ASC LIMIT 1) AS price_effective_on,'
            . ' (SELECT v.towball_mass_kg FROM polaris_rv_variants v WHERE v.model_id = m.id AND v.deleted_at IS NULL AND v.publication_status = \'published\' AND v.lifecycle_status = \'active\' ORDER BY v.id ASC LIMIT 1) AS towball_mass_kg'
            . ' FROM polaris_rv_models m'
            . ' INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id'
            . ' WHERE ' . implode(' AND ', $where);
        if ($having !== []) {
            $sql .= ' HAVING ' . implode(' AND ', $having);
        }
        $sql .= ' ORDER BY ' . $orderBy
            . ' LIMIT ' . max(1, min(100, $limit));

        return Database::select($sql, $params);
    }

    /** @return array<int,array<string,mixed>> */
    public function sourcesForBrand(int $brandId, int $limit = 20): array
    {
        return Database::select(
            'SELECT * FROM polaris_data_sources WHERE brand_id = ? ORDER BY retrieved_at DESC, id DESC LIMIT '
            . max(1, min(50, $limit)),
            [$brandId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function sourcesForModel(int $modelId): array
    {
        try {
            return Database::select(
                'SELECT s.*, ms.is_primary
                 FROM polaris_model_sources ms
                 INNER JOIN polaris_data_sources s ON s.id = ms.source_id
                 WHERE ms.model_id = ?
                 ORDER BY ms.is_primary DESC, s.retrieved_at DESC, s.id DESC',
                [$modelId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Field-level provenance rows for a primary variant (deterministic labels).
     *
     * @param array<string,mixed>|null $variant
     * @param list<array<string,mixed>> $sources
     * @return list<array{field:string,value:string,unit:string,source_label:string,authority:string}>
     */
    public function variantProvenanceRows(?array $variant, array $sources): array
    {
        if ($variant === null) {
            return [];
        }
        $primary = $sources[0] ?? null;
        $chip = $primary !== null
            ? CatalogueService::provenanceChip($primary)
            : ['label' => 'Source not linked', 'authority' => 'unknown', 'retrieved' => null];
        $rows = [];
        $map = [
            'sleeps' => ['Sleeps', ''],
            'body_length_m' => ['Body length', 'm'],
            'tare_kg' => ['Tare', 'kg'],
            'atm_kg' => ['ATM', 'kg'],
            'payload_kg' => ['Payload', 'kg'],
            'fresh_water_l' => ['Fresh water', 'L'],
            'solar_w' => ['Solar', 'W'],
            'bathroom_type' => ['Bathroom', ''],
            'price_label' => ['Price', ''],
        ];
        foreach ($map as $key => [$label, $unit]) {
            if (!array_key_exists($key, $variant) || $variant[$key] === null || $variant[$key] === '') {
                continue;
            }
            $value = is_float($variant[$key])
                ? number_format((float) $variant[$key], 2)
                : (string) $variant[$key];
            $rows[] = [
                'field' => $label,
                'value' => $value,
                'unit' => $unit,
                'source_label' => $chip['label'],
                'authority' => $chip['authority'],
            ];
        }
        return $rows;
    }

    /** @return array<int,array<string,mixed>> */
    public function findPublishedModelsByIds(int $brandId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }
        $rows = $this->publishedModels($brandId, [], 100);
        $wanted = array_fill_keys($ids, true);
        $matched = [];
        foreach ($rows as $row) {
            if (isset($wanted[(int) $row['id']])) {
                $matched[] = $row;
            }
        }
        usort($matched, static function (array $a, array $b) use ($ids): int {
            return array_search((int) $a['id'], $ids, true) <=> array_search((int) $b['id'], $ids, true);
        });
        return $matched;
    }

    /** @return array<string,mixed>|null */
    public function findPublishedModel(int $brandId, string $manufacturerSlug, string $modelSlug): ?array
    {
        return Database::selectOne(
            'SELECT m.*, mf.trading_name AS manufacturer_name, mf.slug AS manufacturer_slug, mf.legal_name AS manufacturer_legal_name,'
            . ' mf.website_url AS manufacturer_website, mf.verification_status AS manufacturer_verification,'
            . ' mf.claim_status AS manufacturer_claim_status, mf.description AS manufacturer_description,'
            . ' mf.is_demo AS manufacturer_is_demo'
            . ' FROM polaris_rv_models m'
            . ' INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id'
            . ' WHERE m.brand_id = ? AND mf.slug = ? AND m.slug = ?'
            . " AND m.publication_status = 'published' AND m.lifecycle_status = 'active' AND m.deleted_at IS NULL"
            . " AND mf.publication_status = 'published' AND mf.lifecycle_status = 'active' AND mf.deleted_at IS NULL"
            . ' LIMIT 1',
            [$brandId, $manufacturerSlug, $modelSlug]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function variantsForModel(int $modelId): array
    {
        return Database::select(
            'SELECT * FROM polaris_rv_variants'
            . " WHERE model_id = ? AND publication_status = 'published' AND lifecycle_status = 'active' AND deleted_at IS NULL"
            . ' ORDER BY name ASC',
            [$modelId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function floorplansForModel(int $modelId): array
    {
        return Database::select(
            'SELECT * FROM polaris_floorplans'
            . " WHERE model_id = ? AND publication_status = 'published' AND lifecycle_status = 'active' AND deleted_at IS NULL"
            . ' ORDER BY title ASC',
            [$modelId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function publishedManufacturers(int $brandId): array
    {
        return Database::select(
            'SELECT mf.*,'
            . ' (SELECT COUNT(*) FROM polaris_rv_models m WHERE m.manufacturer_id = mf.id'
            . " AND m.publication_status = 'published' AND m.lifecycle_status = 'active' AND m.deleted_at IS NULL) AS model_count"
            . ' FROM polaris_manufacturers mf'
            . " WHERE mf.brand_id = ? AND mf.publication_status = 'published' AND mf.lifecycle_status = 'active' AND mf.deleted_at IS NULL"
            . ' ORDER BY mf.is_demo ASC, mf.trading_name ASC',
            [$brandId]
        );
    }

    /** @return array<string,mixed>|null */
    public function findPublishedManufacturer(int $brandId, string $slug): ?array
    {
        return Database::selectOne(
            'SELECT * FROM polaris_manufacturers'
            . " WHERE brand_id = ? AND slug = ? AND publication_status = 'published'"
            . " AND lifecycle_status = 'active' AND deleted_at IS NULL LIMIT 1",
            [$brandId, $slug]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function modelsForManufacturer(int $manufacturerId): array
    {
        return Database::select(
            'SELECT * FROM polaris_rv_models'
            . " WHERE manufacturer_id = ? AND publication_status = 'published' AND lifecycle_status = 'active' AND deleted_at IS NULL"
            . ' ORDER BY name ASC',
            [$manufacturerId]
        );
    }

    /** @return array{manufacturers:int,models:int,published_models:int} */
    public function adminCounts(int $brandId): array
    {
        try {
            return [
                'manufacturers' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM polaris_manufacturers WHERE brand_id = ? AND deleted_at IS NULL',
                    [$brandId]
                ),
                'models' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM polaris_rv_models WHERE brand_id = ? AND deleted_at IS NULL',
                    [$brandId]
                ),
                'published_models' => (int) Database::scalar(
                    "SELECT COUNT(*) FROM polaris_rv_models WHERE brand_id = ? AND publication_status = 'published' AND lifecycle_status = 'active' AND deleted_at IS NULL",
                    [$brandId]
                ),
            ];
        } catch (Throwable) {
            return ['manufacturers' => 0, 'models' => 0, 'published_models' => 0];
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function adminManufacturers(int $brandId, ?string $lifecycle = null): array
    {
        $where = ['brand_id = ?'];
        $params = [$brandId];
        if ($lifecycle === 'recycle_bin') {
            $where[] = "lifecycle_status = 'recycle_bin'";
        } else {
            $where[] = 'deleted_at IS NULL';
            if ($lifecycle !== null && $lifecycle !== '') {
                $where[] = 'lifecycle_status = ?';
                $params[] = $lifecycle;
            }
        }
        return Database::select(
            'SELECT * FROM polaris_manufacturers WHERE ' . implode(' AND ', $where)
            . ' ORDER BY lifecycle_status ASC, trading_name ASC LIMIT 200',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function adminModels(int $brandId, ?string $lifecycle = null): array
    {
        $where = ['m.brand_id = ?'];
        $params = [$brandId];
        if ($lifecycle === 'recycle_bin') {
            $where[] = "m.lifecycle_status = 'recycle_bin'";
        } else {
            $where[] = 'm.deleted_at IS NULL';
            if ($lifecycle !== null && $lifecycle !== '') {
                $where[] = 'm.lifecycle_status = ?';
                $params[] = $lifecycle;
            }
        }
        return Database::select(
            'SELECT m.*, mf.trading_name AS manufacturer_name FROM polaris_rv_models m'
            . ' INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY m.lifecycle_status ASC, mf.trading_name ASC, m.name ASC LIMIT 200',
            $params
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function adminVariants(int $brandId, int $limit = 200): array
    {
        return Database::select(
            'SELECT v.*, m.name AS model_name, mf.trading_name AS manufacturer_name
             FROM polaris_rv_variants v
             INNER JOIN polaris_rv_models m ON m.id = v.model_id
             INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id
             WHERE m.brand_id = ? AND v.deleted_at IS NULL AND m.deleted_at IS NULL
             ORDER BY mf.trading_name ASC, m.name ASC, v.name ASC
             LIMIT ' . max(1, min(500, $limit)),
            [$brandId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function adminFloorplans(int $brandId, int $limit = 200): array
    {
        return Database::select(
            'SELECT f.*, m.name AS model_name, mf.trading_name AS manufacturer_name
             FROM polaris_floorplans f
             INNER JOIN polaris_rv_models m ON m.id = f.model_id
             INNER JOIN polaris_manufacturers mf ON mf.id = m.manufacturer_id
             WHERE m.brand_id = ? AND f.deleted_at IS NULL AND m.deleted_at IS NULL
             ORDER BY mf.trading_name ASC, m.name ASC
             LIMIT ' . max(1, min(500, $limit)),
            [$brandId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function adminAnalyticsSummary(int $brandId): array
    {
        try {
            return Database::select(
                'SELECT event_name, COUNT(*) AS event_count, MAX(created_at) AS last_seen
                 FROM polaris_analytics_events
                 WHERE brand_id = ?
                 GROUP BY event_name
                 ORDER BY event_count DESC
                 LIMIT 40',
                [$brandId]
            );
        } catch (Throwable) {
            return [];
        }
    }

    public function setModelLifecycle(int $brandId, int $modelId, string $lifecycle, ?string $reason = null): bool
    {
        if (!in_array($lifecycle, ['active', 'archived', 'recycle_bin'], true)) {
            return false;
        }
        $affected = Database::affecting(
            'UPDATE polaris_rv_models SET lifecycle_status = ?, archival_reason = ?, updated_at = NOW()'
            . ($lifecycle === 'recycle_bin' ? ', deleted_at = NOW()' : ', deleted_at = NULL')
            . ' WHERE id = ? AND brand_id = ?',
            [$lifecycle, $reason, $modelId, $brandId]
        );
        return $affected > 0;
    }
}
