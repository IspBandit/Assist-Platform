-- Demand-driven Google Places rescue on zero/weak provider searches (ADR 0042).
-- Off by default until Places credentials, budget and Quality Gate evidence.

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES (
    'provider_places_rescue',
    0,
    'When on, zero/weak VanAssist provider searches may call Google Places (budget-gated), show labelled public-source results, and auto-create unclaimed listings (ADR 0042).',
    NOW()
)
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = NOW();
