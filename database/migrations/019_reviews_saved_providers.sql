-- =====================================================================
-- 019 Reviews + saved providers (demand analytics, Phase 11 stage 4)
--
-- ADDITIVE ONLY. Two new tables, no existing table altered or dropped, so
-- this is safe on the live database and reversible by dropping the tables.
--
--   provider_reviews  — a customer review tied to a confirmed service outcome
--                       (one review per outcome; moderated before publishing).
--   saved_providers   — a customer's saved/short-listed providers.
-- =====================================================================

CREATE TABLE provider_reviews (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NOT NULL,
    customer_id     INT UNSIGNED NULL,
    request_id      INT UNSIGNED NULL,
    outcome_id      BIGINT UNSIGNED NULL,
    rating          TINYINT UNSIGNED NOT NULL,
    title           VARCHAR(150) NULL,
    body            TEXT NULL,
    would_recommend TINYINT(1) NULL,
    is_verified_use TINYINT(1) NOT NULL DEFAULT 0,
    status          ENUM('pending','published','rejected') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pr_outcome (outcome_id),
    KEY idx_pr_provider (provider_id),
    KEY idx_pr_status (status),
    KEY idx_pr_provider_status (provider_id, status),
    CONSTRAINT fk_pr_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_pr_outcome FOREIGN KEY (outcome_id) REFERENCES service_outcomes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_providers (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id  INT UNSIGNED NOT NULL,
    provider_id  INT UNSIGNED NOT NULL,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sp_customer_provider (customer_id, provider_id),
    KEY idx_sp_customer (customer_id),
    KEY idx_sp_provider (provider_id),
    CONSTRAINT fk_sp_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
