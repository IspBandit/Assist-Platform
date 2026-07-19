-- Canonical providers may participate in multiple Assist Platform brands.
-- No provider IDs or legacy VanAssist slugs are changed by this migration.

CREATE TABLE provider_brand_listings (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id            INT UNSIGNED NOT NULL,
    provider_id         INT UNSIGNED NOT NULL,
    slug                VARCHAR(200) NOT NULL,
    display_name        VARCHAR(190) NOT NULL,
    status              ENUM('draft','pending','active','suspended','rejected') NOT NULL DEFAULT 'pending',
    is_featured         TINYINT(1) NOT NULL DEFAULT 0,
    is_verified         TINYINT(1) NOT NULL DEFAULT 0,
    search_visible      TINYINT(1) NOT NULL DEFAULT 1,
    seo_title           VARCHAR(190) NULL,
    seo_description     VARCHAR(320) NULL,
    created_at          DATETIME NOT NULL,
    updated_at          DATETIME NULL,
    deleted_at          DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_brand_listing (brand_id, provider_id),
    UNIQUE KEY uq_provider_brand_slug (brand_id, slug),
    KEY idx_provider_brand_status (brand_id, status, search_visible, deleted_at),
    KEY idx_provider_listing_provider (provider_id),
    CONSTRAINT fk_provider_brand_listing_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_provider_brand_listing_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_url_aliases (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id        INT UNSIGNED NOT NULL,
    listing_id      INT UNSIGNED NOT NULL,
    alias_path      VARCHAR(255) NOT NULL,
    redirect_status SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    created_at      DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_url_alias (brand_id, alias_path),
    KEY idx_provider_url_alias_listing (listing_id),
    CONSTRAINT fk_provider_url_alias_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_provider_url_alias_listing FOREIGN KEY (listing_id) REFERENCES provider_brand_listings (id) ON DELETE CASCADE,
    CONSTRAINT chk_provider_url_alias_status CHECK (redirect_status IN (301, 302, 307, 308))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
