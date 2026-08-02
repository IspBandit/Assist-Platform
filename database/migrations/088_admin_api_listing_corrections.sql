-- Option B Increment B: listing correction queue for Admin API review.

CREATE TABLE listing_corrections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entity_type ENUM('provider', 'stay', 'facility') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    submitter_name VARCHAR(150) NOT NULL,
    submitter_email VARCHAR(190) NOT NULL,
    field_name VARCHAR(80) NOT NULL,
    proposed_value TEXT NOT NULL,
    current_value TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    reason VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_listing_corrections_status (status, created_at),
    KEY idx_listing_corrections_entity (entity_type, entity_id),
    CONSTRAINT fk_listing_corrections_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
