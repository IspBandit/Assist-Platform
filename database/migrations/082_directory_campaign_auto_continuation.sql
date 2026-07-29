-- Explicit, fail-closed continuation for reviewed factual directory campaigns.
-- The switch defaults off and is unavailable to marketing campaigns.

ALTER TABLE notifications
    ADD COLUMN auto_continue_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER stage_reviewed_by,
    ADD COLUMN auto_continue_enabled_at DATETIME NULL AFTER auto_continue_enabled,
    ADD COLUMN auto_continue_enabled_by INT UNSIGNED NULL AFTER auto_continue_enabled_at,
    ADD COLUMN auto_continue_next_at DATETIME NULL AFTER auto_continue_enabled_by,
    ADD COLUMN auto_continue_last_run_at DATETIME NULL AFTER auto_continue_next_at,
    ADD COLUMN auto_continue_last_error VARCHAR(500) NULL AFTER auto_continue_last_run_at,
    ADD KEY idx_notifications_auto_continue (auto_continue_enabled, campaign_type, status, auto_continue_next_at),
    ADD CONSTRAINT fk_notifications_auto_continue_user FOREIGN KEY (auto_continue_enabled_by) REFERENCES users (id) ON DELETE SET NULL;
