-- Make inbound provider claim requests reviewable and provider verification
-- evidence-backed. No existing provider is auto-claimed or auto-verified.

ALTER TABLE provider_prospects
    ADD COLUMN request_type ENUM('interest','claim','correction') NOT NULL DEFAULT 'interest' AFTER provider_id,
    ADD COLUMN claim_status ENUM('pending','evidence_requested','approved','rejected') NULL AFTER request_type,
    ADD COLUMN authority_evidence TEXT NULL AFTER claim_status,
    ADD COLUMN claim_review_notes TEXT NULL AFTER authority_evidence,
    ADD COLUMN claim_reviewed_by INT UNSIGNED NULL AFTER claim_review_notes,
    ADD COLUMN claim_reviewed_at DATETIME NULL AFTER claim_reviewed_by,
    ADD KEY idx_pp_claim_queue (request_type, claim_status, created_at),
    ADD KEY idx_pp_provider_request (provider_id, request_type, claim_status),
    ADD CONSTRAINT fk_pp_claim_reviewer FOREIGN KEY (claim_reviewed_by) REFERENCES users (id) ON DELETE SET NULL;

ALTER TABLE providers
    ADD COLUMN verification_basis VARCHAR(80) NULL AFTER is_verified,
    ADD COLUMN verification_notes VARCHAR(500) NULL AFTER verification_basis,
    ADD COLUMN verified_by INT UNSIGNED NULL AFTER verification_notes,
    ADD COLUMN verified_at DATETIME NULL AFTER verified_by,
    ADD CONSTRAINT fk_providers_verifier FOREIGN KEY (verified_by) REFERENCES users (id) ON DELETE SET NULL;

INSERT INTO email_templates (template_key,name,subject,html_body,text_body,is_enabled,created_at,updated_at)
VALUES
('provider_claim_request_approved','Approved provider claim request','{{business_name}} — your VanAssist claim request is approved',
 '<h1>Continue your listing claim</h1><p>Hi {{greeting}},</p><p>We reviewed your request to claim <strong>{{business_name}}</strong>. You can now create or connect your provider account and review the listing details.</p><p><a href="{{action_url}}">Continue secure claim</a></p><p>This link expires in {{expiry_days}} days. Claim approval does not mark the business or its services as independently verified.</p>',
 'Hi {{greeting}},\n\nWe reviewed your request to claim {{business_name}}. Continue the secure claim here:\n{{action_url}}\n\nThis link expires in {{expiry_days}} days. Claim approval does not mark the business or its services as independently verified.',1,NOW(),NOW()),
('provider_claim_evidence_requested','Provider claim evidence requested','More information needed for your {{business_name}} claim',
 '<h1>More claim evidence needed</h1><p>We are reviewing your request to claim <strong>{{business_name}}</strong>, but need more information before granting account access.</p><p>{{review_notes}}</p><p>Please reply to this email with the requested evidence. No listing control has been granted.</p>',
 'We are reviewing your request to claim {{business_name}}, but need more information before granting account access.\n\n{{review_notes}}\n\nPlease reply with the requested evidence. No listing control has been granted.',1,NOW(),NOW()),
('provider_claim_request_rejected','Provider claim request rejected','Update on your {{business_name}} claim request',
 '<h1>Claim request update</h1><p>We could not approve your request to claim <strong>{{business_name}}</strong>.</p><p>{{review_notes}}</p><p>No listing control was granted. Reply if you believe this decision used incomplete information.</p>',
 'We could not approve your request to claim {{business_name}}.\n\n{{review_notes}}\n\nNo listing control was granted. Reply if you believe this decision used incomplete information.',1,NOW(),NOW())
ON DUPLICATE KEY UPDATE name=VALUES(name),subject=VALUES(subject),html_body=VALUES(html_body),text_body=VALUES(text_body),is_enabled=1,updated_at=NOW();
