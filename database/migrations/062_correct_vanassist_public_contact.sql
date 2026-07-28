-- Correct only the obsolete public contact address. Transport credentials are
-- deliberately untouched because SMTP/Graph authentication is independent.

UPDATE site_settings
SET setting_value = 'support@vanassist.com.au', updated_at = NOW()
WHERE setting_key = 'contact_email'
  AND setting_value IN ('vanassist@condrendigital.com.au', 'admin@condrendigital.com.au');

UPDATE content_pages
SET body = REPLACE(body, 'vanassist@condrendigital.com.au', 'support@vanassist.com.au'),
    updated_at = NOW()
WHERE body LIKE '%vanassist@condrendigital.com.au%';
