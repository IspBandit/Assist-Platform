<?php
declare(strict_types=1);
namespace App\Platform\DataIntelligence\Sources;

use App\Core\Database;
use App\Platform\DataIntelligence\MetricSourceInterface;

final class ProviderCoverageSource implements MetricSourceInterface
{
    public function key(): string { return 'provider_coverage'; }

    public function coverage(int $brandId, array $filters = []): array
    {
        $stateId = max(0, (int) ($filters['state_id'] ?? 0));
        $categoryId = max(0, (int) ($filters['category_id'] ?? 0));
        $where = ['t.is_active=1', 'c.brand_id=?', 'c.is_active=1'];
        $params = [$brandId];
        if ($stateId > 0) { $where[] = 't.state_id=?'; $params[] = $stateId; }
        if ($categoryId > 0) { $where[] = 'c.id=?'; $params[] = $categoryId; }

        $sql = 'SELECT t.id AS town_id,t.name AS town,t.primary_postcode,t.latitude,t.longitude,'
            . 's.id AS state_id,s.name AS state,s.abbreviation,c.id AS category_id,c.name AS category,'
            . 'COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND a.category_id IS NOT NULL AND l.status=\'active\' AND l.search_visible=1 THEN l.id END) AS providers,'
            . 'COUNT(DISTINCT CASE WHEN p.id IS NOT NULL AND a.category_id IS NOT NULL AND l.status=\'active\' AND l.search_visible=1 AND l.is_verified=1 THEN l.id END) AS verified,'
            . 'COALESCE(MAX(pop.population),0) AS population,'
            . 'COUNT(DISTINCT CASE WHEN ps.result_count=0 THEN ps.id END) AS zero_results '
            . 'FROM towns t JOIN states s ON s.id=t.state_id CROSS JOIN brand_provider_categories c '
            . 'LEFT JOIN providers p ON p.base_town_id=t.id AND p.deleted_at IS NULL '
            . 'LEFT JOIN provider_brand_listings l ON l.provider_id=p.id AND l.brand_id=c.brand_id AND l.deleted_at IS NULL '
            . 'LEFT JOIN provider_brand_category_assignments a ON a.listing_id=l.id AND a.category_id=c.id '
            . 'LEFT JOIN (SELECT town_id,MAX(population) population FROM locality_population_statistics GROUP BY town_id) pop ON pop.town_id=t.id '
            . 'LEFT JOIN service_categories sc ON sc.slug=c.category_key AND sc.is_active=1 '
            . 'LEFT JOIN provider_searches ps ON ps.town_id=t.id AND ps.category_id=sc.id AND ps.is_excluded=0 AND ps.created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) '
            . 'WHERE ' . implode(' AND ', $where) . ' GROUP BY t.id,t.name,t.primary_postcode,t.latitude,t.longitude,s.id,s.name,s.abbreviation,c.id,c.name '
            . 'HAVING providers < 10 OR zero_results > 0 ORDER BY population DESC,zero_results DESC,t.name LIMIT 2000';
        return Database::select($sql, $params);
    }
}
