<?php

declare(strict_types=1);

namespace App\Platform\AiSearch;

use App\Models\CaravanPark;
use App\Models\Town;
use App\Platform\AiSearch\Adapters\DatasetSearchAdapter;
use App\Platform\AiSearch\Adapters\FacilitySearchPort;
use App\Platform\AiSearch\Adapters\ProviderSearchAdapter;
use App\Platform\AiSearch\Adapters\StaySearchAdapter;
use App\Platform\AiSearch\Adapters\TravellerFacilitySearchAdapter;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use App\Platform\AiSearch\Budget\AIBudgetService;
use App\Platform\AiSearch\Budget\AiCostEstimator;
use App\Platform\AiSearch\Budget\AiSettings;
use App\Platform\AiSearch\Budget\AIUsageService;
use App\Platform\AiSearch\Cache\IntentCache;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\Dto\SearchResponse;
use App\Platform\AiSearch\Intent\IntentInterpreter;
use App\Platform\AiSearch\Intent\IntentNormaliser;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Platform\AiSearch\Logging\AssistSearchLogger;
use App\Platform\AiSearch\Routing\SearchRouter;
use App\Platform\AiSearch\Support\DatasetSearchFeature;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\RoadDistance\RoadDistanceService;
use App\Services\Search\PublicResultWindow;

/**
 * Shared Assist AI Orchestrator — Phase AI-6 (traveller facilities + dataset routing).
 * Rules + cache first; optional OpenAI; gap grouping; facilities; staged dataset results.
 */
final class SearchOrchestrator
{
    private IntentRuleEngine $rules;
    private SearchRouter $router;
    private ProviderSearchAdapter $providers;
    private StaySearchAdapter $stays;
    private FacilitySearchPort $facilities;
    private DatasetSearchAdapter $datasets;
    private ResultAggregator $aggregator;
    private AssistSearchLogger $logger;
    private IntentCache $intentCache;
    private AIBudgetService $budget;
    private AIUsageService $usage;
    private IntentInterpreter $interpreter;
    private KnowledgeGapService $gaps;
    private RoadDistanceService $roadDistances;
    private PublicResultWindow $resultWindow;
    /** @var (\Closure(SearchRequest,Intent):array{0:?array<string,mixed>,1:?float,2:?float,3:string})|null */
    private ?\Closure $locationResolver;

    public function __construct(
        ?IntentRuleEngine $rules = null,
        ?SearchRouter $router = null,
        ?ProviderSearchAdapter $providers = null,
        ?StaySearchAdapter $stays = null,
        ?FacilitySearchPort $facilities = null,
        ?DatasetSearchAdapter $datasets = null,
        ?ResultAggregator $aggregator = null,
        ?AssistSearchLogger $logger = null,
        ?IntentCache $intentCache = null,
        ?AIBudgetService $budget = null,
        ?AIUsageService $usage = null,
        ?IntentInterpreter $interpreter = null,
        ?KnowledgeGapService $gaps = null,
        ?RoadDistanceService $roadDistances = null,
        ?PublicResultWindow $resultWindow = null,
        ?\Closure $locationResolver = null,
    ) {
        $this->rules = $rules ?? new IntentRuleEngine();
        $this->router = $router ?? new SearchRouter();
        $this->providers = $providers ?? new ProviderSearchAdapter();
        $this->stays = $stays ?? new StaySearchAdapter();
        $this->facilities = $facilities ?? new TravellerFacilitySearchAdapter();
        $this->datasets = $datasets ?? new DatasetSearchAdapter();
        $this->aggregator = $aggregator ?? new ResultAggregator();
        $this->logger = $logger ?? new AssistSearchLogger();
        $this->intentCache = $intentCache ?? new IntentCache();
        $this->budget = $budget ?? new AIBudgetService();
        $this->usage = $usage ?? new AIUsageService();
        $this->interpreter = $interpreter ?? new IntentInterpreter();
        $this->gaps = $gaps ?? new KnowledgeGapService();
        $this->roadDistances = $roadDistances ?? new RoadDistanceService();
        $this->resultWindow = $resultWindow ?? new PublicResultWindow();
        $this->locationResolver = $locationResolver;
    }

