<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Geo;
use App\Models\Provider;
use App\Models\ServiceCategory;
use App\Models\Town;
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Services\Demand\DemandRecorder;
use App\Services\RoadDistance\RoadDistanceService;
use App\Services\Search\ProviderFallbackCategories;
use App\Services\Search\ProviderSearchRadiusLadder;
use App\Services\Search\PublicResultWindow;
use App\Services\Search\StructuredSearchDestination;
use App\Services\Search\ZeroResultProviderRescueService;

/**
 * Handles the homepage "Find a service" search: a free-text town/postcode plus
 * an optional service category. Resolves the location to a town and lists
 * matching providers (direct matches and trade-based possible matches).
 */
final class SearchController extends Controller
{
    public function find(Request $request): Response
    {
        if (in_array(current_brand()->id(), ['towsmart', 'trailerwise'], true)) {
            return (new ProviderController())->index($request);
        }

        $location = trim((string) $request->input('location', ''));
        if ($location === '') {
            $location = trim((string) $request->input('text', ''));
        }
        $categorySlug = trim((string) $request->input('category', ''));
        $timeframe = trim((string) $request->input('timeframe', ''));
        $resultLimit = PublicResultWindow::requested($request->input('limit'));

        // Optional device GPS coordinates ("Use my location"). Only used when no
        // town/postcode was typed.
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        $lat = is_numeric($latRaw) ? (float) $latRaw : null;
        $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;
        $hasCoords = $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        if (current_brand()->id() === 'vanassist') {
            $destination = StructuredSearchDestination::path(
                $categorySlug,
                $location,
                $hasCoords ? $lat : null,
                $hasCoords ? $lng : null,
                AiSearchFeature::enabled(),
            );
            if ($destination !== null) {
                return $this->redirect($destination);
            }
        }

        $category = $categorySlug !== '' ? ServiceCategory::findActiveBySlug($categorySlug) : null;
        $categoryId = $category !== null ? (int) $category['id'] : null;

        $usedLocation = false;
        $alternatives = [];
        // A location the user typed always wins over hidden/stale device
        // coordinates. GPS is used only when the location field is empty.
        if ($location !== '') {
            $townMatches = Town::searchActive($location);
            if ($townMatches === []) {
                $townMatches = Town::searchActiveFuzzy($location);
            }
            $town = $townMatches[0] ?? null;
            $alternatives = array_slice($townMatches, 1, 5);
            $hasCoords = false;
            $lat = null;
            $lng = null;
        } elseif ($hasCoords) {
            $town = Town::nearestActive($lat, $lng);
            $usedLocation = $town !== null;
        } else {
            $town = null;
        }

        [$originLat, $originLng, $originLabel] = $this->resolveOrigin($town, $hasCoords ? $lat : null, $hasCoords ? $lng : null, $usedLocation);
        $hasOrigin = $originLat !== null && $originLng !== null;

        $distanceRaw = $request->input('max_distance');
        $userSetDistance = $distanceRaw !== null && trim((string) $distanceRaw) !== '';
        $useRadiusLadder = $categoryId !== null && $hasOrigin && !$userSetDistance;
        $distanceFilter = $useRadiusLadder
            ? ['scope' => 'km', 'km' => ProviderSearchRadiusLadder::stepsKm()[0], 'town_radius_km' => (int) config('geo.default_town_radius_km', 20)]
            : Geo::resolveDistanceFilter($distanceRaw, $town !== null);
        $distanceSelection = $distanceFilter['scope'] === 'km' ? $distanceFilter['km'] : $distanceFilter['scope'];
        $maxDistance = $distanceFilter['scope'] === 'km' ? $distanceFilter['km'] : null;

        // What to show in the search box / heading after a GPS lookup.
        $locationDisplay = $location;
        if ($usedLocation && $town !== null) {
            $locationDisplay = (string) $town['name'];
            if (!empty($town['state_abbr'])) {
                $locationDisplay .= ', ' . $town['state_abbr'];
            }
        }

        $matches = [];
        $possible = [];
        $usedRegionalPool = false;
        $radiusExpanded = false;
        $rescueExternals = [];
        $rescueAttribution = null;
        $rescueMessage = null;

        if ($town !== null || ($categoryId !== null && $hasOrigin)) {
            if ($categoryId !== null && $hasOrigin) {
                $explicitKm = ($distanceFilter['scope'] === 'km' && $userSetDistance) ? (int) $distanceFilter['km'] : null;
                $ladder = ProviderSearchRadiusLadder::expand(
                    static fn (int $km): array => Provider::forCategoryNear($categoryId, (float) $originLat, (float) $originLng, $km),
                    $explicitKm
                );
                $distanceFilter = [
                    'scope' => 'km',
                    'km' => $ladder['radius_km'],
                    'town_radius_km' => (int) config('geo.default_town_radius_km', 20),
                ];
                $maxDistance = $ladder['radius_km'];
                $distanceSelection = $ladder['radius_km'];
                $radiusExpanded = $ladder['expanded'];
                $usedRegionalPool = $ladder['expanded'];
                foreach ($ladder['rows'] as $row) {
                    if ((int) ($row['is_inferred'] ?? 0) === 1) {
                        $possible[] = $row;
                    } else {
                        $matches[] = $row;
                    }
                }
            } elseif ($town !== null && $categoryId !== null) {
                $townId = (int) $town['id'];
                foreach (Provider::forCategory($categoryId, $townId) as $row) {
                    if ((int) $row['is_inferred'] === 1) {
                        $possible[] = $row;
                    } else {
                        $matches[] = $row;
                    }
                }
            } elseif ($town !== null) {
                $matches = Provider::inTown((int) $town['id'], (int) ($town['region_id'] ?? 0));
            }
        } elseif ($categoryId !== null && $location === '') {
            // Category only, no location → national results for that service.
            foreach (Provider::forCategory($categoryId) as $row) {
                if ((int) $row['is_inferred'] === 1) {
                    $possible[] = $row;
                } else {
                    $matches[] = $row;
                }
            }
        }

        $exactMatchCount = count($matches) + count($possible);
        $relatedCategorySlugs = ProviderFallbackCategories::related([$categorySlug]);
        if ($categoryId !== null && $exactMatchCount === 0 && $relatedCategorySlugs !== []
            && ($town !== null || $location === '' || $hasOrigin)) {
            $fallbackRows = [];
            foreach ($relatedCategorySlugs as $relatedSlug) {
                $relatedCategory = ServiceCategory::findActiveBySlug($relatedSlug);
                if ($relatedCategory === null) {
                    continue;
                }
                $relatedId = (int) $relatedCategory['id'];
                if ($hasOrigin && $maxDistance !== null) {
                    $rows = Provider::forCategoryNear($relatedId, (float) $originLat, (float) $originLng, (int) $maxDistance);
                } elseif ($town !== null) {
                    $rows = Provider::forCategory($relatedId, (int) $town['id']);
                } else {
                    $rows = Provider::forCategory($relatedId);
                }
                foreach ($rows as $row) {
                    $providerId = (int) ($row['id'] ?? 0);
                    if ($providerId <= 0 || isset($fallbackRows[$providerId])) {
                        continue;
                    }
                    $row['is_inferred'] = 1;
                    $row['search_fallback'] = 'related_category';
                    $fallbackRows[$providerId] = $row;
                }
            }
            $possible = array_values($fallbackRows);
        }

        // Demand-driven Places rescue when the directory is still empty/weak.
        $shownBeforeRescue = count($matches) + count($possible);
        $weakAt = max(1, (int) config('places_rescue.weak_result_threshold', 3));
        if ($categorySlug !== '' && $shownBeforeRescue < $weakAt && ($town !== null || $hasOrigin)) {
            try {
                $rescue = (new ZeroResultProviderRescueService())->rescue(
                    [$categorySlug],
                    $town,
                    $originLat,
                    $originLng,
                    current_brand()->databaseId(),
                    $maxDistance
                );
                foreach ($rescue['providers'] as $row) {
                    $id = (int) ($row['id'] ?? 0);
                    if ($id <= 0) {
                        continue;
                    }
                    $already = false;
                    foreach (array_merge($matches, $possible) as $existing) {
                        if ((int) ($existing['id'] ?? 0) === $id) {
                            $already = true;
                            break;
                        }
                    }
                    if ($already) {
                        continue;
                    }
                    $matches[] = $row;
                }
                $rescueExternals = $rescue['externals'];
                $rescueAttribution = $rescue['attribution'];
                $rescueMessage = $rescue['message'];
                if ($rescue['created'] > 0 || $rescue['merged'] > 0 || $rescueExternals !== []) {
                    $usedRegionalPool = true;
                }
            } catch (\Throwable) {
                // Rescue must never break the traveller journey.
            }
        }

        if ($hasOrigin) {
            $townIdForFilter = (!$useRadiusLadder && $distanceFilter['scope'] === Geo::SCOPE_TOWN && $town !== null)
                ? (int) $town['id']
                : null;
            $matches = Geo::applyDistanceFilter($matches, $originLat, $originLng, $distanceFilter, $townIdForFilter);
            $possible = Geo::applyDistanceFilter($possible, $originLat, $originLng, $distanceFilter, $townIdForFilter);
            $resultWindow = (new PublicResultWindow())->apply(['matches' => $matches, 'possible' => $possible], $resultLimit);
            $matches = $resultWindow['groups']['matches'];
            $possible = $resultWindow['groups']['possible'];
            $routed = (new RoadDistanceService())->enrichGroups(
                ['matches' => $matches, 'possible' => $possible],
                $originLat,
                $originLng,
                $maxDistance,
            );
            $matches = $routed['matches'];
            $possible = $routed['possible'];
        } else {
            $resultWindow = (new PublicResultWindow())->apply(['matches' => $matches, 'possible' => $possible], $resultLimit);
            $matches = $resultWindow['groups']['matches'];
            $possible = $resultWindow['groups']['possible'];
        }

        $usedNearbyFallback = $exactMatchCount === 0 && array_filter(
            $possible,
            static fn (array $row): bool => isset($row['search_fallback'])
        ) !== [];

        // Paid visibility is kept in an explicitly labelled block. Organic
        // direct results rank verified listings first, then nearest distance;
        // related/inferred services remain a separate group.
        $matches = $this->rankDirectMatches($matches);
        usort($possible, [$this, 'compareProviderDistance']);

        $locationNotFound = $town === null && ($location !== '' || $hasCoords);

        // Record the search session + provider impressions (no-op unless the
        // demand_analytics flag is on; never blocks the response).
        $searchId = null;
        if ($town !== null || $categoryId !== null) {
            $shown = array_merge($matches, $possible);
            $searchId = DemandRecorder::recordSearch([
                'town_id'      => $town['id'] ?? null,
                'region_id'    => $town['region_id'] ?? null,
                'state_id'     => $town['state_id'] ?? null,
                'postcode'     => preg_match('/^\d{3,4}$/', $location) === 1 ? $location : null,
                'category_id'  => $categoryId,
                'result_count' => count($shown) + count($rescueExternals),
                'exact_match_count' => $exactMatchCount,
                'used_nearby_fallback' => $usedNearbyFallback || $radiusExpanded || $rescueExternals !== [],
                'radius_expanded' => $radiusExpanded ? 1 : 0,
                'radius_km' => $maxDistance,
            ]);
            DemandRecorder::recordImpressions($searchId, $shown, $categoryId);
        }

        // Pre-fill the "request assistance" CTA with what they searched for.
        $requestQuery = [];
        if ($categorySlug !== '') {
            $requestQuery['category'] = $categorySlug;
        }
        if ($locationDisplay !== '') {
            $requestQuery['location'] = $locationDisplay;
        }
        if ($timeframe !== '') {
            $requestQuery['timeframe'] = $timeframe;
        }
        if ($maxDistance !== null) {
            $requestQuery['max_distance'] = (string) $maxDistance;
        } elseif ($distanceFilter['scope'] === Geo::SCOPE_TOWN) {
            $requestQuery['max_distance'] = Geo::SCOPE_TOWN;
        }
        if ($town !== null) {
            $requestQuery['town'] = (string) ($town['slug'] ?? $town['id']);
        }
        $requestUrl = url('request-assistance') . ($requestQuery !== [] ? ('?' . http_build_query($requestQuery)) : '');

        $nearbyRuns = [];
        if ($town !== null) {
            $nearbyRuns = $this->safeRuns((int) $town['region_id']);
        }

        $heading = 'Find a service';
        if ($category !== null) {
            $heading = (string) $category['name'];
        }
        if ($town !== null) {
            $heading .= ' in ' . $town['name'];
        }

        $categories = ServiceCategory::activeAll();

        return $this->view('public.search-results', [
            'title'            => $heading . ' — VanAssist',
            'metaDescription'  => 'Find caravan and RV services, roadside help, fuel, EV charging and traveller essentials through VanAssist.',
            'metaRobots'       => 'noindex,follow',
            'heading'          => $heading,
            'location'         => $locationDisplay,
            'usedLocation'     => $usedLocation,
            'categorySlug'     => $categorySlug,
            'category'         => $category,
            'timeframe'        => $timeframe,
            'maxDistance'      => $maxDistance,
            'distanceScope'    => $distanceFilter['scope'],
            'distanceSelection'=> $distanceSelection,
            'hasOrigin'        => $hasOrigin,
            'originLabel'      => $originLabel,
            'town'             => $town,
            'alternatives'     => $alternatives,
            'locationNotFound' => $locationNotFound,
            'matches'          => $matches,
            'possible'         => $possible,
            'rescueExternals'  => $rescueExternals,
            'rescueAttribution'=> $rescueAttribution,
            'rescueMessage'    => $rescueMessage,
            'usedRegionalPool' => $usedRegionalPool,
            'requestUrl'       => $requestUrl,
            'searchId'         => $searchId,
            'categories'       => $categories,
            'categoryGroups'   => ServiceCategory::groupedForVanAssist($categories),
            'lat'              => $hasCoords ? $lat : null,
            'lng'              => $hasCoords ? $lng : null,
            'nearbyRuns'       => $nearbyRuns,
            'hasMore'          => $resultWindow['has_more'],
            'showMoreUrl'      => $resultWindow['has_more'] ? url('find?' . http_build_query(array_filter([
                'location' => $location,
                'category' => $categorySlug,
                'timeframe' => $timeframe,
                'max_distance' => $request->input('max_distance'),
                'lat' => $hasCoords ? $lat : null,
                'lng' => $hasCoords ? $lng : null,
                'limit' => 40,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''))) : null,
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function safeRuns(int $regionId): array
    {
        if ($regionId <= 0) {
            return [];
        }
        try {
            return Database::select(
                "SELECT r.*, p.business_name FROM service_runs r "
                . "INNER JOIN providers p ON p.id = r.provider_id "
                . "WHERE r.is_public = 1 AND r.deleted_at IS NULL AND r.status IN ('forming','confirmed') "
                . "AND (r.region_id = ? OR EXISTS (SELECT 1 FROM service_run_towns srt JOIN towns t ON t.id = srt.town_id WHERE srt.run_id = r.id AND t.region_id = ?)) "
                . "ORDER BY r.start_date ASC LIMIT 4",
                [$regionId, $regionId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Record structured "couldn't find a suitable provider" feedback. */
    public function feedback(Request $request): Response
    {
        $reason = (string) $request->input('reason');
        DemandRecorder::recordDemandGap($reason, [
            'town_id'     => (int) $request->input('town_id') ?: null,
            'region_id'   => (int) $request->input('region_id') ?: null,
            'category_id' => (int) $request->input('category_id') ?: null,
            'search_id'   => (int) $request->input('search_id') ?: null,
            'comment'     => $request->input('comment'),
        ]);
        return $this->redirectWith('/find?' . http_build_query(array_filter([
            'location' => (string) $request->input('location'),
            'category' => (string) $request->input('category'),
            'max_distance' => (string) $request->input('max_distance'),
        ])), 'success', 'Thanks — your feedback helps us bring more providers to your area.');
    }

    /**
     * @param array<string,mixed>|null $town
     * @return array{0:?float,1:?float,2:?string} lat, lng, label
     */
    private function resolveOrigin(?array $town, ?float $gpsLat, ?float $gpsLng, bool $usedGps): array
    {
        if ($usedGps && $gpsLat !== null && $gpsLng !== null) {
            $label = $town !== null ? (string) $town['name'] : 'your location';
            if ($town !== null && !empty($town['state_abbr'])) {
                $label .= ', ' . $town['state_abbr'];
            }

            return [$gpsLat, $gpsLng, $label];
        }

        if ($town !== null && $town['latitude'] !== null && $town['longitude'] !== null) {
            $label = (string) $town['name'];
            if (!empty($town['state_abbr'])) {
                $label .= ', ' . $town['state_abbr'];
            }

            return [(float) $town['latitude'], (float) $town['longitude'], $label];
        }

        return [null, null, null];
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private function rankDirectMatches(array $rows): array
    {
        $sponsored = array_values(array_filter($rows, static fn (array $row): bool => !empty($row['is_featured'])));
        $organic = array_values(array_filter($rows, static fn (array $row): bool => empty($row['is_featured'])));
        usort($sponsored, [$this, 'compareProviderDistance']);
        usort($organic, function (array $a, array $b): int {
            $verified = ((int) ($b['is_verified'] ?? 0)) <=> ((int) ($a['is_verified'] ?? 0));
            return $verified !== 0 ? $verified : $this->compareProviderDistance($a, $b);
        });
        return array_merge($sponsored, $organic);
    }

    /** @param array<string,mixed> $a @param array<string,mixed> $b */
    private function compareProviderDistance(array $a, array $b): int
    {
        $aDistance = isset($a['distance_km']) && is_numeric($a['distance_km']) ? (float) $a['distance_km'] : INF;
        $bDistance = isset($b['distance_km']) && is_numeric($b['distance_km']) ? (float) $b['distance_km'] : INF;
        $distance = $aDistance <=> $bDistance;
        return $distance !== 0 ? $distance : strcasecmp((string) ($a['business_name'] ?? ''), (string) ($b['business_name'] ?? ''));
    }
}
