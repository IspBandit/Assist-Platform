-- =====================================================================
-- 015 Auto-matching & dispatch (automated request -> provider matching)
--
-- ADDITIVE ONLY. Adds columns to existing tables plus one lightweight,
-- append-only log table. It never drops or rewrites existing data, so it is
-- safe to apply to the live database and is reversible by dropping the added
-- columns / table (see docs/auto-matching-implementation.md "Rollback").
--
-- All automated behaviour is gated by the `auto_matching` feature flag, so
-- applying this migration changes nothing until that flag is switched on.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Per-match automation metadata.
-- ---------------------------------------------------------------------
ALTER TABLE service_request_matches
    ADD COLUMN auto_invited TINYINT(1) NOT NULL DEFAULT 0 AFTER matched_by,
    ADD COLUMN invited_at DATETIME NULL AFTER auto_invited,
    ADD COLUMN match_reasons VARCHAR(500) NULL AFTER match_score,
    ADD COLUMN released_at DATETIME NULL AFTER contact_released,
    ADD COLUMN release_reason VARCHAR(40) NULL AFTER released_at;

-- ---------------------------------------------------------------------
-- Per-request automation state.
--   pending        -> awaiting an automated matching pass
--   done           -> auto-match ran and invited provider(s)
--   fallback_admin -> no suitable provider; needs manual attention
--   locked         -> enough providers given contact; no more auto invites
--   off            -> automation explicitly paused for this request
-- ---------------------------------------------------------------------
ALTER TABLE service_requests
    ADD COLUMN auto_match_state ENUM('pending','done','fallback_admin','locked','off') NOT NULL DEFAULT 'pending' AFTER status,
    ADD COLUMN auto_matched_at DATETIME NULL AFTER auto_match_state,
    ADD COLUMN interested_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER auto_matched_at;

ALTER TABLE service_requests
    ADD KEY idx_sr_auto_state (auto_match_state, status);

-- ---------------------------------------------------------------------
-- Provider automation preferences.
-- ---------------------------------------------------------------------
ALTER TABLE providers
    ADD COLUMN auto_invite_opt_out TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified,
    ADD COLUMN notify_channel ENUM('email','sms','both') NOT NULL DEFAULT 'email' AFTER auto_invite_opt_out;

-- ---------------------------------------------------------------------
-- Append-only audit of automated matching actions. No foreign keys
-- (consistent with the analytics tables) so a write can never be blocked.
-- ---------------------------------------------------------------------
CREATE TABLE auto_match_log (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id  INT UNSIGNED NOT NULL,
    provider_id INT UNSIGNED NULL,
    action      VARCHAR(40) NOT NULL,
    detail      VARCHAR(255) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_aml_request (request_id),
    KEY idx_aml_action (action),
    KEY idx_aml_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
