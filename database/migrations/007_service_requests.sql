-- =====================================================================
-- 007 Service requests (the core customer demand record)
-- =====================================================================

CREATE TABLE service_requests (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference         VARCHAR(20) NOT NULL,
    customer_id       INT UNSIGNED NULL,
    park_id           INT UNSIGNED NULL,

    -- Contact (Step 6)
    contact_name      VARCHAR(150) NOT NULL,
    contact_email     VARCHAR(190) NOT NULL,
    contact_phone     VARCHAR(40) NULL,
    preferred_contact ENUM('email','phone','either') NOT NULL DEFAULT 'either',

    -- Location (Step 1)
    town_id           INT UNSIGNED NULL,
    region_id         INT UNSIGNED NULL,
    state_id          INT UNSIGNED NULL,
    postcode          VARCHAR(10) NULL,
    location_label    VARCHAR(190) NULL,
    private_address   VARCHAR(255) NULL,
    max_distance_km   INT UNSIGNED NULL,
    mobile_preferred  TINYINT(1) NOT NULL DEFAULT 1,
    workshop_acceptable TINYINT(1) NOT NULL DEFAULT 1,

    -- Vehicle (Step 2)
    vehicle_type      ENUM('caravan','camper_trailer','motorhome','campervan','fifth_wheeler','other') NULL,
    vehicle_make      VARCHAR(120) NULL,
    vehicle_model     VARCHAR(120) NULL,
    vehicle_year      SMALLINT UNSIGNED NULL,
    vehicle_registration VARCHAR(40) NULL,
    vehicle_length_m  DECIMAL(5,2) NULL,
    is_towable        TINYINT(1) NULL,
    is_usable         TINYINT(1) NULL,

    -- Service (Step 3) primary category
    primary_category_id INT UNSIGNED NULL,

    -- Fault details (Step 4)
    title             VARCHAR(190) NOT NULL,
    description       MEDIUMTEXT NULL,
    issue_started     VARCHAR(120) NULL,
    error_code        VARCHAR(120) NULL,
    appliance_brand   VARCHAR(120) NULL,
    appliance_model   VARCHAR(120) NULL,
    appliance_serial  VARCHAR(120) NULL,
    previous_attempt  TEXT NULL,
    safety_concern    TINYINT(1) NOT NULL DEFAULT 0,
    urgency           ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    travel_deadline   DATE NULL,
    preferred_dates   VARCHAR(255) NULL,
    flexible_dates    TINYINT(1) NOT NULL DEFAULT 1,
    willing_group_day TINYINT(1) NOT NULL DEFAULT 0,

    -- Consent / lifecycle
    consent_terms     TINYINT(1) NOT NULL DEFAULT 0,
    consent_privacy   TINYINT(1) NOT NULL DEFAULT 0,
    consent_share     TINYINT(1) NOT NULL DEFAULT 0,
    marketing_opt_in  TINYINT(1) NOT NULL DEFAULT 0,

    status            ENUM('draft','awaiting_verification','pending_moderation','open','matching',
                           'provider_interested','information_requested','offered_appointment',
                           'added_to_run','accepted','in_progress','completed','closed',
                           'cancelled','rejected','expired') NOT NULL DEFAULT 'draft',
    is_spam           TINYINT(1) NOT NULL DEFAULT 0,
    is_demo           TINYINT(1) NOT NULL DEFAULT 0,
    verify_token_hash VARCHAR(255) NULL,
    verified_at       DATETIME NULL,
    source            VARCHAR(60) NOT NULL DEFAULT 'web',
    ip_address        VARCHAR(45) NULL,

    created_at        DATETIME NULL,
    updated_at        DATETIME NULL,
    deleted_at        DATETIME NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_reference (reference),
    KEY idx_sr_customer (customer_id),
    KEY idx_sr_town (town_id),
    KEY idx_sr_region (region_id),
    KEY idx_sr_category (primary_category_id),
    KEY idx_sr_status (status),
    KEY idx_sr_park (park_id),
    KEY idx_sr_created (created_at),
    CONSTRAINT fk_sr_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_category FOREIGN KEY (primary_category_id) REFERENCES service_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_sr_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_categories (
    request_id  INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    is_primary  TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (request_id, category_id),
    KEY idx_src_category (category_id),
    CONSTRAINT fk_src_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_src_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_images (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id   INT UNSIGNED NOT NULL,
    stored_name  VARCHAR(255) NOT NULL,
    thumb_name   VARCHAR(255) NULL,
    mime_type    VARCHAR(80) NULL,
    file_size    INT UNSIGNED NULL,
    width        SMALLINT UNSIGNED NULL,
    height       SMALLINT UNSIGNED NULL,
    sort_order   SMALLINT NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sri_request (request_id),
    CONSTRAINT fk_sri_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_status_history (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id  INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status   VARCHAR(40) NOT NULL,
    changed_by  INT UNSIGNED NULL,
    note        VARCHAR(500) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_srsh_request (request_id),
    CONSTRAINT fk_srsh_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_srsh_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_notes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id  INT UNSIGNED NOT NULL,
    author_id   INT UNSIGNED NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 1,
    body        TEXT NOT NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_srn_request (request_id),
    CONSTRAINT fk_srn_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_srn_author FOREIGN KEY (author_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_matches (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id   INT UNSIGNED NOT NULL,
    provider_id  INT UNSIGNED NOT NULL,
    matched_by   INT UNSIGNED NULL,
    match_score  DECIMAL(6,2) NULL,
    status       ENUM('suggested','invited','interested','declined','more_info','offered','accepted','unsuitable','reported','withdrawn') NOT NULL DEFAULT 'suggested',
    contact_released TINYINT(1) NOT NULL DEFAULT 0,
    admin_note   VARCHAR(500) NULL,
    provider_note VARCHAR(500) NULL,
    run_id       INT UNSIGNED NULL,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_srm (request_id, provider_id),
    KEY idx_srm_provider (provider_id),
    KEY idx_srm_status (status),
    CONSTRAINT fk_srm_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_srm_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_srm_matcher FOREIGN KEY (matched_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_messages (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id   INT UNSIGNED NOT NULL,
    match_id     INT UNSIGNED NULL,
    sender_id    INT UNSIGNED NULL,
    sender_role  VARCHAR(40) NULL,
    body         TEXT NOT NULL,
    read_at      DATETIME NULL,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_srmsg_request (request_id),
    KEY idx_srmsg_match (match_id),
    CONSTRAINT fk_srmsg_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_srmsg_match FOREIGN KEY (match_id) REFERENCES service_request_matches (id) ON DELETE SET NULL,
    CONSTRAINT fk_srmsg_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
