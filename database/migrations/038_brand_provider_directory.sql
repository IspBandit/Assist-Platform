-- Brand-specific provider discovery, categorisation and relevant advertising.

CREATE TABLE brand_provider_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    category_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(320) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brand_provider_category (brand_id, category_key),
    KEY idx_brand_provider_category_active (brand_id, is_active, sort_order),
    CONSTRAINT fk_brand_provider_category_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_brand_category_assignments (
    listing_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    assignment_source ENUM('provider','admin','import','heuristic') NOT NULL DEFAULT 'heuristic',
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (listing_id, category_id),
    KEY idx_provider_brand_category_lookup (category_id, is_verified, confidence),
    CONSTRAINT fk_provider_brand_category_listing FOREIGN KEY (listing_id) REFERENCES provider_brand_listings (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_brand_category_category FOREIGN KEY (category_id) REFERENCES brand_provider_categories (id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_brand_category_confidence CHECK (confidence BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_discovery_evidence (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    source_type ENUM('existing_catalogue','openstreetmap','google_places','provider_claim','admin_review','other') NOT NULL,
    source_reference VARCHAR(255) NULL,
    verification_status ENUM('discovered','claimed','business_confirmed','admin_verified','rejected') NOT NULL DEFAULT 'discovered',
    discovered_at DATETIME NOT NULL,
    last_checked_at DATETIME NULL,
    checked_by INT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_discovery_source (provider_id, brand_id, source_type, source_reference),
    KEY idx_provider_discovery_review (brand_id, verification_status, last_checked_at),
    CONSTRAINT fk_provider_discovery_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_discovery_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_provider_discovery_checker FOREIGN KEY (checked_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertising_campaigns (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    advertiser_provider_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    status ENUM('draft','pending','active','paused','completed','rejected') NOT NULL DEFAULT 'draft',
    headline VARCHAR(120) NOT NULL,
    body VARCHAR(300) NULL,
    destination_url VARCHAR(500) NOT NULL,
    desktop_image_path VARCHAR(255) NULL,
    mobile_image_path VARCHAR(255) NULL,
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ad_campaign_delivery (brand_id, status, starts_at, ends_at),
    CONSTRAINT fk_ad_campaign_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_ad_campaign_provider FOREIGN KEY (advertiser_provider_id) REFERENCES providers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertising_campaign_targets (
    campaign_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    state_id INT UNSIGNED NULL,
    region_id INT UNSIGNED NULL,
    town_id INT UNSIGNED NULL,
    placement VARCHAR(80) NOT NULL DEFAULT 'directory',
    created_at DATETIME NOT NULL,
    KEY idx_ad_target_delivery (placement, category_id, state_id, region_id, town_id),
    CONSTRAINT fk_ad_target_campaign FOREIGN KEY (campaign_id) REFERENCES advertising_campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_target_category FOREIGN KEY (category_id) REFERENCES brand_provider_categories (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_target_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_target_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_target_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO brand_provider_categories (brand_id, category_key, name, description, sort_order, is_active, created_at) VALUES
(1,'caravan-rv-repairs','Caravan & RV repairs','Mobile and workshop caravan, camper and motorhome repair services.',10,1,NOW()),
(1,'auto-electrical','Auto electrical','12-volt, batteries, solar, lighting and electrical diagnosis.',20,1,NOW()),
(1,'tyres-wheels-bearings','Tyres, wheels & bearings','Tyres, wheel, hub and bearing services relevant to RV travel.',30,1,NOW()),
(1,'roadworthy-inspections','Roadworthy & inspections','Relevant safety, roadworthy and inspection providers.',40,1,NOW()),
(1,'roadside-recovery','Roadside & recovery','Breakdown assistance, towing and recovery.',50,1,NOW()),
(2,'public-weighing','Weighing services','Public weighbridges and mobile vehicle/trailer weighing.',10,1,NOW()),
(2,'towing-training','Towing training','Practical towing instruction and safety education.',20,1,NOW()),
(2,'towbars-hitches','Towbars & hitches','Towbar, hitch, coupling and weight-distribution specialists.',30,1,NOW()),
(2,'brakes-controllers','Brakes & controllers','Trailer brakes, brake controllers and breakaway systems.',40,1,NOW()),
(2,'suspension-payload','Suspension & payload','Vehicle suspension, load and payload specialists.',50,1,NOW()),
(2,'towing-electrical','Towing electrical','Trailer wiring, plugs, cameras, lighting and auto electrical.',60,1,NOW()),
(2,'tyres-wheels','Tyres & wheels','Tyre, wheel and alignment businesses relevant to towing.',70,1,NOW()),
(2,'towing-inspections','Towing inspections','Combination checks, trailer inspections and compliance support.',80,1,NOW()),
(3,'trailer-repairs','Trailer repairs & servicing','General, mobile and workshop trailer repair services.',10,1,NOW()),
(3,'roadworthy-inspections','Roadworthy & inspections','Approved inspections, safety certificates and compliance services.',20,1,NOW()),
(3,'tyres-wheels-bearings','Tyres, wheels & bearings','Tyre shops, wheels, hubs, balancing and bearing services.',30,1,NOW()),
(3,'brakes-axles-suspension','Brakes, axles & suspension','Electric brakes, controllers, axles, springs and suspension.',40,1,NOW()),
(3,'auto-electrical','Auto electrical','Trailer lighting, plugs, wiring, batteries and diagnostics.',50,1,NOW()),
(3,'fabrication-engineering','Fabrication & engineering','Welding, chassis work, modifications and engineering.',60,1,NOW()),
(3,'parts-accessories','Parts & accessories','Trailer components, replacement parts and upgrades.',70,1,NOW()),
(3,'manufacturers-dealers','Manufacturers & dealers','Trailer builders, dealers and authorised product support.',80,1,NOW()),
(3,'mobile-trailer-services','Mobile trailer services','Providers able to attend trailers on site or roadside.',90,1,NOW());

