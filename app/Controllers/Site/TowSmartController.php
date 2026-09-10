<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Models\BrandProviderCategory;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TowSmartCalculator;
use App\Services\TowSmartCatalog;
use App\Services\SeoSchema;
use InvalidArgumentException;

final class TowSmartController extends Controller
{
    private const FIELDS = [
        'vehicle_gvm', 'vehicle_gcm', 'vehicle_max_braked_towing', 'vehicle_max_towball',
        'vehicle_mass_before_ball', 'trailer_atm', 'trailer_loaded_mass', 'towball_mass',
        'vehicle_catalogue_id', 'vehicle_name', 'vehicle_kerb_mass', 'vehicle_front_axle_limit',
        'vehicle_rear_axle_limit', 'passengers_mass', 'vehicle_cargo_mass', 'vehicle_accessories_mass',
        'fuel_mass', 'trailer_catalogue_id', 'trailer_name', 'trailer_type', 'trailer_axle_config',
        'trailer_tare_mass', 'trailer_gtm', 'trailer_tare_ball_mass', 'trailer_cargo_mass',
        'trailer_accessories_mass', 'trailer_front_accessories_mass', 'trailer_rear_accessories_mass',
        'tank_1_litres', 'tank_1_position', 'tank_2_litres', 'tank_2_position',
    ];

    public function home(Request $request): Response
    {
        $this->requireBrand();
        return $this->view('towsmart.home', [
            'title' => 'Tow smarter. Tow safer.',
            'metaDescription' => 'Check your loaded towing combination against vehicle and trailer mass limits with clear Australian towing guidance.',
            'canonical' => current_brand()->url() . '/',
            'categories' => $this->brandCategories(),
            'jsonLd' => SeoSchema::brandWebsite(current_brand()),
        ]);
    }

    public function calculator(Request $request): Response
    {
        $this->requireBrand();
        return $this->view('towsmart.calculator', [
            'title' => 'Towing weight calculator',
            'canonical' => current_brand()->url() . '/calculator',
            'values' => [],
            'result' => null,
            'errors' => Session::errors(),
            'catalogueCounts' => TowSmartCatalog::counts(),
        ]);
    }

    public function calculate(Request $request): Response
    {
        $this->requireBrand();
        $values = $request->only(self::FIELDS);
        try {
            $result = TowSmartCalculator::calculate($values);
        } catch (InvalidArgumentException $e) {
            Session::flashErrors(['calculator' => $e->getMessage()]);
            Session::flashInput($values);
            return $this->redirect('/calculator');
        }

        return $this->view('towsmart.calculator', [
            'title' => 'Your towing weight check',
            'canonical' => current_brand()->url() . '/calculator',
            'values' => $values,
            'result' => $result,
            'errors' => Session::errors(),
            'catalogueCounts' => TowSmartCatalog::counts(),
        ]);
    }

    public function catalogue(Request $request): Response
    {
        $this->requireBrand();
        $type = (string) $request->route('type', '');
        $q = trim((string) $request->input('q', ''));
        if (!in_array($type, ['vehicles', 'trailers'], true)) {
            return $this->json(['items' => [], 'error' => 'Unknown catalogue.'], 404);
        }
        return $this->json(['items' => TowSmartCatalog::search($type, $q)]);
    }

