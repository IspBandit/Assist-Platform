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
        if (current_brand()->id() === 'localtorque') {
            return $this->localTorqueIndex();
        }
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
        if (current_brand()->id() === 'localtorque') {
            return $this->localTorqueShow($request, $slug);
        }
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

    private function localTorqueIndex(): Response
    {
        $brand = current_brand();
        $categories = Database::select(
            'SELECT id, category_key AS slug, name, description AS short_description '
            . 'FROM brand_provider_categories WHERE brand_id = ? AND is_active = 1 ORDER BY sort_order, name',
            [$brand->databaseId()]
        );

        return $this->view('localtorque.categories', [
            'title' => 'Automotive business categories — LocalTorque',
            'metaDescription' => 'Browse Australian mechanics, workshops, mobile repairers and automotive specialists by category.',
            'canonical' => url('services'),
            'categories' => $categories,
            'category' => null,
            'providers' => [],
        ]);
    }

    private function localTorqueShow(Request $request, string $slug): Response
    {
        $brand = current_brand();
        $category = Database::selectOne(
            'SELECT id, category_key AS slug, name, description AS short_description '
            . 'FROM brand_provider_categories WHERE brand_id = ? AND category_key = ? AND is_active = 1',
            [$brand->databaseId(), $slug]
        );
        if ($category === null) {
            $this->abort(404, 'Automotive category not found.');
        }
        $townId = (int) $request->input('town') ?: null;
        $providers = Provider::brandDirectory($brand->databaseId(), $townId, (int) $category['id'], '', 60, 0)['rows'];

        return $this->view('localtorque.categories', [
            'title' => $category['name'] . ' — LocalTorque',
            'metaDescription' => $category['short_description'],
            'canonical' => url('category/' . $category['slug']),
            'categories' => [],
            'category' => $category,
            'providers' => $providers,
        ]);
    }
}
