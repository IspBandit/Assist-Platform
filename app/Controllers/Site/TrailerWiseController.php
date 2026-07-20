<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Brand\BrandContext;

final class TrailerWiseController extends Controller
{
    public function index(Request $request): Response
    {
        $brand = $this->brand();
        $type = trim((string) $request->query('type', ''));
        $params = [$brand->databaseId()];
        $filter = '';
        if ($type !== '') {
            $filter = ' AND tt.slug = ?';
            $params[] = $type;
        }
        $listings = Database::select(
            "SELECT tl.slug, tl.title, tl.manufacturer_name, tl.model_name, tl.model_year, tl.listing_kind, tl.price_aud_cents, tt.name AS trailer_type, pbl.display_name AS provider_name "
            . "FROM trailer_listings tl INNER JOIN trailer_types tt ON tt.id = tl.trailer_type_id "
            . "INNER JOIN provider_brand_listings pbl ON pbl.id = tl.provider_listing_id "
            . "WHERE pbl.brand_id = ? AND pbl.status = 'active' AND pbl.deleted_at IS NULL AND tl.status = 'active' AND tl.deleted_at IS NULL" . $filter
            . " ORDER BY tl.published_at DESC, tl.id DESC LIMIT 60",
            $params,
        );
        $types = Database::select("SELECT slug, name FROM trailer_types WHERE is_active = 1 ORDER BY sort_order, name");
        return $this->view('brands.trailerwise-marketplace', compact('brand', 'listings', 'types', 'type'));
    }

    public function show(Request $request): Response
    {
        $brand = $this->brand();
        $listing = Database::selectOne(
            "SELECT tl.*, tt.name AS trailer_type, pbl.display_name AS provider_name FROM trailer_listings tl "
            . "INNER JOIN trailer_types tt ON tt.id = tl.trailer_type_id INNER JOIN provider_brand_listings pbl ON pbl.id = tl.provider_listing_id "
            . "WHERE pbl.brand_id = ? AND pbl.status = 'active' AND tl.status = 'active' AND tl.deleted_at IS NULL AND tl.slug = ? LIMIT 1",
            [$brand->databaseId(), (string) $request->route('slug')],
        );
        if ($listing === null) {
            $this->abort(404, 'Listing not found');
        }
        return $this->view('brands.trailerwise-listing', compact('brand', 'listing'));
    }

    private function brand(): \App\Platform\Brand\Brand
    {
        $brand = BrandContext::current();
        if ($brand->id() !== 'trailerwise' || !$brand->moduleEnabled('trailer_marketplace')) {
            $this->abort(404, 'Page not found');
        }
        return $brand;
    }
}
