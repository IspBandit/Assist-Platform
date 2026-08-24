<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Logging;

use App\Core\Database;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Services\Demand\TrafficQuality;
use Throwable;

/**
 * Persists NL search analytics. Never throws to callers.
 */
final class AssistSearchLogger
{
    /**
     * @param array<string,mixed>|null $town
     */
    public function log(
        SearchRequest $request,
        string $normalisedQuery,
        Intent $intent,
        int $localCount,
        int $externalCount,
        ?string $fallbackReason,
        ?array $town,
        string $locationPrecision,
        ?array $responseSummary = null,
    ): ?int {
        try {
            return Database::insert(
                'INSERT INTO assist_searches (
                    brand_id, session_id, request_id, channel, raw_query, normalised_query,
                    intent_json, intent_source, confidence, adapter_keys,
                    local_result_count, external_result_count, fallback_reason, response_summary,
                    town_id, radius_km, location_precision, is_excluded, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $request->brandDatabaseId,
                    $request->sessionId,
                    $request->requestId,
                    $request->channel,
                    mb_substr($request->rawQuery, 0, 500),
                    mb_substr($normalisedQuery, 0, 500),
                    json_encode($intent->toArray(), JSON_THROW_ON_ERROR),
                    $intent->source,
                    $intent->confidence,
                    json_encode($intent->adapterKeys, JSON_THROW_ON_ERROR),
                    max(0, min(65535, $localCount)),
                    max(0, min(65535, $externalCount)),
                    $fallbackReason !== null && $fallbackReason !== '' ? mb_substr($fallbackReason, 0, 120) : null,
                    $responseSummary !== null ? json_encode($responseSummary, JSON_THROW_ON_ERROR) : null,
                    $town['id'] ?? null,
                    $intent->radiusKm,
                    $locationPrecision,
                    TrafficQuality::excludesCurrentRequest() ? 1 : 0,
                ]
            );
        } catch (Throwable) {
            return null;
        }
    }
}
