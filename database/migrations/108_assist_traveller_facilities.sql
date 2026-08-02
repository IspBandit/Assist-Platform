-- Assist AI-6: traveller_facilities entity (ADR 0032 / 0027).
-- Never overload caravan_parks with standalone amenity POIs.
-- Feature flag off by default; Ask only surfaces status=active + reviewed/verified.

CREATE TABLE IF NOT EXISTS traveller_facilities (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    facility_type VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    formatted_address VARCHAR(500) NULL,
    locality VARCHAR(120) NULL,
    town_id INT UNSIGNED NULL,
    state_id INT UNSIGNED NULL,
    operating_status VARCHAR(40) NOT NULL DEFAULT 'unknown',
    opening_hours VARCHAR(500) NULL,
    accessibility_notes VARCHAR(500) NULL,
    source_key VARCHAR(80) NULL,
    source_record_id VARCHAR(190) NULL,
    source_licence VARCHAR(80) NULL,
    source_attribution VARCHAR(255) NULL,
    source_url VARCHAR(1000) NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
    verification_status ENUM('unverified','reviewed','verified','rejected') NOT NULL DEFAULT 'unverified',
    status ENUM('draft','pending','active','suspended','archived') NOT NULL DEFAULT 'draft',
    brand_id INT UNSIGNED NULL,
    linked_provider_id INT UNSIGNED NULL,
    last_checked_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tf_slug (slug),
    UNIQUE KEY uq_tf_source_external (source_key, source_record_id),
    KEY idx_tf_type_status (facility_type, status, verification_status),
    KEY idx_tf_geo (latitude, longitude),
    KEY idx_tf_town (town_id),
    KEY idx_tf_brand (brand_id),
    CONSTRAINT fk_tf_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_provider FOREIGN KEY (linked_provider_id) REFERENCES providers (id) ON DELETE SET NULL,
    CONSTRAINT chk_tf_confidence CHECK (confidence BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES (
    'assist_ai_traveller_facilities',
    0,
    'Ask VanAssist traveller_facilities adapter (AI-6, off by default). Separate from caravan_parks (ADR 0032).',
    NOW()
)
ON DUPLICATE KEY UPDATE description = VALUES(description);
