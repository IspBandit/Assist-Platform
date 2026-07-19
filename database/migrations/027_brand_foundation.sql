-- Assist Platform multi-brand foundation.
-- Additive only: existing VanAssist records and routes are unchanged.

CREATE TABLE brands (
    id                  INT UNSIGNED NOT NULL,
    brand_key           VARCHAR(50) NOT NULL,
    name                VARCHAR(120) NOT NULL,
    legal_name          VARCHAR(190) NOT NULL,
    status              ENUM('active','private','coming_soon','disabled') NOT NULL DEFAULT 'disabled',
    default_locale      VARCHAR(20) NOT NULL DEFAULT 'en-AU',
    default_currency    CHAR(3) NOT NULL DEFAULT 'AUD',
    storage_namespace   VARCHAR(80) NOT NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brands_key (brand_key),
    UNIQUE KEY uq_brands_storage_namespace (storage_namespace),
    KEY idx_brands_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brand_domains (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id        INT UNSIGNED NOT NULL,
    hostname        VARCHAR(190) NOT NULL,
    environment     ENUM('local','staging','production') NOT NULL DEFAULT 'production',
    is_primary      TINYINT(1) NOT NULL DEFAULT 0,
    verified_at     DATETIME NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_brand_domains_hostname (hostname),
    KEY idx_brand_domains_brand_environment (brand_id, environment),
    CONSTRAINT fk_brand_domains_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO brands (
    id, brand_key, name, legal_name, status, default_locale,
    default_currency, storage_namespace, created_at
) VALUES
    (1, 'vanassist', 'VanAssist', 'VanAssist', 'active', 'en-AU', 'AUD', 'vanassist', NOW()),
    (2, 'towwise', 'TowWise', 'TowWise', 'coming_soon', 'en-AU', 'AUD', 'towwise', NOW()),
    (3, 'trailerwise', 'TrailerWise', 'TrailerWise', 'coming_soon', 'en-AU', 'AUD', 'trailerwise', NOW());
