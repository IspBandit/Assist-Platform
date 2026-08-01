-- Assist AI Search (Phase AI-1): NL search logging.
-- Feature flag assist_ai_search defaults OFF via seed; structured /find unchanged.
-- No AI vendor tables in this migration.

CREATE TABLE IF NOT EXISTS assist_searches (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id             INT UNSIGNED NULL,
    session_id           BIGINT UNSIGNED NULL,
    request_id           VARCHAR(64) NULL,
    channel              VARCHAR(40) NOT NULL DEFAULT 'ask_vanassist',
    raw_query            VARCHAR(500) NOT NULL,
    normalised_query     VARCHAR(500) NOT NULL,
    intent_json          JSON NULL,
    intent_source        VARCHAR(20) NOT NULL DEFAULT 'none',
    confidence           DECIMAL(4,3) NULL,
    adapter_keys         JSON NULL,
    local_result_count   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    external_result_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    fallback_reason      VARCHAR(120) NULL,
    town_id              INT UNSIGNED NULL,
    radius_km            INT UNSIGNED NULL,
    location_precision   VARCHAR(20) NOT NULL DEFAULT 'none',
    created_at           DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_as_brand_created (brand_id, created_at),
    KEY idx_as_normalised (normalised_query(191)),
    KEY idx_as_town_created (town_id, created_at),
    KEY idx_as_intent_source (intent_source, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES (
    'assist_ai_search',
    0,
    'Ask VanAssist natural-language search (deterministic orchestrator; off by default).',
    NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
