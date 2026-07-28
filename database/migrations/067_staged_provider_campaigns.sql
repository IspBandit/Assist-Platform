-- CORE-005: fail-closed, reviewed and rate-limited marketing broadcasts.

ALTER TABLE notifications
    ADD COLUMN delivery_stage ENUM('draft','test','pilot','daily_50','daily_100','complete') NOT NULL DEFAULT 'draft' AFTER status,
    ADD COLUMN last_batch_at DATETIME NULL AFTER recipient_count,
    ADD COLUMN stage_reviewed_at DATETIME NULL AFTER last_batch_at,
    ADD COLUMN stage_reviewed_by INT UNSIGNED NULL AFTER stage_reviewed_at,
    ADD KEY idx_notifications_delivery_stage (brand_id, delivery_stage),
    ADD CONSTRAINT fk_notifications_stage_reviewer FOREIGN KEY (stage_reviewed_by) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE email_queue
    ADD COLUMN notification_id INT UNSIGNED NULL AFTER brand_id,
    ADD KEY idx_email_queue_notification (notification_id, status),
    ADD CONSTRAINT fk_email_queue_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE SET NULL;

ALTER TABLE notification_recipients
    ADD COLUMN queue_id BIGINT UNSIGNED NULL AFTER notification_id,
    ADD COLUMN delivery_stage ENUM('pilot','daily_50','daily_100') NOT NULL DEFAULT 'pilot' AFTER status,
    ADD UNIQUE KEY uq_notification_recipient_email (notification_id, email),
    ADD KEY idx_notification_recipient_stage (notification_id, delivery_stage, created_at),
    ADD CONSTRAINT fk_notification_recipient_queue FOREIGN KEY (queue_id) REFERENCES email_queue (id) ON DELETE SET NULL;

CREATE TABLE notification_test_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id INT UNSIGNED NOT NULL,
    queue_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    queued_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notification_test_delivery (notification_id, created_at),
    CONSTRAINT fk_notification_test_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
    CONSTRAINT fk_notification_test_queue FOREIGN KEY (queue_id) REFERENCES email_queue (id) ON DELETE SET NULL,
    CONSTRAINT fk_notification_test_user FOREIGN KEY (queued_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Legacy scheduled broadcasts must be reviewed under the new staged workflow.
UPDATE notifications SET status='draft', scheduled_at=NULL, updated_at=NOW() WHERE status='scheduled';
