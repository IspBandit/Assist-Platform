<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Models\Region;
use App\Models\ServiceCategory;
use App\Models\Town;

/**
 * Public location pages (region index, region detail, town detail) generated
 * from the database. Town pages honour their `noindex` flag for SEO.
 */
final class LocationController extends Controller
{
    /**
     * Town type-ahead used by public forms. Returns active towns matching a
     * name/postcode query, each with its region so the form can auto-fill it.
     */
    public function searchTowns(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return $this->json(['towns' => []]);
        }

        $out = [];
        foreach (Town::searchActive($q, 8) as $t) {
            $out[] = [
                'id'          => (int) $t['id'],
                'name'        => (string) $t['name'],
                'region_id'   => isset($t['region_id']) ? (int) $t['region_id'] : null,
                'region_name' => $t['region_name'] ?? null,
                'state_abbr'  => $t['state_abbr'] ?? null,
                'postcode'    => $t['primary_postcode'] ?? null,
            ];
        }
        return $this->json(['towns' => $out]);
    }

    /**
     * Resolve the closest active town to a GPS fix. Used by the public
     * "Use my location" control so mobile users can search without typing.
     */
    public function nearestTown(Request $request): Response
    {
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        if (!is_numeric($latRaw) || !is_numeric($lngRaw)) {
            return $this->json(['town' => null, 'error' => 'Invalid coordinates.']);
        }

        $lat = (float) $latRaw;
        $lng = (float) $lngRaw;
        $town = Town::nearestActive($lat, $lng);
        if ($town === null) {
            return $this->json(['town' => null, 'error' => 'No town or suburb found near your location. Try typing a name or postcode.']);
        }

        $label = (string) $town['name'];
        if (!empty($town['state_abbr'])) {
            $label .= ' / ' . $town['state_abbr'];
        }

        return $this->json([
            'town' => self::townPayload($town, $label),
        ]);
    }

    /**
     * Providers near a town for the homepage spotlight (JSON).
     * Accepts town_id and/or lat/lng (GPS). Discovered listings are labelled.
     */
    public function nearbyProviders(Request $request): Response
    {
        $townId = (int) $request->input('town_id', 0);
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        $hasCoords = is_numeric($latRaw) && is_numeric($lngRaw);

        $town = null;
        if ($townId > 0) {
            $town = Town::findActiveById($townId);
        } elseif ($hasCoords) {
            $lat = (float) $latRaw;
            $lng = (float) $lngRaw;
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                $town = Town::nearestActive($lat, $lng);
            }
        }

        if ($town === null) {
            return $this->json([
                'town'      => null,
                'providers' => [],
                'find_url'  => url('find'),
                'error'     => 'Could not resolve a town. Try location access or search by town.',
            ]);
        }

        $providers = Provider::forHomeNearTown(
            (int) $town['id'],
            isset($town['region_id']) ? (int) $town['region_id'] : null,
        );

        return $this->json([
            'town'      => self::townPayload($town, self::townLabel($town)),
            'providers' => self::providerSpotlightPayload($providers),
            'find_url'  => url('find') . '?' . http_build_query([
                'location' => self::townLabel($town),
            ]),
        ]);
    }

    /** @param array<string,mixed> $town */
    private static function townLabel(array $town): string
    {
        $label = (string) $town['name'];
        if (!empty($town['state_abbr'])) {
            $label .= ' / ' . $town['state_abbr'];
        }

        return $label;
    }

    /**
     * @param array<string,mixed> $town
     * @return array<string,mixed>
     */
    private static function townPayload(array $town, string $label): array
    {
        return [
            'id'          => (int) $town['id'],
            'name'        => (string) $town['name'],
            'slug'        => (string) ($town['slug'] ?? ''),
            'state_abbr'  => $town['state_abbr'] ?? null,
            'region_name' => $town['region_name'] ?? null,
            'postcode'    => $town['primary_postcode'] ?? null,
            'distance_km' => isset($town['distance_km']) ? round((float) $town['distance_km'], 1) : null,
            'label'       => $label,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $providers
     * @return array<int,array<string,mixed>>
     */
    private static function providerSpotlightPayload(array $providers): array
    {
        $out = [];
        foreach ($providers as $p) {
            $out[] = [
                'id'            => (int) $p['id'],
                'business_name' => (string) $p['business_name'],
                'slug'          => (string) $p['slug'],
                'service_model' => (string) ($p['service_model'] ?? ''),
                'is_verified'   => !empty($p['is_verified']),
                'is_featured'   => !empty($p['is_featured']),
                'is_unclaimed'  => !empty($p['is_unclaimed']),
                'slot'          => (string) ($p['slot'] ?? 'local'),
                'town_name'     => $p['town_name'] ?? null,
                'state_abbr'    => $p['state_abbr'] ?? null,
                'profile_url'   => url('providers/' . $p['slug']),
            ];
        }

        return $out;
    }

    public function regionsIndex(Request $request): Response
    {
        return $this->view('public.regions-index', [
            'title'           => 'Regions we cover',
            'metaDescription' => 'Explore the regions where VanAssist helps caravan and RV travellers connect with mobile and workshop service providers.',
            'canonical'       => url('regions'),
            'regions'         => Region::publicListing(),
        ]);
    }

    public function regionShow(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $region = Region::findActiveBySlug($slug);
        if ($region === null) {
            $this->abort(404, 'Region not found.');
        }

        return $this->view('public.region', [
            'title'           => $region['seo_title'] ?: ($region['name'] . ' caravan services — VanAssist'),
            'metaDescription' => $region['seo_description'] ?: ('Caravan and RV services across ' . $region['name'] . ', ' . $region['state_name'] . '.'),
            'canonical'       => url('regions/' . $region['slug']),
            'region'          => $region,
            'towns'           => Town::activeInRegion((int) $region['id']),
            'townTotal'       => Town::countActiveInRegion((int) $region['id']),
            'categories'      => ServiceCategory::activeTopLevel(),
            'providers'       => Provider::inRegion((int) $region['id']),
        ]);
    }

    public function townShow(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $town = Town::findActiveBySlug($slug);
        if ($town === null) {
            $this->abort(404, 'Town not found.');
        }

        return $this->view('public.town', [
            'title'           => $town['seo_title'] ?: ($town['name'] . ' caravan & RV services — VanAssist'),
            'metaDescription' => $town['seo_description'] ?: ('Find caravan and RV help in ' . $town['name'] . ', ' . $town['state_abbr'] . '. Register a request to bring a provider to town.'),
            'canonical'       => url('towns/' . $town['slug']),
            // Town pages default to noindex until they have enough local content.
            'metaRobots'      => ((int) $town['noindex'] === 1) ? 'noindex,follow' : null,
            'town'            => $town,
            'neighbours'      => Town::neighbours((int) $town['id']),
            'categories'      => ServiceCategory::activeTopLevel(),
            'providers'       => Provider::inTown((int) $town['id'], (int) ($town['region_id'] ?? 0)),
        ]);
    }
}
