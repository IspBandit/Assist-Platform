<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Retention;

use App\Core\Database;
use Throwable;

/**
 * Purges Assist AI raw telemetry past retention windows (AI-7).
 * Aggregated ai_usage_daily and open knowledge_gaps are retained.
 */
final class AiRetentionService
{
    /**
     * @return array<string,int|string>
     */
    public function purge(): array
    {
        $searchDays = max(30, (int) config('ai_search.retention_assist_searches_days', 180));
        $usageDays = max(30, (int) config('ai_search.retention_usage_events_days', 180));
        $gapEventDays = max(30, (int) config('ai_search.retention_gap_events_days', 365));

        $out = [
            'assist_searches_purged' => 0,
            'ai_usage_events_purged' => 0,
            'ai_intent_cache_expired' => 0,
            'knowledge_gap_events_purged' => 0,
        ];

        try {
            $out['assist_searches_purged'] = Database::affecting(
                "DELETE FROM assist_searches WHERE created_at < DATE_SUB(NOW(), INTERVAL {$searchDays} DAY)"
            );
        } catch (Throwable $e) {
            $out['assist_searches_error'] = $e->getMessage();
        }

        try {
            $out['ai_usage_events_purged'] = Database::affecting(
                "DELETE FROM ai_usage_events WHERE created_at < DATE_SUB(NOW(), INTERVAL {$usageDays} DAY)"
            );
        } catch (Throwable $e) {
            $out['ai_usage_error'] = $e->getMessage();
        }

        try {
            $out['ai_intent_cache_expired'] = Database::affecting(
                'DELETE FROM ai_intent_cache WHERE expires_at < NOW()'
            );
        } catch (Throwable $e) {
            $out['ai_cache_error'] = $e->getMessage();
        }

        try {
            $out['knowledge_gap_events_purged'] = Database::affecting(
                "DELETE FROM knowledge_gap_events WHERE created_at < DATE_SUB(NOW(), INTERVAL {$gapEventDays} DAY)"
            );
        } catch (Throwable $e) {
            $out['gap_events_error'] = $e->getMessage();
        }

        return $out;
    }

    /**
     * @return array{assist_searches_days:int,usage_events_days:int,gap_events_days:int}
     */
    public static function windows(): array
    {
        return [
            'assist_searches_days' => max(30, (int) config('ai_search.retention_assist_searches_days', 180)),
            'usage_events_days' => max(30, (int) config('ai_search.retention_usage_events_days', 180)),
            'gap_events_days' => max(30, (int) config('ai_search.retention_gap_events_days', 365)),
        ];
    }
}
