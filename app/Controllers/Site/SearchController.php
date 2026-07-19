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
use App\Services\Demand\DemandRecorder;

/**
 * Handles the homepage "Find a service" search: a free-text town/postcode plus
 * an optional service category. Resolves the location to a town and lists
 * matching providers (direct matches and trade-based possible matches).
 */
final class SearchController extends Controller
{
    public function find(Request $request): Response
    {
        $location = trim((string) $request->input('location', ''));
        $categorySlug = trim((string) $request->input('category', ''));
        $timeframe = trim((string) $request->input('timeframe', ''));

        // Optional device GPS coordinates ("Use my location"). Only used when no
        // town/postcode was typed.
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        $lat = is_numeric($latRaw) ? (float) $latRaw : null;
        $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;
        $hasCoords = $lat !== null && $lng !== null && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180;

        $category = $categorySlug !== '' ? ServiceCategory::findActiveBySlug($categorySlug) : null;
        $categoryId = $category !== null ? (int) $category['id'] : null;

        $usedLocation = false;
        $alternatives = [];
        // When lat/lng are present (device GPS), they take precedence over a typed
        // town string — the label we fill may be "Gladstone, QLD" which does not
        // match the towns.name column verbatim.
        if ($hasCoords) {
            $town = Town::nearestActive($lat, $lng);
            $usedLocation = $town !== null;
        } elseif ($location !== '') {
            $townMatches = Town::searchActive($location);
            $town = $townMatches[0] ?? null;
            $alternatives = array_slice($townMatches, 1, 5);
        } else {
            $town = null;
        }

        $distanceFilter = Geo::resolveDistanceFilter($request->input('max_distance'), $town !== null);
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

        if ($town !== null) {
            $townId = (int) $town['id'];
            if ($categoryId !== null) {
                foreach (Provider::forCategory($categoryId, $townId) as $row) {
                    if ((int) $row['is_inferred'] === 1) {
                        $possible[] = $row;
                    } else {
                        $matches[] = $row;
                    }
                }
            } else {
                $matches = Provider::inTown($townId, (int) ($town['region_id'] ?? 0));
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

        [$originLat, $originLng, $originLabel] = $this->resolveOrigin($town, $hasCoords ? $lat : null, $hasCoords ? $lng : null, $usedLocation);
        $hasOrigin = $originLat !== null && $originLng !== null;
        if ($hasOrigin) {
            $townIdForFilter = $town !== null ? (int) $town['id'] : null;
            $matches = Geo::applyDistanceFilter($matches, $originLat, $originLng, $distanceFilter, $townIdForFilter);
            $possible = Geo::applyDistanceFilter($possible, $originLat, $originLng, $distanceFilter, $townIdForFilter);
        }

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
                'result_count' => count($shown),
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

        return $this->view('public.search-results', [
            'title'            => $heading . ' — VanAssist',
            'metaDescription'  => 'Search caravan and RV service providers across the VanAssist network.',
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
            'requestUrl'       => $requestUrl,
            'searchId'         => $searchId,
            'categories'       => ServiceCategory::activeTopLevel(),
            'lat'              => $hasCoords ? $lat : null,
            'lng'              => $hasCoords ? $lng : null,
            'nearbyRuns'       => $nearbyRuns,
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
}
