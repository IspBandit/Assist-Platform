-- =====================================================================
-- 016 Unclaimed provider listings (research-imported businesses)
--
-- ADDITIVE ONLY. Lets real businesses sourced from public research be shown
-- as clearly-marked "unclaimed" directory listings that the business can later
-- claim. Reversible by dropping the added columns.
--
-- Unclaimed listings:
--   * are active in the directory but flagged is_unclaimed = 1 and is_verified = 0
--   * only ever surface contact details the business already publishes publicly
--   * carry provenance (source_note / source_url) for transparency
--   * can be claimed via claim_token -> converts to a normal pending provider
-- =====================================================================

ALTER TABLE providers
    ADD COLUMN is_unclaimed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_verified,
    ADD COLUMN claimed_at DATETIME NULL AFTER is_unclaimed,
    ADD COLUMN claim_token VARCHAR(64) NULL AFTER claimed_at,
    ADD COLUMN source_note VARCHAR(190) NULL AFTER claim_token,
    ADD COLUMN source_url VARCHAR(500) NULL AFTER source_note;

ALTER TABLE providers
    ADD KEY idx_providers_unclaimed (is_unclaimed);
