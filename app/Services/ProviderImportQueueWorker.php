<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Platform\Brand\BrandRegistry;
use RuntimeException;

/**
 * Advances provider discovery and publication work without an open browser.
 *
 * Google Places rows remain temporary review candidates unless independent
 * evidence has been recorded. Safe duplicates and already-confirmed listings
 * are processed automatically; claimed provider records are never changed.
 */
final class ProviderImportQueueWorker
{
    /** @return array<string,mixed> */
    public function run(float $seconds = 45.0): array
    {
        $deadline = microtime(true) + max(5.0, min(240.0, $seconds));
        $brand = BrandRegistry::fromArray((array) config('brands.registry', []))->find('vanassist');
        if ($brand === null) {
            throw new RuntimeException('VanAssist brand is not configured.');
        }
        $brandId = $brand->databaseId();

        $screened = 0;
        $screenedNew = 0;
        $screenedHeld = 0;
        $screenedMerged = 0;
        while (microtime(true) < $deadline - 3.0) {
            $job = Database::selectOne(
                "SELECT j.id FROM data_source_import_jobs j INNER JOIN data_source_connectors c ON c.id=j.connector_id "
                . "WHERE j.brand_id=? AND c.connector_key='national_route_places' AND j.status IN ('queued','running') ORDER BY j.id LIMIT 1",
                [$brandId]
            );
            if ($job === null) {
                break;
            }
            $batch = (new NationalRouteImportService())->processJob((int) $job['id'], $brandId, 500);
            $screened += (int) $batch['processed'];
            $screenedNew += (int) $batch['inserted'];
            $screenedHeld += (int) $batch['held'];
            $screenedMerged += (int) $batch['auto_merged'];
            if ((int) $batch['processed'] === 0 && empty($batch['done'])) {
                break;
            }
        }

        $reviewerId = $this->reviewerId($brandId);
        $duplicates = ['processed'=>0, 'remaining'=>0];
        $publication = ['merged'=>0, 'approved'=>0, 'processed'=>0, 'failed'=>0, 'blocked'=>0, 'remaining'=>0, 'reasons'=>[]];
        if ($reviewerId > 0 && microtime(true) < $deadline - 2.0) {
            do {
                $duplicateBatch = (new DataSourceService())->resolveExactDuplicates($brandId, $reviewerId, 2000);
                $duplicates['processed'] += (int) $duplicateBatch['processed'];
                $duplicates['remaining'] = (int) $duplicateBatch['remaining'];
            } while ($duplicateBatch['processed'] > 0 && $duplicates['remaining'] > 0 && microtime(true) < $deadline - 2.0);

            do {
                $batch = (new DataSourceService())->processEligibleQueue([], $brandId, $reviewerId, 150, 12.0);
                foreach (['merged','approved','processed','failed'] as $key) {
                    $publication[$key] += (int) $batch[$key];
                }
                $publication['blocked'] = (int) $batch['blocked'];
                $publication['remaining'] = (int) $batch['remaining'];
                $publication['reasons'] = (array) $batch['reasons'];
            } while ($batch['processed'] > 0 && $publication['remaining'] > 0 && microtime(true) < $deadline - 1.0);
        }

        $campaigns = ProviderCampaignDrafts::prepareForBrand($brandId);
        $status = (new DataSourceService())->eligibleQueueSummary($brandId, []);
        $activeJobs = (int) Database::scalar(
            "SELECT COUNT(*) FROM data_source_import_jobs j INNER JOIN data_source_connectors c ON c.id=j.connector_id "
            . "WHERE j.brand_id=? AND c.connector_key='national_route_places' AND j.status IN ('queued','running')",
            [$brandId]
        );

        return [
            'screened'=>$screened,
            'candidates_new'=>$screenedNew,
            'screened_held'=>$screenedHeld,
            'screened_merged'=>$screenedMerged,
            'duplicates_merged'=>$duplicates['processed'],
            'providers_published'=>$publication['approved'],
            'publication_failed'=>$publication['failed'],
            'eligible_remaining'=>$status['eligible'],
            'review_required'=>$status['blocked'],
            'pending_total'=>$status['pending'],
            'active_import_jobs'=>$activeJobs,
            'campaign_drafts_created'=>$campaigns,
            'reviewer_available'=>$reviewerId > 0,
            'blocked_reasons'=>$status['reasons'],
        ];
    }

    private function reviewerId(int $brandId): int
    {
        $requested = (int) Database::scalar(
            'SELECT requested_by FROM data_source_import_jobs WHERE brand_id=? AND requested_by IS NOT NULL ORDER BY id DESC LIMIT 1',
            [$brandId]
        );
        if ($requested > 0 && (int) Database::scalar("SELECT COUNT(*) FROM users WHERE id=? AND status='active' AND deleted_at IS NULL", [$requested]) === 1) {
            return $requested;
        }
        return (int) Database::scalar(
            "SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id "
            . "WHERE u.status='active' AND u.deleted_at IS NULL AND r.slug='super-administrator' ORDER BY u.id LIMIT 1"
        );
    }
}
