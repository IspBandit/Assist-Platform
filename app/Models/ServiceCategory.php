<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ServiceCategory extends Model
{
    protected static string $table = 'service_categories';

    /** @return array<int,array<string,mixed>> All categories with parent name (admin). */
    public static function listing(): array
    {
        return Database::select(
            'SELECT c.*, p.name AS parent_name FROM service_categories c '
            . 'LEFT JOIN service_categories p ON p.id = c.parent_id '
            . 'ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
    }

    /** @return array<int,array<string,mixed>> Active top-level categories. */
    public static function activeTopLevel(): array
    {
        return Database::select(
            'SELECT * FROM service_categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name'
        );
    }

    /** @return array<int,array<string,mixed>> Every active selectable service. */
    public static function activeAll(): array
    {
        return Database::select(
            'SELECT c.*, p.name AS parent_name FROM service_categories c '
            . 'LEFT JOIN service_categories p ON p.id = c.parent_id '
            . 'WHERE c.is_active = 1 '
            . 'ORDER BY COALESCE(p.sort_order, c.sort_order), p.name, c.parent_id IS NULL, c.sort_order, c.name'
        );
    }

    /**
     * Keep the broad traveller catalogue easy to scan without making database
     * hierarchy a prerequisite for older installations.
     *
     * @param array<int,array<string,mixed>> $categories
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function groupedForVanAssist(array $categories): array
    {
        $groups = [];
        foreach ($categories as $category) {
            $group = self::vanAssistGroup((string) ($category['slug'] ?? ''));
            $groups[$group][] = $category;
        }
        return $groups;
    }

    public static function vanAssistGroup(string $slug): string
    {
        $travel = [
            'fuel-and-travel-stops', 'ev-charging', 'lpg-refills-and-bottle-exchange',
            'potable-water-refill', 'dump-points', 'rest-areas-and-rv-friendly-parking',
            'caravan-parks-and-campgrounds', 'free-and-low-cost-camps',
            'groceries-and-travel-supplies', 'emergency-accommodation',
            'pet-friendly-travel-and-veterinary', 'vehicle-and-caravan-washing',
        ];
        $roadside = [
            'roadside-assistance', 'towing-and-vehicle-recovery', '4wd-and-remote-area-recovery',
            'mobile-mechanics', 'mechanical-repairs', 'diesel-mechanics', 'tyres-and-wheels',
            'brakes-and-bearings', 'suspension', 'auto-electrical-and-batteries',
            'locksmith-and-security', 'windscreen-and-auto-glass',
        ];
        $parts = [
            'caravan-and-rv-parts', 'towing-equipment-and-accessories', 'caravan-storage',
        ];
        $inspection = [
            'pre-trip-inspection', 'roadworthy-inspection', 'weighbridges-and-mobile-weighing',
            'trailer-and-engineering', 'insurance-repairs',
        ];
        $body = [
            'general-caravan-repairs', 'structural-repairs', 'fibreglass-repairs',
            'awning-repairs', 'roof-leaks', 'mobile-welding-and-fabrication',
        ];

        if (in_array($slug, $travel, true)) {
            return 'Travel essentials & places';
        }
        if (in_array($slug, $roadside, true)) {
            return 'Breakdown, vehicle & roadside help';
        }
        if (in_array($slug, $parts, true)) {
            return 'Parts, accessories & storage';
        }
        if (in_array($slug, $inspection, true)) {
            return 'Safety, inspection & compliance';
        }
        if (in_array($slug, $body, true)) {
            return 'Caravan body & structural repairs';
        }
        if ($slug === 'unsure-which-service-is-needed') {
            return 'Not sure what you need?';
        }
        return 'Caravan systems & appliances';
    }

    /** @return array<int,array<string,mixed>> Active children of a category. */
    public static function activeChildren(int $parentId): array
    {
        return Database::select(
            'SELECT * FROM service_categories WHERE is_active = 1 AND parent_id = ? ORDER BY sort_order, name',
            [$parentId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT * FROM service_categories WHERE slug = ? AND is_active = 1',
            [$slug]
        );
    }

    /** @return array<int,array<string,mixed>> Categories selectable as a parent (excludes self). */
    public static function parentOptions(?int $excludeId = null): array
    {
        $sql = 'SELECT id, name FROM service_categories WHERE parent_id IS NULL';
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY sort_order, name';
        return Database::select($sql, $params);
    }
}
