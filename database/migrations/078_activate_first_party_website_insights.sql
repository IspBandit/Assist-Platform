-- Activate the owner-approved, privacy-disclosed first-party Website Insights.
-- This records anonymous sessions and aggregate website behaviour only; the
-- implementation deliberately excludes analytics IP storage, staff and bots.

INSERT INTO site_settings (setting_key, setting_value, setting_group, value_type, updated_at)
VALUES ('analytics_enabled', '1', 'analytics', 'boolean', NOW())
ON DUPLICATE KEY UPDATE
    setting_value = VALUES(setting_value),
    setting_group = VALUES(setting_group),
    value_type = VALUES(value_type),
    updated_at = VALUES(updated_at);

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES (
    'demand_analytics',
    1,
    'Enable privacy-conscious website demand, provider-interest and outcome analytics.',
    NOW()
)
ON DUPLICATE KEY UPDATE
    is_enabled = VALUES(is_enabled),
    description = VALUES(description),
    updated_at = VALUES(updated_at);

UPDATE content_pages
SET body = CONCAT(
        body,
        '<h2>Website analytics</h2>',
        '<p>We use privacy-conscious first-party analytics to count visits and understand pages viewed, searches, no-result demand and provider-interest actions. A randomly generated session identifier is used for aggregate reporting. Analytics does not store visitor IP addresses or attempt to identify anonymous visitors. Staff activity and recognised automated traffic are excluded. Security logs are separate and may retain technical information needed to protect the service.</p>'
    ),
    updated_at = NOW()
WHERE page_key = 'privacy'
  AND body NOT LIKE '%privacy-conscious first-party analytics%';
