-- COM-002: review-first organisation outreach without mixing club, association,
-- media or partnership contacts into provider/customer marketing audiences.

CREATE TABLE organisation_outreach_contacts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_name VARCHAR(190) NOT NULL,
    organisation_type ENUM(
        'club','club_federation','industry_association','manufacturer','dealer_network',
        'rental_fleet','park_network','publication','tourism_body','touring_association','other'
    ) NOT NULL,
    coverage VARCHAR(120) NULL,
    state_code VARCHAR(3) NULL,
    website_url VARCHAR(500) NOT NULL,
    contact_role VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    source_url VARCHAR(500) NOT NULL,
    source_checked_at DATE NOT NULL,
    publication_context VARCHAR(500) NOT NULL,
    relevance_reason VARCHAR(500) NOT NULL,
    consent_basis ENUM('express_written','express_phone','express_web','inferred_role_relevant') NULL,
    consent_evidence VARCHAR(1000) NULL,
    no_unsolicited_warning TINYINT(1) NOT NULL DEFAULT 0,
    personal_or_ambiguous TINYINT(1) NOT NULL DEFAULT 0,
    review_status ENUM('research','held','eligible','do_not_contact') NOT NULL DEFAULT 'research',
    reviewed_at DATETIME NULL,
    reviewed_by INT UNSIGNED NULL,
    outcome_status ENUM('not_contacted','sent','replied','interested','shared','declined','bounced','opted_out') NOT NULL DEFAULT 'not_contacted',
    outcome_notes VARCHAR(1000) NULL,
    next_follow_up_at DATETIME NULL,
    last_contacted_at DATETIME NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_organisation_outreach_email (email),
    KEY idx_organisation_outreach_review (review_status, source_checked_at),
    KEY idx_organisation_outreach_type (organisation_type, state_code),
    CONSTRAINT fk_organisation_outreach_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE organisation_outreach_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    organisation_contact_id BIGINT UNSIGNED NOT NULL,
    notification_id INT UNSIGNED NULL,
    notification_recipient_id BIGINT UNSIGNED NULL,
    event_type ENUM('reviewed','queued','sent','failed','suppressed','replied','interested','shared','declined','bounced','opted_out','follow_up') NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    notes VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_outreach_event_contact (organisation_contact_id, created_at),
    KEY idx_outreach_event_campaign (notification_id, event_type),
    CONSTRAINT fk_outreach_event_contact FOREIGN KEY (organisation_contact_id) REFERENCES organisation_outreach_contacts (id) ON DELETE CASCADE,
    CONSTRAINT fk_outreach_event_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE SET NULL,
    CONSTRAINT fk_outreach_event_recipient FOREIGN KEY (notification_recipient_id) REFERENCES notification_recipients (id) ON DELETE SET NULL,
    CONSTRAINT fk_outreach_event_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE notifications
    ADD COLUMN organisation_type VARCHAR(40) NULL AFTER provider_brand_category_id,
    MODIFY COLUMN campaign_type ENUM(
        'general_marketing','provider_marketing','directory_accuracy','organisation_outreach'
    ) NOT NULL DEFAULT 'general_marketing',
    MODIFY COLUMN audience_type ENUM(
        'town','region','category','providers','provider_category','customers_open','all','organisations'
    ) NOT NULL;

ALTER TABLE notification_recipients
    ADD COLUMN organisation_contact_id BIGINT UNSIGNED NULL AFTER provider_id,
    MODIFY COLUMN compliance_basis ENUM(
        'marketing_consent','factual_directory_record','role_relevant_publication'
    ) NULL,
    ADD KEY idx_notification_recipient_organisation (notification_id, organisation_contact_id),
    ADD CONSTRAINT fk_notification_recipient_organisation
        FOREIGN KEY (organisation_contact_id) REFERENCES organisation_outreach_contacts (id) ON DELETE SET NULL;
