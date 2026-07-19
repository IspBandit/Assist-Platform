<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires an authenticated user. Also enforces the admin idle-session timeout.
 */
final class Authenticate implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $auth = Auth::instance();

        if ($auth->guest()) {
            Session::set('_intended_url', $request->path());
            Session::flash('error', 'Please sign in to continue.');
            return (new Response('', 302))->withHeader('Location', url('login'));
        }

        // Idle timeout for privileged users.
        if ($auth->hasAnyRole('moderator', 'administrator', Auth::SUPER_ADMIN)) {
            $timeout = (int) config('security.admin_session_timeout_minutes', 30) * 60;
            $last = (int) Session::get('_auth_last_activity', time());
            if ($timeout > 0 && (time() - $last) > $timeout) {
                $auth->logout();
                Session::flash('error', 'Your session timed out. Please sign in again.');
                return (new Response('', 302))->withHeader('Location', url('login'));
            }
        }

        Session::set('_auth_last_activity', time());

        return $next($request);
    }
}
