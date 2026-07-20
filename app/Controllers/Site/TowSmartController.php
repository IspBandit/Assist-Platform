<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TowSmartCalculator;
use InvalidArgumentException;

final class TowSmartController extends Controller
{
    private const FIELDS = [
        'vehicle_gvm', 'vehicle_gcm', 'vehicle_max_braked_towing', 'vehicle_max_towball',
        'vehicle_mass_before_ball', 'trailer_atm', 'trailer_loaded_mass', 'towball_mass',
    ];

    public function home(Request $request): Response
    {
        $this->requireBrand();
        return $this->view('towsmart.home', [
            'title' => 'Tow smarter. Tow safer.',
            'metaDescription' => 'Check your loaded towing combination against vehicle and trailer mass limits with clear Australian towing guidance.',
            'canonical' => current_brand()->url() . '/',
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

    private function requireBrand(): void
    {
        if (current_brand()->id() !== 'towsmart' || !current_brand()->moduleEnabled('towing_tools')) {
            $this->abort(404);
        }
    }
}
