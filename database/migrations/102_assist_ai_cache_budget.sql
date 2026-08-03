-- Assist AI Search (Phase AI-2): intent cache, settings, budget and usage.
-- Paid AI remains disabled by default. No API keys stored in these tables.
-- Structured /find unchanged. Admin API Phase 1 untouched.

CREATE TABLE IF NOT EXISTS ai_settings (
    id                      TINYINT UNSIGNED NOT NULL DEFAULT 1,
    ai_enabled              TINYINT(1) NOT NULL DEFAULT 0,
    openai_enabled          TINYINT(1) NOT NULL DEFAULT 0,
    model_allowlist_json    JSON NULL,
    daily_request_cap       INT UNSIGNED NOT NULL DEFAULT 0,
    monthly_request_cap     INT UNSIGNED NOT NULL DEFAULT 0,
    daily_budget_aud        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_budget_aud      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    soft_warn_pct           TINYINT UNSIGNED NOT NULL DEFAULT 80,
    max_prompt_chars        INT UNSIGNED NOT NULL DEFAULT 2000,
    max_output_tokens       INT UNSIGNED NOT NULL DEFAULT 500,
    max_retries             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    timeout_seconds         SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    intent_cache_ttl_hours  INT UNSIGNED NOT NULL DEFAULT 168,
    updated_at              DATETIME NULL,
    updated_by              INT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_ai_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 091 introduced the Admin API's minimal AI tables. Extend that
-- existing schema before seeding the fuller orchestrator settings.
ALTER TABLE ai_settings
    ADD COLUMN model_allowlist_json JSON NULL AFTER openai_enabled,
    ADD COLUMN soft_warn_pct TINYINT UNSIGNED NOT NULL DEFAULT 80 AFTER monthly_budget_aud,
    ADD COLUMN max_prompt_chars INT UNSIGNED NOT NULL DEFAULT 2000 AFTER soft_warn_pct,
    ADD COLUMN max_output_tokens INT UNSIGNED NOT NULL DEFAULT 500 AFTER max_prompt_chars,
    ADD COLUMN max_retries TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER max_output_tokens,
    ADD COLUMN timeout_seconds SMALLINT UNSIGNED NOT NULL DEFAULT 15 AFTER max_retries,
    ADD COLUMN intent_cache_ttl_hours INT UNSIGNED NOT NULL DEFAULT 168 AFTER timeout_seconds,
    ADD COLUMN updated_by INT UNSIGNED NULL AFTER updated_at;

INSERT INTO ai_settings (
    id, ai_enabled, openai_enabled, model_allowlist_json,
    daily_request_cap, monthly_request_cap, daily_budget_aud, monthly_budget_aud,
    soft_warn_pct, max_prompt_chars, max_output_tokens, max_retries, timeout_seconds,
    intent_cache_ttl_hours, updated_at
) VALUES (
    1, 0, 0, JSON_ARRAY(),
    0, 0, 0.00, 0.00,
    80, 2000, 500, 1, 15,
    168, NOW()
)
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

CREATE TABLE IF NOT EXISTS ai_intent_cache (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key          CHAR(64) NOT NULL,
    brand_key          VARCHAR(40) NOT NULL,
    normalised_query   VARCHAR(500) NOT NULL,
    locale             VARCHAR(16) NOT NULL DEFAULT 'en-AU',
    taxonomy_version   VARCHAR(40) NOT NULL,
    rules_version      VARCHAR(40) NOT NULL,
    model_version      VARCHAR(80) NULL,
    intent_json        JSON NOT NULL,
    intent_source      VARCHAR(20) NOT NULL DEFAULT 'rules',
    confidence         DECIMAL(4,3) NULL,
    hit_count          INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at         DATETIME NOT NULL,
    created_at         DATETIME NOT NULL,
    last_hit_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ai_intent_cache_key (cache_key),
    KEY idx_ai_cache_expires (expires_at),
    KEY idx_ai_cache_brand_q (brand_key, normalised_query(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage_events (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id         VARCHAR(64) NULL,
    brand_key          VARCHAR(40) NOT NULL DEFAULT '',
    operation_type     VARCHAR(40) NOT NULL,
    provider           VARCHAR(40) NULL,
    model              VARCHAR(80) NULL,
    input_tokens       INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens      INT UNSIGNED NOT NULL DEFAULT 0,
    cached             TINYINT(1) NOT NULL DEFAULT 0,
    estimated_cost_aud DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    actual_cost_aud    DECIMAL(10,6) NULL,
    duration_ms        INT UNSIGNED NULL,
    success            TINYINT(1) NOT NULL DEFAULT 1,
    fallback_reason    VARCHAR(120) NULL,
    assist_search_id   BIGINT UNSIGNED NULL,
    intent_confidence  DECIMAL(4,3) NULL,
    budget_state       VARCHAR(40) NULL,
    created_at         DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ai_usage_created (created_at),
    KEY idx_ai_usage_brand_op (brand_key, operation_type, created_at),
    KEY idx_ai_usage_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_usage_events
    ADD COLUMN actual_cost_aud DECIMAL(10,6) NULL AFTER estimated_cost_aud,
    ADD COLUMN duration_ms INT UNSIGNED NULL AFTER actual_cost_aud,
    ADD COLUMN fallback_reason VARCHAR(120) NULL AFTER success,
    ADD COLUMN assist_search_id BIGINT UNSIGNED NULL AFTER fallback_reason,
    ADD COLUMN intent_confidence DECIMAL(4,3) NULL AFTER assist_search_id,
    ADD COLUMN budget_state VARCHAR(40) NULL AFTER intent_confidence,
    ADD KEY idx_ai_usage_request (request_id);

CREATE TABLE IF NOT EXISTS ai_usage_daily (
    usage_date          DATE NOT NULL,
    brand_key           VARCHAR(40) NOT NULL DEFAULT '',
    operation_type      VARCHAR(40) NOT NULL DEFAULT '',
    requests            INT UNSIGNED NOT NULL DEFAULT 0,
    cache_hits          INT UNSIGNED NOT NULL DEFAULT 0,
    ai_requests         INT UNSIGNED NOT NULL DEFAULT 0,
    rules_only          INT UNSIGNED NOT NULL DEFAULT 0,
    failed_requests     INT UNSIGNED NOT NULL DEFAULT 0,
    budget_blocked      INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost_aud  DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    updated_at          DATETIME NULL,
    PRIMARY KEY (usage_date, brand_key, operation_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_usage_daily
    ADD COLUMN operation_type VARCHAR(40) NOT NULL DEFAULT '' AFTER brand_key,
    ADD COLUMN ai_requests INT UNSIGNED NOT NULL DEFAULT 0 AFTER cache_hits,
    ADD COLUMN rules_only INT UNSIGNED NOT NULL DEFAULT 0 AFTER ai_requests,
    ADD COLUMN failed_requests INT UNSIGNED NOT NULL DEFAULT 0 AFTER rules_only,
    ADD COLUMN budget_blocked INT UNSIGNED NOT NULL DEFAULT 0 AFTER failed_requests,
    DROP PRIMARY KEY,
    ADD PRIMARY KEY (usage_date, brand_key, operation_type);
