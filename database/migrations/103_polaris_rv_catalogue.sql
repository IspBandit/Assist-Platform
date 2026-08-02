-- Project Polaris: brand registration and new-RV catalogue foundation (POL-001).
-- Additive only. Demo fixtures are labelled is_demo=1 and must not ship as production truth.

INSERT INTO brands (
    id, brand_key, name, legal_name, status, default_locale,
    default_currency, storage_namespace, created_at
) VALUES (
    5, 'polaris', 'Polaris', 'Polaris', 'private', 'en-AU', 'AUD', 'polaris', NOW()
);

INSERT INTO brand_domains (brand_id, hostname, environment, is_primary, created_at)
VALUES (5, 'polaris.test', 'local', 1, NOW());

INSERT INTO permissions (slug, name, perm_group, description, created_at) VALUES
('polaris.manage', 'Manage Polaris RV catalogue', 'polaris', 'Manufacturers, models, lifecycle and review queues', NOW()),
('polaris.review', 'Review Polaris extracted data', 'polaris', 'Approve, edit or reject draft specifications', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), perm_group = VALUES(perm_group);

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('super-administrator', 'administrator', 'platform-administrator', 'brand-administrator', 'editor')
  AND p.slug IN ('polaris.manage', 'polaris.review');

CREATE TABLE polaris_manufacturers (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    legal_name              VARCHAR(190) NOT NULL,
    trading_name            VARCHAR(190) NOT NULL,
    slug                    VARCHAR(120) NOT NULL,
    abn                     VARCHAR(20) NULL,
    country_code            CHAR(2) NOT NULL DEFAULT 'AU',
    website_url             VARCHAR(500) NULL,
    description             TEXT NULL,
    manufacturing_location  VARCHAR(190) NULL,
    australian_made_claim   VARCHAR(500) NULL,
    warranty_summary        TEXT NULL,
    claim_status            ENUM('unclaimed','pending','claimed','rejected') NOT NULL DEFAULT 'unclaimed',
    verification_status     ENUM('unverified','pending','verified','disputed') NOT NULL DEFAULT 'unverified',
    publication_status      ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status        ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    data_quality_score      TINYINT UNSIGNED NULL,
    is_demo                 TINYINT(1) NOT NULL DEFAULT 0,
    last_reviewed_at        DATETIME NULL,
    archival_reason         VARCHAR(500) NULL,
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_mfr_brand_slug (brand_id, slug),
    KEY idx_polaris_mfr_lifecycle (lifecycle_status, publication_status),
    KEY idx_polaris_mfr_claim (claim_status),
    CONSTRAINT fk_polaris_mfr_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_rv_models (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    manufacturer_id         INT UNSIGNED NOT NULL,
    name                    VARCHAR(190) NOT NULL,
    slug                    VARCHAR(120) NOT NULL,
    category                ENUM(
        'caravan','hybrid_caravan','camper_trailer','motorhome',
        'campervan','slide_on','other'
    ) NOT NULL,
    description             TEXT NULL,
    production_status       ENUM('current','upcoming','superseded','discontinued') NOT NULL DEFAULT 'current',
    first_model_year        SMALLINT UNSIGNED NULL,
    final_model_year        SMALLINT UNSIGNED NULL,
    verification_status     ENUM('unverified','pending','verified','disputed') NOT NULL DEFAULT 'unverified',
    publication_status      ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status        ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    is_demo                 TINYINT(1) NOT NULL DEFAULT 0,
    last_reviewed_at        DATETIME NULL,
    archival_reason         VARCHAR(500) NULL,
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_model_mfr_slug (manufacturer_id, slug),
    KEY idx_polaris_model_brand_cat (brand_id, category, publication_status),
    KEY idx_polaris_model_lifecycle (lifecycle_status, production_status),
    CONSTRAINT fk_polaris_model_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_polaris_model_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_rv_model_years (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_id                INT UNSIGNED NOT NULL,
    model_year              SMALLINT UNSIGNED NOT NULL,
    announcement_date       DATE NULL,
    available_from          DATE NULL,
    superseded_at           DATE NULL,
    production_status       ENUM('current','upcoming','superseded','discontinued') NOT NULL DEFAULT 'current',
    changes_summary         TEXT NULL,
    brochure_label          VARCHAR(190) NULL,
    publication_status      ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status        ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_model_year (model_id, model_year),
    CONSTRAINT fk_polaris_my_model FOREIGN KEY (model_id) REFERENCES polaris_rv_models (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_rv_variants (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_id                INT UNSIGNED NOT NULL,
    model_year_id           INT UNSIGNED NULL,
    name                    VARCHAR(190) NOT NULL,
    slug                    VARCHAR(120) NOT NULL,
    layout_summary          VARCHAR(255) NULL,
    sleeps                  TINYINT UNSIGNED NULL,
    body_length_m           DECIMAL(5,2) NULL,
    overall_length_m        DECIMAL(5,2) NULL,
    tare_kg                 INT UNSIGNED NULL,
    atm_kg                  INT UNSIGNED NULL,
    gtm_kg                  INT UNSIGNED NULL,
    towball_mass_kg         INT UNSIGNED NULL,
    fresh_water_l           INT UNSIGNED NULL,
    grey_water_l            INT UNSIGNED NULL,
    solar_w                 INT UNSIGNED NULL,
    battery_ah              INT UNSIGNED NULL,
    bathroom_type           VARCHAR(80) NULL,
    kitchen_type            VARCHAR(80) NULL,
    price_status            ENUM('rrp','from','indicative','contact_dealer','unknown') NOT NULL DEFAULT 'unknown',
    price_aud_cents         INT UNSIGNED NULL,
    price_effective_on      DATE NULL,
    price_expires_on        DATE NULL,
    publication_status      ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status        ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_variant_slug (model_id, slug),
    KEY idx_polaris_variant_weights (atm_kg, tare_kg, sleeps),
    KEY idx_polaris_variant_price (price_status, price_aud_cents),
    CONSTRAINT fk_polaris_var_model FOREIGN KEY (model_id) REFERENCES polaris_rv_models (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_var_year FOREIGN KEY (model_year_id) REFERENCES polaris_rv_model_years (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_specification_definitions (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    spec_key                VARCHAR(80) NOT NULL,
    display_label           VARCHAR(190) NOT NULL,
    category                VARCHAR(80) NOT NULL,
    data_type               ENUM('string','integer','decimal','boolean','enum') NOT NULL DEFAULT 'string',
    unit_family             VARCHAR(40) NULL,
    allowed_units           VARCHAR(120) NULL,
    importance              ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    user_explanation        TEXT NULL,
    comparison_behaviour    ENUM('diff','prefer_higher','prefer_lower','categorical','hide') NOT NULL DEFAULT 'diff',
    filterable              TINYINT(1) NOT NULL DEFAULT 0,
    searchable              TINYINT(1) NOT NULL DEFAULT 0,
    is_active               TINYINT(1) NOT NULL DEFAULT 1,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_spec_key (spec_key),
    KEY idx_polaris_spec_cat (category, importance)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_data_sources (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    source_type             ENUM(
        'manufacturer_submission','brochure','public_webpage','manual_research',
        'dealer_submission','community_correction','csv_import','other'
    ) NOT NULL,
    title                   VARCHAR(255) NOT NULL,
    url                     VARCHAR(1000) NULL,
    retrieved_at            DATE NULL,
    published_at            DATE NULL,
    authority               ENUM('manufacturer','dealer','public','community','internal') NOT NULL DEFAULT 'public',
    licence_notes           VARCHAR(500) NULL,
    content_hash            CHAR(64) NULL,
    review_status           ENUM('pending','accepted','rejected') NOT NULL DEFAULT 'pending',
    is_demo                 TINYINT(1) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_source_brand (brand_id, source_type),
    CONSTRAINT fk_polaris_source_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_specification_values (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    definition_id           INT UNSIGNED NOT NULL,
    entity_type             ENUM('manufacturer','model','model_year','variant') NOT NULL,
    entity_id               INT UNSIGNED NOT NULL,
    value_text              VARCHAR(500) NULL,
    value_number            DECIMAL(14,4) NULL,
    value_bool              TINYINT(1) NULL,
    unit                    VARCHAR(20) NULL,
    normalised_number       DECIMAL(14,4) NULL,
    raw_source_value        VARCHAR(500) NULL,
    source_id               INT UNSIGNED NULL,
    confidence              TINYINT UNSIGNED NULL,
    verification_status     ENUM('unverified','pending','verified','rejected','contradicted') NOT NULL DEFAULT 'unverified',
    effective_model_year    SMALLINT UNSIGNED NULL,
    notes                   TEXT NULL,
    reviewed_by_user_id     INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_spec_entity (entity_type, entity_id, definition_id),
    KEY idx_polaris_spec_verify (verification_status),
    CONSTRAINT fk_polaris_sv_def FOREIGN KEY (definition_id) REFERENCES polaris_specification_definitions (id),
    CONSTRAINT fk_polaris_sv_source FOREIGN KEY (source_id) REFERENCES polaris_data_sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_floorplans (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    model_id                INT UNSIGNED NOT NULL,
    model_year_id           INT UNSIGNED NULL,
    variant_id              INT UNSIGNED NULL,
    title                   VARCHAR(190) NOT NULL,
    accessible_description  TEXT NULL,
    bed_configuration       VARCHAR(120) NULL,
    bathroom_position       VARCHAR(80) NULL,
    kitchen_position        VARCHAR(80) NULL,
    seating_configuration   VARCHAR(120) NULL,
    image_path              VARCHAR(500) NULL,
    source_id               INT UNSIGNED NULL,
    verification_status     ENUM('unverified','pending','verified') NOT NULL DEFAULT 'unverified',
    publication_status      ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status        ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    is_demo                 TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at              DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_fp_model (model_id, publication_status),
    CONSTRAINT fk_polaris_fp_model FOREIGN KEY (model_id) REFERENCES polaris_rv_models (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_fp_year FOREIGN KEY (model_year_id) REFERENCES polaris_rv_model_years (id) ON DELETE SET NULL,
    CONSTRAINT fk_polaris_fp_variant FOREIGN KEY (variant_id) REFERENCES polaris_rv_variants (id) ON DELETE SET NULL,
    CONSTRAINT fk_polaris_fp_source FOREIGN KEY (source_id) REFERENCES polaris_data_sources (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO polaris_specification_definitions
    (spec_key, display_label, category, data_type, unit_family, allowed_units, importance, user_explanation, comparison_behaviour, filterable, searchable, is_active, created_at)
VALUES
('tare_kg','Tare mass','weights','integer','mass','kg','critical','Unladen mass of the RV as specified by the manufacturer.','prefer_lower',1,1,1,NOW()),
('atm_kg','Aggregate trailer mass (ATM)','weights','integer','mass','kg','critical','Maximum permitted mass of the trailer including payload.','prefer_lower',1,1,1,NOW()),
('payload_kg','Payload','weights','integer','mass','kg','critical','ATM minus tare where both values are known.','prefer_higher',1,1,1,NOW()),
('sleeps','Sleeping capacity','sleeping','integer',NULL,NULL,'high','Number of people the layout is designed to sleep.','prefer_higher',1,1,1,NOW()),
('fresh_water_l','Fresh water','water','integer','volume','L','high','Onboard fresh water capacity.','prefer_higher',1,1,1,NOW()),
('solar_w','Solar capacity','electrical','integer','power','W','high','Installed or standard solar array wattage when specified.','prefer_higher',1,1,1,NOW()),
('battery_ah','Battery capacity','electrical','integer','charge','Ah','high','House battery capacity when specified.','prefer_higher',1,1,1,NOW()),
('body_length_m','Body length','dimensions','decimal','length','m','high','Body length excluding drawbar where distinguished.','prefer_lower',1,1,1,NOW());

-- Demonstration fixtures only (is_demo = 1).
INSERT INTO polaris_manufacturers
    (brand_id, legal_name, trading_name, slug, website_url, description, claim_status, verification_status, publication_status, lifecycle_status, is_demo, last_reviewed_at, created_at)
VALUES
(5, 'Demo Horizon Caravans Pty Ltd', 'Demo Horizon', 'demo-horizon', 'https://example.invalid/demo-horizon',
 'Demonstration manufacturer for local Polaris development. Not a real business.',
 'claimed', 'verified', 'published', 'active', 1, NOW(), NOW()),
(5, 'Demo Outback Hybrids Pty Ltd', 'Demo Outback Hybrids', 'demo-outback-hybrids', NULL,
 'Unclaimed demonstration manufacturer profile.',
 'unclaimed', 'unverified', 'published', 'active', 1, NULL, NOW()),
(5, 'Demo Archive Motors Pty Ltd', 'Demo Archive Motors', 'demo-archive-motors', NULL,
 'Archived demonstration manufacturer.',
 'unclaimed', 'unverified', 'unpublished', 'archived', 1, NULL, NOW());

INSERT INTO polaris_data_sources
    (brand_id, source_type, title, url, retrieved_at, authority, review_status, is_demo, created_at)
VALUES
(5, 'manual_research', 'Demo Horizon 2026 brochure (fixture)', 'https://example.invalid/demo-horizon/brochure', CURDATE(), 'internal', 'accepted', 1, NOW());

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, last_reviewed_at, created_at)
SELECT 5, id, 'Southern Cross', 'southern-cross', 'caravan',
       'Demo couple-touring caravan with island bed. Fixture data only.',
       'current', 2025, 'verified', 'published', 'active', 1, NOW(), NOW()
FROM polaris_manufacturers WHERE slug = 'demo-horizon' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Range Runner', 'range-runner', 'hybrid_caravan',
       'Demo hybrid for rough-gravel touring. Fixture data only.',
       'current', 2026, 'unverified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers WHERE slug = 'demo-outback-hybrids' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Weekend Escape', 'weekend-escape', 'camper_trailer',
       'Demo lightweight camper trailer. Fixture data only.',
       'current', 2024, 'verified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers WHERE slug = 'demo-horizon' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'Coastal Voyager', 'coastal-voyager', 'motorhome',
       'Demo motorhome. Fixture data only.',
       'upcoming', 2027, 'pending', 'published', 'active', 1, NOW()
FROM polaris_manufacturers WHERE slug = 'demo-horizon' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'City Hopper', 'city-hopper', 'campervan',
       'Demo campervan with incomplete specifications.',
       'current', 2025, 'unverified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers WHERE slug = 'demo-outback-hybrids' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT 5, id, 'TrayMaster', 'traymaster', 'slide_on',
       'Demo slide-on camper. Fixture data only.',
       'current', 2025, 'verified', 'published', 'active', 1, NOW()
FROM polaris_manufacturers WHERE slug = 'demo-horizon' AND brand_id = 5;

INSERT INTO polaris_rv_models
    (brand_id, manufacturer_id, name, slug, category, description, production_status, first_model_year, final_model_year, verification_status, publication_status, lifecycle_status, is_demo, archival_reason, created_at)
SELECT 5, id, 'Legacy Series', 'legacy-series', 'caravan',
       'Archived superseded demo model.',
       'discontinued', 2018, 2022, 'unverified', 'unpublished', 'archived', 1, 'Demonstration superseded — demo archive', NOW()
FROM polaris_manufacturers WHERE slug = 'demo-archive-motors' AND brand_id = 5;

INSERT INTO polaris_rv_model_years (model_id, model_year, production_status, brochure_label, publication_status, lifecycle_status, created_at)
SELECT id, 2026, 'current', '2026 demo brochure', 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'southern-cross' AND is_demo = 1;

INSERT INTO polaris_rv_model_years (model_id, model_year, production_status, brochure_label, publication_status, lifecycle_status, created_at)
SELECT id, 2025, 'superseded', '2025 demo brochure', 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'southern-cross' AND is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, model_year_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, gtm_kg, towball_mass_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, price_effective_on, publication_status, lifecycle_status, created_at)
SELECT m.id, y.id, '18ft Island Bed', '18ft-island-bed', 'Island bed, rear ensuite, club lounge',
       2, 5.50, 7.20, 1850, 2500, 2350, 180, 190, 110, 400, 200,
       'Ensuite', 'Internal', 'from', 8990000, CURDATE(), 'published', 'active', NOW()
FROM polaris_rv_models m
JOIN polaris_rv_model_years y ON y.model_id = m.id AND y.model_year = 2026
WHERE m.slug = 'southern-cross' AND m.is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, fresh_water_l, grey_water_l, solar_w, battery_ah,
     bathroom_type, kitchen_type, price_status, price_aud_cents, publication_status, lifecycle_status, created_at)
SELECT id, 'Off-Grid Pack', 'off-grid-pack', 'East-west bed, external kitchen',
       3, 5.20, 6.90, 1680, 2400, 220, 140, 600, 300,
       'Combined', 'External', 'rrp', 11200000, 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'range-runner' AND is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, name, slug, layout_summary, sleeps, body_length_m, overall_length_m,
     tare_kg, atm_kg, fresh_water_l, bathroom_type, kitchen_type, price_status, price_aud_cents, publication_status, lifecycle_status, created_at)
SELECT id, 'Soft Floor', 'soft-floor', 'Front fold soft floor',
       4, 3.80, 5.10, 980, 1400, 80, 'None', 'External', 'from', 4200000, 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'weekend-escape' AND is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, name, slug, layout_summary, sleeps, overall_length_m, price_status, publication_status, lifecycle_status, created_at)
SELECT id, 'Base', 'base', 'Compact campervan — weights not yet verified',
       2, 5.40, 'contact_dealer', 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'city-hopper' AND is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, name, slug, layout_summary, sleeps, tare_kg, atm_kg, price_status, price_aud_cents, publication_status, lifecycle_status, created_at)
SELECT id, 'Dual Cab Tray', 'dual-cab-tray', 'Slide-on for dual-cab ute',
       2, 650, 900, 'indicative', 3850000, 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'traymaster' AND is_demo = 1;

INSERT INTO polaris_rv_variants
    (model_id, name, slug, layout_summary, sleeps, price_status, publication_status, lifecycle_status, created_at)
SELECT id, 'Bunk Family', 'bunk-family', 'Upcoming motorhome — pricing unavailable',
       4, 'unknown', 'published', 'active', NOW()
FROM polaris_rv_models WHERE slug = 'coastal-voyager' AND is_demo = 1;

INSERT INTO polaris_floorplans
    (model_id, variant_id, title, accessible_description, bed_configuration, bathroom_position, kitchen_position, seating_configuration, verification_status, publication_status, lifecycle_status, is_demo, created_at)
SELECT m.id, v.id, 'Island bed layout',
       'Entry on the roadside. Island bed toward the front, club lounge mid-body, ensuite across the rear.',
       'Island bed', 'Rear', 'Internal roadside', 'Club lounge',
       'verified', 'published', 'active', 1, NOW()
FROM polaris_rv_models m
JOIN polaris_rv_variants v ON v.model_id = m.id AND v.slug = '18ft-island-bed'
WHERE m.slug = 'southern-cross' AND m.is_demo = 1;
