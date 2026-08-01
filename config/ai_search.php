<?php

declare(strict_types=1);

/**
 * Assist AI Search / Orchestrator configuration (Phase AI-1).
 * Paid AI remains disabled; deterministic rules only.
 */
return [
    'max_query_length' => 240,
    'default_radius_km' => 25,
    'min_confidence' => 0.55,
    'intent_rules_version' => 'intent_rules_v1',
    'intent_schema_version' => 'intent_schema_v1',
    'taxonomy_version' => 'taxonomy_v1',
    'intent_cache_ttl_hours' => 168,
];
