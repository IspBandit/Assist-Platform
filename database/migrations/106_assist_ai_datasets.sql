-- Assist AI-5: dataset routing feature flag (staged DATA-006 candidates).
-- No new tables — reuses data_source_import_candidates. Paid Places stay admin-only.

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES (
    'assist_ai_datasets',
    0,
    'Ask VanAssist dataset routing: show staged DATA-006 candidates with provenance (AI-5, off by default).',
    NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
