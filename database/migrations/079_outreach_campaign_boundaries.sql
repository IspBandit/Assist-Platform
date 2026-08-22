-- Separate factual directory-accuracy notices from consent-gated marketing.
-- The application fixes factual notice content server-side and records the
-- compliance basis used for every queued recipient.

ALTER TABLE notifications
    ADD COLUMN campaign_type ENUM('general_marketing','provider_marketing','directory_accuracy')
        NOT NULL DEFAULT 'general_marketing' AFTER channel,
    ADD KEY idx_notifications_campaign_type (brand_id, campaign_type, status);

UPDATE notifications
SET campaign_type='provider_marketing'
WHERE audience_type IN ('providers','provider_category');

ALTER TABLE email_queue
    MODIFY COLUMN message_type ENUM('transactional','marketing','directory_accuracy')
        NOT NULL DEFAULT 'transactional';

ALTER TABLE email_suppressions
    MODIFY COLUMN reason ENUM('marketing_opt_out','directory_notice_opt_out','hard_bounce','complaint','admin') NOT NULL,
    MODIFY COLUMN scope ENUM('marketing','directory_accuracy','all') NOT NULL DEFAULT 'marketing';

ALTER TABLE notification_recipients
    ADD COLUMN provider_id INT UNSIGNED NULL AFTER user_id,
    ADD COLUMN compliance_basis ENUM('marketing_consent','factual_directory_record') NULL AFTER delivery_stage,
    ADD COLUMN compliance_evidence VARCHAR(1000) NULL AFTER compliance_basis,
    ADD KEY idx_notification_recipient_provider (notification_id, provider_id),
    ADD CONSTRAINT fk_notification_recipient_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL;
