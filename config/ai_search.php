<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * Assist AI Search / Orchestrator configuration (Phase AI-7).
 * Paid AI remains disabled until env key + admin enable + allowlist + caps.
 * Dataset routing is gated by feature flag assist_ai_datasets (off by default).
 * Model ids are never hard-coded as the selected model — only cost-rate hints.
 */
return [
    'max_query_length' => 240,
    'default_radius_km' => 25,
    'specialist_radius_km' => 150,
    'min_confidence' => 0.55,
    'intent_rules_version' => 'intent_rules_v1',
    'intent_schema_version' => 'intent_schema_v1',
    'taxonomy_version' => 'taxonomy_v1',
    'intent_cache_ttl_hours' => 720,
    'weak_result_threshold' => 3,
    'dataset_max_results' => 12,

    // AI-7 retention (days). Aggregated ai_usage_daily and knowledge_gaps kept.
    'retention_assist_searches_days' => 180,
    'retention_usage_events_days' => 180,
    'retention_gap_events_days' => 365,

    // Cost simulator defaults (what-if; not billing).
    'cost_sim_default_input_tokens' => 800,
    'cost_sim_default_output_tokens' => 500,

    // Ask VanAssist public rate limit (middleware args mirrored in docs).
    'ask_rate_max_attempts' => 20,
    'ask_rate_window_seconds' => 3600,
    'ask_rate_block_seconds' => 3600,

    // Offline OSM seed for admin/CLI staging into DATA-006 (never live Overpass from Ask).
    'osm_offline_enabled' => filter_var(Env::get('AI_OSM_OFFLINE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'osm_seed_path' => (string) Env::get('AI_OSM_SEED_PATH', ''),

    // Conservative USD→AUD for budget estimates (not billing).
    'usd_to_aud' => (float) Env::get('AI_USD_TO_AUD', 1.6),

    // Cost estimate table (USD per 1M tokens). Prefix match on allowlisted model.
    // Update when OpenAI pricing changes; does not select which model to call.
    'model_cost_usd_per_1m' => [
        'gpt-4.1-nano' => ['input' => 0.10, 'output' => 0.40],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
    ],
];
