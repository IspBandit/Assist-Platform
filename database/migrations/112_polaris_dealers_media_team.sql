-- Polaris: dealers, manufacturer media, team memberships, merge audit support.
-- Draft-first; no used inventory marketplace.

CREATE TABLE IF NOT EXISTS polaris_dealers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    trading_name VARCHAR(190) NOT NULL,
    slug VARCHAR(120) NOT NULL,
    website_url VARCHAR(500) NULL,
    phone VARCHAR(40) NULL,
    email VARCHAR(190) NULL,
    locality VARCHAR(120) NULL,
    state_abbr CHAR(3) NULL,
    claim_status ENUM('unclaimed','pending','claimed','rejected') NOT NULL DEFAULT 'unclaimed',
    claimed_by_user_id INT UNSIGNED NULL,
    verification_status ENUM('unverified','pending','verified') NOT NULL DEFAULT 'unverified',
    publication_status ENUM('draft','published','unpublished') NOT NULL DEFAULT 'draft',
    lifecycle_status ENUM('active','archived','recycle_bin') NOT NULL DEFAULT 'active',
    is_demo TINYINT(1) NOT NULL DEFAULT 0,
    deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_dealer_slug (brand_id, slug),
    KEY idx_polaris_dealer_claim (claim_status),
    CONSTRAINT fk_polaris_dealer_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_polaris_dealer_user FOREIGN KEY (claimed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS polaris_manufacturer_dealers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    manufacturer_id INT UNSIGNED NOT NULL,
    dealer_id INT UNSIGNED NOT NULL,
    brands_represented VARCHAR(255) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_mfr_dealer (manufacturer_id, dealer_id),
    CONSTRAINT fk_polaris_md_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_md_dealer FOREIGN KEY (dealer_id) REFERENCES polaris_dealers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS polaris_manufacturer_media (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    manufacturer_id INT UNSIGNED NOT NULL,
    media_type ENUM('brochure','floorplan','logo','hero','other') NOT NULL DEFAULT 'other',
    title VARCHAR(190) NOT NULL,
    storage_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    byte_size INT UNSIGNED NOT NULL DEFAULT 0,
    review_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_mfr_media (manufacturer_id, media_type),
    CONSTRAINT fk_polaris_mm_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_mm_user FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS polaris_manufacturer_team (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    manufacturer_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role_label ENUM('owner','editor','viewer') NOT NULL DEFAULT 'editor',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_mfr_team (manufacturer_id, user_id),
    CONSTRAINT fk_polaris_mt_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_mt_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS polaris_manufacturer_merges (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    survivor_id INT UNSIGNED NOT NULL,
    absorbed_id INT UNSIGNED NOT NULL,
    absorbed_slug VARCHAR(120) NOT NULL,
    notes VARCHAR(500) NULL,
    merged_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_merge_survivor (survivor_id),
    CONSTRAINT fk_polaris_merge_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_polaris_merge_survivor FOREIGN KEY (survivor_id) REFERENCES polaris_manufacturers (id),
    CONSTRAINT fk_polaris_merge_user FOREIGN KEY (merged_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO polaris_dealers
    (brand_id, trading_name, slug, locality, state_abbr, claim_status, verification_status, publication_status, is_demo, created_at)
VALUES
    (5, 'Demo Outback RV Centre', 'demo-outback-rv-centre', 'Dubbo', 'NSW', 'unclaimed', 'unverified', 'published', 1, NOW()),
    (5, 'Demo Coastal Caravans', 'demo-coastal-caravans', 'Geelong', 'VIC', 'unclaimed', 'unverified', 'published', 1, NOW())
ON DUPLICATE KEY UPDATE updated_at = NOW();
