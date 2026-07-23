<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Deterministic, restartable classification of canonical providers into brand directories. */
final class BrandProviderEnrichmentService
{
    /** @return array{providers_scanned:int,listings_created:int,assignments_created:int,evidence_created:int} */
    public function run(bool $dryRun = false): array
    {
        foreach (['provider_brand_listings', 'brand_provider_categories', 'provider_brand_category_assignments', 'provider_discovery_evidence'] as $table) {
            if (!Database::tableExists($table)) { throw new RuntimeException('Run migration 038 before provider enrichment.'); }
        }
        $rules = (array) config('provider_discovery', []);
        $brandIds = ['towsmart' => 2, 'trailerwise' => 3, 'localtorque' => 4];
        $providers = Database::select(
            "SELECT p.id, p.business_name, p.slug, p.description, p.is_unclaimed, GROUP_CONCAT(DISTINCT c.name SEPARATOR ' ') AS service_names "
            . 'FROM providers p LEFT JOIN provider_services ps ON ps.provider_id = p.id LEFT JOIN service_categories c ON c.id = ps.category_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL GROUP BY p.id, p.business_name, p.slug, p.description, p.is_unclaimed"
        );
        $counts = ['providers_scanned' => count($providers), 'listings_created' => 0, 'assignments_created' => 0, 'evidence_created' => 0];

        foreach ($providers as $provider) {
            $haystack = mb_strtolower(implode(' ', [(string) $provider['business_name'], (string) ($provider['description'] ?? ''), (string) ($provider['service_names'] ?? '')]));
            foreach ($brandIds as $brandKey => $brandId) {
                $matches = [];
                foreach ((array) ($rules[$brandKey] ?? []) as $categoryKey => $keywords) {
                    $hits = 0;
                    foreach ((array) $keywords as $keyword) { if (str_contains($haystack, mb_strtolower((string) $keyword))) { $hits++; } }
                    if ($hits > 0) { $matches[(string) $categoryKey] = min(95, 68 + ($hits * 9)); }
                }
                if ($matches === []) { continue; }

                $listingId = (int) Database::scalar('SELECT id FROM provider_brand_listings WHERE brand_id = ? AND provider_id = ?', [$brandId, $provider['id']]);
                if ($dryRun) {
                    if ($listingId === 0) {
                        $counts['listings_created']++;
                        foreach (array_keys($matches) as $categoryKey) {
                            if ((int) Database::scalar('SELECT id FROM brand_provider_categories WHERE brand_id = ? AND category_key = ?', [$brandId, $categoryKey]) > 0) {
                                $counts['assignments_created']++;
                            }
                        }
                    } else {
                        foreach (array_keys($matches) as $categoryKey) {
                            $exists = (int) Database::scalar(
                                'SELECT COUNT(*) FROM provider_brand_category_assignments a '
                                . 'JOIN brand_provider_categories c ON c.id = a.category_id '
                                . 'WHERE a.listing_id = ? AND c.brand_id = ? AND c.category_key = ?',
                                [$listingId, $brandId, $categoryKey]
                            );
                            if ($exists === 0 && (int) Database::scalar('SELECT id FROM brand_provider_categories WHERE brand_id = ? AND category_key = ?', [$brandId, $categoryKey]) > 0) {
                                $counts['assignments_created']++;
                            }
                        }
                    }
                    $evidenceExists = (int) Database::scalar(
                        "SELECT COUNT(*) FROM provider_discovery_evidence WHERE provider_id = ? AND brand_id = ? AND source_type = 'existing_catalogue' AND source_reference = 'canonical-provider'",
                        [$provider['id'], $brandId]
                    );
                    if ($evidenceExists === 0) { $counts['evidence_created']++; }
                    continue;
                }

                if ($listingId === 0) {
                    $listingId = Database::insert(
                        "INSERT INTO provider_brand_listings (brand_id, provider_id, slug, display_name, status, is_featured, is_verified, search_visible, created_at) VALUES (?, ?, ?, ?, 'active', 0, 0, 1, NOW())",
                        [$brandId, $provider['id'], $provider['slug'], $provider['business_name']]
                    );
                    $counts['listings_created']++;
                }
                foreach ($matches as $categoryKey => $confidence) {
                    $categoryId = (int) Database::scalar('SELECT id FROM brand_provider_categories WHERE brand_id = ? AND category_key = ?', [$brandId, $categoryKey]);
                    if ($categoryId === 0) { continue; }
                    $counts['assignments_created'] += Database::affecting(
                        "INSERT IGNORE INTO provider_brand_category_assignments (listing_id, category_id, assignment_source, confidence, is_verified, created_at) VALUES (?, ?, 'heuristic', ?, 0, NOW())",
                        [$listingId, $categoryId, $confidence]
                    );
                }
                $counts['evidence_created'] += Database::affecting(
                    "INSERT IGNORE INTO provider_discovery_evidence (provider_id, brand_id, source_type, source_reference, verification_status, discovered_at, notes) VALUES (?, ?, 'existing_catalogue', 'canonical-provider', 'discovered', NOW(), 'Automatically classified from canonical provider name, description and services; requires business or administrator verification.')",
                    [$provider['id'], $brandId]
                );
            }
        }
        return $counts;
    }
}
