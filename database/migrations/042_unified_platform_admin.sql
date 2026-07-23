-- Unified Assist Platform administration and secure cross-domain session handoff.

CREATE TABLE admin_brand_handoff_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    token_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    source_brand_id INT UNSIGNED NOT NULL,
    target_brand_id INT UNSIGNED NOT NULL,
    return_path VARCHAR(255) NOT NULL DEFAULT '/admin',
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_brand_handoff_token (token_hash),
    KEY idx_admin_brand_handoff_expiry (expires_at, consumed_at),
    KEY idx_admin_brand_handoff_user (user_id, created_at),
    CONSTRAINT fk_admin_handoff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_admin_handoff_source_brand FOREIGN KEY (source_brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_admin_handoff_target_brand FOREIGN KEY (target_brand_id) REFERENCES brands (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE social_media_assets
    ADD COLUMN template_key VARCHAR(60) NOT NULL DEFAULT 'editorial' AFTER intention,
    ADD COLUMN campaign_name VARCHAR(120) NULL AFTER template_key,
    ADD KEY idx_social_assets_campaign (brand_id, campaign_name, template_key);

INSERT IGNORE INTO roles (slug, name, level, description, created_at) VALUES
('platform-administrator', 'Platform Administrator', 90, 'Platform-wide operational administration without super-admin recovery powers.', NOW()),
('brand-administrator', 'Brand Administrator', 70, 'Administration limited to explicitly assigned brands.', NOW()),
('editor', 'Editor', 45, 'Brand-scoped content and publishing management.', NOW()),
('support', 'Support', 45, 'Customer and provider support workflows.', NOW()),
('finance', 'Finance', 60, 'Billing, finance and revenue reporting.', NOW()),
('marketing', 'Marketing', 45, 'Campaigns, Social Studio, email and marketing analytics.', NOW());

INSERT IGNORE INTO permissions (slug, name, perm_group, created_at) VALUES
('platform.control', 'Manage platform control centre', 'platform', NOW()),
('platform.health', 'View platform health', 'platform', NOW()),
('brands.switch', 'Switch authorised admin brand', 'platform', NOW()),
('brands.manage', 'Manage brand configuration', 'platform', NOW()),
('social.manage', 'Manage Social Studio campaigns', 'content', NOW()),
('marketing.manage', 'Manage marketing campaigns', 'marketing', NOW()),
('support.manage', 'Manage support workflows', 'support', NOW());

-- Grant platform-wide operators all defined permissions. Super administrators
-- retain the application-level recovery bypass; this explicit mapping keeps
-- platform administrators auditable and compatible with permission reports.
INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('platform-administrator', 'administrator');

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug = 'brand-administrator' AND p.slug IN (
    'users.manage','providers.manage','providers.approve','prospects.manage','documents.verify',
    'customers.manage','requests.manage','requests.match','locations.manage','categories.manage',
    'parks.manage','content.manage','content.moderate','email.manage','notifications.send',
    'seo.manage','reports.view','demand.view','demand.export','complaints.manage','settings.manage',
    'feature_flags.manage','billing.manage','social.manage','marketing.manage','support.manage','brands.switch'
);

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE (r.slug = 'editor' AND p.slug IN ('content.manage','seo.manage'))
   OR (r.slug = 'support' AND p.slug IN ('providers.manage','customers.manage','requests.manage','complaints.manage','support.manage'))
   OR (r.slug = 'finance' AND p.slug IN ('billing.manage','reports.view','owner_finance.view','owner_finance.view_reports','owner_finance.export'))
   OR (r.slug = 'marketing' AND p.slug IN ('content.manage','email.manage','notifications.send','reports.view','social.manage','marketing.manage'));
