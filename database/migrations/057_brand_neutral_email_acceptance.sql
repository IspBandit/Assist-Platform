-- COM-001: preserve administrator edits while removing stale VanAssist wording
-- from templates that are used by every active brand.

UPDATE email_templates
SET subject = REPLACE(subject, 'VanAssist', '{{brand_name}}'),
    html_body = REPLACE(html_body, 'VanAssist', '{{brand_name}}'),
    text_body = REPLACE(text_body, 'VanAssist', '{{brand_name}}'),
    updated_at = NOW()
WHERE template_key IN (
    'email_verification',
    'password_reset',
    'provider_invitation',
    'provider_application_received',
    'provider_approved',
    'provider_rejected'
);

-- Post-activation probes use the normal immutable brand queue path. They are
-- intentionally idempotent and deliver to the owner-controlled operations
-- mailbox so dedicated Exchange mailbox attribution can be externally proven.
INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 1, 'vanassist_dedicated_mailbox_probe_20260728', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'VanAssist dedicated sender acceptance 20260728',
       '<p>VanAssist application delivery probe after shared-mailbox activation.</p>',
       'VanAssist application delivery probe after shared-mailbox activation.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'vanassist_dedicated_mailbox_probe_20260728');

INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 2, 'towsmart_dedicated_mailbox_probe_20260728', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'TowSmart dedicated sender acceptance 20260728',
       '<p>TowSmart application delivery probe after shared-mailbox activation.</p>',
       'TowSmart application delivery probe after shared-mailbox activation.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'towsmart_dedicated_mailbox_probe_20260728');

INSERT INTO email_queue (brand_id, template_key, recipient_email, recipient_name, subject, html_body, text_body, status, scheduled_at, created_at)
SELECT 3, 'trailerwise_dedicated_mailbox_probe_20260728', 'operations@vanassist.com.au', 'Assist Platform Operations',
       'TrailerWise dedicated sender acceptance 20260728',
       '<p>TrailerWise application delivery probe after shared-mailbox activation.</p>',
       'TrailerWise application delivery probe after shared-mailbox activation.',
       'pending', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM email_queue WHERE template_key = 'trailerwise_dedicated_mailbox_probe_20260728');
