-- =====================================================================
-- 021 Remove the middle name "Christopher" from the operator's name across
-- existing data. Data-only migration. Uses REPLACE of the exact full name so
-- it preserves any surrounding text (e.g. "... t/as VanAssist") and never
-- touches unrelated records that merely contain the word "Christopher".
-- =====================================================================

UPDATE site_settings
   SET setting_value = REPLACE(setting_value, 'Glen Christopher Condren', 'Glen Condren'),
       updated_at = NOW()
 WHERE setting_value LIKE '%Glen Christopher Condren%';

UPDATE tax_settings
   SET setting_value = REPLACE(setting_value, 'Glen Christopher Condren', 'Glen Condren'),
       updated_at = NOW()
 WHERE setting_value LIKE '%Glen Christopher Condren%';

UPDATE content_pages
   SET body = REPLACE(body, 'Glen Christopher Condren', 'Glen Condren'),
       updated_at = NOW()
 WHERE body LIKE '%Glen Christopher Condren%';

UPDATE users
   SET name = REPLACE(name, 'Glen Christopher Condren', 'Glen Condren'),
       updated_at = NOW()
 WHERE name LIKE '%Glen Christopher Condren%';
