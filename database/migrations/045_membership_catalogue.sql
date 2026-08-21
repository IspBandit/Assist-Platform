-- CORE-004: agreed shared membership catalogue.
-- Additive and fail-closed: this does not enable billing, gateways or checkout.
-- Existing provider plan assignments are intentionally preserved for review.

INSERT INTO billing_plans
    (internal_name, public_name, slug, description, monthly_price_cents, annual_price_cents,
     currency, gst_inclusive, trial_days, display_order, is_active, is_public,
     signup_available, is_legacy, is_recommended, terms_summary, created_at, updated_at)
VALUES
    ('Launch Access', 'Launch Access', 'launch_access', 'Temporary full-value access while marketplace value is built. No payment method is required and no charge is created.', 0, 0, 'AUD', 1, 0, 10, 1, 0, 0, 0, 0, 'Temporary no-charge launch access. Providers later choose a membership or move safely to Free Listing.', NOW(), NOW()),
    ('Free Listing', 'Free Listing', 'free_listing', 'A permanent useful business listing with core profile, contact and discovery capabilities.', 0, 0, 'AUD', 1, 0, 20, 1, 1, 1, 0, 0, 'Free ongoing listing. No payment method required.', NOW(), NOW()),
    ('Founding Verified', 'Founding Verified', 'founding_verified', 'Protected early-provider offer with verified membership capabilities while continuously active.', 1000, 10000, 'AUD', 1, 0, 30, 1, 1, 0, 0, 0, '$10 monthly or $100 annual while continuously active. Charging remains unavailable until commercial acceptance.', NOW(), NOW()),
    ('Verified Provider', 'Verified Provider', 'verified_provider', 'Core verified membership with expanded profile, matching, reporting and multi-brand eligibility.', 1500, 15000, 'AUD', 1, 0, 40, 1, 1, 0, 0, 1, '$15 monthly or $150 annual. Charging remains unavailable until commercial acceptance.', NOW(), NOW()),
    ('Featured Provider', 'Featured Provider', 'featured_provider', 'Verified capabilities plus clearly labelled increased visibility that never overrides relevance or safety.', 2900, 29000, 'AUD', 1, 0, 50, 1, 1, 0, 0, 0, '$29 monthly or $290 annual. Featured placement is labelled. Charging remains unavailable until commercial acceptance.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    internal_name = VALUES(internal_name), public_name = VALUES(public_name), description = VALUES(description),
    monthly_price_cents = VALUES(monthly_price_cents), annual_price_cents = VALUES(annual_price_cents),
    display_order = VALUES(display_order), is_active = VALUES(is_active), is_public = VALUES(is_public),
    signup_available = VALUES(signup_available), is_recommended = VALUES(is_recommended),
    terms_summary = VALUES(terms_summary), updated_at = NOW();

INSERT INTO billing_plan_prices (plan_id, billing_interval, amount_cents, currency, gst_inclusive, is_active, created_at)
SELECT p.id, price.billing_interval, price.amount_cents, 'AUD', 1, 1, NOW()
FROM billing_plans p
CROSS JOIN (
    SELECT 'launch_access' slug, 'monthly' billing_interval, 0 amount_cents UNION ALL
    SELECT 'launch_access', 'annual', 0 UNION ALL SELECT 'free_listing', 'monthly', 0 UNION ALL
    SELECT 'free_listing', 'annual', 0 UNION ALL SELECT 'founding_verified', 'monthly', 1000 UNION ALL
    SELECT 'founding_verified', 'annual', 10000 UNION ALL SELECT 'verified_provider', 'monthly', 1500 UNION ALL
    SELECT 'verified_provider', 'annual', 15000 UNION ALL SELECT 'featured_provider', 'monthly', 2900 UNION ALL
    SELECT 'featured_provider', 'annual', 29000
) price ON price.slug = p.slug
WHERE NOT EXISTS (
    SELECT 1 FROM billing_plan_prices existing
    WHERE existing.plan_id = p.id AND existing.billing_interval = price.billing_interval
);

INSERT INTO billing_plan_limits (plan_id, limit_key, limit_value, created_at)
SELECT p.id, limits.limit_key,
    CASE
        WHEN limits.limit_key IN ('maximum_service_categories', 'maximum_service_areas') AND p.slug <> 'free_listing' THEN NULL
        WHEN limits.limit_key = 'maximum_active_runs' AND p.slug = 'featured_provider' THEN NULL
        WHEN limits.limit_key = 'maximum_active_runs' THEN IF(p.slug = 'free_listing', 1, 5)
        WHEN limits.limit_key IN ('maximum_service_categories', 'maximum_service_areas') THEN IF(p.slug = 'free_listing', IF(limits.limit_key = 'maximum_service_categories', 3, 2), NULL)
        WHEN limits.limit_key = 'maximum_provider_users' THEN IF(p.slug = 'free_listing', 1, IF(p.slug = 'featured_provider', 5, 3))
        WHEN limits.limit_key = 'maximum_branches' THEN IF(p.slug = 'free_listing', 1, IF(p.slug = 'featured_provider', 5, 3))
    END,
    NOW()
FROM billing_plans p
CROSS JOIN (
    SELECT 'maximum_active_runs' limit_key UNION ALL SELECT 'maximum_service_categories' UNION ALL
    SELECT 'maximum_service_areas' UNION ALL SELECT 'maximum_provider_users' UNION ALL SELECT 'maximum_branches'
) limits
WHERE p.slug IN ('launch_access','free_listing','founding_verified','verified_provider','featured_provider')
ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value);

INSERT INTO billing_plan_features (plan_id, feature_key, is_enabled, created_at)
SELECT p.id, features.feature_key,
    CASE
        WHEN p.slug = 'free_listing' THEN features.feature_key = 'can_create_service_run'
        WHEN features.feature_key IN ('can_access_api','can_use_custom_branding') THEN 0
        WHEN features.feature_key IN ('can_be_featured','can_use_priority_matching') THEN p.slug = 'featured_provider'
        ELSE 1
    END,
    NOW()
FROM billing_plans p
JOIN (
    SELECT 'can_create_service_run' feature_key UNION ALL SELECT 'can_view_full_matching_requests' UNION ALL
    SELECT 'can_access_demand_reports' UNION ALL SELECT 'can_be_featured' UNION ALL SELECT 'can_export_data' UNION ALL
    SELECT 'can_use_priority_matching' UNION ALL SELECT 'can_create_caravan_park_service_days' UNION ALL
    SELECT 'can_access_advanced_statistics' UNION ALL SELECT 'can_use_custom_branding' UNION ALL SELECT 'can_access_api'
) features
WHERE p.slug IN ('launch_access','free_listing','founding_verified','verified_provider','featured_provider')
ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled);

-- Legacy catalogue entries remain for referential integrity but cannot accept new signups.
UPDATE billing_plans
SET is_active = 0, is_public = 0, signup_available = 0, is_legacy = 1, updated_at = NOW()
WHERE slug IN ('founding_free', 'free', 'standard', 'professional', 'enterprise');
