<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Geo;
use App\Models\BrandProviderCategory;
use App\Models\Provider;
use App\Models\Town;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Services\Demand\DemandRecorder;
use App\Services\DirectoryPresentation;
use App\Services\FoundingGraphicService;
use App\Services\RoadDistance\RoadDistanceService;
use App\Services\SeoSchema;

final class ProviderController extends Controller
{
    private const DISTANCE_RANK_CANDIDATE_LIMIT = 2500;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('q', ''));
        if ($search === '') {
            $search = trim((string) $request->input('text', ''));
        }
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

        // A typed location always wins over hidden/stale device coordinates.
        if ($location !== '') {
            $townMatches = Town::searchActive($location);
            $resolvedTown = $townMatches[0] ?? null;
            $townId = isset($townMatches[0]['id']) ? (int) $townMatches[0]['id'] : null;
            $hasCoords = false;
            $lat = null;
            $lng = null;
        } elseif ($hasCoords) {
            $gpsTown = Town::nearestActive((float) $lat, (float) $lng);
            $resolvedTown = $gpsTown;
            $townId = isset($gpsTown['id']) ? (int) $gpsTown['id'] : null;
            if ($gpsTown !== null) {
                $location = (string) $gpsTown['name'];
                if (!empty($gpsTown['state_abbr'])) {
                    $location .= ', ' . $gpsTown['state_abbr'];
                }
            }
        }
        $locationFound = ($location === '' && !$hasCoords) || $townId !== null;
        $brand = current_brand();
        $brandScoped = $brand->id() !== 'vanassist';
        $categoryInput = trim((string) $request->input('category', ''));
        $categoryId = ctype_digit($categoryInput) && (int) $categoryInput > 0
            ? (int) $categoryInput
            : null;
        if ($brandScoped && $categoryId === null && $categoryInput !== '') {
            $categoryId = $this->brandCategoryId($brand->databaseId(), $categoryInput);
        }
        $serviceModel = trim((string) $request->input('service_model', ''));
        if (!in_array($serviceModel, ['mobile', 'workshop'], true)) {
            $serviceModel = '';
        }

        $result = !$locationFound
            ? ['rows' => [], 'total' => 0]
            : ($brandScoped
                ? Provider::brandDirectory($brand->databaseId(), $townId, $categoryId, $search, $perPage, ($page - 1) * $perPage, $serviceModel)
                : Provider::publicDirectory($townId, $categoryId, $search, $perPage, ($page - 1) * $perPage, $serviceModel));

        $originLat = $hasCoords ? $lat : (is_numeric($resolvedTown['latitude'] ?? null) ? (float) $resolvedTown['latitude'] : null);
        $originLng = $hasCoords ? $lng : (is_numeric($resolvedTown['longitude'] ?? null) ? (float) $resolvedTown['longitude'] : null);
        $distanceRanked = false;
        if ($locationFound && $originLat !== null && $originLng !== null && (int) $result['total'] > 0) {
            $total = (int) $result['total'];
            if ($total <= self::DISTANCE_RANK_CANDIDATE_LIMIT) {
                $candidateResult = $brandScoped
                    ? Provider::brandDirectory($brand->databaseId(), $townId, $categoryId, $search, max(1, $total), 0, $serviceModel)
                    : Provider::publicDirectory($townId, $categoryId, $search, max(1, $total), 0, $serviceModel);
                $candidateRows = $this->hydrateDirectoryDistances($candidateResult['rows'], $originLat, $originLng);
                usort($candidateRows, [$this, 'compareDirectoryDistance']);
                $result['rows'] = array_slice($candidateRows, ($page - 1) * $perPage, $perPage);
                $distanceRanked = true;
            } else {
                // Keep the existing server ordering for unusually broad pools rather
                // than claiming a complete nearest-first ordering from a truncated set.
                $result['rows'] = $this->hydrateDirectoryDistances($result['rows'], $originLat, $originLng);
            }

            if ($result['rows'] !== []) {
                $routed = (new RoadDistanceService())->enrichGroups(
                    ['providers' => $result['rows']],
                    $originLat,
                    $originLng,
                );
                $result['rows'] = $routed['providers'];
                if ($distanceRanked) {
                    usort($result['rows'], [$this, 'compareDirectoryDistance']);
                }
            }
        }

        $categories = $brandScoped
            ? Database::select(
                'SELECT id, name FROM brand_provider_categories WHERE '
                . BrandProviderCategory::publicDirectorySql($brand->databaseId())
                . ' ORDER BY sort_order, name',
                BrandProviderCategory::publicDirectoryParams($brand->databaseId())
            )
            : Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name');

        // Treat a filtered directory browse as a search on every brand. This is
        // best-effort and remains a no-op while demand analytics is disabled.
        if ($search !== '' || $location !== '' || $hasCoords || $categoryId !== null) {
            $searchId = DemandRecorder::recordSearch([
                'town_id' => $townId,
                'region_id' => $resolvedTown['region_id'] ?? null,
                'state_id' => $resolvedTown['state_id'] ?? null,
                'category_id' => $categoryId,
                'service_type' => $serviceModel !== '' ? $serviceModel : 'either',
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
            'categoryKey' => $categoryInput,
            'serviceModel' => $serviceModel,
            'categories' => $categories,
            'brand' => $brand,
            'directoryCopy' => DirectoryPresentation::copyFor($brand->id()),
            'distanceRanked' => $distanceRanked,
            'usesRoadDistance' => RoadDistanceService::groupsUseRoadDistance(['providers' => $result['rows']]),
        ]);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function hydrateDirectoryDistances(array $rows, float $originLat, float $originLng): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        ))));
        if ($ids === []) {
            return $rows;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $coordinates = Database::select(
            "SELECT p.id, "
            . "CASE WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL THEN p.latitude WHEN t.coordinate_confidence IN ('authoritative','statistical') THEN t.latitude END AS latitude, "
            . "CASE WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL THEN p.longitude WHEN t.coordinate_confidence IN ('authoritative','statistical') THEN t.longitude END AS longitude, "
            . "CASE WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL THEN 'provider_point' WHEN t.coordinate_confidence IN ('authoritative','statistical') AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL THEN 'town_centre' ELSE 'unknown' END AS distance_basis "
            . "FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id WHERE p.id IN ({$placeholders})",
            $ids
        );
        $byId = [];
        foreach ($coordinates as $coordinate) {
            $byId[(int) $coordinate['id']] = $coordinate;
        }

        foreach ($rows as &$row) {
            $coordinate = $byId[(int) ($row['id'] ?? 0)] ?? null;
            if ($coordinate === null) {
                continue;
            }
            $row['latitude'] = $coordinate['latitude'];
            $row['longitude'] = $coordinate['longitude'];
            $row['distance_basis'] = $coordinate['distance_basis'];
            if (($coordinate['distance_basis'] ?? '') === 'provider_point'
                && is_numeric($coordinate['latitude'] ?? null)
                && is_numeric($coordinate['longitude'] ?? null)) {
                $row['distance_km'] = round(Geo::haversineExactKm(
                    $originLat,
                    $originLng,
                    (float) $coordinate['latitude'],
                    (float) $coordinate['longitude']
                ), 1);
                $row['distance_metric'] = 'straight_line';
            }
        }
        unset($row);

        return $rows;
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function compareDirectoryDistance(array $a, array $b): int
    {
        $aDistance = is_numeric($a['distance_km'] ?? null) ? (float) $a['distance_km'] : INF;
        $bDistance = is_numeric($b['distance_km'] ?? null) ? (float) $b['distance_km'] : INF;
        $distance = $aDistance <=> $bDistance;
        if ($distance !== 0) {
            return $distance;
        }

        $aCategory = isset($a['category_match_verified'])
            ? (empty($a['category_match_verified']) ? 1 : 0)
            : (isset($a['category_match_inferred']) && !empty($a['category_match_inferred']) ? 1 : 0);
        $bCategory = isset($b['category_match_verified'])
            ? (empty($b['category_match_verified']) ? 1 : 0)
            : (isset($b['category_match_inferred']) && !empty($b['category_match_inferred']) ? 1 : 0);
        if ($aCategory !== $bCategory) {
            return $aCategory <=> $bCategory;
        }

        $verified = ((int) ($b['is_verified'] ?? 0)) <=> ((int) ($a['is_verified'] ?? 0));
        if ($verified !== 0) {
            return $verified;
        }
        $featured = ((int) ($b['is_featured'] ?? 0)) <=> ((int) ($a['is_featured'] ?? 0));
        if ($featured !== 0) {
            return $featured;
        }
        return strcmp((string) ($a['business_name'] ?? ''), (string) ($b['business_name'] ?? ''));
    }

    private function brandCategoryId(int $brandId, string $categoryKey): ?int
    {
        $category = Database::selectOne(
            'SELECT id FROM brand_provider_categories WHERE '
            . BrandProviderCategory::publicDirectorySql($brandId)
            . ' AND category_key = ?',
            [...BrandProviderCategory::publicDirectoryParams($brandId), $categoryKey]
        );

        return $category !== null ? (int) $category['id'] : null;
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
        $gapId = (int) $request->input('g');
        if ($gapId > 0) {
            (new KnowledgeGapService())->recordClickThrough($gapId);
        }
        $runs = [];
        if ($brand->id() === 'vanassist' && Database::tableExists('service_runs')) {
            $runs = Database::select(
                "SELECT id, title, slug, status, start_date FROM service_runs WHERE provider_id = ? AND status IN ('forming','confirmed','limited') AND is_public = 1 AND deleted_at IS NULL ORDER BY start_date LIMIT 10",
                [$id]
            );
        }
        $publicSlug = (string) ($provider['brand_slug'] ?? $provider['slug']);
        $profilePath = 'providers/' . $publicSlug;

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
            'url' => url('providers/' . $publicSlug),
        ];
        if (!empty($provider['description'])) { $data['description'] = mb_substr(strip_tags((string) $provider['description']), 0, 300); }
        if (!empty($provider['show_public_phone']) && !empty($provider['public_phone'])) { $data['telephone'] = (string) $provider['public_phone']; }
        if (!empty($provider['town_name'])) {
            $data['address'] = ['@type' => 'PostalAddress', 'addressLocality' => (string) $provider['town_name'], 'addressRegion' => (string) ($provider['state_abbr'] ?? ''), 'addressCountry' => 'AU'];
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
    }
}
