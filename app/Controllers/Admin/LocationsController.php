<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Region;
use App\Models\State;
use App\Models\Town;
use App\Services\AuditLog;
use App\Services\Seeder;
use Throwable;

/**
 * Admin management of the location hierarchy: states, regions and towns.
 * The hierarchy is fully data-driven so new states/regions/towns can be added
 * for national expansion without code changes.
 */
final class LocationsController extends Controller
{
    // ---- Overview ----------------------------------------------------------

    public function index(Request $request): Response
    {
        $this->requirePermission('locations.manage');

        return $this->view('admin.locations.index', [
            'title'  => 'Locations',
            'states' => State::allWithCountry(),
        ]);
    }

    /**
     * Re-run the idempotent location seed (database/seeds/data.php) against the
     * live database. Adds any new regions/towns shipped in a release without a
     * reinstall; existing rows are left untouched (INSERT IGNORE). Safe to run
     * repeatedly. This is how all-Queensland coverage lands on an already
     * installed site that has no shell access (e.g. shared cPanel hosting).
     */
    public function syncFromSeed(Request $request): Response
    {
        $this->requirePermission('locations.manage');

        $before = (int) Database::scalar('SELECT COUNT(*) FROM towns');
        $regionsBefore = (int) Database::scalar('SELECT COUNT(*) FROM regions');

        try {
            (new Seeder())->seedLocations();
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/locations', 'error', 'Location sync failed: ' . $e->getMessage());
        }

        $townsAdded = (int) Database::scalar('SELECT COUNT(*) FROM towns') - $before;
        $regionsAdded = (int) Database::scalar('SELECT COUNT(*) FROM regions') - $regionsBefore;

        AuditLog::record('location.sync_from_seed', 'locations', null, null, "regions:+{$regionsAdded} towns:+{$townsAdded}");

        $msg = $townsAdded === 0 && $regionsAdded === 0
            ? 'Locations already up to date - nothing new to add.'
            : "Locations synced: added {$regionsAdded} region(s) and {$townsAdded} town(s).";

        return $this->redirectWith('/admin/locations', 'success', $msg);
    }

    // ---- States ------------------------------------------------------------

    public function stateForm(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $state = $id ? State::find($id) : null;
        if ($id && $state === null) {
            $this->abort(404);
        }

        return $this->view('admin.locations.state-form', [
            'title'     => $state ? 'Edit state' : 'New state',
            'state'     => $state,
            'countries' => Database::select('SELECT id, name FROM countries ORDER BY name'),
        ]);
    }

    public function saveState(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWith('/admin/locations/states/new', 'error', 'State name is required.');
        }

        $countryId = (int) $request->input('country_id') ?: $this->defaultCountryId();
        $slug = $this->uniqueSlug('states', $request->input('slug') ?: $name, $id);

