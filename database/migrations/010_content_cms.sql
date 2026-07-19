-- =====================================================================
-- 010 Content management: pages, reusable blocks, FAQs, settings, flags
-- =====================================================================

CREATE TABLE content_pages (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_key      VARCHAR(80) NOT NULL,
    title         VARCHAR(190) NOT NULL,
    slug          VARCHAR(190) NOT NULL,
    body          MEDIUMTEXT NULL,
    is_published  TINYINT(1) NOT NULL DEFAULT 1,
    is_system     TINYINT(1) NOT NULL DEFAULT 0,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    canonical_url VARCHAR(255) NULL,
    noindex       TINYINT(1) NOT NULL DEFAULT 0,
    og_title      VARCHAR(190) NULL,
    og_description VARCHAR(320) NULL,
    og_image      VARCHAR(255) NULL,
    schema_json   MEDIUMTEXT NULL,
    updated_by    INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_cp_key (page_key),
    UNIQUE KEY uq_cp_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_blocks (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    block_group   VARCHAR(80) NOT NULL,
    block_key     VARCHAR(100) NULL,
    title         VARCHAR(190) NULL,
    subtitle      VARCHAR(255) NULL,
    body          MEDIUMTEXT NULL,
    image_path    VARCHAR(255) NULL,
    button_label  VARCHAR(100) NULL,
    button_url    VARCHAR(255) NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cb_group (block_group),
    KEY idx_cb_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category    VARCHAR(80) NOT NULL DEFAULT 'general',
    question    VARCHAR(320) NOT NULL,
    answer      MEDIUMTEXT NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_faq_category (category),
    KEY idx_faq_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value MEDIUMTEXT NULL,
    setting_group VARCHAR(60) NOT NULL DEFAULT 'general',
    value_type    VARCHAR(20) NOT NULL DEFAULT 'string',
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ss_key (setting_key),
    KEY idx_ss_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE feature_flags (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    flag_key    VARCHAR(80) NOT NULL,
    is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ff_key (flag_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
