-- Owner-approved search launch: allow public pages from the three active brands
-- to be indexed. Existing page-level noindex directives and robots exclusions
-- continue to protect admin, account, provider, installation and billing routes.
INSERT INTO site_settings (
    setting_key,
    setting_value,
    setting_group,
    value_type,
    updated_at
) VALUES (
    'seo_allow_indexing',
    '1',
    'seo',
    'boolean',
    NOW()
)
ON DUPLICATE KEY UPDATE
    setting_value = '1',
    setting_group = 'seo',
    value_type = 'boolean',
    updated_at = NOW();
