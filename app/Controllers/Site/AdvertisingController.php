<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Advertising\ContextualCampaignService;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Brand\BrandContext;

final class AdvertisingController extends Controller
{
    public function go(Request $request): Response
    {
        $brand = BrandContext::current();
        if (!$brand->featureEnabled('advertising.enabled')) $this->abort(404, 'Page not found');
        $campaign = Database::selectOne(
            "SELECT c.id AS campaign_id, cr.id AS creative_id, c.context_key, c.destination_url FROM advertising_campaigns c INNER JOIN advertising_creatives cr ON cr.campaign_id=c.id INNER JOIN advertisers a ON a.id=c.advertiser_id WHERE c.id=? AND cr.id=? AND c.brand_id=? AND c.status='active' AND cr.status='approved' AND a.status='active' AND c.deleted_at IS NULL AND (c.starts_at IS NULL OR c.starts_at<=NOW()) AND (c.ends_at IS NULL OR c.ends_at>=NOW()) LIMIT 1",
            [(int)$request->route('campaign'), (int)$request->route('creative'), $brand->databaseId()],
        );
        if ($campaign === null || filter_var($campaign['destination_url'], FILTER_VALIDATE_URL) === false || !in_array(parse_url($campaign['destination_url'], PHP_URL_SCHEME), ['https'], true)) {
            $this->abort(404, 'Campaign not found');
        }
        (new ContextualCampaignService())->recordEvent($brand, $campaign, 'click');
        return (new Response('', 302))->withHeader('Location', (string)$campaign['destination_url']);
    }
}
