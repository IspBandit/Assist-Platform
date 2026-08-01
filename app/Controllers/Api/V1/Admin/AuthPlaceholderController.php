<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * Placeholder protected routes — Increment 2 will implement real auth.
 * Middleware rejects before these actions run; methods exist for route wiring.
 */
final class AuthPlaceholderController extends Controller
{
    public function me(Request $request): Response
    {
        // Unreachable while RequireAdminApiBearerPlaceholder is active.
        return $this->json(['data' => null], 501);
    }
}
