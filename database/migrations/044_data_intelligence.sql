-- Data Intelligence sits above connector ingestion. It stores optional
-- population facts and actionable, auditable coverage tasks; provider metrics
-- remain derived from canonical provider/listing records.

CREATE TABLE data_intelligence_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_key VARCHAR(80) NOT NULL,
    name VARCHAR(150) NOT NULL,
    source_class VARCHAR(255) NOT NULL,
    status ENUM('active','disabled','error') NOT NULL DEFAULT 'active',
    settings_json JSON NULL,
    last_refreshed_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_intelligence_source_key (source_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE locality_population_statistics (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    town_id INT UNSIGNED NOT NULL,
    population INT UNSIGNED NOT NULL,
    reference_year SMALLINT UNSIGNED NULL,
    source_key VARCHAR(80) NOT NULL,
    source_reference VARCHAR(500) NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 100,
    imported_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_locality_population_source (town_id, source_key, reference_year),
    KEY idx_locality_population_value (population),
    CONSTRAINT fk_locality_population_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE,
    CONSTRAINT chk_locality_population_confidence CHECK (confidence BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_intelligence_tasks (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    state_id INT UNSIGNED NULL,
    region_id INT UNSIGNED NULL,
    town_id INT UNSIGNED NULL,
    task_type ENUM('coverage_import','verification_campaign','data_quality_review','provider_outreach') NOT NULL,
    title VARCHAR(190) NOT NULL,
    rationale VARCHAR(500) NOT NULL,
    opportunity_score DECIMAL(5,2) NOT NULL DEFAULT 0,
    priority ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
    status ENUM('open','in_progress','completed','dismissed') NOT NULL DEFAULT 'open',
    source_key VARCHAR(80) NOT NULL DEFAULT 'provider_coverage',
    context_json JSON NULL,
    assigned_to INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_intelligence_tasks_queue (brand_id, status, priority, opportunity_score),
    KEY idx_intelligence_tasks_location (state_id, region_id, town_id),
    CONSTRAINT fk_intelligence_task_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_intelligence_task_category FOREIGN KEY (category_id) REFERENCES brand_provider_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_intelligence_task_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE SET NULL,
    CONSTRAINT fk_intelligence_task_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_intelligence_task_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_intelligence_task_assignee FOREIGN KEY (assigned_to) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_intelligence_task_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT chk_intelligence_task_score CHECK (opportunity_score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO data_intelligence_sources (source_key,name,source_class,status,settings_json,created_at)
VALUES ('provider_coverage','Canonical provider coverage','App\\Platform\\DataIntelligence\\Sources\\ProviderCoverageSource','active',JSON_OBJECT(),NOW());

INSERT IGNORE INTO permissions (slug,name,perm_group,created_at) VALUES
('data_intelligence.view','View Data Intelligence dashboards','platform',NOW()),
('data_intelligence.manage','Create and manage Data Intelligence tasks','platform',NOW());

INSERT IGNORE INTO role_permissions (role_id,permission_id,created_at)
SELECT r.id,p.id,NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('platform-administrator','administrator') AND p.slug LIKE 'data_intelligence.%';
