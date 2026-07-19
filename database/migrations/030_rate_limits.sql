-- Persistent hashed rate-limit state for authentication and public abuse controls.

CREATE TABLE rate_limit_buckets (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_key      VARCHAR(80) NOT NULL,
    subject_hash    CHAR(64) NOT NULL,
    attempts        INT UNSIGNED NOT NULL DEFAULT 0,
    window_started_at DATETIME NOT NULL,
    blocked_until   DATETIME NULL,
    last_attempt_at DATETIME NOT NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_limit_action_subject (action_key, subject_hash),
    KEY idx_rate_limit_blocked (blocked_until),
    KEY idx_rate_limit_last_attempt (last_attempt_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
