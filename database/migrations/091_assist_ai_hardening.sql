-- Assist AI-7: retention indexes + scheduled task registration.
-- No new domain tables. Reuses assist_searches / ai_* / knowledge_gap_events.
-- Index adds are best-effort via separate statements; IGNORE duplicate-key on re-run
-- is handled by operators repairing dirty state. Prefer CREATE INDEX IF NOT EXISTS
-- is not portable on older MariaDB — use information_schema-safe single INSERTs here.

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES (
    'ai_retention',
    'Purge Assist AI raw search/usage/cache/gap-event rows past retention windows',
    'never'
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
