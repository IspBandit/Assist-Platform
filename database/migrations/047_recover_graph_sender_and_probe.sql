-- COM-001 production recovery: the Graph endpoint must send as the real
-- operations mailbox while brand aliases remain Reply-To identities.
-- Retry only Graph failures and enqueue one idempotent owner-controlled probe.

UPDATE email_queue
SET status = 'pending', attempts = 0, leased_until = NULL,
    lease_token = NULL, next_attempt_at = NULL
WHERE status = 'failed'
  AND last_error LIKE 'Microsoft Graph request failed%';

INSERT INTO email_queue (
    brand_id, template_key, recipient_email, recipient_name, subject,
    html_body, text_body, status, scheduled_at, created_at
)
SELECT
    1,
    'production_delivery_probe_20260726',
    'operations@vanassist.com.au',
    'Assist Platform Operations',
    'Assist Platform email delivery restored',
    '<p>This controlled message confirms that the production transactional email queue is delivering through the approved Microsoft Graph operations mailbox.</p>',
    'This controlled message confirms that the production transactional email queue is delivering through the approved Microsoft Graph operations mailbox.',
    'pending',
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM email_queue
    WHERE template_key = 'production_delivery_probe_20260726'
);
