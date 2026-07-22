CREATE TABLE tow_vehicles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL DEFAULT 2,
    nickname VARCHAR(100) NOT NULL,
    make VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    model_year SMALLINT UNSIGNED NULL,
    gvm_kg DECIMAL(8,1) NOT NULL,
    gcm_kg DECIMAL(8,1) NOT NULL,
    max_braked_towing_kg DECIMAL(8,1) NOT NULL,
    max_towball_kg DECIMAL(8,1) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_tow_vehicles_owner (user_id, brand_id),
    CONSTRAINT fk_tow_vehicles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_tow_vehicles_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE towable_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL DEFAULT 2,
    asset_type ENUM('caravan','camper_trailer','trailer','boat_trailer','horse_float','other') NOT NULL DEFAULT 'caravan',
    nickname VARCHAR(100) NOT NULL,
    make VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    model_year SMALLINT UNSIGNED NULL,
    tare_kg DECIMAL(8,1) NULL,
    atm_kg DECIMAL(8,1) NOT NULL,
    typical_towball_kg DECIMAL(8,1) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_towable_assets_owner (user_id, brand_id),
    CONSTRAINT fk_towable_assets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_towable_assets_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE towing_combinations (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL DEFAULT 2,
    vehicle_id INT UNSIGNED NULL,
    towable_asset_id INT UNSIGNED NULL,
    label VARCHAR(150) NOT NULL,
    input_snapshot JSON NOT NULL,
    result_snapshot JSON NOT NULL,
    result_status ENUM('within_limits','near_limit','exceeds_limit') NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_towing_combinations_owner (user_id, brand_id, created_at),
    CONSTRAINT fk_towing_combinations_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_towing_combinations_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_towing_combinations_vehicle FOREIGN KEY (vehicle_id) REFERENCES tow_vehicles (id) ON DELETE SET NULL,
    CONSTRAINT fk_towing_combinations_asset FOREIGN KEY (towable_asset_id) REFERENCES towable_assets (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