        $data = [
            'country_id'      => $countryId,
            'name'            => $name,
            'slug'            => $slug,
            'abbreviation'    => trim((string) $request->input('abbreviation')) ?: null,
            'is_active'       => $request->input('is_active') ? 1 : 0,
            'seo_title'       => trim((string) $request->input('seo_title')) ?: null,
            'seo_description' => trim((string) $request->input('seo_description')) ?: null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            State::update($id, $data);
            AuditLog::record('location.state_updated', 'state', (string) $id, null, $name);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = State::create($data);
            AuditLog::record('location.state_created', 'state', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/locations', 'success', 'State saved.');
    }

    // ---- Regions -----------------------------------------------------------

    public function regions(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $stateId = (int) $request->input('state') ?: null;

        return $this->view('admin.locations.regions', [
            'title'    => 'Regions',
            'regions'  => Region::listing($stateId),
            'states'   => Database::select('SELECT id, name FROM states ORDER BY name'),
            'stateId'  => $stateId,
        ]);
    }

    public function regionForm(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $region = $id ? Region::find($id) : null;
        if ($id && $region === null) {
            $this->abort(404);
        }

        return $this->view('admin.locations.region-form', [
            'title'  => $region ? 'Edit region' : 'New region',
            'region' => $region,
            'states' => Database::select('SELECT id, name FROM states ORDER BY name'),
        ]);
    }

    public function saveRegion(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        $stateId = (int) $request->input('state_id');
        if ($name === '' || $stateId === 0) {
            return $this->redirectWith('/admin/locations/regions', 'error', 'Region name and state are required.');
        }

        $slug = $this->uniqueSlug('regions', $request->input('slug') ?: $name, $id, 'state_id', $stateId);

        $data = [
            'state_id'        => $stateId,
            'name'            => $name,
            'slug'            => $slug,
            'is_active'       => $request->input('is_active') ? 1 : 0,
            'is_featured'     => $request->input('is_featured') ? 1 : 0,
            'public_content'  => trim((string) $request->input('public_content')) ?: null,
            'seo_title'       => trim((string) $request->input('seo_title')) ?: null,
            'seo_description' => trim((string) $request->input('seo_description')) ?: null,
            'updated_at'      => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            Region::update($id, $data);
            AuditLog::record('location.region_updated', 'region', (string) $id, null, $name);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = Region::create($data);
            AuditLog::record('location.region_created', 'region', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/locations/regions', 'success', 'Region saved.');
    }

    // ---- Towns -------------------------------------------------------------

    public function towns(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $stateId = (int) $request->input('state') ?: null;
        $regionId = (int) $request->input('region') ?: null;
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        $result = Town::listing($stateId, $regionId, $perPage, $offset);

        return $this->view('admin.locations.towns', [
            'title'    => 'Towns',
            'towns'    => $result['rows'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => $perPage,
            'states'   => Database::select('SELECT id, name FROM states ORDER BY name'),
            'regions'  => Database::select('SELECT id, name FROM regions ORDER BY name'),
            'stateId'  => $stateId,
            'regionId' => $regionId,
        ]);
    }

    public function townForm(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $town = $id ? Town::find($id) : null;
        if ($id && $town === null) {
            $this->abort(404);
        }

        return $this->view('admin.locations.town-form', [
            'title'   => $town ? 'Edit town' : 'New town',
            'town'    => $town,
            'states'  => Database::select('SELECT id, name FROM states ORDER BY name'),
            'regions' => Database::select('SELECT id, name, state_id FROM regions ORDER BY name'),
        ]);
    }

    public function saveTown(Request $request): Response
    {
        $this->requirePermission('locations.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('name'));
        $stateId = (int) $request->input('state_id');
        if ($name === '' || $stateId === 0) {
            return $this->redirectWith('/admin/locations/towns', 'error', 'Town name and state are required.');
        }

        $slug = $this->uniqueSlug('towns', $request->input('slug') ?: $name, $id, 'state_id', $stateId);
        $lat = $request->input('latitude');
        $lng = $request->input('longitude');

        $data = [
            'state_id'         => $stateId,
            'region_id'        => (int) $request->input('region_id') ?: null,
            'name'             => $name,
            'slug'             => $slug,
            'primary_postcode' => trim((string) $request->input('primary_postcode')) ?: null,
            'latitude'         => is_numeric($lat) ? (float) $lat : null,
            'longitude'        => is_numeric($lng) ? (float) $lng : null,
            'is_active'        => $request->input('is_active') ? 1 : 0,
            'is_featured'      => $request->input('is_featured') ? 1 : 0,
            'is_launch_town'   => $request->input('is_launch_town') ? 1 : 0,
            'noindex'          => $request->input('noindex') ? 1 : 0,
            'public_content'   => trim((string) $request->input('public_content')) ?: null,
            'seo_title'        => trim((string) $request->input('seo_title')) ?: null,
            'seo_description'  => trim((string) $request->input('seo_description')) ?: null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            Town::update($id, $data);
            AuditLog::record('location.town_updated', 'town', (string) $id, null, $name);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = Town::create($data);
            AuditLog::record('location.town_created', 'town', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/locations/towns', 'success', 'Town saved.');
    }

    // ---- Helpers -----------------------------------------------------------

    private function defaultCountryId(): int
    {
        return (int) Database::scalar('SELECT id FROM countries ORDER BY id LIMIT 1');
    }

    /**
     * Generate a unique slug for a table, optionally scoped to a parent column
     * (e.g. regions/towns are unique per state). Excludes the current row on edit.
     */
    private function uniqueSlug(string $table, string $source, int $excludeId, ?string $scopeColumn = null, ?int $scopeValue = null): string
    {
        $base = str_slug($source);
        if ($base === '') {
            $base = 'item';
        }
        $slug = $base;
        $n = 1;
        while (true) {
            $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = ?";
            $params = [$slug];
            if ($scopeColumn !== null) {
                $sql .= " AND {$scopeColumn} = ?";
                $params[] = $scopeValue;
            }
            if ($excludeId > 0) {
                $sql .= ' AND id <> ?';
                $params[] = $excludeId;
            }
            if ((int) Database::scalar($sql, $params) === 0) {
                return $slug;
            }
            $slug = $base . '-' . (++$n);
        }
    }
}
