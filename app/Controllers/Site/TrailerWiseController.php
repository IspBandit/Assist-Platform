<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class TrailerWiseController extends Controller
{
    public function home(Request $request): Response
    {
        $this->requireBrand();
        $featured = $this->listings('', '', '', 6);
        return $this->view('trailerwise.home', [
            'title' => 'Smarter trailer ownership',
            'metaDescription' => 'Find Australian trailer repairers, service centres, parts suppliers, inspectors, certifiers and specialist businesses.',
            'canonical' => current_brand()->url() . '/',
            'listings' => $featured,
        ]);
    }

    public function marketplace(Request $request): Response
    {
        $this->requireBrand();
        $type = trim((string) $request->query('type', ''));
        $sale = trim((string) $request->query('listing_type', ''));
        $search = trim((string) $request->query('q', ''));
        return $this->view('trailerwise.marketplace', [
            'title' => 'Trailer marketplace',
            'canonical' => current_brand()->url() . '/marketplace',
            'listings' => $this->listings($type, $sale, $search, 60),
            'filters' => ['type' => $type, 'listing_type' => $sale, 'q' => $search],
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requireBrand();
        $listing = Database::selectOne(
            "SELECT l.*, p.business_name, p.phone, p.email, p.website FROM trailer_listings l JOIN providers p ON p.id = l.provider_id WHERE l.brand_id = ? AND l.slug = ? AND l.status = 'active' AND l.deleted_at IS NULL AND p.status = 'active' AND p.deleted_at IS NULL",
            [current_brand()->databaseId(), (string) $request->route('slug')]
        );
        if ($listing === null) {
            $this->abort(404);
        }
        return $this->view('trailerwise.show', [
            'title' => $listing['title'],
            'metaDescription' => mb_substr(strip_tags((string) $listing['description']), 0, 300),
            'canonical' => current_brand()->url() . '/trailers/' . $listing['slug'],
            'listing' => $listing,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function listings(string $type, string $sale, string $search, int $limit): array
    {
        $where = ["l.brand_id = ?", "l.status = 'active'", 'l.deleted_at IS NULL', "p.status = 'active'", 'p.deleted_at IS NULL'];
        $params = [current_brand()->databaseId()];
        if ($type !== '') { $where[] = 'l.trailer_type = ?'; $params[] = $type; }
        if ($sale !== '') { $where[] = 'l.listing_type = ?'; $params[] = $sale; }
        if ($search !== '') { $where[] = '(l.title LIKE ? OR l.make LIKE ? OR l.model LIKE ?)'; $like = '%' . $search . '%'; array_push($params, $like, $like, $like); }
        return Database::select('SELECT l.*, p.business_name FROM trailer_listings l JOIN providers p ON p.id = l.provider_id WHERE ' . implode(' AND ', $where) . ' ORDER BY l.is_featured DESC, l.created_at DESC LIMIT ' . $limit, $params);
    }

    private function requireBrand(): void
    {
        if (current_brand()->id() !== 'trailerwise' || !current_brand()->moduleEnabled('trailer_marketplace')) {
            $this->abort(404);
        }
    }
}
