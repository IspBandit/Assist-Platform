<?php

declare(strict_types=1);

namespace App\Platform\AiSearch;

use App\Models\Town;
use App\Platform\AiSearch\Adapters\DatasetSearchAdapter;
use App\Platform\AiSearch\Adapters\ProviderSearchAdapter;
use App\Platform\AiSearch\Adapters\StaySearchAdapter;
use App\Platform\AiSearch\Adapters\TravellerFacilitySearchAdapter;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\Dto\SearchResponse;
use App\Platform\AiSearch\Intent\IntentNormaliser;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use App\Platform\AiSearch\Logging\AssistSearchLogger;
use App\Platform\AiSearch\Routing\SearchRouter;

/**
 * Shared Assist AI Orchestrator — Phase AI-1 deterministic foundation.
 * No paid AI. No external datasets. No traveller-facility table.
 */
final class SearchOrchestrator
{
    private IntentRuleEngine $rules;
    private SearchRouter $router;
    private ProviderSearchAdapter $providers;
    private StaySearchAdapter $stays;
    private TravellerFacilitySearchAdapter $facilities;
    private DatasetSearchAdapter $datasets;
    private ResultAggregator $aggregator;
    private AssistSearchLogger $logger;

    public function __construct(
        ?IntentRuleEngine $rules = null,
        ?SearchRouter $router = null,
        ?ProviderSearchAdapter $providers = null,
        ?StaySearchAdapter $stays = null,
        ?TravellerFacilitySearchAdapter $facilities = null,
        ?DatasetSearchAdapter $datasets = null,
        ?ResultAggregator $aggregator = null,
        ?AssistSearchLogger $logger = null,
    ) {
        $this->rules = $rules ?? new IntentRuleEngine();
        $this->router = $router ?? new SearchRouter();
        $this->providers = $providers ?? new ProviderSearchAdapter();
        $this->stays = $stays ?? new StaySearchAdapter();
        $this->facilities = $facilities ?? new TravellerFacilitySearchAdapter();
        $this->datasets = $datasets ?? new DatasetSearchAdapter();
        $this->aggregator = $aggregator ?? new ResultAggregator();
        $this->logger = $logger ?? new AssistSearchLogger();
    }

    public function handle(SearchRequest $request): SearchResponse
    {
        $maxLen = (int) config('ai_search.max_query_length', 240);
        $raw = trim($request->rawQuery);
        $messages = [];
        $fallback = '';

        if ($raw === '' || mb_strlen($raw) > $maxLen) {
            $intent = new Intent(
                intentType: Intent::TYPE_UNKNOWN,
                providerCategoryKeys: [],
                stayTypeKeys: [],
                facilityTypeKeys: [],
                locationText: null,
                useCurrentLocation: false,
                radiusKm: null,
                urgency: 'normal',
                adapterKeys: [],
                confidence: 0.0,
                clarificationRequired: true,
                clarificationReason: $raw === ''
                    ? 'Enter what you need help finding.'
                    : 'That search is too long. Please shorten it.',
                source: 'none',
            );
            $meta = IntentNormaliser::analyse($raw);
            $id = $this->logger->log($request, $meta['normalised'], $intent, 0, 0, 'validation', null, 'none');

            return new SearchResponse($intent, [], [], null, null, null, 'validation', [
                (string) $intent->clarificationReason,
            ], $id, false);
        }

        $meta = IntentNormaliser::analyse($raw);
        $intent = $this->rules->interpret($raw);
        $validated = IntentSchemaValidator::validate($intent);
        $intent = $validated['intent'];

        if ($intent->clarificationRequired && $intent->clarificationReason !== null) {
            $messages[] = $intent->clarificationReason;
        }

        if ($intent->intentType === Intent::TYPE_UNKNOWN || $intent->adapterKeys === []) {
            $fallback = 'unknown_intent';
            $messages[] = $intent->clarificationReason
                ?? 'Try the category search above, or rephrase your request.';
            $id = $this->logger->log(
                $request,
                $meta['normalised'],
                $intent,
                0,
                0,
                $fallback,
                null,
                'none'
            );

            return new SearchResponse($intent, [], [], null, null, null, $fallback, array_values(array_unique($messages)), $id, false);
        }

        [$town, $originLat, $originLng, $precision] = $this->resolveLocation($request, $intent);

        if ($intent->useCurrentLocation && ($originLat === null || $originLng === null) && $town === null) {
            $messages[] = 'Location permission is needed for “near me” searches, or add a town name.';
        }

        $adapters = $this->router->adaptersFor($intent);
        $providerRows = [];
        $stayRows = [];

        if (in_array('providers', $adapters, true)) {
            $providerRows = $this->providers->search($intent, $town, $originLat, $originLng);
        }
        if (in_array('stays', $adapters, true)) {
            $stayRows = $this->stays->search($intent, $town, $originLat, $originLng);
            if ($stayRows === [] && $town === null && ($originLat === null || $originLng === null)) {
                $messages[] = 'Add a town or use your location to search stays.';
            }
        }

        // Stubs keep API stable; results intentionally empty in AI-1.
        $this->facilities->search($intent);
        $this->datasets->search($intent);

        $aggregated = $this->aggregator->aggregate($providerRows, $stayRows);
        $localCount = count($aggregated['providers']) + count($aggregated['stays']);
        if ($localCount === 0) {
            $fallback = 'no_results';
            $messages[] = 'No matching listings were found in VanAssist yet. Try a wider area or the category search.';
        }

        $id = $this->logger->log(
            $request,
            $meta['normalised'],
            $intent,
            $localCount,
            0,
            $fallback !== '' ? $fallback : null,
            $town,
            $precision
        );

        return new SearchResponse(
            intent: $intent,
            providers: $aggregated['providers'],
            stays: $aggregated['stays'],
            town: $town,
            originLat: $originLat,
            originLng: $originLng,
            fallbackReason: $fallback,
            messages: array_values(array_unique($messages)),
            assistSearchId: $id,
            searched: true,
        );
    }

    /**
     * @return array{0:?array<string,mixed>,1:?float,2:?float,3:string}
     */
    private function resolveLocation(SearchRequest $request, Intent $intent): array
    {
        $lat = $request->latitude;
        $lng = $request->longitude;
        $hasCoords = $lat !== null && $lng !== null
            && $lat >= -90 && $lat <= 90
            && $lng >= -180 && $lng <= 180;

        if ($intent->useCurrentLocation && $hasCoords) {
            $town = Town::nearestActive($lat, $lng);
            return [$town, $lat, $lng, 'gps_short'];
        }

        if ($intent->locationText !== null && $intent->locationText !== '') {
            $matches = Town::searchActive($intent->locationText, 5);
            $town = $matches[0] ?? null;
            if ($town !== null) {
                $tLat = isset($town['latitude']) ? (float) $town['latitude'] : null;
                $tLng = isset($town['longitude']) ? (float) $town['longitude'] : null;
                return [$town, $tLat, $tLng, 'town'];
            }
            return [null, $hasCoords ? $lat : null, $hasCoords ? $lng : null, 'none'];
        }

        if ($hasCoords) {
            $town = Town::nearestActive($lat, $lng);
            return [$town, $lat, $lng, 'gps_short'];
        }

        return [null, null, null, 'none'];
    }
}
