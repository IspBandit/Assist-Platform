<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Knowledge;

use App\Core\Database;
use App\Core\Session;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Services\Demand\TrackingSession;
use Throwable;

/**
 * Grouped knowledge-gap engine (DATA-013 / AI-4).
 * Records weak/zero/unknown NL searches; never invents listings.
 */
final class KnowledgeGapService
{
    public const QUALITY_NONE = 'none';
    public const QUALITY_WEAK = 'weak';
    public const QUALITY_ADEQUATE = 'adequate';

    public const STATUS_OPEN = 'open';
    public const STATUS_RESEARCHING = 'researching';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_WONT_FIX = 'wont_fix';

    /** @var list<string> */
    private const SAFETY_FACILITIES = [
        'public_toilet', 'dump_point', 'drinking_water', 'hospital', 'medical_centre',
        'pharmacy', 'emergency_services', 'fuel', 'lpg_refill',
    ];

    /** @var list<string> */
    private const SAFETY_PROVIDERS = [
        'roadside-assistance', 'towing-and-vehicle-recovery', 'dump-points',
        'potable-water-refill', 'lpg-refills-and-bottle-exchange', 'fuel-and-travel-stops',
    ];

    private const DEDUPE_SECONDS = 30;

    /**
     * @param array<string,mixed>|null $town
     * @return ?int knowledge_gaps.id when a gap was recorded/updated
     */
    public function observe(
        SearchRequest $request,
        string $normalisedQuery,
        Intent $intent,
        int $localCount,
        int $externalCount,
        ?array $town,
        string $locationPrecision,
        ?int $assistSearchId,
        bool $aiUsed,
    ): ?int {
        $quality = $this->classifyQuality($intent, $localCount);
        if ($quality === self::QUALITY_ADEQUATE) {
            return null;
        }

        $townId = isset($town['id']) ? (int) $town['id'] : null;
        $radius = $intent->radiusKm ?? (int) config('ai_search.default_radius_km', 25);
        $radiusBucket = $this->radiusBucket($radius);
        $taxonomy = $this->taxonomyPayload($intent);
        $gapKey = $this->buildGapKey(
            $request->brandKey,
            $normalisedQuery,
            $intent->intentType,
            $townId,
            $taxonomy,
            $radiusBucket
        );

        $safety = $this->isSafetyRelevant($intent);
        $remote = $townId === null || $locationPrecision === 'none';
        $priority = $this->scorePriority([
            'search_count' => 1,
            'zero_result_count' => $quality === self::QUALITY_NONE ? 1 : 0,
            'weak_result_count' => $quality === self::QUALITY_WEAK ? 1 : 0,
            'urgency_urgent_count' => $intent->urgency === 'urgent' ? 1 : 0,
            'safety_relevant' => $safety,
            'remote_location' => $remote,
            'contact_action_count' => 0,
            'click_through_count' => 0,
        ]);

        try {
            $existing = Database::selectOne('SELECT id, search_count, zero_result_count, weak_result_count, urgency_urgent_count, ai_used_count, approx_unique_sessions, click_through_count, contact_action_count, safety_relevant, remote_location FROM knowledge_gaps WHERE gap_key = ? LIMIT 1', [$gapKey]);

            if ($existing === null) {
                $gapId = Database::insert(
                    'INSERT INTO knowledge_gaps (
                        gap_key, brand_key, brand_id, original_query_sample, normalised_query,
                        intent_type, intent_json, provider_category_keys, stay_type_keys, facility_type_keys,
                        town_id, location_text, radius_bucket_km, result_quality,
                        local_result_count_last, external_result_count_last,
                        zero_result_count, weak_result_count, search_count, approx_unique_sessions,
                        urgency_urgent_count, ai_used_count, safety_relevant, remote_location,
                        priority_score, resolution_status, first_seen_at, last_seen_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())',
                    [
                        $gapKey,
                        mb_substr($request->brandKey, 0, 40),
                        $request->brandDatabaseId,
                        mb_substr($request->rawQuery, 0, 500),
                        mb_substr($normalisedQuery, 0, 500),
                        mb_substr($intent->intentType, 0, 40),
                        json_encode($intent->toArray(), JSON_THROW_ON_ERROR),
                        json_encode($taxonomy['providers'], JSON_THROW_ON_ERROR),
                        json_encode($taxonomy['stays'], JSON_THROW_ON_ERROR),
                        json_encode($taxonomy['facilities'], JSON_THROW_ON_ERROR),
                        $townId,
                        $intent->locationText !== null ? mb_substr($intent->locationText, 0, 120) : null,
                        $radiusBucket,
                        $quality,
                        max(0, min(65535, $localCount)),
                        max(0, min(65535, $externalCount)),
                        $quality === self::QUALITY_NONE ? 1 : 0,
                        $quality === self::QUALITY_WEAK ? 1 : 0,
                        $request->sessionId !== null ? 1 : 0,
                        $intent->urgency === 'urgent' ? 1 : 0,
                        $aiUsed ? 1 : 0,
                        $safety ? 1 : 0,
                        $remote ? 1 : 0,
                        $priority,
                        self::STATUS_OPEN,
                    ]
                );
            } else {
                $gapId = (int) $existing['id'];
                $searchCount = (int) $existing['search_count'] + 1;
                $zero = (int) $existing['zero_result_count'] + ($quality === self::QUALITY_NONE ? 1 : 0);
                $weak = (int) $existing['weak_result_count'] + ($quality === self::QUALITY_WEAK ? 1 : 0);
                $urgent = (int) $existing['urgency_urgent_count'] + ($intent->urgency === 'urgent' ? 1 : 0);
                $aiCount = (int) $existing['ai_used_count'] + ($aiUsed ? 1 : 0);
                $unique = (int) $existing['approx_unique_sessions'] + ($request->sessionId !== null ? 1 : 0);
                $priority = $this->scorePriority([
                    'search_count' => $searchCount,
                    'zero_result_count' => $zero,
                    'weak_result_count' => $weak,
                    'urgency_urgent_count' => $urgent,
                    'safety_relevant' => ((int) $existing['safety_relevant'] === 1) || $safety,
                    'remote_location' => ((int) $existing['remote_location'] === 1) || $remote,
                    'contact_action_count' => (int) $existing['contact_action_count'],
                    'click_through_count' => (int) $existing['click_through_count'],
                ]);

                Database::query(
                    'UPDATE knowledge_gaps SET
                        original_query_sample = ?,
                        intent_json = ?,
                        result_quality = ?,
                        local_result_count_last = ?,
                        external_result_count_last = ?,
                        zero_result_count = ?,
                        weak_result_count = ?,
                        search_count = ?,
                        approx_unique_sessions = ?,
                        urgency_urgent_count = ?,
                        ai_used_count = ?,
                        safety_relevant = ?,
                        remote_location = ?,
                        priority_score = ?,
                        last_seen_at = NOW(),
                        updated_at = NOW()
                     WHERE id = ? AND resolution_status IN (?, ?)',
                    [
                        mb_substr($request->rawQuery, 0, 500),
                        json_encode($intent->toArray(), JSON_THROW_ON_ERROR),
                        $quality,
                        max(0, min(65535, $localCount)),
                        max(0, min(65535, $externalCount)),
                        $zero,
                        $weak,
                        $searchCount,
                        $unique,
                        $urgent,
                        $aiCount,
                        (((int) $existing['safety_relevant'] === 1) || $safety) ? 1 : 0,
                        (((int) $existing['remote_location'] === 1) || $remote) ? 1 : 0,
                        $priority,
                        $gapId,
                        self::STATUS_OPEN,
                        self::STATUS_RESEARCHING,
                    ]
                );
            }

            if ($gapId > 0) {
                Database::insert(
                    'INSERT INTO knowledge_gap_events (
                        knowledge_gap_id, assist_search_id, session_id, result_quality, local_result_count, created_at
                    ) VALUES (?, ?, ?, ?, ?, NOW())',
                    [
                        $gapId,
                        $assistSearchId,
                        $request->sessionId,
                        $quality,
                        max(0, min(65535, $localCount)),
                    ]
                );
                return $gapId;
            }
        } catch (Throwable) {
            // Gap logging must never break search.
        }
        return null;
    }

    /** Record Ask result click-through against a knowledge gap (session-deduped). */
    public function recordClickThrough(int $gapId): void
    {
        $this->bumpOutcomeCounter($gapId, 'click_through_count', 'kg_click');
    }

    /** Record Ask contact action against a knowledge gap (session-deduped). */
    public function recordContactAction(int $gapId): void
    {
        $this->bumpOutcomeCounter($gapId, 'contact_action_count', 'kg_contact');
    }

    private function bumpOutcomeCounter(int $gapId, string $column, string $sessionPrefix): void
    {
        if ($gapId < 1 || !in_array($column, ['click_through_count', 'contact_action_count'], true)) {
            return;
        }
        try {
            $sessionKey = $sessionPrefix . ':' . $gapId;
            $last = (int) Session::get($sessionKey, 0);
            if ($last > 0 && (time() - $last) < self::DEDUPE_SECONDS) {
                return;
            }
            Session::set($sessionKey, time());

            $row = Database::selectOne(
                'SELECT id, search_count, zero_result_count, weak_result_count, urgency_urgent_count,
                        safety_relevant, remote_location, click_through_count, contact_action_count
                 FROM knowledge_gaps WHERE id = ? LIMIT 1',
                [$gapId]
            );
            if ($row === null) {
                return;
            }

            $clicks = (int) $row['click_through_count'] + ($column === 'click_through_count' ? 1 : 0);
            $contacts = (int) $row['contact_action_count'] + ($column === 'contact_action_count' ? 1 : 0);
            $priority = $this->scorePriority([
                'search_count' => (int) $row['search_count'],
                'zero_result_count' => (int) $row['zero_result_count'],
                'weak_result_count' => (int) $row['weak_result_count'],
                'urgency_urgent_count' => (int) $row['urgency_urgent_count'],
                'safety_relevant' => (int) $row['safety_relevant'] === 1,
                'remote_location' => (int) $row['remote_location'] === 1,
                'contact_action_count' => $contacts,
                'click_through_count' => $clicks,
            ]);

            Database::query(
                match ($column) {
                    'contact_action_count' => 'UPDATE knowledge_gaps SET contact_action_count = contact_action_count + 1, priority_score = ?, updated_at = NOW() WHERE id = ?',
                    default => 'UPDATE knowledge_gaps SET click_through_count = click_through_count + 1, priority_score = ?, updated_at = NOW() WHERE id = ?',
                },
                [$priority, $gapId]
            );

            // TrackingSession touch keeps demand session alive when present.
            try {
                TrackingSession::id();
            } catch (Throwable) {
            }
        } catch (Throwable) {
            // Outcome analytics must never break redirects.
        }
    }

    public function classifyQuality(Intent $intent, int $localCount): string
    {
        if ($intent->intentType === Intent::TYPE_UNKNOWN || $intent->adapterKeys === []) {
            return self::QUALITY_NONE;
        }
        $weakAt = max(1, (int) config('ai_search.weak_result_threshold', 3));
        if ($localCount <= 0) {
            return self::QUALITY_NONE;
        }
        if ($localCount < $weakAt) {
            return self::QUALITY_WEAK;
        }
        return self::QUALITY_ADEQUATE;
    }

    /**
     * @param array{
     *   search_count:int,
     *   zero_result_count:int,
     *   weak_result_count:int,
     *   urgency_urgent_count:int,
     *   safety_relevant:bool,
     *   remote_location:bool,
     *   contact_action_count:int,
     *   click_through_count:int
     * } $signals
     */
    public function scorePriority(array $signals): int
    {
        $score = 0;
        $searches = max(0, $signals['search_count']);
        $score += min(40, (int) floor(log(1 + $searches, 2) * 10));
        $zeroRate = $searches > 0 ? $signals['zero_result_count'] / $searches : 0.0;
        $score += (int) round($zeroRate * 25);
        $weakRate = $searches > 0 ? $signals['weak_result_count'] / $searches : 0.0;
        $score += (int) round($weakRate * 10);
        if ($signals['urgency_urgent_count'] > 0) {
            $score += 15;
        }
        if ($signals['safety_relevant']) {
            $score += 20;
        }
        if ($signals['remote_location']) {
            $score += 5;
        }
        $score += min(10, $signals['contact_action_count'] * 2);
        $score += min(5, $signals['click_through_count']);
        return max(0, min(100, $score));
    }

    /**
     * @param array{providers:list<string>,stays:list<string>,facilities:list<string>} $taxonomy
     */
    public function buildGapKey(
        string $brandKey,
        string $normalisedQuery,
        string $intentType,
        ?int $townId,
        array $taxonomy,
        int $radiusBucket,
    ): string {
        $providers = $taxonomy['providers'];
        $stays = $taxonomy['stays'];
        $facilities = $taxonomy['facilities'];
        sort($providers);
        sort($stays);
        sort($facilities);
        $payload = implode('|', [
            mb_strtolower(trim($brandKey)),
            mb_strtolower(trim($normalisedQuery)),
            $intentType,
            $townId !== null ? (string) $townId : 'none',
            implode(',', $providers),
            implode(',', $stays),
            implode(',', $facilities),
            (string) $radiusBucket,
        ]);
        return hash('sha256', $payload);
    }

    public function radiusBucket(int $radiusKm): int
    {
        $r = max(1, min(500, $radiusKm));
        return (int) (floor(($r - 1) / 25) * 25 + 25);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listForAdmin(string $brandKey, string $status = self::STATUS_OPEN, int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        try {
            if ($status === 'all') {
                return Database::select(
                    'SELECT * FROM knowledge_gaps WHERE brand_key = ? ORDER BY priority_score DESC, last_seen_at DESC LIMIT ' . $limit,
                    [$brandKey]
                );
            }
            return Database::select(
                'SELECT * FROM knowledge_gaps WHERE brand_key = ? AND resolution_status = ? ORDER BY priority_score DESC, last_seen_at DESC LIMIT ' . $limit,
                [$brandKey, $status]
            );
        } catch (Throwable) {
            return [];
        }
    }

    public function updateStatus(int $id, string $status, ?string $job, ?string $notes, ?int $actorId): bool
    {
        $allowed = [self::STATUS_OPEN, self::STATUS_RESEARCHING, self::STATUS_RESOLVED, self::STATUS_WONT_FIX];
        if (!in_array($status, $allowed, true) || $id <= 0) {
            return false;
        }
        try {
            Database::query(
                'UPDATE knowledge_gaps SET
                    resolution_status = ?,
                    assigned_research_job = ?,
                    resolution_notes = ?,
                    resolved_at = CASE WHEN ? IN (?, ?) THEN NOW() ELSE resolved_at END,
                    updated_at = NOW()
                 WHERE id = ?',
                [
                    $status,
                    $job !== null && $job !== '' ? mb_substr($job, 0, 120) : null,
                    $notes !== null && $notes !== '' ? mb_substr($notes, 0, 500) : null,
                    $status,
                    self::STATUS_RESOLVED,
                    self::STATUS_WONT_FIX,
                    $id,
                ]
            );
            unset($actorId);
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Map knowledge_gaps rows into Phase 1 Admin API SearchGap envelope fields
     * (DATA-013 RIC hand-off without expanding locked OpenAPI).
     *
     * Item meta.source is always knowledge_gaps so dual-source merge
     * (SearchGapDualSource / GET /search-gaps) can attribute rows safely.
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     * @see docs/SEARCH_GAP_DUAL_SOURCE.md
     */
    public function toSearchGapItems(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $localLast = (int) ($row['local_result_count_last'] ?? 0);
            $items[] = [
                'query_text' => (string) ($row['normalised_query'] ?? $row['original_query_sample'] ?? ''),
                'location_text' => isset($row['location_text']) ? (string) $row['location_text'] : null,
                'result_count' => $localLast,
                'search_count' => (int) ($row['search_count'] ?? 0),
                'first_seen' => (string) ($row['first_seen_at'] ?? ''),
                'last_seen' => (string) ($row['last_seen_at'] ?? ''),
                'intent' => isset($row['intent_type']) ? (string) $row['intent_type'] : null,
                'urgency_score' => (int) ($row['priority_score'] ?? 0),
                'town_id' => array_key_exists('town_id', $row) && $row['town_id'] !== null && $row['town_id'] !== ''
                    ? (int) $row['town_id']
                    : null,
                'category_id' => null,
                'meta' => [
                    'source' => SearchGapDualSource::SOURCE_KNOWLEDGE,
                    'gap_id' => (int) ($row['id'] ?? 0),
                    'result_quality' => (string) ($row['result_quality'] ?? ''),
                    'resolution_status' => (string) ($row['resolution_status'] ?? ''),
                    'safety_relevant' => (int) ($row['safety_relevant'] ?? 0) === 1,
                    'brand_key' => (string) ($row['brand_key'] ?? ''),
                ],
            ];
        }
        return $items;
    }

    /**
     * Knowledge-only SearchGap collection for admin JSON export bridge.
     * Post CORE-011 merge, AdminApiSearchGapService uses Option B dual-source
     * via SearchGapDualSource (provider_searches + these items).
     *
     * @return array{data:list<array<string,mixed>>,meta:array<string,mixed>}
     * @see docs/SEARCH_GAP_DUAL_SOURCE.md
     */
    public function searchGapCollection(string $brandKey, string $status = self::STATUS_OPEN, int $limit = 100): array
    {
        $rows = $this->listForAdmin($brandKey, $status, $limit);
        $items = $this->toSearchGapItems($rows);
        return [
            'data' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'source' => SearchGapDualSource::SOURCE_KNOWLEDGE,
                'brand_key' => $brandKey,
                'status' => $status,
                'sparse' => $items === [],
                'contract' => 'SearchGapCollectionResponse',
            ],
        ];
    }

    /**
     * @return array{providers:list<string>,stays:list<string>,facilities:list<string>}
     */
    private function taxonomyPayload(Intent $intent): array
    {
        return [
            'providers' => $intent->providerCategoryKeys,
            'stays' => $intent->stayTypeKeys,
            'facilities' => $intent->facilityTypeKeys,
        ];
    }

    private function isSafetyRelevant(Intent $intent): bool
    {
        foreach ($intent->facilityTypeKeys as $key) {
            if (in_array($key, self::SAFETY_FACILITIES, true)) {
                return true;
            }
        }
        foreach ($intent->providerCategoryKeys as $key) {
            if (in_array($key, self::SAFETY_PROVIDERS, true)) {
                return true;
            }
        }
        return false;
    }
}
