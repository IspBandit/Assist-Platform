<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Unified rules for whether a provider serves a town, region or state — used by
 * public search, directory pages, region/town listings and admin matching.
 */
final class ProviderCoverage
{
    /**
     * SQL fragment: provider alias `p` explicitly serves the town identified by ? placeholders.
     * Pass the town id six times (base, town area, region subquery, state subquery, radius target, region fallback).
     */
    public static function sqlServesTown(): string
    {
        return '(p.base_town_id = ? '
            . "OR EXISTS (SELECT 1 FROM provider_service_areas psa WHERE psa.provider_id = p.id AND psa.area_type = 'town' AND psa.town_id = ?) "
            . "OR EXISTS (SELECT 1 FROM provider_service_areas pra WHERE pra.provider_id = p.id AND pra.area_type = 'region' AND pra.region_id = (SELECT region_id FROM towns WHERE id = ?)) "
            . "OR EXISTS (SELECT 1 FROM provider_service_areas pst WHERE pst.provider_id = p.id AND pst.area_type = 'state' AND pst.state_id = (SELECT state_id FROM towns WHERE id = ?)) "
            . self::sqlRadiusFromBaseTown()
            . 'OR p.region_id = (SELECT region_id FROM towns WHERE id = ?))';
    }

    /** @return array<int,int> town id repeated for sqlServesTown placeholders */
    public static function servesTownParams(int $townId): array
    {
        return [$townId, $townId, $townId, $townId, $townId, $townId];
    }

    /**
     * SQL fragment: provider serves a region (base, region area, any town in region, or state-wide for that region).
     */
    public static function sqlServesRegion(): string
    {
        return '(p.region_id = ? '
            . "OR EXISTS (SELECT 1 FROM provider_service_areas pra WHERE pra.provider_id = p.id AND pra.area_type = 'region' AND pra.region_id = ?) "
            . "OR EXISTS (SELECT 1 FROM provider_service_areas pta WHERE pta.provider_id = p.id AND pta.area_type = 'town' AND pta.town_id IN (SELECT id FROM towns WHERE region_id = ? AND is_active = 1)) "
            . "OR EXISTS (SELECT 1 FROM provider_service_areas pss WHERE pss.provider_id = p.id AND pss.area_type = 'state' AND pss.state_id = (SELECT state_id FROM regions WHERE id = ?)) "
            . 'OR p.base_town_id IN (SELECT id FROM towns WHERE region_id = ? AND is_active = 1))';
    }

    /** @return array<int,int> */
    public static function servesRegionParams(int $regionId): array
    {
        return array_fill(0, 5, $regionId);
    }

    public static function servesTown(int $providerId, int $townId): bool
    {
        if ($townId <= 0) {
            return false;
        }
        $sql = self::sqlServesTown();
        $params = array_merge([$providerId], self::servesTownParams($townId));
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM providers p WHERE p.id = ? AND p.status = 'active' AND p.deleted_at IS NULL AND {$sql}",
            $params
        ) > 0;
    }

    public static function servesRegion(int $providerId, int $regionId): bool
    {
        if ($regionId <= 0) {
            return false;
        }
        $sql = self::sqlServesRegion();
        $params = array_merge([$providerId], self::servesRegionParams($regionId));
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM providers p WHERE p.id = ? AND p.status = 'active' AND p.deleted_at IS NULL AND {$sql}",
            $params
        ) > 0;
    }

    private static function sqlRadiusFromBaseTown(): string
    {
        return "OR EXISTS (SELECT 1 FROM provider_service_areas pr "
            . 'INNER JOIN towns base ON base.id = p.base_town_id '
            . 'INNER JOIN towns tgt ON tgt.id = ? '
            . "WHERE pr.provider_id = p.id AND pr.area_type = 'radius' AND pr.radius_km IS NOT NULL AND pr.radius_km > 0 "
            . 'AND base.latitude IS NOT NULL AND base.longitude IS NOT NULL '
            . 'AND tgt.latitude IS NOT NULL AND tgt.longitude IS NOT NULL '
            . 'AND (6371 * ACOS(LEAST(1, GREATEST(-1, '
            . 'COS(RADIANS(base.latitude)) * COS(RADIANS(tgt.latitude)) * COS(RADIANS(tgt.longitude) - RADIANS(base.longitude)) '
            . '+ SIN(RADIANS(base.latitude)) * SIN(RADIANS(tgt.latitude)) '
            . ')))) <= pr.radius_km) ';
    }
}
