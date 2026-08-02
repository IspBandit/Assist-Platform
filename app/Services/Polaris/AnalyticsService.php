<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Core\Database;
use Throwable;

/**
 * Privacy-conscious Polaris first-party analytics. Does not store free-text prompts.
 */
final class AnalyticsService
{
    /**
     * @param array<string,scalar|null> $properties
     */
    public static function track(
        string $eventName,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        array $properties = [],
        string $privacyClass = 'anonymous'
    ): void {
        try {
            if (current_brand()->id() !== 'polaris') {
                return;
            }
            // Strip free-text fields that could contain sensitive content.
            unset($properties['prompt'], $properties['q'], $properties['notes'], $properties['evidence']);
            Database::insert(
                'INSERT INTO polaris_analytics_events
                    (brand_id, event_name, user_id, session_key, entity_type, entity_id, properties_json, privacy_class, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    current_brand()->databaseId(),
                    substr($eventName, 0, 80),
                    $userId,
                    substr(session_id() ?: '', 0, 64) ?: null,
                    $entityType,
                    $entityId,
                    $properties === [] ? null : json_encode($properties, JSON_THROW_ON_ERROR),
                    in_array($privacyClass, ['anonymous', 'authenticated', 'sensitive'], true) ? $privacyClass : 'anonymous',
                ]
            );
        } catch (Throwable) {
            // Analytics must never break product flows.
        }
    }

    /**
     * Manufacturer-scoped portal rollup for model detail views and saves.
     * Find impressions / dealer enquiry clicks remain planned until those events exist.
     *
     * @return array{
     *   days: int,
     *   views: int,
     *   saves: int,
     *   by_model: list<array{id:int,name:string,slug:string,views:int,saves:int}>
     * }
     */
    public static function manufacturerSummary(int $brandId, int $manufacturerId, int $days = 30): array
    {
        $days = max(1, min(90, $days));
        $empty = self::shapeManufacturerSummary($days, [], []);
        if ($brandId < 1 || $manufacturerId < 1) {
            return $empty;
        }

        try {
            $totals = Database::select(
                'SELECT e.event_name, COUNT(*) AS event_count
                 FROM polaris_analytics_events e
                 INNER JOIN polaris_rv_models m ON m.id = e.entity_id
                   AND m.manufacturer_id = ?
                   AND m.brand_id = ?
                 WHERE e.brand_id = ?
                   AND e.entity_type = \'model\'
                   AND e.event_name IN (\'rv_viewed\', \'rv_saved\')
                   AND e.created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
                 GROUP BY e.event_name',
                [$manufacturerId, $brandId, $brandId]
            );
            $byModel = Database::select(
                'SELECT m.id, m.name, m.slug,
                        SUM(CASE WHEN e.event_name = \'rv_viewed\' THEN 1 ELSE 0 END) AS views,
                        SUM(CASE WHEN e.event_name = \'rv_saved\' THEN 1 ELSE 0 END) AS saves
                 FROM polaris_rv_models m
                 LEFT JOIN polaris_analytics_events e
                   ON e.entity_id = m.id
                  AND e.entity_type = \'model\'
                  AND e.brand_id = ?
                  AND e.event_name IN (\'rv_viewed\', \'rv_saved\')
                  AND e.created_at >= DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
                 WHERE m.manufacturer_id = ?
                   AND m.brand_id = ?
                   AND m.deleted_at IS NULL
                 GROUP BY m.id, m.name, m.slug
                 ORDER BY views DESC, saves DESC, m.name ASC
                 LIMIT 40',
                [$brandId, $manufacturerId, $brandId]
            );
            return self::shapeManufacturerSummary($days, $totals, $byModel);
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * Pure shaping helper for portal analytics (unit-testable without MariaDB).
     *
     * @param list<array<string,mixed>> $totals
     * @param list<array<string,mixed>> $byModel
     * @return array{
     *   days: int,
     *   views: int,
     *   saves: int,
     *   by_model: list<array{id:int,name:string,slug:string,views:int,saves:int}>
     * }
     */
    public static function shapeManufacturerSummary(int $days, array $totals, array $byModel): array
    {
        $views = 0;
        $saves = 0;
        foreach ($totals as $row) {
            $name = (string) ($row['event_name'] ?? '');
            $count = (int) ($row['event_count'] ?? 0);
            if ($name === 'rv_viewed') {
                $views = $count;
            } elseif ($name === 'rv_saved') {
                $saves = $count;
            }
        }

        $models = [];
        foreach ($byModel as $row) {
            $models[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'views' => (int) ($row['views'] ?? 0),
                'saves' => (int) ($row['saves'] ?? 0),
            ];
        }

        return [
            'days' => max(1, min(90, $days)),
            'views' => $views,
            'saves' => $saves,
            'by_model' => $models,
        ];
    }
}
