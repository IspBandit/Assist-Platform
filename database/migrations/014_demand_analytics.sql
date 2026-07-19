-- =====================================================================
-- 014 Demand-to-outcome analytics (user needs, provider usage, demand)
--
-- ADDITIVE ONLY. This migration never alters or drops an existing table,
-- so it is safe to apply to the live database and is fully reversible by
-- dropping the tables it creates (see docs/DEMAND-ANALYTICS.md "Rollback").
--
-- It layers a measurable funnel on top of the existing service_requests /
-- service_request_matches model:
--   need -> search -> impression -> profile view -> contact action ->
--   request -> response -> quote -> selection -> booking -> completion.
--
-- High-volume tables (analytics_events, provider_search_results) follow the
-- existing page_views design: indexed columns but NO foreign keys, so inserts
-- stay fast on shared hosting and a missing reference can never block a write.
-- Relational, lower-volume tables use foreign keys consistent with the rest
-- of the schema.
-- =====================================================================

-- ---------------------------------------------------------------------
-- First-party, privacy-conscious session identity (anonymous + linked)
-- ---------------------------------------------------------------------
CREATE TABLE tracking_sessions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_token   CHAR(40) NOT NULL,
    user_id         INT UNSIGNED NULL,
    customer_id     INT UNSIGNED NULL,
    device_type     ENUM('mobile','tablet','desktop','bot','unknown') NOT NULL DEFAULT 'unknown',
    referral_source VARCHAR(120) NULL,
    user_agent_hash CHAR(64) NULL,
    is_bot          TINYINT(1) NOT NULL DEFAULT 0,
    is_excluded     TINYINT(1) NOT NULL DEFAULT 0,
    first_seen_at   DATETIME NULL,
    last_seen_at    DATETIME NULL,
    linked_at       DATETIME NULL,
    created_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ts_token (session_token),
    KEY idx_ts_user (user_id),
    KEY idx_ts_customer (customer_id),
    KEY idx_ts_last_seen (last_seen_at),
    CONSTRAINT fk_ts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_ts_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Granular funnel events (no FKs by design; mirrors page_views)
