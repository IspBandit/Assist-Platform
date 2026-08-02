-- Polaris Phase 5/3: shareable comparisons + preference persistence helpers.
-- Extends migration 088 preference/saved tables.

CREATE TABLE IF NOT EXISTS polaris_comparisons (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    public_token CHAR(16) NOT NULL,
    model_ids_json JSON NOT NULL,
    user_id INT UNSIGNED NULL,
    title VARCHAR(160) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_compare_token (public_token),
    KEY idx_polaris_compare_user (user_id, created_at),
    CONSTRAINT fk_polaris_compare_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_compare_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
