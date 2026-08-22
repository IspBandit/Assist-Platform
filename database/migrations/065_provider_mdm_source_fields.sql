-- Additive LocalTorque MDM fields. Canonical provider identity remains shared
-- while record-level provenance preserves every imported public source.

ALTER TABLE providers
    ADD COLUMN trading_name VARCHAR(190) NULL AFTER business_name,
    ADD COLUMN operator_name VARCHAR(190) NULL AFTER trading_name,
    ADD COLUMN latitude DECIMAL(10,7) NULL AFTER street_address,
    ADD COLUMN longitude DECIMAL(10,7) NULL AFTER latitude,
    ADD COLUMN coordinates_approximate TINYINT(1) NOT NULL DEFAULT 0 AFTER longitude,
    ADD COLUMN opening_hours VARCHAR(500) NULL AFTER coordinates_approximate,
    ADD COLUMN operational_status VARCHAR(60) NULL AFTER opening_hours,
    ADD COLUMN fuel_types_json TEXT NULL AFTER operational_status,
    ADD COLUMN source_licence VARCHAR(80) NULL AFTER source_type,
    ADD KEY idx_providers_geo (latitude, longitude);

CREATE TABLE provider_source_records (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    source_key VARCHAR(80) NOT NULL,
    external_id VARCHAR(190) NOT NULL,
    source_url VARCHAR(1000) NULL,
    source_licence VARCHAR(80) NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
    publishable TINYINT(1) NOT NULL DEFAULT 0,
    needs_review TINYINT(1) NOT NULL DEFAULT 1,
    payload_json MEDIUMTEXT NULL,
    first_seen_at DATETIME NOT NULL,
    last_seen_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_source_external (source_key, external_id),
    KEY idx_provider_source_provider (provider_id),
    KEY idx_provider_source_review (publishable, needs_review, source_key),
    CONSTRAINT fk_provider_source_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_source_confidence CHECK (confidence BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
