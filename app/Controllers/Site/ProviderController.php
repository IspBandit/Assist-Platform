<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Models\Town;
use App\Services\Demand\DemandRecorder;
use App\Services\DirectoryPresentation;
use App\Services\FoundingGraphicService;
use App\Services\SeoSchema;

final class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 18;
        $townId = (int) $request->input('town') ?: null;
        $location = trim((string) $request->input('location', ''));
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        $lat = is_numeric($latRaw) ? (float) $latRaw : null;
        $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;
        $hasCoords = $lat !== null && $lng !== null
            && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;
        $resolvedTown = null;
        if ($hasCoords) {
            $gpsTown = Town::nearestActive($lat, $lng);
            $resolvedTown = $gpsTown;
            $townId = isset($gpsTown['id']) ? (int) $gpsTown['id'] : null;
            if ($gpsTown !== null) {
                $location = (string) $gpsTown['name'];
                if (!empty($gpsTown['state_abbr'])) {
                    $location .= ', ' . $gpsTown['state_abbr'];
                }
            }
        } elseif ($location !== '') {
            $townMatches = Town::searchActive($location);
            $resolvedTown = $townMatches[0] ?? null;
            $townId = isset($townMatches[0]['id']) ? (int) $townMatches[0]['id'] : null;
        }
        $locationFound = ($location === '' && !$hasCoords) || $townId !== null;
        $categoryId = (int) $request->input('category') ?: null;
        $brand = current_brand();
        $brandScoped = $brand->id() !== 'vanassist';

        $result = !$locationFound
            ? ['rows' => [], 'total' => 0]
            : ($brandScoped
                ? Provider::brandDirectory($brand->databaseId(), $townId, $categoryId, $search, $perPage, ($page - 1) * $perPage)
                : Provider::publicDirectory($townId, $categoryId, $search, $perPage, ($page - 1) * $perPage));
        $categories = $brandScoped
            ? Database::select('SELECT id, name FROM brand_provider_categories WHERE brand_id = ? AND is_active = 1 ORDER BY sort_order, name', [$brand->databaseId()])
            : Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name');

        // Treat a filtered directory browse as a search on every brand. This is
        // best-effort and remains a no-op while demand analytics is disabled.
        if ($search !== '' || $location !== '' || $hasCoords || $categoryId !== null) {
            $searchId = DemandRecorder::recordSearch([
                'town_id' => $townId,
                'region_id' => $resolvedTown['region_id'] ?? null,
                'state_id' => $resolvedTown['state_id'] ?? null,
                'category_id' => $categoryId,
                'result_count' => (int) $result['total'],
            ]);
            DemandRecorder::recordImpressions($searchId, $result['rows'], $categoryId);
        }

        return $this->view('public.providers-index', [
            'title' => 'Find a service provider — ' . $brand->name(),
            'metaDescription' => 'Browse relevant Australian service providers in the ' . $brand->name() . ' network.',
            'canonical' => url('providers'),
            'providers' => $result['rows'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
            'location' => $location,
            'lat' => $hasCoords ? $lat : null,
            'lng' => $hasCoords ? $lng : null,
            'locationFound' => $locationFound,
            'townId' => $townId,
            'categoryId' => $categoryId,
            'categories' => $categories,
            'brand' => $brand,
            'directoryCopy' => DirectoryPresentation::copyFor($brand->id()),
        ]);
    }

    public function show(Request $request): Response
    {
        $brand = current_brand();
        $brandScoped = $brand->id() !== 'vanassist';
        $slug = (string) $request->route('slug');
        $provider = $brandScoped
            ? Provider::findPublicBrandBySlug($brand->databaseId(), $slug)
            : Provider::findPublicBySlug($slug);
        if ($provider === null) { $this->abort(404, 'Provider not found.'); }

        if ($brandScoped) {
            $provider['business_name'] = $provider['brand_display_name'];
            $provider['is_verified'] = $provider['brand_verified'];
            $provider['is_featured'] = $provider['brand_featured'];
        }

        $id = (int) $provider['id'];
        $searchId = (int) $request->input('s') ?: null;
        DemandRecorder::recordProfileView($id, $searchId);
        $runs = [];
        if ($brand->id() === 'vanassist' && Database::tableExists('service_runs')) {
            $runs = Database::select(
                "SELECT id, title, slug, status, start_date FROM service_runs WHERE provider_id = ? AND status IN ('forming','confirmed','limited') AND is_public = 1 AND deleted_at IS NULL ORDER BY start_date LIMIT 10",
                [$id]
            );
        }
        $publicSlug = (string) ($provider['brand_slug'] ?? $provider['slug']);
        $profilePath = $brand->id() === 'localtorque' ? 'business/' . $publicSlug : 'providers/' . $publicSlug;

        return $this->view('public.provider-profile', [
            'title' => ($provider['brand_seo_title'] ?? $provider['seo_title'] ?? null) ?: ($provider['business_name'] . ' — ' . $brand->name()),
            'metaDescription' => ($provider['brand_seo_description'] ?? $provider['seo_description'] ?? null) ?: ('Services from ' . $provider['business_name'] . ' on ' . $brand->name() . '.'),
            'canonical' => url($profilePath),
            'provider' => $provider,
            'searchId' => $searchId,
            'services' => $brandScoped ? Provider::brandServices($brand->databaseId(), $id) : Provider::services($id),
            'areas' => Provider::areas($id),
            'licences' => Database::select(
                "SELECT licence_type, issuing_authority FROM provider_licences WHERE provider_id = ? AND verification_status = 'verified' AND display_publicly = 1 ORDER BY licence_type",
                [$id]
            ),
            'capabilities' => Database::select(
                "SELECT capability_label,jurisdiction_code,valid_until FROM provider_capability_credentials WHERE provider_id=? AND brand_id=? AND verification_status='verified' AND (valid_until IS NULL OR valid_until>=CURRENT_DATE) ORDER BY capability_label",
                [$id, $brand->databaseId()]
            ),
            'runs' => $runs,
            'jsonLd' => [
                $this->providerSchema($provider, $publicSlug),
                SeoSchema::breadcrumbs([
                    ['name'=>'Home','url'=>url('/')],
                    ['name'=>'Providers','url'=>url('providers')],
                    ['name'=>(string)$provider['business_name'],'url'=>url($profilePath)],
                ]),
            ],
            'promotionAd' => $brand->id() === 'vanassist' ? FoundingGraphicService::deliveredAd($id) : null,
            'brand' => $brand,
            'requestsEnabled' => $brand->moduleEnabled('requests'),
        ]);
    }

    /** @param array<string,mixed> $provider */
    private function providerSchema(array $provider, string $publicSlug): ?string
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => (string) ($provider['brand_display_name'] ?? $provider['business_name']),
            'url' => url(current_brand()->id() === 'localtorque' ? 'business/' . $publicSlug : 'providers/' . $publicSlug),
        ];
        if (!empty($provider['description'])) { $data['description'] = mb_substr(strip_tags((string) $provider['description']), 0, 300); }
        if (!empty($provider['show_public_phone']) && !empty($provider['public_phone'])) { $data['telephone'] = (string) $provider['public_phone']; }
        if (!empty($provider['town_name'])) {
            $data['address'] = ['@type' => 'PostalAddress', 'addressLocality' => (string) $provider['town_name'], 'addressRegion' => (string) ($provider['state_abbr'] ?? ''), 'addressCountry' => 'AU'];
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
