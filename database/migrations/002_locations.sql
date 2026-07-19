-- =====================================================================
-- 002 Location hierarchy: country > state > region > town > postcode
-- Designed for national expansion. No state is hardcoded in logic.
-- =====================================================================

CREATE TABLE countries (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name       VARCHAR(100) NOT NULL,
    slug       VARCHAR(100) NOT NULL,
    iso_code   CHAR(2) NOT NULL DEFAULT 'AU',
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_countries_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE states (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    country_id   INT UNSIGNED NOT NULL,
    name         VARCHAR(100) NOT NULL,
    slug         VARCHAR(100) NOT NULL,
    abbreviation VARCHAR(10) NULL,
    is_active    TINYINT(1) NOT NULL DEFAULT 0,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_states_slug (slug),
    KEY idx_states_country (country_id),
    KEY idx_states_active (is_active),
    CONSTRAINT fk_states_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    state_id    INT UNSIGNED NOT NULL,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(120) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    public_content MEDIUMTEXT NULL,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regions_state_slug (state_id, slug),
    KEY idx_regions_active (is_active),
    CONSTRAINT fk_regions_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE towns (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    state_id    INT UNSIGNED NOT NULL,
    region_id   INT UNSIGNED NULL,
    name        VARCHAR(150) NOT NULL,
    slug        VARCHAR(150) NOT NULL,
    primary_postcode VARCHAR(10) NULL,
    latitude    DECIMAL(10,7) NULL,
    longitude   DECIMAL(10,7) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_launch_town TINYINT(1) NOT NULL DEFAULT 0,
    public_content MEDIUMTEXT NULL,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    noindex     TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_towns_state_slug (state_id, slug),
    KEY idx_towns_region (region_id),
    KEY idx_towns_active (is_active),
    KEY idx_towns_launch (is_launch_town),
    KEY idx_towns_geo (latitude, longitude),
    CONSTRAINT fk_towns_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE,
    CONSTRAINT fk_towns_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE postcodes (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code       VARCHAR(10) NOT NULL,
    town_id    INT UNSIGNED NULL,
    state_id   INT UNSIGNED NOT NULL,
    latitude   DECIMAL(10,7) NULL,
    longitude  DECIMAL(10,7) NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_postcodes_code (code),
    KEY idx_postcodes_town (town_id),
    CONSTRAINT fk_postcodes_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE,
    CONSTRAINT fk_postcodes_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE town_neighbours (
    town_id          INT UNSIGNED NOT NULL,
    neighbour_town_id INT UNSIGNED NOT NULL,
    distance_km      DECIMAL(7,2) NULL,
    PRIMARY KEY (town_id, neighbour_town_id),
    KEY idx_tn_neighbour (neighbour_town_id),
    CONSTRAINT fk_tn_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE,
    CONSTRAINT fk_tn_neighbour FOREIGN KEY (neighbour_town_id) REFERENCES towns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
