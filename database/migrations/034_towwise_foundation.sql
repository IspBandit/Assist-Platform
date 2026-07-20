-- TowWise asset and calculation foundation.
-- Informational calculations only; no row represents legal certification.

CREATE TABLE towing_assets (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    asset_type          ENUM('tow_vehicle','caravan','camper','trailer') NOT NULL,
    nickname            VARCHAR(120) NULL,
    make                VARCHAR(120) NULL,
    model               VARCHAR(120) NULL,
    series_name         VARCHAR(120) NULL,
    variant_name        VARCHAR(120) NULL,
    model_year          SMALLINT UNSIGNED NULL,
    specifications      JSON NOT NULL,
    specification_source VARCHAR(500) NULL,
    source_verified_at  DATETIME NULL,
    is_archived         TINYINT(1) NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_towing_assets_user_type (user_id, asset_type, is_archived, deleted_at),
    CONSTRAINT fk_towing_assets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT chk_towing_assets_year CHECK (model_year IS NULL OR model_year BETWEEN 1900 AND 2200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE towing_combinations (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    tow_vehicle_id      BIGINT UNSIGNED NOT NULL,
    towed_asset_id      BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(160) NULL,
    input_snapshot      JSON NOT NULL,
    result_snapshot     JSON NOT NULL,
    calculation_version VARCHAR(40) NOT NULL,
    status              ENUM('within_known_limits','near_known_limit','exceeds_known_limit','incomplete') NOT NULL,
    calculated_at       DATETIME NOT NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_towing_combinations_user (user_id, status, deleted_at),
    KEY idx_towing_combinations_vehicle (tow_vehicle_id),
    KEY idx_towing_combinations_towed (towed_asset_id),
    CONSTRAINT fk_towing_combinations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_towing_combinations_vehicle FOREIGN KEY (tow_vehicle_id) REFERENCES towing_assets (id) ON DELETE RESTRICT,
    CONSTRAINT fk_towing_combinations_towed FOREIGN KEY (towed_asset_id) REFERENCES towing_assets (id) ON DELETE RESTRICT,
    CONSTRAINT chk_towing_combination_assets CHECK (tow_vehicle_id <> towed_asset_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
