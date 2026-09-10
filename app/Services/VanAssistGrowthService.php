<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/** Read model and guarded actions for VanAssist's trust-and-growth work. */
final class VanAssistGrowthService
{
    /** @return array<string,mixed> */
    public function dashboard(int $brandId): array
    {
        return [
            'facility_summary' => $this->facilitySummary($brandId),
            'facility_types' => $this->facilityTypes($brandId),
            'facility_states' => $this->facilityStates($brandId),
            'search_priorities' => $this->searchPriorities($brandId),
            'provider_trust' => $this->providerTrust($brandId),
            'seo_candidates' => $this->seoCandidates($brandId),
        ];
    }

    /** @return array<string,int> */
    private function facilitySummary(int $brandId): array
    {
        $scope = '(brand_id IS NULL OR brand_id=?)';
        return [
            'published' => (int) Database::scalar("SELECT COUNT(*) FROM traveller_facilities WHERE {$scope} AND status='active' AND verification_status IN ('reviewed','verified') AND deleted_at IS NULL", [$brandId]),
            'searchable' => (int) Database::scalar("SELECT COUNT(*) FROM traveller_facilities WHERE {$scope} AND status='active' AND verification_status IN ('reviewed','verified') AND latitude IS NOT NULL AND longitude IS NOT NULL AND deleted_at IS NULL", [$brandId]),
            'verified' => (int) Database::scalar("SELECT COUNT(*) FROM traveller_facilities WHERE {$scope} AND status='active' AND verification_status='verified' AND deleted_at IS NULL", [$brandId]),
            'pending_candidates' => (int) Database::scalar("SELECT COUNT(*) FROM traveller_facility_import_candidates WHERE (brand_id IS NULL OR brand_id=?) AND review_status='pending'", [$brandId]),
            'rejected_candidates' => (int) Database::scalar("SELECT COUNT(*) FROM traveller_facility_import_candidates WHERE (brand_id IS NULL OR brand_id=?) AND review_status='rejected'", [$brandId]),
            'active_datasets' => (int) Database::scalar("SELECT COUNT(*) FROM government_datasets WHERE is_enabled=1"),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function facilityTypes(int $brandId): array
    {
        return Database::select(
            "SELECT facility_type AS label, COUNT(*) AS published, SUM(latitude IS NOT NULL AND longitude IS NOT NULL) AS searchable, SUM(verification_status='verified') AS verified, MAX(last_checked_at) AS last_checked_at FROM traveller_facilities WHERE (brand_id IS NULL OR brand_id=?) AND status='active' AND verification_status IN ('reviewed','verified') AND deleted_at IS NULL GROUP BY facility_type ORDER BY published DESC,facility_type",
            [$brandId]
        );
    }

    /** @return list<array<string,mixed>> */
    private function facilityStates(int $brandId): array
    {
        return Database::select(
            "SELECT COALESCE(s.abbreviation,'Unresolved') AS label, COUNT(*) AS published, COUNT(DISTINCT tf.facility_type) AS types, SUM(tf.latitude IS NOT NULL AND tf.longitude IS NOT NULL) AS searchable FROM traveller_facilities tf LEFT JOIN states s ON s.id=tf.state_id WHERE (tf.brand_id IS NULL OR tf.brand_id=?) AND tf.status='active' AND tf.verification_status IN ('reviewed','verified') AND tf.deleted_at IS NULL GROUP BY COALESCE(s.abbreviation,'Unresolved') ORDER BY published DESC,label",
            [$brandId]
        );
    }

    /** @return list<array<string,mixed>> */
    private function searchPriorities(int $brandId): array
    {
        return Database::select(
            "SELECT id,original_query_sample AS query_sample,intent_type,COALESCE(location_text,'Location unresolved') AS location_name,search_count,zero_result_count,weak_result_count,click_through_count,contact_action_count,priority_score,last_seen_at,resolution_status FROM knowledge_gaps WHERE (brand_id=? OR (brand_id IS NULL AND brand_key='vanassist')) AND resolution_status IN ('open','researching') ORDER BY priority_score DESC,zero_result_count DESC,last_seen_at DESC LIMIT 40",
            [$brandId]
        );
    }

    /** @return array<string,int> */
    private function providerTrust(int $brandId): array
    {
        return [
            'active' => (int) Database::scalar("SELECT COUNT(DISTINCT p.id) FROM providers p JOIN provider_brand_listings l ON l.provider_id=p.id AND l.brand_id=? WHERE p.status='active' AND p.deleted_at IS NULL AND l.status='active' AND l.search_visible=1 AND l.deleted_at IS NULL", [$brandId]),
            'claimed' => (int) Database::scalar("SELECT COUNT(DISTINCT p.id) FROM providers p JOIN provider_brand_listings l ON l.provider_id=p.id AND l.brand_id=? WHERE p.status='active' AND p.deleted_at IS NULL AND p.is_unclaimed=0 AND l.status='active' AND l.search_visible=1 AND l.deleted_at IS NULL", [$brandId]),
            'verified' => (int) Database::scalar("SELECT COUNT(DISTINCT p.id) FROM providers p JOIN provider_brand_listings l ON l.provider_id=p.id AND l.brand_id=? WHERE p.status='active' AND p.deleted_at IS NULL AND p.is_verified=1 AND p.verified_at IS NOT NULL AND l.status='active' AND l.search_visible=1 AND l.deleted_at IS NULL", [$brandId]),
            'pending_claims' => (int) Database::scalar("SELECT COUNT(*) FROM provider_prospects pp WHERE pp.request_type='claim' AND pp.claim_status='pending' AND EXISTS (SELECT 1 FROM provider_brand_listings l WHERE l.provider_id=pp.provider_id AND l.brand_id=? AND l.deleted_at IS NULL)", [$brandId]),
            'evidence_requested' => (int) Database::scalar("SELECT COUNT(*) FROM provider_prospects pp WHERE pp.request_type='claim' AND pp.claim_status='evidence_requested' AND EXISTS (SELECT 1 FROM provider_brand_listings l WHERE l.provider_id=pp.provider_id AND l.brand_id=? AND l.deleted_at IS NULL)", [$brandId]),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function seoCandidates(int $brandId): array
    {
        $rows = Database::select(
            "SELECT t.id,t.name,t.slug,s.abbreviation AS state_abbr,t.public_content,t.seo_title,t.seo_description,t.noindex, COALESCE(p.providers,0) AS providers,COALESCE(f.facilities,0) AS facilities,COALESCE(k.stays,0) AS stays,COALESCE(q.searches,0) AS searches FROM towns t JOIN states s ON s.id=t.state_id LEFT JOIN (SELECT p.base_town_id AS town_id,COUNT(DISTINCT p.id) AS providers FROM providers p JOIN provider_brand_listings l ON l.provider_id=p.id AND l.brand_id=? WHERE p.status='active' AND p.deleted_at IS NULL AND l.status='active' AND l.search_visible=1 AND l.deleted_at IS NULL GROUP BY p.base_town_id) p ON p.town_id=t.id LEFT JOIN (SELECT town_id,COUNT(*) AS facilities FROM traveller_facilities WHERE (brand_id IS NULL OR brand_id=?) AND status='active' AND verification_status IN ('reviewed','verified') AND deleted_at IS NULL GROUP BY town_id) f ON f.town_id=t.id LEFT JOIN (SELECT town_id,COUNT(*) AS stays FROM caravan_parks WHERE status='active' AND public_page_enabled=1 AND deleted_at IS NULL GROUP BY town_id) k ON k.town_id=t.id LEFT JOIN (SELECT town_id,COUNT(*) AS searches FROM provider_searches WHERE brand_id=? AND is_excluded=0 AND created_at>=DATE_SUB(NOW(),INTERVAL 90 DAY) GROUP BY town_id) q ON q.town_id=t.id WHERE t.is_active=1 AND t.noindex=1 ORDER BY (COALESCE(p.providers,0)+COALESCE(f.facilities,0)+COALESCE(k.stays,0)) DESC,COALESCE(q.searches,0) DESC,t.name LIMIT 100",
            [$brandId, $brandId, $brandId]
        );
        foreach ($rows as &$row) {
            $evidence = (int) $row['providers'] + (int) $row['facilities'] + (int) $row['stays'];
            $contentReady = trim((string) $row['public_content']) !== '' && trim((string) $row['seo_title']) !== '' && trim((string) $row['seo_description']) !== '';
            $row['evidence_total'] = $evidence;
            $row['eligible'] = $evidence >= 3 && $contentReady;
            $row['blocker'] = $contentReady ? ($evidence < 3 ? 'Needs at least three live local records.' : '') : 'Needs reviewed public copy, title and description.';
        }
        unset($row);
        return $rows;
    }

    public function publishTown(int $brandId, int $townId): void
    {
        foreach ($this->seoCandidates($brandId) as $candidate) {
            if ((int) $candidate['id'] !== $townId) continue;
            if (empty($candidate['eligible'])) throw new RuntimeException((string) $candidate['blocker']);
            Database::query('UPDATE towns SET noindex=0,is_featured=1,updated_at=NOW() WHERE id=? AND noindex=1', [$townId]);
            AuditLog::record('seo.town_published', 'town', (string) $townId, 'noindex', 'index');
            return;
        }
        throw new RuntimeException('Town is not an eligible evidence-backed SEO candidate.');
    }
}
