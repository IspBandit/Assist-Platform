-- Brand-scoped first-party website analytics.
-- Existing rows pre-date reliable hostname attribution and remain NULL rather
-- than being guessed into a brand. New rows use the trusted current Brand id.

ALTER TABLE page_views
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD COLUMN session_id BIGINT UNSIGNED NULL AFTER brand_id,
    ADD COLUMN user_id INT UNSIGNED NULL AFTER session_id,
    ADD COLUMN device_type VARCHAR(16) NOT NULL DEFAULT 'unknown' AFTER referrer_source,
    ADD KEY idx_pv_brand_created (brand_id, created_at),
    ADD KEY idx_pv_brand_session (brand_id, session_id),
    ADD KEY idx_pv_brand_route (brand_id, route);

ALTER TABLE analytics_events
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_ae_brand_created (brand_id, created_at),
    ADD KEY idx_ae_brand_event_created (brand_id, event_name, created_at);

ALTER TABLE provider_searches
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_psr_brand_created (brand_id, created_at),
    ADD KEY idx_psr_brand_category_created (brand_id, category_id, created_at);

ALTER TABLE provider_contact_actions
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_pca_brand_created (brand_id, created_at),
    ADD KEY idx_pca_brand_action_created (brand_id, action_type, created_at);

ALTER TABLE demand_gap_feedback
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_dgf_brand_created (brand_id, created_at);

ALTER TABLE service_outcomes
    ADD COLUMN brand_id INT UNSIGNED NULL AFTER id,
    ADD KEY idx_so_brand_created (brand_id, created_at),
    ADD KEY idx_so_brand_provider_created (brand_id, provider_id, created_at);
