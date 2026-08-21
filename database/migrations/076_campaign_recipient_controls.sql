-- COM-002: campaign-specific provider recipient review controls.

CREATE TABLE notification_provider_exclusions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NOT NULL,
    reason VARCHAR(500) NOT NULL,
    excluded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_notification_provider_exclusion (notification_id, provider_id),
    KEY idx_notification_provider_exclusion_provider (provider_id),
    CONSTRAINT fk_npe_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
    CONSTRAINT fk_npe_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_npe_user FOREIGN KEY (excluded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
