<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Selects paid, clearly labelled campaigns for the regulatory-library context.
 * Campaign delivery never changes document ordering or organic provider ranks.
 */
final class RegulatorySponsor
{
    /** @return array<string,mixed>|null */
    public function town(int $townId): ?array
    {
        if ($townId < 1) {
            return null;
        }

        return Database::selectOne(
            'SELECT t.id, t.name, t.region_id, t.state_id, t.primary_postcode, s.abbreviation AS state_abbr '
            . 'FROM towns t INNER JOIN states s ON s.id=t.state_id WHERE t.id=? AND t.is_active=1',
            [$townId]
        );
    }

    /**
     * @param array<string,mixed>|null $town
     * @return array<int,array<string,mixed>>
     */
    public function campaigns(?array $town, string $jurisdiction, string $kind, string $vehicle, string $query): array
    {
        $categoryKeys = self::categoryKeys(current_brand()->id(), $kind, $vehicle, $query);
        $params = [current_brand()->databaseId()];
        $where = [
            "c.brand_id=?",
            "c.status='active'",
            "(c.starts_at IS NULL OR c.starts_at<=NOW())",
            "(c.ends_at IS NULL OR c.ends_at>=NOW())",
            "t.placement='regulatory_library'",
        ];

        if ($town !== null) {
            $where[] = '(t.town_id IS NULL OR t.town_id=? OR t.region_id=? OR t.state_id=?)';
            array_push($params, (int) $town['id'], (int) $town['region_id'], (int) $town['state_id']);
        } elseif ($jurisdiction !== '' && $jurisdiction !== 'AUS') {
            $where[] = '(t.state_id IS NULL OR st.abbreviation=?)';
            $params[] = $jurisdiction;
        } else {
            $where[] = 't.town_id IS NULL AND t.region_id IS NULL AND t.state_id IS NULL';
        }

        if ($categoryKeys !== []) {
            $placeholders = implode(',', array_fill(0, count($categoryKeys), '?'));
            $where[] = '(t.category_id IS NULL OR cat.category_key IN (' . $placeholders . '))';
            array_push($params, ...$categoryKeys);
        } else {
            $where[] = 't.category_id IS NULL';
        }

        $campaigns = Database::select(
            'SELECT DISTINCT c.id, c.headline, c.body, c.destination_url, c.desktop_image_path, c.mobile_image_path, '
            . 'pbl.slug AS provider_slug, pbl.display_name AS provider_name '
            . 'FROM advertising_campaigns c INNER JOIN advertising_campaign_targets t ON t.campaign_id=c.id '
            . 'LEFT JOIN states st ON st.id=t.state_id '
            . 'LEFT JOIN brand_provider_categories cat ON cat.id=t.category_id '
            . 'LEFT JOIN provider_brand_listings pbl ON pbl.provider_id=c.advertiser_provider_id '
            . 'AND pbl.brand_id=c.brand_id AND pbl.status=\'active\' AND pbl.deleted_at IS NULL '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'AND (c.total_budget_cents IS NULL OR COALESCE((SELECT SUM(am.spend_cents) FROM advertising_campaign_daily_metrics am WHERE am.campaign_id=c.id),0)<c.total_budget_cents) '
            . 'AND (c.daily_budget_cents IS NULL OR COALESCE((SELECT dm.spend_cents FROM advertising_campaign_daily_metrics dm WHERE dm.campaign_id=c.id AND dm.metric_date=CURRENT_DATE),0)<c.daily_budget_cents) '
            . 'ORDER BY c.starts_at DESC, c.id DESC LIMIT 3',
            $params
        );

        $campaigns = array_values(array_filter(
            $campaigns,
            static fn (array $campaign): bool => self::safeDestination((string) $campaign['destination_url'])
        ));
        CampaignMetrics::impressions(array_map(static fn (array $campaign): int => (int) $campaign['id'], $campaigns));
        return $campaigns;
    }

    /** @return array<int,string> */
    public static function categoryKeys(string $brandId, string $kind, string $vehicle, string $query): array
    {
        $keys = [];
        if ($brandId === 'vanassist') {
            $keys = ['caravan-rv-repairs', 'auto-electrical', 'tyres-wheels-bearings', 'roadworthy-inspections', 'roadside-recovery'];
        } elseif ($brandId === 'towsmart') {
            $keys = ['public-weighing', 'towing-training', 'towbars-hitches', 'brakes-controllers', 'suspension-payload', 'towing-inspections'];
        } elseif ($brandId === 'trailerwise') {
            $keys = ['roadworthy-inspections', 'trailer-repairs', 'brakes-axles-suspension', 'fabrication-engineering', 'manufacturers-dealers'];
        }
        if (in_array($kind, ['roadworthiness', 'inspection_manual'], true)) {
            $keys[] = 'roadworthy-inspections';
            $keys[] = 'vehicle-inspections';
        } elseif (in_array($kind, ['modifications', 'code_of_practice', 'street_rods'], true)) {
            array_push($keys, 'fabrication', 'performance-workshops', 'vehicle-inspections');
        } elseif (in_array($kind, ['towing', 'load_restraint'], true)) {
            array_push($keys, 'towing-training', 'towbars-hitches', 'public-weighing');
        } elseif (in_array($kind, ['trailer_construction', 'registration'], true)) {
            array_push($keys, 'manufacturers-dealers', 'fabrication-engineering', 'roadworthy-inspections');
        }
        if ($vehicle === 'motorcycle') {
            $keys[] = 'motorcycle-workshops';
        } elseif ($vehicle === 'heavy-vehicle') {
            $keys[] = 'diesel-specialists';
            $keys[] = 'fleet-maintenance';
        } elseif ($vehicle === 'trailer') {
            $keys[] = 'trailer-repairs';
        }
        $query = mb_strtolower($query);
        foreach ([
            'suspension' => 'suspension', 'brake' => 'brake-clutch',
            'engine' => 'engine-rebuilders', 'exhaust' => 'exhaust-shops',
            'electrical' => 'auto-electricians', 'tyre' => 'tyre-shops',
            '4wd' => 'four-wheel-drive-specialists', 'bullbar' => 'bullbar-installation',
        ] as $needle => $category) {
            if (str_contains($query, $needle)) {
                $keys[] = $category;
            }
        }

        return array_values(array_unique($keys));
    }

    public static function safeDestination(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($scheme, ['http', 'https'], true);
    }
}
