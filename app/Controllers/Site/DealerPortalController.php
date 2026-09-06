<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;

/** Retired Polaris dealer-portal tombstone. */
final class DealerPortalController extends Controller
{
    public function claims(Request $request): Response { return $this->retired(); }
    public function submitClaim(Request $request): Response { return $this->retired(); }

    private function retired(): Response
    {
        throw new HttpException(404, 'Page not found');
    }
}
