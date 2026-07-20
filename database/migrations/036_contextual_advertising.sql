-- Shared contextual advertising foundation. Advertising never affects organic
-- calculations or safety outcomes and every rendered placement requires a label.

CREATE TABLE advertisers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NULL,
    business_name   VARCHAR(190) NOT NULL,
    contact_email   VARCHAR(190) NULL,
    status          ENUM('pending','active','paused','rejected','archived') NOT NULL DEFAULT 'pending',
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    deleted_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_advertisers_status (status, deleted_at),
    CONSTRAINT fk_advertisers_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertising_campaigns (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    advertiser_id   INT UNSIGNED NOT NULL,
    brand_id        INT UNSIGNED NOT NULL,
    name            VARCHAR(190) NOT NULL,
    placement       VARCHAR(80) NOT NULL,
    context_key     VARCHAR(80) NOT NULL DEFAULT 'general',
    state_code      VARCHAR(10) NULL,
    status          ENUM('draft','pending','active','paused','completed','rejected','archived') NOT NULL DEFAULT 'draft',
    starts_at       DATETIME NULL,
    ends_at         DATETIME NULL,
    destination_url VARCHAR(1000) NOT NULL,
    sponsorship_label VARCHAR(80) NOT NULL DEFAULT 'Sponsored',
    priority        SMALLINT NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    deleted_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_campaign_delivery (brand_id, placement, context_key, status, starts_at, ends_at, deleted_at),
    KEY idx_campaign_advertiser (advertiser_id, status),
    CONSTRAINT fk_campaign_advertiser FOREIGN KEY (advertiser_id) REFERENCES advertisers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_campaign_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertising_creatives (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    headline        VARCHAR(120) NOT NULL,
    body_text       VARCHAR(320) NOT NULL,
    call_to_action  VARCHAR(60) NOT NULL,
    image_path      VARCHAR(500) NULL,
    alt_text        VARCHAR(190) NULL,
    status          ENUM('draft','pending','approved','rejected','archived') NOT NULL DEFAULT 'draft',
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_creative_campaign (campaign_id, status),
    CONSTRAINT fk_creative_campaign FOREIGN KEY (campaign_id) REFERENCES advertising_campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE advertising_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    campaign_id     BIGINT UNSIGNED NOT NULL,
    creative_id     BIGINT UNSIGNED NOT NULL,
    brand_id        INT UNSIGNED NOT NULL,
    event_type      ENUM('impression','click') NOT NULL,
    placement       VARCHAR(80) NOT NULL,
    context_key     VARCHAR(80) NOT NULL,
    session_hash    CHAR(64) NULL,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_ad_event_campaign (campaign_id, event_type, created_at),
    KEY idx_ad_event_brand (brand_id, event_type, created_at),
    CONSTRAINT fk_ad_event_campaign FOREIGN KEY (campaign_id) REFERENCES advertising_campaigns (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_event_creative FOREIGN KEY (creative_id) REFERENCES advertising_creatives (id) ON DELETE CASCADE,
    CONSTRAINT fk_ad_event_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
