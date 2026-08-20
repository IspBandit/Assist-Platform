-- Option B Increment F: RIC live sync conflict queue for Admin API resolution.

CREATE TABLE api_sync_conflicts (
    id CHAR(36) NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40) NOT NULL,
    local_ref VARCHAR(190) NOT NULL,
    live_id INT UNSIGNED NULL,
    status ENUM('open', 'resolved_push', 'resolved_pull', 'deferred', 'ignored') NOT NULL DEFAULT 'open',
    conflict_reason VARCHAR(500) NOT NULL,
    local_payload_json JSON NOT NULL,
    live_payload_json JSON NULL,
    resolution_json JSON NULL,
    created_by_client_id CHAR(36) NULL,
    resolved_by_user_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    resolved_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_api_sync_conflicts_brand_status (brand_id, status, created_at),
    KEY idx_api_sync_conflicts_entity (entity_type, local_ref),
    CONSTRAINT fk_api_sync_conflicts_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_sync_conflicts_client FOREIGN KEY (created_by_client_id) REFERENCES api_oauth_clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_sync_conflicts_resolver FOREIGN KEY (resolved_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE api_import_jobs
    MODIFY status ENUM('received', 'validated', 'staged', 'failed', 'cancelled', 'published') NOT NULL DEFAULT 'received';
