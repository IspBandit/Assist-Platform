<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Platform\Brand\BrandContext;
use App\Services\RateLimiter;
use App\Services\Turnstile;

/**
 * Ask VanAssist rate limit with Turnstile unlock escalation when blocked.
 */
final class AskVanAssistRateLimit
{
    private int $maxAttempts;
    private int $windowSeconds;
    private int $blockSeconds;

    public function __construct(
        private readonly string $action = 'public.ask-vanassist',
        string $maxAttempts = '20',
        string $windowSeconds = '3600',
        string $blockSeconds = '3600',
    ) {
        $this->maxAttempts = max(1, (int) $maxAttempts);
        $this->windowSeconds = max(1, (int) $windowSeconds);
        $this->blockSeconds = max(1, (int) $blockSeconds);
    }

    public function handle(Request $request, callable $next): Response
    {
        $brand = BrandContext::hasCurrent() ? BrandContext::current()->id() : 'platform';
        $subjects = [$brand . '|ip:' . $request->ip()];

        if (RateLimiter::blocked($this->action, $subjects)) {
            if (Turnstile::enabled() && $request->method() === 'GET') {
                $html = View::render('public.ask-unlock', [
                    'title' => 'Confirm you are human',
                    'metaDescription' => 'Complete a short security check to continue Ask VanAssist.',
                    'canonical' => url('ask/unlock'),
                    'retryAfter' => $this->blockSeconds,
                ]);
                return Response::html($html, 429)
                    ->withHeader('Retry-After', (string) $this->blockSeconds)
                    ->withHeader('Cache-Control', 'no-store');
            }

            return Response::text('Too many requests. Please try again later.', 429)
                ->withHeader('Retry-After', (string) $this->blockSeconds)
                ->withHeader('Cache-Control', 'no-store');
        }

        $response = $next($request);
        RateLimiter::hit(
            $this->action,
            $subjects,
            $this->maxAttempts,
            $this->windowSeconds,
            $this->blockSeconds
        );

        return $response;
    }
}
