-- VAN-001 / DATA-014: structured stay facilities and moderated community evidence.
-- Specific facility claims are immutable evidence; public values are resolved in application code.

CREATE TABLE IF NOT EXISTS stay_facility_claims (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id INT UNSIGNED NOT NULL,
    facility_type VARCHAR(60) NOT NULL,
    facility_status ENUM('yes','no','unknown','conditional') NOT NULL DEFAULT 'unknown',
    facility_value VARCHAR(120) NULL,
    details VARCHAR(1000) NULL,
    source_type ENUM('government','operator','admin_verified','user_approved','trusted_import','open_data') NOT NULL,
    source_name VARCHAR(190) NOT NULL,
    source_url VARCHAR(1000) NULL,
    source_record_id VARCHAR(190) NULL,
    source_confidence TINYINT UNSIGNED NOT NULL DEFAULT 50,
    source_specificity ENUM('generic','facility') NOT NULL DEFAULT 'facility',
    contribution_item_id BIGINT UNSIGNED NULL,
    verified_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    superseded_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sfc_park_type (park_id, facility_type, superseded_at),
    KEY idx_sfc_stale (last_seen_at, verified_at),
    KEY idx_sfc_source_record (source_type, source_record_id),
    CONSTRAINT fk_sfc_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facility_contributions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    park_id INT UNSIGNED NOT NULL,
    submitter_user_id INT UNSIGNED NULL,
    submitter_name VARCHAR(150) NULL,
    submitter_email VARCHAR(190) NULL,
    submitter_fingerprint CHAR(64) NOT NULL,
    comment VARCHAR(2000) NULL,
    evidence_url VARCHAR(1000) NULL,
    status ENUM('pending','under_review','approved','partially_approved','rejected','duplicate','superseded','withdrawn') NOT NULL DEFAULT 'pending',
    duplicate_of_id BIGINT UNSIGNED NULL,
    moderator_user_id INT UNSIGNED NULL,
    moderator_notes VARCHAR(2000) NULL,
    moderated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fc_queue (status, created_at),
    KEY idx_fc_park (park_id, created_at),
    CONSTRAINT fk_fc_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE,
    CONSTRAINT fk_fc_user FOREIGN KEY (submitter_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_fc_duplicate FOREIGN KEY (duplicate_of_id) REFERENCES facility_contributions (id) ON DELETE SET NULL,
    CONSTRAINT fk_fc_moderator FOREIGN KEY (moderator_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facility_contribution_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contribution_id BIGINT UNSIGNED NOT NULL,
    facility_type VARCHAR(60) NOT NULL,
    existing_status VARCHAR(20) NULL,
    existing_value VARCHAR(120) NULL,
    suggested_status ENUM('yes','no','unknown','conditional') NOT NULL,
    suggested_value VARCHAR(120) NULL,
    suggested_details VARCHAR(1000) NULL,
    decision ENUM('pending','approved','edited','rejected','duplicate') NOT NULL DEFAULT 'pending',
    resulting_claim_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fci_contribution (contribution_id),
    KEY idx_fci_duplicate (facility_type, suggested_status, suggested_value),
    CONSTRAINT fk_fci_contribution FOREIGN KEY (contribution_id) REFERENCES facility_contributions (id) ON DELETE CASCADE,
    CONSTRAINT fk_fci_claim FOREIGN KEY (resulting_claim_id) REFERENCES stay_facility_claims (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE stay_facility_claims
    ADD CONSTRAINT fk_sfc_contribution_item FOREIGN KEY (contribution_item_id) REFERENCES facility_contribution_items (id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS facility_contribution_confirmations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contribution_id BIGINT UNSIGNED NOT NULL,
    confirmer_user_id INT UNSIGNED NULL,
    confirmer_fingerprint CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_fcc_independent (contribution_id, confirmer_fingerprint),
    CONSTRAINT fk_fcc_contribution FOREIGN KEY (contribution_id) REFERENCES facility_contributions (id) ON DELETE CASCADE,
    CONSTRAINT fk_fcc_user FOREIGN KEY (confirmer_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facility_moderation_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    contribution_id BIGINT UNSIGNED NOT NULL,
    moderator_user_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    previous_status VARCHAR(30) NOT NULL,
    new_status VARCHAR(30) NOT NULL,
    old_value JSON NULL,
    new_value JSON NULL,
    notes VARCHAR(2000) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_fma_contribution (contribution_id, created_at),
    CONSTRAINT fk_fma_contribution FOREIGN KEY (contribution_id) REFERENCES facility_contributions (id) ON DELETE CASCADE,
    CONSTRAINT fk_fma_moderator FOREIGN KEY (moderator_user_id) REFERENCES users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Regression fixture through the normal evidence model, matched to the canonical stay.
-- A missing production park is left untouched; the importer can apply the same claims later.
INSERT INTO stay_facility_claims
    (park_id, facility_type, facility_status, facility_value, details, source_type, source_name,
     source_url, source_record_id, source_confidence, source_specificity, verified_at, last_seen_at, created_at, updated_at)
SELECT cp.id, facts.facility_type, facts.facility_status, facts.facility_value, facts.details,
       'government', 'Queensland Parks and Wildlife Service', cp.source_url,
       CONCAT('qld-parks:griffiths-creek:', facts.facility_type), 100, 'facility', NOW(), NOW(), NOW(), NOW()
FROM caravan_parks cp
JOIN (
    SELECT 'dump_point' AS facility_type, 'yes' AS facility_status, 'portable_toilet_waste_disposal' AS facility_value, 'Portable toilet waste disposal is available.' AS details
    UNION ALL SELECT 'water', 'conditional', 'untreated', 'Water is available and must be treated before drinking.'
    UNION ALL SELECT 'toilets', 'no', NULL, 'No toilets are provided.'
) facts
WHERE LOWER(cp.name) LIKE 'griffiths creek%'
  AND cp.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1 FROM stay_facility_claims existing
      WHERE existing.park_id = cp.id AND existing.source_record_id = CONCAT('qld-parks:griffiths-creek:', facts.facility_type)
  );
