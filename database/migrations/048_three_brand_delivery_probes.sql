-- COM-001 external acceptance: enqueue one idempotent production delivery
-- probe for each public brand through the normal transactional email queue.
-- All messages go only to the owner-controlled operations mailbox.

INSERT INTO email_queue (
    brand_id, template_key, recipient_email, recipient_name, subject,
    html_body, text_body, status, scheduled_at, created_at
)
SELECT
    1,
    'vanassist_delivery_probe_20260726',
    'operations@vanassist.com.au',
    'Assist Platform Operations',
    'VanAssist transactional email delivery verified',
    '<p>This controlled message verifies VanAssist transactional email delivery through the production queue. Replies use the VanAssist support identity.</p>',
    'This controlled message verifies VanAssist transactional email delivery through the production queue. Replies use the VanAssist support identity.',
    'pending', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM email_queue
    WHERE template_key = 'vanassist_delivery_probe_20260726'
);

INSERT INTO email_queue (
    brand_id, template_key, recipient_email, recipient_name, subject,
    html_body, text_body, status, scheduled_at, created_at
)
SELECT
    2,
    'towsmart_delivery_probe_20260726',
    'operations@vanassist.com.au',
    'Assist Platform Operations',
    'TowSmart transactional email delivery verified',
    '<p>This controlled message verifies TowSmart transactional email delivery through the production queue. Replies use the TowSmart support identity.</p>',
    'This controlled message verifies TowSmart transactional email delivery through the production queue. Replies use the TowSmart support identity.',
    'pending', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM email_queue
    WHERE template_key = 'towsmart_delivery_probe_20260726'
);

INSERT INTO email_queue (
    brand_id, template_key, recipient_email, recipient_name, subject,
    html_body, text_body, status, scheduled_at, created_at
)
SELECT
    3,
    'trailerwise_delivery_probe_20260726',
    'operations@vanassist.com.au',
    'Assist Platform Operations',
    'TrailerWise transactional email delivery verified',
    '<p>This controlled message verifies TrailerWise transactional email delivery through the production queue. Replies use the TrailerWise support identity.</p>',
    'This controlled message verifies TrailerWise transactional email delivery through the production queue. Replies use the TrailerWise support identity.',
    'pending', NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM email_queue
    WHERE template_key = 'trailerwise_delivery_probe_20260726'
);
