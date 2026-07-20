<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Advertising\ContextualCampaignService;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Brand\BrandContext;
use App\TowWise\TowingCombinationCalculator;
use App\TowWise\TowingCombinationInput;
use InvalidArgumentException;

final class TowWiseController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render([], null, null);
    }

    public function calculate(Request $request): Response
    {
        $required = ['vehicle_gvm', 'vehicle_gcm', 'maximum_braked_towing', 'maximum_towball', 'loaded_vehicle', 'trailer_atm', 'trailer_gtm', 'actual_towball', 'actual_trailer_mass'];
        $optional = ['towbar_limit', 'coupling_limit', 'front_axle_limit', 'actual_front_axle', 'rear_axle_limit', 'actual_rear_axle', 'trailer_axle_limit', 'actual_trailer_axle'];
        $fields = array_merge($required, $optional);
        $values = [];
        foreach ($required as $field) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '' || !is_numeric($raw)) {
                return $this->render($request->only($fields), null, 'Enter a valid non-negative kilogram value for every field.');
            }
            $values[$field] = (float) $raw;
        }
        foreach ($optional as $field) {
            $raw = trim((string) $request->input($field, ''));
            $values[$field] = $raw === '' ? null : (is_numeric($raw) ? (float) $raw : null);
            if ($raw !== '' && !is_numeric($raw)) {
                return $this->render($request->only($fields), null, 'Optional measurements must be valid non-negative kilogram values.');
            }
        }

        try {
            $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
                $values['vehicle_gvm'], $values['vehicle_gcm'], $values['maximum_braked_towing'],
                $values['maximum_towball'], $values['loaded_vehicle'], $values['trailer_atm'],
                $values['trailer_gtm'], $values['actual_towball'],
                $values['actual_trailer_mass'], $values['towbar_limit'], $values['coupling_limit'],
                $values['front_axle_limit'], $values['actual_front_axle'], $values['rear_axle_limit'],
                $values['actual_rear_axle'], $values['trailer_axle_limit'], $values['actual_trailer_axle'],
            ));
        } catch (InvalidArgumentException $exception) {
            return $this->render($request->only($fields), null, $exception->getMessage());
        }

        return $this->render($request->only($fields), $result, null);
    }

    private function render(array $values, ?object $result, ?string $error): Response
    {
        $brand = BrandContext::current();
        if ($brand->id() !== 'towwise' || !$brand->moduleEnabled('towing_tools')) {
            $this->abort(404, 'Page not found');
        }
        $promotions = [];
        if ($result instanceof \App\TowWise\TowingCombinationResult) {
            $contexts = ['mobile_weighing'];
            foreach (['vehicle_gvm', 'rear_axle'] as $key) {
                if (($result->checks[$key]['status'] ?? 'within') !== 'within') $contexts[] = 'suspension';
            }
            foreach (['towball_limit', 'towbar_rating', 'coupling_rating'] as $key) {
                if (!isset($result->checks[$key]) || $result->checks[$key]['status'] !== 'within') $contexts[] = 'towing_equipment';
            }
            $promotions = (new ContextualCampaignService())->forResult($brand, $contexts);
        }
        return $this->view('brands.towwise-tools', compact('brand', 'values', 'result', 'error', 'promotions'));
    }
}
