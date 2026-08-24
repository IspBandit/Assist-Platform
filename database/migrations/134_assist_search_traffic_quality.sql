-- Separate genuine Ask usage from recognised bots, synthetic checks and
-- same-brand navigations that did not retain the first-party session cookie.

ALTER TABLE assist_searches
    ADD COLUMN is_excluded TINYINT(1) NOT NULL DEFAULT 0 AFTER location_precision,
    ADD KEY idx_as_brand_excluded_created (brand_id, is_excluded, created_at);

-- Existing rows without an attributable non-bot session are not safe to use
-- in visitor, conversion or zero-result rates. Raw rows remain available for
-- operational diagnosis and retention processing.
UPDATE assist_searches a
LEFT JOIN tracking_sessions ts ON ts.id = a.session_id
SET a.is_excluded = 1
WHERE a.session_id IS NULL
   OR ts.id IS NULL
   OR ts.is_bot = 1
   OR ts.is_excluded = 1
   OR (
       ts.referral_source = 'internal'
       AND ts.user_id IS NULL
       AND ts.customer_id IS NULL
       AND ts.first_seen_at = ts.last_seen_at
   );
