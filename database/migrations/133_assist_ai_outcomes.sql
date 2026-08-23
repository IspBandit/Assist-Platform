-- Stage 1 Ask outcome explanation; search and ranking remain authoritative.
INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES ('assist_ai_outcomes', 0, 'Ask VanAssist deterministic understanding, fit evidence and safest-next-action layer.', NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
