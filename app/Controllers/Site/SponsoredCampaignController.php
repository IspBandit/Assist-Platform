<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CampaignMetrics;
use App\Services\RegulatorySponsor;

final class SponsoredCampaignController extends Controller
{
    public function click(Request $request): Response
    {
        $id = (int) $request->route('campaign');
        $campaign = Database::selectOne(
            "SELECT c.*,COALESCE((SELECT SUM(m.spend_cents) FROM advertising_campaign_daily_metrics m WHERE m.campaign_id=c.id),0) AS spend,"
            . "COALESCE((SELECT dm.spend_cents FROM advertising_campaign_daily_metrics dm WHERE dm.campaign_id=c.id AND dm.metric_date=CURRENT_DATE),0) AS daily_spend "
            . "FROM advertising_campaigns c WHERE c.id=? AND c.brand_id=? AND c.status='active' "
            . "AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW())",
            [$id, current_brand()->databaseId()]
        );
        if ($campaign === null || !RegulatorySponsor::safeDestination((string) $campaign['destination_url'])) {
            $this->abort(404);
        }
        $price = (string) $campaign['billing_model'] === 'cpc' ? (int) ($campaign['unit_price_cents'] ?? 0) : 0;
        if ($campaign['total_budget_cents'] !== null && (int) $campaign['spend'] + $price > (int) $campaign['total_budget_cents']) {
            $this->abort(404);
        }
        if ($campaign['daily_budget_cents'] !== null && (int) $campaign['daily_spend'] + $price > (int) $campaign['daily_budget_cents']) {
            $this->abort(404);
        }
        CampaignMetrics::click($id, $price);
        Session::set('sponsored_attribution', [
            'campaign_id' => $id,
            'provider_id' => (int) ($campaign['advertiser_provider_id'] ?? 0),
            'expires_at' => time() + 86400,
        ]);
        return $this->redirect((string) $campaign['destination_url']);
    }
}
