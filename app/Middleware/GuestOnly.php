<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;

/**
 * Prevents already-authenticated users from viewing login/registration pages.
 */
final class GuestOnly implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $auth = Auth::instance();
        if ($auth->check()) {
            // Staff land on the admin dashboard so an already-signed-in admin who
            // hits /login isn't bounced to the customer account page (and left
            // hunting for the way back in). Everyone else goes to their account.
            $destination = $auth->hasAnyRole('moderator', 'administrator', Auth::SUPER_ADMIN)
                ? 'admin'
                : 'account';
            return (new Response('', 302))->withHeader('Location', url($destination));
        }
        return $next($request);
    }
}
