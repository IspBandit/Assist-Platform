<?php

declare(strict_types=1);

namespace App\Advertising;

use App\Core\Database;
use App\Platform\Brand\Brand;
use Throwable;

final class ContextualCampaignService
{
    /** @param array<int,string> $contexts @return array<int,array<string,mixed>> */
    public function forResult(Brand $brand, array $contexts, int $limit = 2): array
    {
        if (!$brand->featureEnabled('advertising.enabled') || $contexts === [] || $limit < 1) {
            return [];
        }
        try {
            if (!Database::tableExists('advertising_campaigns')) return [];
            $contexts = array_values(array_unique(array_merge($contexts, ['general'])));
            $marks = implode(',', array_fill(0, count($contexts), '?'));
            $campaigns = Database::select(
                "SELECT c.id AS campaign_id, cr.id AS creative_id, c.context_key, c.destination_url, c.sponsorship_label, cr.headline, cr.body_text, cr.call_to_action, cr.image_path, cr.alt_text, a.business_name "
                . "FROM advertising_campaigns c INNER JOIN advertisers a ON a.id=c.advertiser_id INNER JOIN advertising_creatives cr ON cr.campaign_id=c.id "
                . "WHERE c.brand_id=? AND c.placement='towwise_result' AND c.context_key IN ({$marks}) AND c.status='active' AND c.deleted_at IS NULL AND a.status='active' AND a.deleted_at IS NULL AND cr.status='approved' "
                . "AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) ORDER BY c.priority DESC, c.id DESC LIMIT " . min(6, $limit),
                array_merge([$brand->databaseId()], $contexts),
            );
            foreach ($campaigns as $campaign) {
                $this->recordEvent($brand, $campaign, 'impression');
            }
            return $campaigns;
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string,mixed> $campaign */
    public function recordEvent(Brand $brand, array $campaign, string $event): void
    {
        if (!in_array($event, ['impression', 'click'], true)) return;
        try {
            Database::query(
                'INSERT INTO advertising_events (campaign_id, creative_id, brand_id, event_type, placement, context_key, session_hash, created_at) VALUES (?,?,?,?,?,?,?,NOW())',
                [(int)$campaign['campaign_id'], (int)$campaign['creative_id'], $brand->databaseId(), $event, 'towwise_result', (string)$campaign['context_key'], session_id() !== '' ? hash('sha256', session_id()) : null],
            );
        } catch (Throwable) {
        }
    }
}
