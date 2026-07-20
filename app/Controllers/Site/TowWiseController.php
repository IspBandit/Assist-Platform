<?php

declare(strict_types=1);

namespace App\Controllers\Site;

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
        $fields = ['vehicle_gvm', 'vehicle_gcm', 'maximum_braked_towing', 'maximum_towball', 'loaded_vehicle', 'trailer_atm', 'trailer_gtm', 'actual_towball'];
        $values = [];
        foreach ($fields as $field) {
            $raw = trim((string) $request->input($field, ''));
            if ($raw === '' || !is_numeric($raw)) {
                return $this->render($request->only($fields), null, 'Enter a valid non-negative kilogram value for every field.');
            }
            $values[$field] = (float) $raw;
        }

        try {
            $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
                $values['vehicle_gvm'], $values['vehicle_gcm'], $values['maximum_braked_towing'],
                $values['maximum_towball'], $values['loaded_vehicle'], $values['trailer_atm'],
                $values['trailer_gtm'], $values['actual_towball'],
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
        return $this->view('brands.towwise-tools', compact('brand', 'values', 'result', 'error'));
    }
}
