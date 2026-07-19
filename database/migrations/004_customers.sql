-- =====================================================================
-- 004 Customers and their preferences (extends users)
-- =====================================================================

CREATE TABLE customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    preferred_contact ENUM('email','phone','either') NOT NULL DEFAULT 'either',
    home_town_id    INT UNSIGNED NULL,
    notes           TEXT NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    deleted_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customers_user (user_id),
    KEY idx_customers_town (home_town_id),
    CONSTRAINT fk_customers_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_customers_town FOREIGN KEY (home_town_id) REFERENCES towns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_saved_locations (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id INT UNSIGNED NOT NULL,
    town_id     INT UNSIGNED NOT NULL,
    label       VARCHAR(120) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_csl (customer_id, town_id),
    KEY idx_csl_town (town_id),
    CONSTRAINT fk_csl_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_csl_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_alerts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    customer_id   INT UNSIGNED NOT NULL,
    town_id       INT UNSIGNED NULL,
    region_id     INT UNSIGNED NULL,
    category_id   INT UNSIGNED NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ca_customer (customer_id),
    KEY idx_ca_town (town_id),
    KEY idx_ca_region (region_id),
    KEY idx_ca_category (category_id),
    CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
