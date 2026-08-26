<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\Outcome\OutcomeComposer;
use App\Platform\AiSearch\SearchOrchestrator;
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Platform\AiSearch\Support\OutcomeFeature;
use App\Platform\Brand\BrandContext;
use App\Services\Demand\TrackingSession;
use App\Services\Demand\TrafficQuality;
use App\Services\RateLimiter;
use App\Services\Search\PublicResultWindow;
use App\Services\ProductBrandAsk;

/**
 * Ask VanAssist — parallel NL search entry (Phase AI-1).
 * Feature-flagged off by default; does not replace /find.
 */
final class AssistSearchController extends Controller
{
    public function form(Request $request): Response
    {
        $this->guard();

        if (in_array(current_brand()->id(), ['towsmart', 'trailerwise'], true)) {
            return $this->productBrandForm($request);
        }

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
                'outcome' => null,
                'structuredFindUrl' => url('find'),
                'staysUrl' => url('stays'),
                'needsDeviceLocation' => false,
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
        $resultLimit = PublicResultWindow::requested($request->input('limit'));
        if ($q !== '') {
            $brand = current_brand();
            $orchestrator = new SearchOrchestrator();
            $sessionId = null;
            try {
                $sessionId = TrafficQuality::excludesCurrentRequest() ? null : TrackingSession::id();
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
                resultLimit: $resultLimit,
            ));
        }

        $needsDeviceLocation = $q !== ''
            && $result !== null
            && $lat === null
            && $lng === null
            && ($result->intent->locationText === null || $result->intent->locationText === '');
        $outcome = $result !== null && OutcomeFeature::enabled() ? (new OutcomeComposer())->compose($result) : null;

        return $this->view('public.assist-search', [
            'title' => 'Ask VanAssist',
            'metaDescription' => 'Ask VanAssist what you need help finding — providers, stays and traveller essentials across Australia.',
            'canonical' => url('ask'),
            'query' => $q,
            'lat' => $lat,
            'lng' => $lng,
            'result' => $result,
            'outcome' => $outcome,
            'structuredFindUrl' => url('find'),
            'staysUrl' => url('stays'),
            'resultLimit' => $resultLimit,
            'needsDeviceLocation' => $needsDeviceLocation,
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
        if (!in_array(current_brand()->id(), ['vanassist', 'towsmart', 'trailerwise'], true)) {
            $this->abort(404, 'Page not found.');
        }
        if (!AiSearchFeature::enabled()) {
            $this->abort(404, 'Page not found.');
        }
    }

    private function productBrandForm(Request $request): Response
    {
        $brand = current_brand();
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) > (int) config('ai_search.max_query_length', 240)) {
            $query = mb_substr($query, 0, (int) config('ai_search.max_query_length', 240));
        }
        $result = $query !== '' ? (new ProductBrandAsk())->resolve($brand->id(), $query) : null;

        return $this->view('brands.ask', [
            'title' => 'Ask ' . $brand->name(),
            'metaDescription' => 'Describe the towing or trailer help you need and ' . $brand->name() . ' will route you to the appropriate shared service or guidance.',
            'canonical' => url('ask'),
            'query' => $query,
            'result' => $result,
            'brand' => $brand,
        ]);
    }
}
