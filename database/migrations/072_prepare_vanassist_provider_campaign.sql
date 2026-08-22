-- Retire the discontinued custom provider-ad-graphic offer. Historical records
-- remain intact for audit, but no further offer or delivery email can be sent.
UPDATE email_templates
SET is_enabled = 0, updated_at = NOW()
WHERE template_key IN (
    'provider_founding_graphic_unlocked',
    'provider_founding_graphic_delivered'
);

UPDATE email_templates
SET html_body = REPLACE(html_body, '{{founding_offer_line}}', ''),
    text_body = REPLACE(COALESCE(text_body, ''), '{{founding_offer_text}}', ''),
    updated_at = NOW()
WHERE template_key = 'provider_claim_invite';

-- Create a review-only VanAssist provider campaign. Delivery remains gated by
-- internal test, 25-recipient pilot, 50/day and 100/day stages, and the audience
-- resolver admits only providers with documented marketing consent evidence.
INSERT INTO notifications (
    brand_id,
    title,
    body,
    channel,
    audience_type,
    status,
    delivery_stage,
    recipient_count,
    scheduled_at,
    created_by,
    created_at,
    updated_at
)
SELECT
    1,
    'Please check how travellers find your business on VanAssist',
    '<p>Hi,</p><p>Caravan trouble a long way from home is nobody''s preferred sightseeing activity.</p><p>VanAssist helps Australian caravan and road travellers find relevant nearby services. We have a listing for your business and would like you to check that the location, contact details, service area and services shown are accurate.</p><p><a href="https://vanassist.com.au/for-providers">Review VanAssist provider information</a></p><p>Claiming a listing lets you correct its details and decide how travellers can contact you. Please add only services your business genuinely provides.</p>',
    'email',
    'providers',
    'draft',
    'draft',
    0,
    NULL,
    NULL,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM notifications
    WHERE brand_id = 1
      AND title = 'Please check how travellers find your business on VanAssist'
);
