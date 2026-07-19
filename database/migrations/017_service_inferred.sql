-- =====================================================================
-- 017 Inferred (possible-match) provider services
--
-- ADDITIVE ONLY. Lets a provider be linked to a service as a "possible match"
-- (inferred from its trade) rather than an explicit, business-confirmed service.
-- Existing rows default to is_inferred = 0 (direct match), preserving behaviour.
-- =====================================================================

ALTER TABLE provider_services
    ADD COLUMN is_inferred TINYINT(1) NOT NULL DEFAULT 0 AFTER category_id,
    ADD KEY idx_ps_inferred (is_inferred);
