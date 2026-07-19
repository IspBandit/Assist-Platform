-- =====================================================================
-- 006 Caravan park partners
-- =====================================================================

CREATE TABLE caravan_parks (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(190) NOT NULL,
    slug          VARCHAR(200) NOT NULL,
    address       VARCHAR(255) NULL,
    town_id       INT UNSIGNED NULL,
    region_id     INT UNSIGNED NULL,
    state_id      INT UNSIGNED NULL,
    phone         VARCHAR(40) NULL,
    email         VARCHAR(190) NULL,
    website       VARCHAR(255) NULL,
    facebook_url  VARCHAR(255) NULL,
    description   MEDIUMTEXT NULL,
    number_of_sites INT UNSIGNED NULL,
    logo_path     VARCHAR(255) NULL,
    guest_request_contact VARCHAR(190) NULL,
    public_page_enabled TINYINT(1) NOT NULL DEFAULT 0,
    status        ENUM('draft','pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    is_demo       TINYINT(1) NOT NULL DEFAULT 0,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    deleted_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_parks_slug (slug),
    KEY idx_parks_town (town_id),
    KEY idx_parks_status (status),
    CONSTRAINT fk_parks_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_parks_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_parks_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caravan_park_users (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id   INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    role      VARCHAR(60) NOT NULL DEFAULT 'manager',
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cpu (park_id, user_id),
    KEY idx_cpu_user (user_id),
    CONSTRAINT fk_cpu_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE,
    CONSTRAINT fk_cpu_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caravan_park_documents (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id     INT UNSIGNED NOT NULL,
    doc_type    VARCHAR(80) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NULL,
    mime_type   VARCHAR(120) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cpd_park (park_id),
    CONSTRAINT fk_cpd_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE caravan_park_service_day_requests (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id      INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NULL,
    preferred_dates VARCHAR(255) NULL,
    category_id  INT UNSIGNED NULL,
    notes        TEXT NULL,
    status       ENUM('open','reviewing','arranged','declined','completed') NOT NULL DEFAULT 'open',
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cpsdr_park (park_id),
    CONSTRAINT fk_cpsdr_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE,
    CONSTRAINT fk_cpsdr_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_cpsdr_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
