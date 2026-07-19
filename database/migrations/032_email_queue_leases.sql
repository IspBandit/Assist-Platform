-- Atomic email queue claims, worker leases, and delayed retry scheduling.

ALTER TABLE email_queue
    ADD COLUMN lease_token CHAR(32) NULL AFTER status,
    ADD COLUMN leased_until DATETIME NULL AFTER lease_token,
    ADD COLUMN next_attempt_at DATETIME NULL AFTER last_attempt_at,
    ADD KEY idx_eq_claim (status, next_attempt_at, leased_until, scheduled_at);
