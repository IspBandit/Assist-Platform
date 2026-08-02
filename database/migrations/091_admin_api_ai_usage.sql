-- Option B Increment E: minimal AI settings and usage tables for Admin API reporting.

CREATE TABLE IF NOT EXISTS ai_settings (
    id TINYINT UNSIGNED NOT NULL DEFAULT 1,
    ai_enabled TINYINT(1) NOT NULL DEFAULT 0,
    openai_enabled TINYINT(1) NOT NULL DEFAULT 0,
    daily_request_cap INT UNSIGNED NOT NULL DEFAULT 0,
    monthly_request_cap INT UNSIGNED NOT NULL DEFAULT 0,
    daily_budget_aud DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    monthly_budget_aud DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT chk_ai_settings_singleton CHECK (id = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ai_settings (id, ai_enabled, openai_enabled, updated_at)
VALUES (1, 0, 0, NOW())
ON DUPLICATE KEY UPDATE updated_at = VALUES(updated_at);

CREATE TABLE IF NOT EXISTS ai_usage_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(64) NULL,
    brand_key VARCHAR(40) NOT NULL DEFAULT '',
    operation_type VARCHAR(40) NOT NULL,
    provider VARCHAR(40) NULL,
    model VARCHAR(80) NULL,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    cached TINYINT(1) NOT NULL DEFAULT 0,
    estimated_cost_aud DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
    success TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ai_usage_created (created_at),
    KEY idx_ai_usage_brand_op (brand_key, operation_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_usage_daily (
    usage_date DATE NOT NULL,
    brand_key VARCHAR(40) NOT NULL DEFAULT '',
    requests INT UNSIGNED NOT NULL DEFAULT 0,
    input_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    output_tokens INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost_aud DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
    cache_hits INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (usage_date, brand_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
