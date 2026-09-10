-- Bind provider claim links and admin review to the brand that issued them.

ALTER TABLE provider_claim_tokens
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER provider_id,
    ADD KEY idx_pct_brand_status (brand_id, used_at, expires_at),
    ADD CONSTRAINT fk_pct_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT;

-- The issuing brand cannot be inferred safely for historical links. Expire any
-- unused legacy link, retain its audit record under VanAssist, and require a
-- correctly scoped replacement invitation.
UPDATE provider_claim_tokens
SET expires_at = CASE WHEN used_at IS NULL THEN LEAST(expires_at, NOW()) ELSE expires_at END,
    brand_id = 1
WHERE brand_id IS NULL;

ALTER TABLE provider_claim_tokens
    MODIFY brand_id INT UNSIGNED NOT NULL;
