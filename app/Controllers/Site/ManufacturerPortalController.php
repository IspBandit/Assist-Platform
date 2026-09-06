<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Retired Polaris manufacturer-portal tombstone.
 *
 * The historical route declarations fail closed until they are removed from the
 * shared router. No retired product service, catalogue or view is loaded.
 */
final class ManufacturerPortalController extends Controller
{
    public function index(Request $request): Response { return $this->retired(); }
    public function claims(Request $request): Response { return $this->retired(); }
    public function submitClaim(Request $request): Response { return $this->retired(); }
    public function models(Request $request): Response { return $this->retired(); }
    public function editModel(Request $request): Response { return $this->retired(); }
    public function saveModel(Request $request): Response { return $this->retired(); }
    public function profile(Request $request): Response { return $this->retired(); }
    public function saveProfile(Request $request): Response { return $this->retired(); }
    public function media(Request $request): Response { return $this->retired(); }
    public function uploadMedia(Request $request): Response { return $this->retired(); }
    public function dealers(Request $request): Response { return $this->retired(); }
    public function linkDealer(Request $request): Response { return $this->retired(); }
    public function analytics(Request $request): Response { return $this->retired(); }
    public function team(Request $request): Response { return $this->retired(); }
    public function addTeamMember(Request $request): Response { return $this->retired(); }
    public function dataQuality(Request $request): Response { return $this->retired(); }

    private function retired(): Response
    {
        throw new HttpException(404, 'Page not found');
    }
}
