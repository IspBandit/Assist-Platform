CREATE TABLE IF NOT EXISTS ask_question_library (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question VARCHAR(320) NOT NULL,
    normalized_question VARCHAR(320) NOT NULL,
    intent_json JSON NOT NULL,
    intent_type VARCHAR(32) NOT NULL,
    rules_version VARCHAR(40) NOT NULL,
    source VARCHAR(24) NOT NULL DEFAULT 'bundled',
    popularity_rank INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ask_question_library_normalized (normalized_question),
    KEY idx_ask_question_library_active_rank (is_active, popularity_rank),
    KEY idx_ask_question_library_intent (intent_type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ai_settings
    MODIFY intent_cache_ttl_hours INT UNSIGNED NOT NULL DEFAULT 720;

-- Preserve intentional admin values while moving the old default to 30 days.
UPDATE ai_settings SET intent_cache_ttl_hours = 720 WHERE intent_cache_ttl_hours = 168;
