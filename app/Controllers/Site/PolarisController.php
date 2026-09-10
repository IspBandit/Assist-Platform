<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Retired-product tombstone.
 *
 * Polaris is outside the active Assist Platform and acquisition boundary. The
 * old route names are temporarily retained only to fail closed while historical
 * route references are removed from the shared router. No Polaris data,
 * services or views are loaded from this controller.
 */
final class PolarisController extends Controller
{
    public function home(Request $request): Response { return $this->retired(); }
    public function find(Request $request): Response { return $this->retired(); }
    public function browse(Request $request): Response { return $this->retired(); }
    public function showModel(Request $request): Response { return $this->retired(); }
    public function dealerEnquire(Request $request): Response { return $this->retired(); }
    public function compare(Request $request): Response { return $this->retired(); }
    public function shareCompare(Request $request): Response { return $this->retired(); }
    public function manufacturers(Request $request): Response { return $this->retired(); }
    public function showManufacturer(Request $request): Response { return $this->retired(); }
    public function towMatch(Request $request): Response { return $this->retired(); }
    public function floorplans(Request $request): Response { return $this->retired(); }
    public function buyingGuides(Request $request): Response { return $this->retired(); }
    public function buyingGuide(Request $request): Response { return $this->retired(); }
    public function saved(Request $request): Response { return $this->retired(); }
    public function saveModel(Request $request): Response { return $this->retired(); }
    public function unsaveModel(Request $request): Response { return $this->retired(); }
    public function saveSearch(Request $request): Response { return $this->retired(); }
    public function unsaveSearch(Request $request): Response { return $this->retired(); }
    public function accountPreferences(Request $request): Response { return $this->retired(); }
    public function saveAccountPreferences(Request $request): Response { return $this->retired(); }
    public function accountComparisons(Request $request): Response { return $this->retired(); }
    public function accountAlerts(Request $request): Response { return $this->retired(); }
    public function accountTowVehicles(Request $request): Response { return $this->retired(); }

    private function retired(): Response
    {
        throw new HttpException(404, 'Page not found');
    }
}
