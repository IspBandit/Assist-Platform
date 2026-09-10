-- Retire the removed LocalTorque runtime and transfer provider-pack progress
-- to VanAssist-owned task and setting keys without deleting canonical providers.

UPDATE brands SET status='disabled', updated_at=NOW() WHERE brand_key='localtorque';
DELETE FROM brand_domains WHERE brand_id=(SELECT id FROM brands WHERE brand_key='localtorque');
UPDATE provider_brand_listings
SET status='suspended', search_visible=0, updated_at=NOW()
WHERE brand_id=(SELECT id FROM brands WHERE brand_key='localtorque');

INSERT INTO site_settings (setting_key, setting_value, setting_group, value_type, updated_at)
SELECT 'import_provider_pack_fp', setting_value, setting_group, value_type, NOW()
FROM site_settings WHERE setting_key='import_localtorque_fp'
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW();
INSERT INTO site_settings (setting_key, setting_value, setting_group, value_type, updated_at)
SELECT 'import_provider_pack_offset', setting_value, setting_group, value_type, NOW()
FROM site_settings WHERE setting_key='import_localtorque_offset'
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=NOW();
DELETE FROM site_settings WHERE setting_key IN ('import_localtorque_fp','import_localtorque_offset');

INSERT INTO scheduled_tasks (task_key, description, last_status)
VALUES ('import_vanassist_provider_pack', 'Import the authoritative VanAssist provider pack in resumable batches', 'never')
ON DUPLICATE KEY UPDATE description=VALUES(description);
DELETE FROM scheduled_tasks WHERE task_key='import_localtorque_pack';
