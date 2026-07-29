-- Email clients need a release-consistent URL served by the application.
-- Repair any drafts opened between migrations 074 and this release.

UPDATE notifications
SET body = REPLACE(
        body,
        'https://vanassist.com.au/assets/img/email-campaigns/',
        'https://vanassist.com.au/runtime-assets/img/'
    ),
    updated_at = NOW()
WHERE body LIKE '%https://vanassist.com.au/assets/img/email-campaigns/%';