    public function handle(SearchRequest $request): SearchResponse
    {
        $started = hrtime(true);
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
            $this->usage->record([
                'request_id' => $request->requestId,
                'brand_key' => $request->brandKey,
                'operation_type' => 'intent_resolve',
                'provider' => 'none',
                'success' => false,
                'fallback_reason' => 'validation',
                'assist_search_id' => $id,
                'duration_ms' => $this->elapsedMs($started),
                'budget_state' => AIBudgetService::STATE_AI_DISABLED,
            ]);

            return new SearchResponse($intent, [], [], null, null, null, 'validation', [
                (string) $intent->clarificationReason,
            ], $id, false);
        }

        $meta = IntentNormaliser::analyse($raw);
        $cacheKey = $this->intentCache->buildKey($request->brandKey, $meta['normalised']);
        $cached = $this->intentCache->get($cacheKey);
        $fromCache = $cached !== null;

        if ($fromCache) {
            $intent = $cached;
        } else {
            $intent = $this->rules->interpret($raw);
            $validated = IntentSchemaValidator::validate($intent);
            $intent = $validated['intent'];
            if ($intent->intentType !== Intent::TYPE_UNKNOWN) {
                $this->intentCache->put($cacheKey, $request->brandKey, $meta['normalised'], $intent);
            }
        }

        $minConfidence = (float) config('ai_search.min_confidence', 0.55);
        $needsAi = !$fromCache
            && ($intent->intentType === Intent::TYPE_UNKNOWN
                || $intent->confidence < $minConfidence
                || $intent->adapterKeys === []);

        $settings = AiSettings::get();
        $estimate = 0.0;
        $allowlisted = (string) ($settings['model_allowlist'][0] ?? '');
        if ($allowlisted !== '') {
            $estimate = AiCostEstimator::estimateAud(
                $allowlisted,
                min($settings['max_prompt_chars'], mb_strlen($raw) + 1200),
                $settings['max_output_tokens']
            );
        }

        $budgetEval = $this->budget->evaluatePaidAiAttempt($estimate);
        $aiUsed = false;
        if ($needsAi) {
            if ($budgetEval['allowed']) {
                $ai = $this->interpreter->interpret($raw, $request->brandKey, $request->requestId);
                $aiUsed = true;
                $aiResult = $ai['result'];
                $this->usage->record([
                    'request_id' => $request->requestId,
                    'brand_key' => $request->brandKey,
                    'operation_type' => 'intent_interpret',
                    'provider' => 'openai',
                    'model' => $ai['model'],
                    'input_tokens' => $aiResult !== null ? $aiResult->inputTokens : 0,
                    'output_tokens' => $aiResult !== null ? $aiResult->outputTokens : 0,
                    'cached' => false,
                    'estimated_cost_aud' => $ai['estimated_cost_aud'],
                    'duration_ms' => $aiResult !== null ? $aiResult->durationMs : null,
                    'success' => $ai['ok'],
                    'fallback_reason' => $ai['ok'] ? null : ($ai['failure'] ?? 'ai_failed'),
                    'intent_confidence' => $ai['intent'] instanceof Intent ? $ai['intent']->confidence : null,
                    'budget_state' => $budgetEval['state'],
                ]);

                if ($ai['ok'] && $ai['intent'] instanceof Intent) {
                    $intent = $ai['intent'];
                    $fromCache = false;
                    $modelVersion = is_string($ai['model']) ? $ai['model'] : null;
                    $aiCacheKey = $this->intentCache->buildKey(
                        $request->brandKey,
                        $meta['normalised'],
                        'en-AU',
                        $modelVersion
                    );
                    $this->intentCache->put(
                        $aiCacheKey,
                        $request->brandKey,
                        $meta['normalised'],
                        $intent,
                        'en-AU',
                        $modelVersion
                    );
                    $this->intentCache->put($cacheKey, $request->brandKey, $meta['normalised'], $intent, 'en-AU', $modelVersion);
                } else {
                    $fallback = 'ai_failed';
                    if ($intent->clarificationReason === null) {
                        $messages[] = 'Try the category search, or rephrase (for example: dump point near Batemans Bay).';
                    }
                }
            } else {
                $fallback = $budgetEval['state'] === AIBudgetService::STATE_HARD_STOP
                    ? 'budget_blocked'
                    : 'ai_disabled';
                if ($intent->clarificationReason === null) {
                    $messages[] = 'Try the category search, or rephrase (for example: dump point near Batemans Bay).';
                }
            }
        }

        if ($intent->clarificationRequired && $intent->clarificationReason !== null) {
            $messages[] = $intent->clarificationReason;
        }

