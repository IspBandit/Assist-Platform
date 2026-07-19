-- =====================================================================
-- 005 Providers, provider prospect CRM, services, areas, documents, etc.
-- =====================================================================

CREATE TABLE provider_prospects (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    business_name   VARCHAR(190) NOT NULL,
    contact_name    VARCHAR(150) NULL,
    base_town_id    INT UNSIGNED NULL,
    region_id       INT UNSIGNED NULL,
    phone           VARCHAR(40) NULL,
    email           VARCHAR(190) NULL,
    website         VARCHAR(255) NULL,
    facebook_url    VARCHAR(255) NULL,
    google_maps_url VARCHAR(500) NULL,
    services_observed VARCHAR(500) NULL,
    service_model   ENUM('mobile','workshop','both','unknown') NOT NULL DEFAULT 'unknown',
    towns_serviced  VARCHAR(500) NULL,
    source          ENUM('google','facebook','referral','caravan_park','club','other') NOT NULL DEFAULT 'other',
    outreach_status ENUM('not_contacted','attempted','contacted','interested','follow_up','invited','registered','declined','do_not_contact') NOT NULL DEFAULT 'not_contacted',
    last_contact_date DATE NULL,
    next_follow_up_date DATE NULL,
    notes           TEXT NULL,
    assigned_admin_id INT UNSIGNED NULL,
    provider_id     INT UNSIGNED NULL,
    consent_recorded TINYINT(1) NOT NULL DEFAULT 0,
    import_date     DATE NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    deleted_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pp_status (outreach_status),
    KEY idx_pp_town (base_town_id),
    KEY idx_pp_region (region_id),
    KEY idx_pp_assigned (assigned_admin_id),
    KEY idx_pp_email (email),
    CONSTRAINT fk_pp_town FOREIGN KEY (base_town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_pp_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_pp_admin FOREIGN KEY (assigned_admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_prospect_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    note_type   ENUM('call','email','meeting','note') NOT NULL DEFAULT 'note',
    body        TEXT NOT NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ppn_prospect (prospect_id),
    CONSTRAINT fk_ppn_prospect FOREIGN KEY (prospect_id) REFERENCES provider_prospects (id) ON DELETE CASCADE,
    CONSTRAINT fk_ppn_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE providers (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id            INT UNSIGNED NULL,
    business_name      VARCHAR(190) NOT NULL,
    slug               VARCHAR(200) NOT NULL,
    abn                VARCHAR(20) NULL,
    contact_name       VARCHAR(150) NULL,
    phone              VARCHAR(40) NULL,
    public_phone       VARCHAR(40) NULL,
    email              VARCHAR(190) NULL,
    public_email       VARCHAR(190) NULL,
    website            VARCHAR(255) NULL,
    facebook_url       VARCHAR(255) NULL,
    base_town_id       INT UNSIGNED NULL,
    region_id          INT UNSIGNED NULL,
    description        MEDIUMTEXT NULL,
    logo_path          VARCHAR(255) NULL,
    service_model      ENUM('mobile','workshop','both') NOT NULL DEFAULT 'mobile',
    max_travel_km      INT UNSIGNED NULL,
    min_jobs_for_run   TINYINT UNSIGNED NULL,
    min_value_for_run  DECIMAL(10,2) NULL,
    typical_notice_days SMALLINT UNSIGNED NULL,
    show_public_phone  TINYINT(1) NOT NULL DEFAULT 0,
    show_public_email  TINYINT(1) NOT NULL DEFAULT 0,
    status             ENUM('draft','pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    is_featured        TINYINT(1) NOT NULL DEFAULT 0,
    is_verified        TINYINT(1) NOT NULL DEFAULT 0,
    insurance_verified TINYINT(1) NOT NULL DEFAULT 0,
    is_demo            TINYINT(1) NOT NULL DEFAULT 0,
    -- Future billing fields (inactive during free launch)
    plan               ENUM('founding_free','standard_free','future_paid','custom') NOT NULL DEFAULT 'founding_free',
    billing_status     VARCHAR(40) NOT NULL DEFAULT 'none',
    commission_rate    DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    trial_start        DATE NULL,
    trial_end          DATE NULL,
    approved_at        DATETIME NULL,
    seo_title          VARCHAR(190) NULL,
    seo_description    VARCHAR(320) NULL,
    created_at         DATETIME NULL,
    updated_at         DATETIME NULL,
    deleted_at         DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_providers_slug (slug),
    KEY idx_providers_user (user_id),
    KEY idx_providers_town (base_town_id),
    KEY idx_providers_region (region_id),
    KEY idx_providers_status (status),
    KEY idx_providers_verified (is_verified),
    CONSTRAINT fk_providers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_providers_town FOREIGN KEY (base_town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_providers_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_invitations (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    prospect_id INT UNSIGNED NULL,
    provider_id INT UNSIGNED NULL,
    email       VARCHAR(190) NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    accepted_at DATETIME NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pi_email (email),
    KEY idx_pi_prospect (prospect_id),
    CONSTRAINT fk_pi_prospect FOREIGN KEY (prospect_id) REFERENCES provider_prospects (id) ON DELETE SET NULL,
    CONSTRAINT fk_pi_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_contacts (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    name        VARCHAR(150) NULL,
    role        VARCHAR(100) NULL,
    phone       VARCHAR(40) NULL,
    email       VARCHAR(190) NULL,
    is_primary  TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pc_provider (provider_id),
    CONSTRAINT fk_pc_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_services (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    notes       VARCHAR(500) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ps (provider_id, category_id),
    KEY idx_ps_category (category_id),
    CONSTRAINT fk_ps_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_ps_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_service_areas (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    area_type   ENUM('town','region','state','radius','corridor','park') NOT NULL,
    town_id     INT UNSIGNED NULL,
    region_id   INT UNSIGNED NULL,
    state_id    INT UNSIGNED NULL,
    radius_km   INT UNSIGNED NULL,
    label       VARCHAR(190) NULL,
    only_if_demand TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_psa_provider (provider_id),
    KEY idx_psa_town (town_id),
    KEY idx_psa_region (region_id),
    KEY idx_psa_state (state_id),
    CONSTRAINT fk_psa_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_psa_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE,
    CONSTRAINT fk_psa_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE CASCADE,
    CONSTRAINT fk_psa_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_documents (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id   INT UNSIGNED NOT NULL,
    doc_type      VARCHAR(80) NOT NULL,
    original_name VARCHAR(255) NULL,
    stored_name   VARCHAR(255) NOT NULL,
    mime_type     VARCHAR(120) NULL,
    file_size     INT UNSIGNED NULL,
    verification_status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    verification_notes VARCHAR(500) NULL,
    verified_by   INT UNSIGNED NULL,
    verified_at   DATETIME NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pd_provider (provider_id),
    KEY idx_pd_status (verification_status),
    CONSTRAINT fk_pd_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pd_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_licences (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id      INT UNSIGNED NOT NULL,
    licence_type     VARCHAR(120) NOT NULL,
    licence_number   VARCHAR(120) NULL,
    issuing_authority VARCHAR(150) NULL,
    issue_date       DATE NULL,
    expiry_date      DATE NULL,
    document_id      INT UNSIGNED NULL,
    verification_status ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
    verification_notes VARCHAR(500) NULL,
    display_publicly TINYINT(1) NOT NULL DEFAULT 0,
    created_at       DATETIME NULL,
    updated_at       DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pl_provider (provider_id),
    KEY idx_pl_expiry (expiry_date),
    CONSTRAINT fk_pl_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pl_document FOREIGN KEY (document_id) REFERENCES provider_documents (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_availability (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    start_date  DATE NOT NULL,
    end_date    DATE NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    notes       VARCHAR(255) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pa_provider (provider_id),
    KEY idx_pa_dates (start_date, end_date),
    CONSTRAINT fk_pa_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_verifications (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id  INT UNSIGNED NOT NULL,
    verification_type VARCHAR(80) NOT NULL,
    status       ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
    verified_by  INT UNSIGNED NULL,
    notes        VARCHAR(500) NULL,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pv_provider (provider_id),
    CONSTRAINT fk_pv_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pv_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_internal_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    admin_id    INT UNSIGNED NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pin_provider (provider_id),
    CONSTRAINT fk_pin_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pin_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
