-- =====================================================================
-- 020 Update the public site contact email to vanassist@condrendigital.com.au
-- Data-only migration. Conditional, so it never clobbers a value an admin has
-- since customised: it only changes the original seeded default.
-- =====================================================================

UPDATE site_settings
   SET setting_value = 'vanassist@condrendigital.com.au', updated_at = NOW()
 WHERE setting_key = 'contact_email'
   AND setting_value = 'admin@condrendigital.com.au';

-- Refresh the Contact page copy (both the mailto link and the visible text).
UPDATE content_pages
   SET body = REPLACE(body, 'admin@condrendigital.com.au', 'vanassist@condrendigital.com.au'),
       updated_at = NOW()
 WHERE page_key = 'contact'
   AND body LIKE '%admin@condrendigital.com.au%';
