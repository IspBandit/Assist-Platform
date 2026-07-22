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

/**
 * Public service-category pages generated from the database.
 */
final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = ServiceCategory::activeTopLevel();

        return $this->view('public.services-index', [
            'title'           => 'Caravan & RV service categories',
            'metaDescription' => 'Browse the caravan and RV services available through VanAssist, from 12-volt electrical and solar to brakes, bearings and gas appliance servicing.',
            'canonical'       => url('services'),
            'categories'      => $categories,
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $category = ServiceCategory::findActiveBySlug($slug);
        if ($category === null) {
            $this->abort(404, 'Service category not found.');
        }

        $children = ServiceCategory::activeChildren((int) $category['id']);
        $parent = $category['parent_id'] ? ServiceCategory::find((int) $category['parent_id']) : null;

        $launchTowns = Database::select(
            'SELECT name, slug FROM towns WHERE is_active = 1 AND is_launch_town = 1 ORDER BY name LIMIT 12'
        );

        // Optional area filter (e.g. "Brakes & bearings in Gympie").
        $townId = (int) $request->input('town') ?: null;
        $selectedTown = null;
        if ($townId !== null) {
            $selectedTown = Database::selectOne(
                'SELECT t.id, t.name, t.latitude, t.longitude, s.abbreviation AS state_abbr FROM towns t '
                . 'LEFT JOIN states s ON s.id = t.state_id WHERE t.id = ? AND t.is_active = 1',
                [$townId]
            );
            if ($selectedTown === null) {
                $townId = null;
            }
        }

        $distanceFilter = Geo::resolveDistanceFilter($request->input('max_distance'), $townId !== null);
        $distanceSelection = $distanceFilter['scope'] === 'km' ? $distanceFilter['km'] : $distanceFilter['scope'];
        $maxDistance = $distanceFilter['scope'] === 'km' ? $distanceFilter['km'] : null;

        // Reference point for approximate distances (the searched town's centre).
        $originLat = $selectedTown !== null && $selectedTown['latitude'] !== null ? (float) $selectedTown['latitude'] : null;
        $originLng = $selectedTown !== null && $selectedTown['longitude'] !== null ? (float) $selectedTown['longitude'] : null;

        $matches = [];
        $possible = [];
        foreach (Provider::forCategory((int) $category['id'], $townId) as $row) {
            if ((int) $row['is_inferred'] === 1) {
                $possible[] = $row;
            } else {
                $matches[] = $row;
            }
        }
        if ($originLat !== null && $originLng !== null) {
            $matches = Geo::applyDistanceFilter($matches, $originLat, $originLng, $distanceFilter, $townId);
            $possible = Geo::applyDistanceFilter($possible, $originLat, $originLng, $distanceFilter, $townId);
        }

        $titleName = (string) $category['name'];
        if ($selectedTown !== null) {
            $titleName .= ' in ' . $selectedTown['name'];
        }

        return $this->view('public.service-category', [
            'title'           => $category['seo_title'] && $selectedTown === null
                ? $category['seo_title']
                : ($titleName . ' — VanAssist'),
            'metaDescription' => $category['seo_description'] ?: $category['short_description'],
            'canonical'       => url('services/' . $category['slug']),
            'category'        => $category,
            'children'        => $children,
            'parent'          => $parent,
            'launchTowns'     => $launchTowns,
            'matches'         => $matches,
            'possible'        => $possible,
            'towns'           => [],
            'townId'          => $townId,
            'selectedTown'    => $selectedTown,
            'selectedTownLabel' => $selectedTown !== null
                ? trim($selectedTown['name'] . (!empty($selectedTown['state_abbr']) ? ' / ' . $selectedTown['state_abbr'] : ''))
                : '',
            'maxDistance'     => $maxDistance,
            'distanceScope'   => $distanceFilter['scope'],
            'distanceSelection' => $distanceSelection,
            'hasOrigin'       => $originLat !== null && $originLng !== null,
        ]);
    }
}
