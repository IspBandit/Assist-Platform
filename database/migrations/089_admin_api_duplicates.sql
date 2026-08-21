-- Option B Increment C: duplicate review decisions and merge history for Admin API.

CREATE TABLE api_duplicate_decisions (
    id CHAR(36) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT 'provider',
    record_a_id INT UNSIGNED NOT NULL,
    record_b_id INT UNSIGNED NOT NULL,
    score DECIMAL(5,2) NULL,
    classification VARCHAR(80) NULL,
    status ENUM('open', 'merged', 'not_duplicate', 'deferred') NOT NULL DEFAULT 'open',
    reasons_json JSON NULL,
    merged_into_id INT UNSIGNED NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    meta_json JSON NULL,
    brand_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_api_dup_decisions_brand_status (brand_id, status, created_at),
    KEY idx_api_dup_decisions_pair (entity_type, record_a_id, record_b_id),
    CONSTRAINT fk_api_dup_decisions_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_dup_decisions_decider FOREIGN KEY (decided_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_merge_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    decision_id CHAR(36) NOT NULL,
    surviving_id INT UNSIGNED NOT NULL,
    absorbed_id INT UNSIGNED NOT NULL,
    field_choices_json JSON NULL,
    actor_user_id INT UNSIGNED NULL,
    actor_client_id CHAR(36) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_api_merge_history_decision (decision_id),
    CONSTRAINT fk_api_merge_history_decision FOREIGN KEY (decision_id) REFERENCES api_duplicate_decisions (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_merge_history_user FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_merge_history_client FOREIGN KEY (actor_client_id) REFERENCES api_oauth_clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
