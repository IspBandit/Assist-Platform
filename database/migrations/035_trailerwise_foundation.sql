-- TrailerWise relational foundation.
-- Businesses remain canonical providers; individual trailers are separate listings.

CREATE TABLE trailer_types (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(100) NOT NULL,
    name        VARCHAR(120) NOT NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trailer_types_slug (slug),
    KEY idx_trailer_types_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trailer_business_profiles (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_listing_id INT UNSIGNED NOT NULL,
    business_type       ENUM('manufacturer','dealer','reseller','repairer','parts_supplier','inspector','certifier','engineer','hire','transport') NOT NULL,
    licence_reference   VARCHAR(190) NULL,
    licence_expires_at  DATE NULL,
    is_authorised       TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trailer_business_type (provider_listing_id, business_type),
    KEY idx_trailer_business_lookup (business_type, is_authorised, deleted_at),
    CONSTRAINT fk_trailer_business_listing FOREIGN KEY (provider_listing_id) REFERENCES provider_brand_listings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trailer_business_types (
    business_profile_id BIGINT UNSIGNED NOT NULL,
    trailer_type_id     INT UNSIGNED NOT NULL,
    created_at          DATETIME NOT NULL,
    PRIMARY KEY (business_profile_id, trailer_type_id),
    KEY idx_trailer_business_types_type (trailer_type_id, business_profile_id),
    CONSTRAINT fk_trailer_business_types_profile FOREIGN KEY (business_profile_id) REFERENCES trailer_business_profiles (id) ON DELETE CASCADE,
    CONSTRAINT fk_trailer_business_types_type FOREIGN KEY (trailer_type_id) REFERENCES trailer_types (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE trailer_listings (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_listing_id INT UNSIGNED NOT NULL,
    trailer_type_id     INT UNSIGNED NOT NULL,
    slug                VARCHAR(200) NOT NULL,
    title               VARCHAR(190) NOT NULL,
    manufacturer_name   VARCHAR(190) NULL,
    model_name          VARCHAR(190) NULL,
    model_year          SMALLINT UNSIGNED NULL,
    listing_kind        ENUM('new','used','hire','information') NOT NULL DEFAULT 'information',
    status              ENUM('draft','pending','active','sold','archived','rejected') NOT NULL DEFAULT 'draft',
    specifications      JSON NULL,
    price_aud_cents     BIGINT UNSIGNED NULL,
    enquiry_enabled     TINYINT(1) NOT NULL DEFAULT 1,
    published_at        DATETIME NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_trailer_listing_slug (provider_listing_id, slug),
    KEY idx_trailer_listing_public (status, trailer_type_id, published_at, deleted_at),
    KEY idx_trailer_listing_provider (provider_listing_id, status, deleted_at),
    CONSTRAINT fk_trailer_listing_provider FOREIGN KEY (provider_listing_id) REFERENCES provider_brand_listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_trailer_listing_type FOREIGN KEY (trailer_type_id) REFERENCES trailer_types (id) ON DELETE RESTRICT,
    CONSTRAINT chk_trailer_listing_year CHECK (model_year IS NULL OR model_year BETWEEN 1900 AND 2200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO trailer_types (slug, name, sort_order, created_at) VALUES
    ('box-trailers', 'Box Trailers', 10, NOW()),
    ('car-trailers', 'Car Trailers', 20, NOW()),
    ('boat-trailers', 'Boat Trailers', 30, NOW()),
    ('horse-floats', 'Horse Floats', 40, NOW()),
    ('livestock-trailers', 'Livestock Trailers', 50, NOW()),
    ('plant-trailers', 'Plant and Machinery Trailers', 60, NOW()),
    ('tipper-trailers', 'Tipper Trailers', 70, NOW()),
    ('flatbed-trailers', 'Flatbed Trailers', 80, NOW()),
    ('tradesman-trailers', 'Tradesman Trailers', 90, NOW()),
    ('enclosed-trailers', 'Enclosed Trailers', 100, NOW()),
    ('food-trailers', 'Food Trailers', 110, NOW()),
    ('motorbike-trailers', 'Motorbike Trailers', 120, NOW()),
    ('camper-trailers', 'Camper Trailers', 130, NOW()),
    ('tiny-house-trailers', 'Tiny-house Trailers', 140, NOW()),
    ('custom-trailers', 'Custom Trailers', 150, NOW());
