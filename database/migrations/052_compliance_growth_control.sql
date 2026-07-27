-- EXP-006 / DATA-009 / COM-006 / COM-007 / CORE-010
-- Saved compliance journeys, consented alerts and handoffs, verified provider
-- capabilities, transparent campaign budgets and regulatory operations.

CREATE TABLE regulatory_journeys (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    garage_asset_id INT UNSIGNED NULL,
    brand_id INT UNSIGNED NOT NULL,
    jurisdiction_code CHAR(3) NOT NULL,
    vehicle_class VARCHAR(40) NOT NULL,
    document_kind VARCHAR(50) NOT NULL,
    intention ENUM('understand','inspect','modify','register','tow','travel') NOT NULL,
    title VARCHAR(190) NOT NULL,
    limitation_text VARCHAR(500) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_regulatory_journey_owner (user_id, created_at),
    CONSTRAINT fk_regulatory_journey_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_regulatory_journey_asset FOREIGN KEY (garage_asset_id) REFERENCES garage_assets (id) ON DELETE SET NULL,
    CONSTRAINT fk_regulatory_journey_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_alert_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    jurisdiction_code CHAR(3) NOT NULL,
    vehicle_class VARCHAR(40) NOT NULL DEFAULT '',
    document_kind VARCHAR(50) NOT NULL DEFAULT '',
    status ENUM('active','paused','unsubscribed') NOT NULL DEFAULT 'active',
    email_enabled TINYINT(1) NOT NULL DEFAULT 1,
    consented_at DATETIME NOT NULL,
    consent_source VARCHAR(80) NOT NULL DEFAULT 'guided_rules',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regulatory_alert_scope (user_id, brand_id, jurisdiction_code, vehicle_class, document_kind),
    KEY idx_regulatory_alert_delivery (status, jurisdiction_code, vehicle_class, document_kind),
    CONSTRAINT fk_regulatory_alert_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_regulatory_alert_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_alert_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    subscription_id BIGINT UNSIGNED NOT NULL,
    document_id INT UNSIGNED NOT NULL,
    status ENUM('queued','sent','failed','suppressed') NOT NULL,
    reason VARCHAR(255) NULL,
    queued_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_regulatory_alert_document (subscription_id, document_id),
    KEY idx_regulatory_alert_audit (status, created_at),
    CONSTRAINT fk_regulatory_alert_delivery_subscription FOREIGN KEY (subscription_id) REFERENCES regulatory_alert_subscriptions (id) ON DELETE CASCADE,
    CONSTRAINT fk_regulatory_alert_delivery_document FOREIGN KEY (document_id) REFERENCES regulatory_documents (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regulatory_provider_handoffs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    journey_id BIGINT UNSIGNED NULL,
    garage_asset_id INT UNSIGNED NULL,
    brand_id INT UNSIGNED NOT NULL,
    destination_brand_id INT UNSIGNED NOT NULL,
    context_json JSON NOT NULL,
    disclosed_fields_json JSON NOT NULL,
    consent_text VARCHAR(500) NOT NULL,
    consented_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_regulatory_handoff_owner (user_id, created_at),
    CONSTRAINT fk_regulatory_handoff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_regulatory_handoff_journey FOREIGN KEY (journey_id) REFERENCES regulatory_journeys (id) ON DELETE SET NULL,
    CONSTRAINT fk_regulatory_handoff_asset FOREIGN KEY (garage_asset_id) REFERENCES garage_assets (id) ON DELETE SET NULL,
    CONSTRAINT fk_regulatory_handoff_source_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_regulatory_handoff_destination_brand FOREIGN KEY (destination_brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_capability_credentials (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    capability_key VARCHAR(80) NOT NULL,
    capability_label VARCHAR(140) NOT NULL,
    jurisdiction_code CHAR(3) NULL,
    evidence_document_id INT UNSIGNED NULL,
    verification_status ENUM('pending','verified','rejected','expired','withdrawn') NOT NULL DEFAULT 'pending',
    valid_from DATE NULL,
    valid_until DATE NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_capability (provider_id, brand_id, capability_key, jurisdiction_code),
    KEY idx_provider_capability_public (brand_id, verification_status, capability_key, valid_until),
    CONSTRAINT fk_provider_capability_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_capability_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_provider_capability_document FOREIGN KEY (evidence_document_id) REFERENCES provider_documents (id) ON DELETE SET NULL,
    CONSTRAINT fk_provider_capability_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE advertising_campaigns
    ADD COLUMN objective ENUM('awareness','provider_profile','phone','website','enquiry') NOT NULL DEFAULT 'provider_profile' AFTER name,
    ADD COLUMN daily_budget_cents INT UNSIGNED NULL AFTER mobile_image_path,
    ADD COLUMN total_budget_cents INT UNSIGNED NULL AFTER daily_budget_cents,
    ADD COLUMN billing_model ENUM('cpc','cpm') NOT NULL DEFAULT 'cpc' AFTER total_budget_cents,
    ADD COLUMN unit_price_cents INT UNSIGNED NULL AFTER billing_model;

CREATE TABLE advertising_campaign_daily_metrics (
    campaign_id INT UNSIGNED NOT NULL,
    metric_date DATE NOT NULL,
    impressions INT UNSIGNED NOT NULL DEFAULT 0,
    clicks INT UNSIGNED NOT NULL DEFAULT 0,
    conversions INT UNSIGNED NOT NULL DEFAULT 0,
    spend_cents INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NULL,
    PRIMARY KEY (campaign_id, metric_date),
    CONSTRAINT fk_ad_campaign_metric_campaign FOREIGN KEY (campaign_id) REFERENCES advertising_campaigns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name, perm_group, created_at) VALUES
('regulatory.manage','Manage regulatory sources','content',NOW()),
('campaigns.manage','Manage provider campaigns','marketing',NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name), perm_group=VALUES(perm_group);

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('super-administrator','administrator','platform-administrator')
AND p.slug IN ('regulatory.manage','campaigns.manage');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('brand-administrator','editor') AND p.slug='regulatory.manage';

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('brand-administrator','marketing') AND p.slug='campaigns.manage';

INSERT INTO scheduled_tasks (task_key,description,last_status) VALUES
('regulatory_alerts','Queue consented official-source change alerts','never')
ON DUPLICATE KEY UPDATE description=VALUES(description);
