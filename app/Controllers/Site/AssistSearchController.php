<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\SearchOrchestrator;
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Platform\Brand\BrandContext;
use App\Services\Demand\TrackingSession;
use App\Services\RateLimiter;

/**
 * Ask VanAssist — parallel NL search entry (Phase AI-1).
 * Feature-flagged off by default; does not replace /find.
 */
final class AssistSearchController extends Controller
{
    public function form(Request $request): Response
    {
        $this->guard();

        // Honeypot: bots filling hidden website field get an empty form response.
        if (trim((string) $request->input('website', '')) !== '') {
            return $this->view('public.assist-search', [
                'title' => 'Ask VanAssist',
                'metaDescription' => 'Ask VanAssist what you need help finding — providers, stays and traveller essentials across Australia.',
                'canonical' => url('ask'),
                'query' => '',
                'lat' => null,
                'lng' => null,
                'result' => null,
                'structuredFindUrl' => url('find'),
                'staysUrl' => url('stays'),
            ]);
        }

        $q = trim((string) $request->input('q', ''));
        $latRaw = $request->input('lat');
        $lngRaw = $request->input('lng');
        $lat = is_numeric($latRaw) ? (float) $latRaw : null;
        $lng = is_numeric($lngRaw) ? (float) $lngRaw : null;
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $lat = null;
        }
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $lng = null;
        }

        $result = null;
        if ($q !== '') {
            $brand = current_brand();
            $orchestrator = new SearchOrchestrator();
            $sessionId = null;
            try {
                $sessionId = TrackingSession::id();
            } catch (\Throwable) {
                $sessionId = null;
            }

            $result = $orchestrator->handle(new SearchRequest(
                rawQuery: $q,
                brandKey: $brand->id(),
                brandDatabaseId: $brand->databaseId(),
                latitude: $lat,
                longitude: $lng,
                radiusKm: null,
                requestId: bin2hex(random_bytes(8)),
                channel: 'ask_vanassist',
                sessionId: $sessionId,
            ));
        }

        return $this->view('public.assist-search', [
            'title' => 'Ask VanAssist',
            'metaDescription' => 'Ask VanAssist what you need help finding — providers, stays and traveller essentials across Australia.',
            'canonical' => url('ask'),
            'query' => $q,
            'lat' => $lat,
            'lng' => $lng,
            'result' => $result,
            'structuredFindUrl' => url('find'),
            'staysUrl' => url('stays'),
        ]);
    }

    /** Turnstile unlock after Ask rate-limit block. */
    public function unlock(Request $request): Response
    {
        $this->guard();

        // VerifyTurnstile middleware already validated the token when enabled.
        $brand = BrandContext::hasCurrent() ? BrandContext::current()->id() : 'vanassist';
        RateLimiter::clear('public.ask-vanassist', [$brand . '|ip:' . $request->ip()]);
        Session::flash('success', 'You can continue searching with Ask VanAssist.');
        return $this->redirect('ask');
    }

    private function guard(): void
    {
        if (current_brand()->id() !== 'vanassist') {
            $this->abort(404, 'Page not found.');
        }
        if (!AiSearchFeature::enabled()) {
            $this->abort(404, 'Page not found.');
        }
    }
}
