-- Preserve brand context for queued/background email processing.

ALTER TABLE email_queue
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_email_queue_brand_status (brand_id, status),
    ADD CONSTRAINT fk_email_queue_brand
        FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT;

UPDATE email_queue SET brand_id = 1 WHERE brand_id IS NULL;

ALTER TABLE email_queue
    MODIFY COLUMN brand_id INT UNSIGNED NOT NULL;
