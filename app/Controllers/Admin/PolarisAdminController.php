<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/**
 * Retired Polaris admin tombstone.
 *
 * Historical route declarations fail closed until removed from the shared admin
 * router. No Polaris catalogue, import, claim, merge or view code is loaded.
 */
final class PolarisAdminController extends Controller
{
    public function index(Request $request): Response { return $this->retired(); }
    public function manufacturers(Request $request): Response { return $this->retired(); }
    public function models(Request $request): Response { return $this->retired(); }
    public function setModelLifecycle(Request $request): Response { return $this->retired(); }
    public function recycleBin(Request $request): Response { return $this->retired(); }
    public function reviewQueue(Request $request): Response { return $this->retired(); }
    public function reviewDraft(Request $request): Response { return $this->retired(); }
    public function reviewClaim(Request $request): Response { return $this->retired(); }
    public function imports(Request $request): Response { return $this->retired(); }
    public function uploadImport(Request $request): Response { return $this->retired(); }
    public function mergeManufacturers(Request $request): Response { return $this->retired(); }
    public function reviewDealerClaim(Request $request): Response { return $this->retired(); }
    public function settings(Request $request): Response { return $this->retired(); }
    public function placeholder(Request $request): Response { return $this->retired(); }

    private function retired(): Response
    {
        throw new HttpException(404, 'Page not found');
    }
}
