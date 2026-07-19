-- =====================================================================
-- 003 Service categories (nestable) and their qualification requirements
-- =====================================================================

CREATE TABLE service_categories (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    parent_id     INT UNSIGNED NULL,
    name          VARCHAR(150) NOT NULL,
    slug          VARCHAR(160) NOT NULL,
    icon          VARCHAR(80) NULL,
    short_description VARCHAR(320) NULL,
    public_description MEDIUMTEXT NULL,
    customer_guidance  MEDIUMTEXT NULL,
    typical_issues     MEDIUMTEXT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    seo_title       VARCHAR(190) NULL,
    seo_description VARCHAR(320) NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sc_slug (slug),
    KEY idx_sc_parent (parent_id),
    KEY idx_sc_active (is_active),
    CONSTRAINT fk_sc_parent FOREIGN KEY (parent_id) REFERENCES service_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_category_requirements (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED NOT NULL,
    requirement   VARCHAR(190) NOT NULL,
    is_mandatory  TINYINT(1) NOT NULL DEFAULT 0,
    notes         VARCHAR(500) NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_scr_category (category_id),
    CONSTRAINT fk_scr_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