-- ---------------------------------------------------------------------
CREATE TABLE analytics_events (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name     VARCHAR(64) NOT NULL,
    session_id     BIGINT UNSIGNED NULL,
    user_id        INT UNSIGNED NULL,
    request_id     INT UNSIGNED NULL,
    provider_id    INT UNSIGNED NULL,
    category_id    INT UNSIGNED NULL,
    town_id        INT UNSIGNED NULL,
    region_id      INT UNSIGNED NULL,
    search_id      BIGINT UNSIGNED NULL,
    match_id       INT UNSIGNED NULL,
    outcome_id     BIGINT UNSIGNED NULL,
    previous_stage VARCHAR(64) NULL,
    route          VARCHAR(190) NULL,
    device_type    ENUM('mobile','tablet','desktop','bot','unknown') NOT NULL DEFAULT 'unknown',
    referral_source VARCHAR(120) NULL,
    metadata       JSON NULL,
    is_excluded    TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ae_event (event_name),
    KEY idx_ae_created (created_at),
    KEY idx_ae_session (session_id),
    KEY idx_ae_provider (provider_id),
    KEY idx_ae_request (request_id),
    KEY idx_ae_category (category_id),
    KEY idx_ae_town (town_id),
    KEY idx_ae_search (search_id),
    KEY idx_ae_event_created (event_name, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Provider search sessions (one per meaningful provider search)
-- ---------------------------------------------------------------------
CREATE TABLE provider_searches (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id        BIGINT UNSIGNED NULL,
    user_id           INT UNSIGNED NULL,
    request_id        INT UNSIGNED NULL,
    town_id           INT UNSIGNED NULL,
    region_id         INT UNSIGNED NULL,
    state_id          INT UNSIGNED NULL,
    postcode          VARCHAR(10) NULL,
    category_id       INT UNSIGNED NULL,
    subcategory_id    INT UNSIGNED NULL,
    urgency           ENUM('low','medium','high','urgent') NULL,
    service_type      ENUM('mobile','workshop','either','roadside','park_callout') NULL,
    radius_km         INT UNSIGNED NULL,
    result_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    exact_match_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    used_nearby_fallback TINYINT(1) NOT NULL DEFAULT 0,
    radius_expanded   TINYINT(1) NOT NULL DEFAULT 0,
    led_to_request    TINYINT(1) NOT NULL DEFAULT 0,
    is_excluded       TINYINT(1) NOT NULL DEFAULT 0,
    created_at        DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_psr_session (session_id),
    KEY idx_psr_town (town_id),
    KEY idx_psr_region (region_id),
    KEY idx_psr_category (category_id),
    KEY idx_psr_created (created_at),
    KEY idx_psr_town_category (town_id, category_id),
    CONSTRAINT fk_psr_session FOREIGN KEY (session_id) REFERENCES tracking_sessions (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE SET NULL,
    CONSTRAINT fk_psr_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Provider impressions per search (deduplicated by (search, provider))
-- ---------------------------------------------------------------------
CREATE TABLE provider_search_results (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    search_id     BIGINT UNSIGNED NOT NULL,
    provider_id   INT UNSIGNED NOT NULL,
    rank_position SMALLINT UNSIGNED NULL,
    match_score   DECIMAL(6,2) NULL,
    distance_km   DECIMAL(7,2) NULL,
    is_organic    TINYINT(1) NOT NULL DEFAULT 1,
    is_sponsored  TINYINT(1) NOT NULL DEFAULT 0,
    is_verified   TINYINT(1) NOT NULL DEFAULT 0,
    is_available  TINYINT(1) NOT NULL DEFAULT 1,
    service_model ENUM('mobile','workshop','both') NULL,
    category_id   INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pres_search_provider (search_id, provider_id),
    KEY idx_pres_provider (provider_id),
    KEY idx_pres_search (search_id),
    KEY idx_pres_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Attributable provider contact actions (phone/email/website/etc.)
-- ---------------------------------------------------------------------
CREATE TABLE provider_contact_actions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id  INT UNSIGNED NOT NULL,
    session_id   BIGINT UNSIGNED NULL,
    user_id      INT UNSIGNED NULL,
    request_id   INT UNSIGNED NULL,
    search_id    BIGINT UNSIGNED NULL,
    match_id     INT UNSIGNED NULL,
    action_type  ENUM('phone','email','website','directions','message','quote_request','assistance_request','booking_request') NOT NULL,
    source_route VARCHAR(190) NULL,
    is_excluded  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pca_provider (provider_id),
    KEY idx_pca_action (action_type),
    KEY idx_pca_created (created_at),
    KEY idx_pca_session (session_id),
    KEY idx_pca_provider_action (provider_id, action_type),
    CONSTRAINT fk_pca_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Service outcomes (the "providers actually used" record of truth)
-- One row per (request, provider). Confidence distinguishes a raw click
-- from a customer-/provider-/mutually-confirmed completed job.
-- ---------------------------------------------------------------------
CREATE TABLE service_outcomes (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id         INT UNSIGNED NULL,
    match_id           INT UNSIGNED NULL,
    provider_id        INT UNSIGNED NOT NULL,
    customer_id        INT UNSIGNED NULL,
    search_id          BIGINT UNSIGNED NULL,
    category_id        INT UNSIGNED NULL,
    town_id            INT UNSIGNED NULL,
    region_id          INT UNSIGNED NULL,
    status             ENUM('contacted','responded','quoted','selected','booked','in_progress','completed','cancelled','unable_to_assist','outside_area','no_response','outcome_unknown') NOT NULL DEFAULT 'contacted',
    confidence         ENUM('inferred','contact_only','customer_reported','provider_reported','both_confirmed','admin_verified') NOT NULL DEFAULT 'inferred',
    used_via_vanassist TINYINT(1) NULL,
    customer_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    provider_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    admin_confirmed    TINYINT(1) NOT NULL DEFAULT 0,
    is_repeat_provider TINYINT(1) NOT NULL DEFAULT 0,
    issue_resolved     TINYINT(1) NULL,
    would_use_again    TINYINT(1) NULL,
    satisfaction_rating TINYINT UNSIGNED NULL,
    value_band         ENUM('under_100','100_249','250_499','500_999','1000_2499','2500_4999','5000_plus','prefer_not_say') NULL,
    work_type          VARCHAR(190) NULL,
    cancellation_reason VARCHAR(255) NULL,
    notes              TEXT NULL,
    contacted_at       DATETIME NULL,
    responded_at       DATETIME NULL,
    selected_at        DATETIME NULL,
    booked_at          DATETIME NULL,
    completed_at       DATETIME NULL,
    cancelled_at       DATETIME NULL,
    is_excluded        TINYINT(1) NOT NULL DEFAULT 0,
    created_at         DATETIME NULL,
    updated_at         DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_so_request_provider (request_id, provider_id),
    KEY idx_so_provider (provider_id),
    KEY idx_so_customer (customer_id),
    KEY idx_so_status (status),
    KEY idx_so_confidence (confidence),
    KEY idx_so_completed (completed_at),
    KEY idx_so_provider_completed (provider_id, completed_at),
    KEY idx_so_category (category_id),
    KEY idx_so_town (town_id),
    CONSTRAINT fk_so_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_so_match FOREIGN KEY (match_id) REFERENCES service_request_matches (id) ON DELETE SET NULL,
    CONSTRAINT fk_so_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_so_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_so_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_so_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_so_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Outcome confirmations audit trail (customer / provider / admin)
-- ---------------------------------------------------------------------
CREATE TABLE outcome_confirmations (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    outcome_id       BIGINT UNSIGNED NULL,
    request_id       INT UNSIGNED NULL,
    provider_id      INT UNSIGNED NULL,
    confirmed_by_role ENUM('customer','provider','admin','system') NOT NULL,
    confirmed_by_user_id INT UNSIGNED NULL,
    confirmation_type VARCHAR(60) NOT NULL,
    detail           JSON NULL,
    created_at       DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_oc_outcome (outcome_id),
    KEY idx_oc_request (request_id),
    KEY idx_oc_provider (provider_id),
    CONSTRAINT fk_oc_outcome FOREIGN KEY (outcome_id) REFERENCES service_outcomes (id) ON DELETE CASCADE,
    CONSTRAINT fk_oc_user FOREIGN KEY (confirmed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Demand-gap feedback (why a user could not find a suitable provider)
-- ---------------------------------------------------------------------
CREATE TABLE demand_gap_feedback (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id  BIGINT UNSIGNED NULL,
    user_id     INT UNSIGNED NULL,
    request_id  INT UNSIGNED NULL,
    search_id   BIGINT UNSIGNED NULL,
    town_id     INT UNSIGNED NULL,
    region_id   INT UNSIGNED NULL,
    category_id INT UNSIGNED NULL,
    reason      ENUM('none_nearby','none_soon_enough','no_mobile','no_workshop','outside_area','wrong_category','could_not_assist','price','no_contact','no_response','licensing','found_elsewhere','other') NOT NULL,
    comment     VARCHAR(500) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dgf_town (town_id),
    KEY idx_dgf_region (region_id),
    KEY idx_dgf_category (category_id),
    KEY idx_dgf_reason (reason),
    KEY idx_dgf_created (created_at),
    CONSTRAINT fk_dgf_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_dgf_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_dgf_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_dgf_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Customer follow-up queue (cron-driven, email by default)
-- ---------------------------------------------------------------------
CREATE TABLE customer_followups (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id    INT UNSIGNED NOT NULL,
    customer_id   INT UNSIGNED NULL,
    outcome_id    BIGINT UNSIGNED NULL,
    followup_type ENUM('reached_provider','provider_responded','chose_provider','work_completed','feedback') NOT NULL,
    channel       ENUM('email','sms') NOT NULL DEFAULT 'email',
    scheduled_for DATETIME NOT NULL,
    sent_at       DATETIME NULL,
    responded_at  DATETIME NULL,
    status        ENUM('pending','sent','responded','skipped','cancelled') NOT NULL DEFAULT 'pending',
    token_hash    VARCHAR(255) NULL,
    attempts      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cf_request (request_id),
    KEY idx_cf_status (status),
    KEY idx_cf_scheduled (scheduled_for),
    KEY idx_cf_status_scheduled (status, scheduled_for),
    CONSTRAINT fk_cf_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_cf_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_cf_outcome FOREIGN KEY (outcome_id) REFERENCES service_outcomes (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Daily aggregate metrics per provider (dashboard performance)
-- ---------------------------------------------------------------------
CREATE TABLE provider_daily_metrics (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_date           DATE NOT NULL,
    provider_id           INT UNSIGNED NOT NULL,
    impressions           INT UNSIGNED NOT NULL DEFAULT 0,
    unique_impressions    INT UNSIGNED NOT NULL DEFAULT 0,
    profile_views         INT UNSIGNED NOT NULL DEFAULT 0,
    unique_profile_views  INT UNSIGNED NOT NULL DEFAULT 0,
    phone_clicks          INT UNSIGNED NOT NULL DEFAULT 0,
    email_clicks          INT UNSIGNED NOT NULL DEFAULT 0,
    website_clicks        INT UNSIGNED NOT NULL DEFAULT 0,
    directions_clicks     INT UNSIGNED NOT NULL DEFAULT 0,
    requests              INT UNSIGNED NOT NULL DEFAULT 0,
    responses             INT UNSIGNED NOT NULL DEFAULT 0,
    quotes                INT UNSIGNED NOT NULL DEFAULT 0,
    selections            INT UNSIGNED NOT NULL DEFAULT 0,
    bookings              INT UNSIGNED NOT NULL DEFAULT 0,
    completed_jobs        INT UNSIGNED NOT NULL DEFAULT 0,
    customer_confirmed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    mutually_confirmed_jobs INT UNSIGNED NOT NULL DEFAULT 0,
    cancellations         INT UNSIGNED NOT NULL DEFAULT 0,
    reviews               INT UNSIGNED NOT NULL DEFAULT 0,
    rating_total          INT UNSIGNED NOT NULL DEFAULT 0,
    response_time_total   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at            DATETIME NULL,
    updated_at            DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pdm_date_provider (metric_date, provider_id),
    KEY idx_pdm_provider (provider_id),
    KEY idx_pdm_provider_date (provider_id, metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Daily aggregate demand metrics by location + category
-- ---------------------------------------------------------------------
CREATE TABLE demand_daily_metrics (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_date       DATE NOT NULL,
    town_id           INT UNSIGNED NULL,
    region_id         INT UNSIGNED NULL,
    category_id       INT UNSIGNED NULL,
    searches          INT UNSIGNED NOT NULL DEFAULT 0,
    unique_sessions   INT UNSIGNED NOT NULL DEFAULT 0,
    requests          INT UNSIGNED NOT NULL DEFAULT 0,
    no_result_searches INT UNSIGNED NOT NULL DEFAULT 0,
    urgent_searches   INT UNSIGNED NOT NULL DEFAULT 0,
    provider_contacts INT UNSIGNED NOT NULL DEFAULT 0,
    confirmed_jobs    INT UNSIGNED NOT NULL DEFAULT 0,
    created_at        DATETIME NULL,
    updated_at        DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ddm_date (metric_date),
    KEY idx_ddm_town (town_id),
    KEY idx_ddm_region (region_id),
    KEY idx_ddm_category (category_id),
    KEY idx_ddm_date_town_cat (metric_date, town_id, category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pre-computed admin reporting snapshots (heavy dashboard payloads)
-- ---------------------------------------------------------------------
CREATE TABLE admin_reporting_snapshots (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    snapshot_key  VARCHAR(120) NOT NULL,
    snapshot_date DATE NOT NULL,
    payload       JSON NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ars_key_date (snapshot_key, snapshot_date),
    KEY idx_ars_key (snapshot_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
