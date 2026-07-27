-- COM-001/COM-002: central bounce, complaint and marketing opt-out safety.

CREATE TABLE email_suppressions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email VARCHAR(190) NOT NULL,
    reason ENUM('marketing_opt_out','hard_bounce','complaint','admin') NOT NULL,
    scope ENUM('marketing','all') NOT NULL DEFAULT 'marketing',
    source VARCHAR(80) NOT NULL DEFAULT 'application',
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_email_suppression (email, reason),
    KEY idx_email_suppression_lookup (email, scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE email_queue
    ADD COLUMN message_type ENUM('transactional','marketing') NOT NULL DEFAULT 'transactional' AFTER template_key,
    ADD KEY idx_email_queue_type_status (message_type, status);

ALTER TABLE notification_recipients
    MODIFY COLUMN status ENUM('queued','sent','failed','suppressed') NOT NULL DEFAULT 'queued';

ALTER TABLE notifications
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_notifications_brand_status (brand_id, status),
    ADD CONSTRAINT fk_notifications_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT;

UPDATE notifications SET brand_id=1 WHERE brand_id IS NULL;

ALTER TABLE notifications MODIFY COLUMN brand_id INT UNSIGNED NOT NULL;

ALTER TABLE providers
    ADD COLUMN marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER consent_recorded,
    ADD COLUMN marketing_consented_at DATETIME NULL AFTER marketing_opt_in,
    ADD COLUMN marketing_consent_source VARCHAR(80) NULL AFTER marketing_consented_at,
    ADD KEY idx_provider_marketing_consent (status, marketing_opt_in);