    public function catalogueItem(Request $request): Response
    {
        $this->requireBrand();
        $type = (string) $request->route('type', '');
        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT);
        if (!in_array($type, ['vehicles', 'trailers'], true) || $id === false) {
            return $this->json(['item' => null], 404);
        }
        $item = TowSmartCatalog::find($type, (int) $id);
        return $this->json(['item' => $item], $item === null ? 404 : 200);
    }

    public function guide(Request $request): Response
    {
        $this->requireBrand();
        return $this->view('towsmart.guide', [
            'title' => 'Australian towing guide',
            'metaDescription' => 'TowSmart definitions, calculation explanations, pre-trip guidance and links to current Australian towing authorities.',
            'canonical' => current_brand()->url() . '/tow-guide',
        ]);
    }

    public function checklist(Request $request): Response
    {
        $this->requireBrand();
        return $this->view('towsmart.checklist', [
            'title' => 'Pre-tow safety checklist',
            'canonical' => current_brand()->url() . '/checklist',
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requireBrand();
        $userId = (int) (current_user()['id'] ?? 0);
        if ($userId < 1) {
            return $this->redirect('/login');
        }

        $values = $request->only(self::FIELDS);
        try {
            $result = TowSmartCalculator::calculate($values);
        } catch (InvalidArgumentException $e) {
            return $this->redirectWith('/calculator', 'error', $e->getMessage());
        }
        $label = trim((string) $request->input('label', 'My towing combination'));
        if ($label === '') {
            $label = 'My towing combination';
        }
        Database::insert(
            'INSERT INTO towing_combinations (user_id, brand_id, label, input_snapshot, result_snapshot, result_status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$userId, current_brand()->databaseId(), mb_substr($label, 0, 150), json_encode($values, JSON_THROW_ON_ERROR), json_encode($result, JSON_THROW_ON_ERROR), $result['status']]
        );
        return $this->redirectWith('/account/towing-combinations', 'success', 'Your towing combination has been saved.');
    }

    public function combinations(Request $request): Response
    {
        $this->requireBrand();
        $userId = (int) (current_user()['id'] ?? 0);
        $items = Database::select(
            'SELECT id, label, result_status, input_snapshot, result_snapshot, created_at FROM towing_combinations WHERE user_id = ? AND brand_id = ? ORDER BY created_at DESC',
            [$userId, current_brand()->databaseId()]
        );
        return $this->view('towsmart.combinations', ['title' => 'Saved towing combinations', 'items' => $items]);
    }

    public function combination(Request $request): Response
    {
        $this->requireBrand();
        $item = $this->ownedCombination($request);

        return $this->view('towsmart.combination', [
            'title' => (string) $item['label'] . ' — saved towing combination',
            'item' => $item,
            'input' => $this->decodeSnapshot((string) $item['input_snapshot']),
            'result' => $this->decodeSnapshot((string) $item['result_snapshot']),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function editCombination(Request $request): Response
    {
        $this->requireBrand();
        $item = $this->ownedCombination($request);

        return $this->view('towsmart.calculator', [
            'title' => 'Edit ' . (string) $item['label'],
            'canonical' => current_brand()->url() . '/account/towing-combinations/' . (int) $item['id'] . '/edit',
            'values' => $this->decodeSnapshot((string) $item['input_snapshot']),
            'result' => $this->decodeSnapshot((string) $item['result_snapshot']),
            'errors' => Session::errors(),
            'catalogueCounts' => TowSmartCatalog::counts(),
            'editingCombination' => $item,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function updateCombination(Request $request): Response
    {
        $this->requireBrand();
        $item = $this->ownedCombination($request);
        $values = $request->only(self::FIELDS);
        try {
            $result = TowSmartCalculator::calculate($values);
        } catch (InvalidArgumentException $e) {
            Session::flashErrors(['calculator' => $e->getMessage()]);
            Session::flashInput($values);
            return $this->redirect('/account/towing-combinations/' . (int) $item['id'] . '/edit');
        }
        $label = trim((string) $request->input('label', (string) $item['label']));
        if ($label === '') {
            $label = 'My towing combination';
        }
        Database::affecting(
            'UPDATE towing_combinations SET label = ?, input_snapshot = ?, result_snapshot = ?, result_status = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND brand_id = ?',
            [mb_substr($label, 0, 150), json_encode($values, JSON_THROW_ON_ERROR), json_encode($result, JSON_THROW_ON_ERROR), $result['status'], (int) $item['id'], (int) current_user()['id'], current_brand()->databaseId()]
        );

        return $this->redirectWith('/account/towing-combinations/' . (int) $item['id'], 'success', 'Saved combination updated with a new calculation snapshot.');
    }

    public function compareCombinations(Request $request): Response
    {
        $this->requireBrand();
        $rawIds = $request->query('ids', []);
        if (!is_array($rawIds)) {
            $rawIds = explode(',', (string) $rawIds);
        }
        $ids = array_slice(array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => max(0, (int) $id),
            $rawIds
        )))), 0, 3);
        $items = [];
        if ($ids !== []) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $items = Database::select(
                'SELECT id, label, result_status, input_snapshot, result_snapshot, created_at, updated_at FROM towing_combinations WHERE user_id = ? AND brand_id = ? AND id IN (' . $placeholders . ') ORDER BY created_at DESC',
                array_merge([(int) current_user()['id'], current_brand()->databaseId()], $ids)
            );
        }

        return $this->view('towsmart.compare', [
            'title' => 'Compare saved towing combinations',
            'items' => $items,
            'available' => Database::select(
                'SELECT id, label, result_status, created_at FROM towing_combinations WHERE user_id = ? AND brand_id = ? ORDER BY created_at DESC',
                [(int) current_user()['id'], current_brand()->databaseId()]
            ),
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function removeCombination(Request $request): Response
    {
        $this->requireBrand();
        $item = $this->ownedCombination($request);
        Database::affecting(
            'DELETE FROM towing_combinations WHERE id = ? AND user_id = ? AND brand_id = ?',
            [(int) $item['id'], (int) current_user()['id'], current_brand()->databaseId()]
        );

        return $this->redirectWith('/account/towing-combinations', 'success', 'Saved combination removed.');
    }

    /** @return array<string,mixed> */
    private function ownedCombination(Request $request): array
    {
        $id = filter_var($request->route('id'), FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            $this->abort(404, 'Saved combination not found.');
        }
        $item = Database::selectOne(
            'SELECT id, label, result_status, input_snapshot, result_snapshot, created_at, updated_at '
            . 'FROM towing_combinations WHERE id = ? AND user_id = ? AND brand_id = ?',
            [(int) $id, (int) current_user()['id'], current_brand()->databaseId()]
        );
        if ($item === null) {
            $this->abort(404, 'Saved combination not found.');
        }

        return $item;
    }

    /** @return array<string,mixed> */
    private function decodeSnapshot(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<int,array<string,mixed>> */
    private function brandCategories(): array
    {
        $brandId = current_brand()->databaseId();

        return Database::select(
            'SELECT id, category_key AS slug, name, description FROM brand_provider_categories WHERE '
            . BrandProviderCategory::publicDirectorySql($brandId)
            . ' ORDER BY sort_order, name LIMIT 8',
            BrandProviderCategory::publicDirectoryParams($brandId)
        );
    }

    private function requireBrand(): void
    {
        if (current_brand()->id() !== 'towsmart' || !current_brand()->moduleEnabled('towing_tools')) {
            $this->abort(404);
        }
    }
}
