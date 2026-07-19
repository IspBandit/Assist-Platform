-- Founding launch-town providers: free ad graphic offer (claim + verify).

CREATE TABLE provider_promotions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NOT NULL,
    promotion_type  VARCHAR(40) NOT NULL DEFAULT 'founding_graphic',
    status          ENUM('eligible','requested','in_progress','delivered','cancelled') NOT NULL DEFAULT 'eligible',
    headline        VARCHAR(120) NULL,
    tagline         VARCHAR(200) NULL,
    brief_notes     TEXT NULL,
    logo_path           VARCHAR(255) NULL,
    image_path_desktop  VARCHAR(255) NULL,
    image_path_mobile   VARCHAR(255) NULL,
    eligible_at     DATETIME NOT NULL,
    requested_at    DATETIME NULL,
    delivered_at    DATETIME NULL,
    delivered_by    INT UNSIGNED NULL,
    created_at      DATETIME NOT NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_promotion_type (provider_id, promotion_type),
    KEY idx_provider_promotions_status (status),
    CONSTRAINT fk_provider_promotions_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_promotions_delivered_by FOREIGN KEY (delivered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