        if ($intent->intentType === Intent::TYPE_UNKNOWN || $intent->adapterKeys === []) {
            if ($fallback === '') {
                $fallback = 'unknown_intent';
            }
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
                'none',
                ['messages' => array_values(array_unique($messages)), 'results' => []]
            );
            $this->recordResolveUsage($request, $intent, $fromCache, $budgetEval['state'], $fallback, $id, $started);
            $gapId = $this->gaps->observe(
                $request,
                $meta['normalised'],
                $intent,
                0,
                0,
                null,
                'none',
                $id,
                $aiUsed
            );

            return new SearchResponse(
                $intent,
                [],
                [],
                null,
                null,
                null,
                $fallback,
                array_values(array_unique($messages)),
                $id,
                false,
                [],
                [],
                $gapId
            );
        }

        [$town, $originLat, $originLng, $precision] = $this->locationResolver !== null
            ? ($this->locationResolver)($request, $intent)
            : $this->resolveLocation($request, $intent);

        if ($originLat === null || $originLng === null) {
            $fallback = $intent->locationText !== null && $intent->locationText !== ''
                ? 'location_unresolved'
                : 'location_required';
            $messages[] = $fallback === 'location_unresolved'
                ? 'We could not identify “' . $intent->locationText . '”. Check the spelling, add a state, or use your current location.'
                : 'Add a town, suburb or postcode, or use your current location.';
            $id = $this->logger->log(
                $request,
                $meta['normalised'],
                $intent,
                0,
                0,
                $fallback,
                null,
                'none',
                ['messages' => array_values(array_unique($messages)), 'results' => []]
            );
            $this->recordResolveUsage($request, $intent, $fromCache, $budgetEval['state'], $fallback, $id, $started);
            $gapId = $this->gaps->observe(
                $request,
                $meta['normalised'],
                $intent,
                0,
                0,
                null,
                'none',
                $id,
                $aiUsed
            );

            return new SearchResponse(
                intent: $intent,
                providers: [],
                stays: [],
                town: null,
                originLat: null,
                originLng: null,
                fallbackReason: $fallback,
                messages: array_values(array_unique($messages)),
                assistSearchId: $id,
                searched: true,
                externals: [],
                facilities: [],
                knowledgeGapId: $gapId,
            );
        }

        $adapters = $this->router->adaptersFor($intent);
        $providerRows = [];
        $stayRows = [];

        if (in_array('providers', $adapters, true)) {
            try {
                $providerRows = $this->providers->search($intent, $town, $originLat, $originLng);
                if ($providerRows === [] && $intent->providerCategoryKeys !== []) {
                    $relatedIntent = $this->relatedProviderFallbackIntent($intent);
                    if ($relatedIntent !== null) {
                        $providerRows = $this->providers->search($relatedIntent, $town, $originLat, $originLng);
                        if ($providerRows !== []) {
                            $messages[] = 'No exact specialist matched nearby. Showing related providers—confirm they handle your issue before travelling.';
                        }
                    }
                }
            } catch (\Throwable) {
                $providerRows = [];
            }
        }
        if (in_array('stays', $adapters, true)) {
            try {
                $stayRows = $this->stays->search($intent, $town, $originLat, $originLng);
            } catch (\Throwable) {
                $stayRows = [];
            }
            if ($stayRows === [] && $town === null && ($originLat === null || $originLng === null)) {
                $messages[] = 'Add a town or use your location to search stays.';
            }
        }

        $facilityRows = [];
        if (in_array('traveller_facilities', $adapters, true)) {
            try {
                $facilityRows = $this->facilities->search($intent, $town, $originLat, $originLng);
            } catch (\Throwable) {
                $facilityRows = [];
            }
            if ($facilityRows === [] && $intent->facilityTypeKeys !== [] && TravellerFacilitiesFeature::enabled()) {
                $messages[] = 'No verified traveller facilities matched yet in this area.';
            }
        }
        $localPreview = count($providerRows) + count($stayRows) + count($facilityRows);
        $adapters = $this->router->withDatasetAugment($adapters, $localPreview, DatasetSearchFeature::enabled());

        $datasetRows = [];
        if (in_array('datasets', $adapters, true)) {
            try {
                $datasetRows = $this->datasets->search(
                    $intent,
                    $town,
                    $originLat,
                    $originLng,
                    $request->brandDatabaseId
                );
            } catch (\Throwable) {
                $datasetRows = [];
            }
        }

        $radiusKm = $this->effectiveRadiusKm($intent, $adapters);
        $aggregated = $this->aggregator->aggregate(
            $providerRows,
            $stayRows,
            $datasetRows,
            $facilityRows,
            $originLat,
            $originLng,
            $radiusKm,
        );
        $window = $this->resultWindow->apply($aggregated, $request->resultLimit);
        $aggregated = $window['groups'];
        $aggregated = $this->roadDistances->enrichGroups(
            $aggregated,
            $originLat,
            $originLng,
            $radiusKm,
        );
        $localCount = count($aggregated['providers']) + count($aggregated['stays']) + count($aggregated['facilities']);
        $externalCount = count($aggregated['externals']);
        if ($localCount === 0 && $externalCount === 0) {
            $fallback = $fallback !== '' ? $fallback : 'no_results';
            $messages[] = 'No matching listings were found in VanAssist yet. Try a wider area or the category search.';
        } elseif ($localCount === 0) {
            $messages[] = 'No verified VanAssist listings matched yet. Showing pending dataset candidates that still need review.';
        } elseif ($externalCount > 0) {
            $messages[] = 'Some results are pending review from imported datasets and are labelled separately.';
        }

        $id = $this->logger->log(
            $request,
            $meta['normalised'],
            $intent,
            $localCount,
            $externalCount,
            $fallback !== '' ? $fallback : null,
            $town,
            $precision,
            $this->responseSummary($aggregated, $messages)
        );
        $this->recordResolveUsage(
            $request,
            $intent,
            $fromCache,
            $budgetEval['state'],
            $fallback !== '' ? $fallback : null,
            $id,
            $started
        );
        $gapId = $this->gaps->observe(
            $request,
            $meta['normalised'],
            $intent,
            $localCount,
            $externalCount,
            $town,
            $precision,
            $id,
            $aiUsed
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
            externals: $aggregated['externals'],
            facilities: $aggregated['facilities'],
            knowledgeGapId: $gapId,
            hasMore: $window['has_more'],
            resultLimit: $request->resultLimit,
            totalCandidates: $window['total'],
        );
    }

    /**
     * Keep only the user-visible learning signal: result identity/type and
     * messages. Contact details and precise coordinates are never retained.
     *
     * @param array<string,list<array<string,mixed>>> $aggregated
     * @param list<string> $messages
     * @return array{messages:list<string>,results:list<array<string,mixed>>}
     */
    private function responseSummary(array $aggregated, array $messages): array
    {
        $results = [];
        foreach (['providers', 'stays', 'facilities', 'externals'] as $type) {
            foreach (array_slice($aggregated[$type] ?? [], 0, 10) as $row) {
                $results[] = array_filter([
                    'type' => rtrim($type, 's'),
                    'id' => isset($row['id']) ? (string) $row['id'] : null,
                    'name' => mb_substr((string) ($row['name'] ?? $row['title'] ?? ''), 0, 160),
                    'source' => isset($row['source']) ? mb_substr((string) $row['source'], 0, 80) : null,
                ], static fn (mixed $value): bool => $value !== null && $value !== '');
            }
        }

        return ['messages' => array_values(array_unique($messages)), 'results' => $results];
    }

    private function relatedProviderFallbackIntent(Intent $intent): ?Intent
    {
        if (array_intersect(
            $intent->providerCategoryKeys,
            ['general-caravan-repairs', 'mobile-mechanics', 'mechanical-repairs']
        ) !== []) {
            return null;
        }

        $electrical = ['12-volt-electrical', '240-volt-electrical', 'solar-and-batteries',
            'dc-dc-charging', 'inverters', 'refrigeration', 'air-conditioning',
            'starlink-and-communications', 'auto-electrical-and-batteries'];
        $vehicle = ['brakes-and-bearings', 'suspension', 'tyres-and-wheels',
            'diesel-mechanics', 'towing-and-vehicle-recovery', '4wd-and-remote-area-recovery'];

        if (array_intersect($intent->providerCategoryKeys, $electrical) !== []) {
            $categories = ['general-caravan-repairs', 'auto-electrical-and-batteries'];
        } elseif (array_intersect($intent->providerCategoryKeys, $vehicle) !== []) {
            $categories = ['mobile-mechanics', 'mechanical-repairs', 'roadside-assistance'];
        } else {
            $categories = ['general-caravan-repairs', 'mobile-mechanics'];
        }

        return new Intent(
            intentType: Intent::TYPE_PROVIDER,
            providerCategoryKeys: $categories,
            stayTypeKeys: [],
            facilityTypeKeys: [],
            locationText: $intent->locationText,
            useCurrentLocation: $intent->useCurrentLocation,
            radiusKm: max(50, (int) ($intent->radiusKm ?? 25)),
            urgency: $intent->urgency,
            adapterKeys: ['providers'],
            confidence: min(0.6, $intent->confidence),
            clarificationRequired: false,
            clarificationReason: null,
            source: 'related_provider_fallback',
        );
    }

    /** @param list<string> $adapters */
    private function effectiveRadiusKm(Intent $intent, array $adapters): int
    {
        if ($intent->radiusKm !== null) {
            return max(1, min(500, $intent->radiusKm));
        }

        return in_array('stays', $adapters, true)
            ? \App\Helpers\Geo::DEFAULT_STAY_DISTANCE_KM
            : max(1, min(500, (int) config('ai_search.default_radius_km', 25)));
    }

    private function recordResolveUsage(
        SearchRequest $request,
        Intent $intent,
        bool $fromCache,
        string $budgetState,
        ?string $fallback,
        ?int $assistSearchId,
        int $started,
    ): void {
        $provider = $fromCache ? 'cache' : ($intent->source === 'rules' ? 'rules' : $intent->source);
        $this->usage->record([
            'request_id' => $request->requestId,
            'brand_key' => $request->brandKey,
            'operation_type' => 'intent_resolve',
            'provider' => $provider,
            'cached' => $fromCache,
            'success' => $intent->intentType !== Intent::TYPE_UNKNOWN,
            'fallback_reason' => $fallback,
            'assist_search_id' => $assistSearchId,
            'intent_confidence' => $intent->confidence,
            'budget_state' => $budgetState,
            'duration_ms' => $this->elapsedMs($started),
            'estimated_cost_aud' => 0.0,
        ]);
    }

    private function elapsedMs(int $started): int
    {
        return (int) max(0, (hrtime(true) - $started) / 1_000_000);
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

        try {
            if ($intent->useCurrentLocation && $hasCoords) {
                $town = Town::nearestActive($lat, $lng);
                return [$town, $lat, $lng, 'gps_short'];
            }

            if ($intent->locationText !== null && $intent->locationText !== '') {
                $matches = Town::searchActive($intent->locationText, 5);
                if ($matches === []) {
                    $matches = Town::searchActiveFuzzy($intent->locationText, 5);
                }
                $town = $matches[0] ?? null;
                if ($town !== null) {
                    $tLat = isset($town['latitude']) ? (float) $town['latitude'] : null;
                    $tLng = isset($town['longitude']) ? (float) $town['longitude'] : null;
                    return [$town, $tLat, $tLng, 'town'];
                }

                $landmark = CaravanPark::resolvePublicLandmark($intent->locationText);
                if ($landmark !== null) {
                    $landmarkLat = is_numeric($landmark['latitude'] ?? null) ? (float) $landmark['latitude'] : null;
                    $landmarkLng = is_numeric($landmark['longitude'] ?? null) ? (float) $landmark['longitude'] : null;
                    if ($landmarkLat !== null && $landmarkLng !== null) {
                        $origin = [
                            'id' => (int) ($landmark['town_id'] ?? 0),
                            'name' => (string) ($landmark['name'] ?? $intent->locationText),
                            'slug' => (string) ($landmark['slug'] ?? ''),
                            'region_id' => $landmark['region_id'] ?? null,
                            'state_id' => $landmark['state_id'] ?? null,
                            'state_name' => $landmark['state_name'] ?? null,
                            'state_abbr' => $landmark['state_abbr'] ?? null,
                            'latitude' => $landmarkLat,
                            'longitude' => $landmarkLng,
                            'location_reference_type' => 'stay',
                        ];
                        return [$origin, $landmarkLat, $landmarkLng, 'stay_landmark'];
                    }
                }

                // Typed location always wins over device coordinates. If it
                // cannot be resolved, fail closed rather than silently search
                // around a stale phone location or across Australia.
                return [null, null, null, 'none'];
            }

            if ($hasCoords) {
                $town = Town::nearestActive($lat, $lng);
                return [$town, $lat, $lng, 'gps_short'];
            }
        } catch (\Throwable) {
            // An explicit typed location must never fall back to unrelated
            // device coordinates when lookup infrastructure fails.
            if ($intent->locationText !== null && $intent->locationText !== '') {
                return [null, null, null, 'none'];
            }
            if ($hasCoords) {
                return [null, $lat, $lng, 'gps_short'];
            }
            return [null, null, null, 'none'];
        }

        return [null, null, null, 'none'];
    }
}
