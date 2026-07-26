-- COM-001 shared-mailbox activation acceptance. Each idempotent probe uses the
-- normal queue and immutable brand ID. Explicit mailbox permission/not-found
-- rejections retain delivery through the approved operations fallback.

INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 1, 'vanassist_brand_mailbox_probe_20260727', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'VanAssist branded sender acceptance',
       '<p>This controlled message verifies the dedicated VanAssist Microsoft 365 shared mailbox sender.</p>',
       'This controlled message verifies the dedicated VanAssist Microsoft 365 shared mailbox sender.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'vanassist_brand_mailbox_probe_20260727');

INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 2, 'towsmart_brand_mailbox_probe_20260727', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'TowSmart branded sender acceptance',
       '<p>This controlled message verifies the dedicated TowSmart Microsoft 365 shared mailbox sender.</p>',
       'This controlled message verifies the dedicated TowSmart Microsoft 365 shared mailbox sender.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'towsmart_brand_mailbox_probe_20260727');

INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 3, 'trailerwise_brand_mailbox_probe_20260727', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'TrailerWise branded sender acceptance',
       '<p>This controlled message verifies the dedicated TrailerWise Microsoft 365 shared mailbox sender.</p>',
       'This controlled message verifies the dedicated TrailerWise Microsoft 365 shared mailbox sender.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'trailerwise_brand_mailbox_probe_20260727');
