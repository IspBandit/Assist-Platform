-- Provenance, coverage confidence, and self-serve claim tokens for unclaimed listings.

ALTER TABLE providers
    ADD COLUMN source_type ENUM('manual','national','osm','locality','prospect') NULL AFTER source_url,
    ADD COLUMN coverage_confidence ENUM('curated','inferred','statewide') NULL AFTER source_type;

CREATE TABLE provider_claim_tokens (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    email       VARCHAR(190) NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used_at     DATETIME NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pct_provider (provider_id),
    KEY idx_pct_email (email),
    CONSTRAINT fk_pct_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_pct_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
