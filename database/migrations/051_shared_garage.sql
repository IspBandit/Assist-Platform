-- CORE-009 / EXP-007: one owner-scoped Garage and private compliance wallet
-- shared by every Assist Platform brand.

CREATE TABLE garage_assets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    created_in_brand_id INT UNSIGNED NOT NULL,
    asset_type ENUM(
        'car','motorcycle','light_truck','heavy_vehicle','street_rod',
        'trailer','caravan','camper_trailer','motorhome','boat_trailer',
        'horse_float','other'
    ) NOT NULL,
    nickname VARCHAR(100) NOT NULL,
    make VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    model_year SMALLINT UNSIGNED NULL,
    registration_jurisdiction CHAR(3) NULL,
    tare_kg DECIMAL(9,1) NULL,
    gvm_kg DECIMAL(9,1) NULL,
    gcm_kg DECIMAL(9,1) NULL,
    atm_kg DECIMAL(9,1) NULL,
    max_braked_towing_kg DECIMAL(9,1) NULL,
    max_towball_kg DECIMAL(9,1) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_garage_assets_owner (user_id, deleted_at, created_at),
    KEY idx_garage_assets_type (asset_type),
    CONSTRAINT fk_garage_assets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_garage_assets_created_brand FOREIGN KEY (created_in_brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE garage_documents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    garage_asset_id INT UNSIGNED NOT NULL,
    document_type ENUM(
        'registration','insurance','roadworthy','inspection','engineering_certificate',
        'modification_approval','service_record','receipt','warranty','other'
    ) NOT NULL DEFAULT 'other',
    label VARCHAR(150) NOT NULL,
    issuing_authority VARCHAR(150) NULL,
    issue_date DATE NULL,
    expires_at DATE NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_garage_documents_asset (garage_asset_id, expires_at, created_at),
    CONSTRAINT fk_garage_documents_asset FOREIGN KEY (garage_asset_id) REFERENCES garage_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE garage_reminder_preferences (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    garage_asset_id INT UNSIGNED NOT NULL,
    reminder_kind ENUM('document_expiry','service_due','inspection_due','registration_due') NOT NULL,
    lead_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_garage_reminder (user_id, garage_asset_id, reminder_kind),
    KEY idx_garage_reminders_due (enabled, reminder_kind),
    CONSTRAINT fk_garage_reminders_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_garage_reminders_asset FOREIGN KEY (garage_asset_id) REFERENCES garage_assets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE garage_brand_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    garage_asset_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    activity_type ENUM('created','viewed','updated','document_uploaded','rules_opened','provider_handoff') NOT NULL,
    context_json JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_garage_activity_owner (user_id, created_at),
    KEY idx_garage_activity_asset (garage_asset_id, created_at),
    CONSTRAINT fk_garage_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_garage_activity_asset FOREIGN KEY (garage_asset_id) REFERENCES garage_assets (id) ON DELETE CASCADE,
    CONSTRAINT fk_garage_activity_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
