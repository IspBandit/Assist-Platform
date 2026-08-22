-- Track coordinate provenance so approximate postcode seeds cannot be
-- presented as verified town-level precision.
ALTER TABLE towns
    ADD COLUMN coordinate_source VARCHAR(80) NULL AFTER longitude,
    ADD COLUMN coordinate_confidence ENUM('authoritative','statistical','unverified') NOT NULL DEFAULT 'unverified' AFTER coordinate_source,
    ADD COLUMN coordinate_reference VARCHAR(100) NULL AFTER coordinate_confidence,
    ADD COLUMN coordinate_verified_at DATE NULL AFTER coordinate_reference,
    ADD KEY idx_towns_coordinate_confidence (coordinate_confidence);
