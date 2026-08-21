<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Turnstile;

final class VerifyTurnstile
{
    public function handle(Request $request, callable $next): Response
    {
        if (Turnstile::verify($request)) {
            return $next($request);
        }

        $input = $request->all();
        unset($input['_csrf'], $input['cf-turnstile-response'], $input['password'], $input['password_confirmation']);
        Session::flashInput($input);
        Session::flash('error', 'The security check could not be confirmed. Please try again.');
        $path = parse_url((string) $request->header('Referer', '/'), PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/')) {
            $path = '/';
        }
        return Response::html('', 303)->withHeader('Location', $path)->withHeader('Cache-Control', 'no-store');
    }
}
