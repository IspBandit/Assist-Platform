-- =====================================================================
-- 009 Notifications and the queued email system
-- =====================================================================

CREATE TABLE email_templates (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key VARCHAR(80) NOT NULL,
    name        VARCHAR(150) NOT NULL,
    subject     VARCHAR(255) NOT NULL,
    html_body   MEDIUMTEXT NOT NULL,
    text_body   MEDIUMTEXT NULL,
    is_enabled  TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_et_key (template_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_queue (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key  VARCHAR(80) NULL,
    recipient_email VARCHAR(190) NOT NULL,
    recipient_name VARCHAR(150) NULL,
    subject       VARCHAR(255) NOT NULL,
    html_body     MEDIUMTEXT NOT NULL,
    text_body     MEDIUMTEXT NULL,
    status        ENUM('pending','processing','sent','failed','cancelled') NOT NULL DEFAULT 'pending',
    attempts      TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_attempt_at DATETIME NULL,
    last_error    VARCHAR(500) NULL,
    scheduled_at  DATETIME NULL,
    sent_at       DATETIME NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_eq_status (status),
    KEY idx_eq_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_log (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    queue_id     BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    subject      VARCHAR(255) NULL,
    status       VARCHAR(40) NOT NULL,
    error        VARCHAR(500) NULL,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_el_queue (queue_id),
    KEY idx_el_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title         VARCHAR(190) NOT NULL,
    body          MEDIUMTEXT NULL,
    channel       ENUM('email','sms') NOT NULL DEFAULT 'email',
    audience_type ENUM('town','region','category','providers','customers_open','all') NOT NULL,
    town_id       INT UNSIGNED NULL,
    region_id     INT UNSIGNED NULL,
    category_id   INT UNSIGNED NULL,
    status        ENUM('draft','scheduled','sending','sent','cancelled') NOT NULL DEFAULT 'draft',
    recipient_count INT UNSIGNED NOT NULL DEFAULT 0,
    scheduled_at  DATETIME NULL,
    sent_at       DATETIME NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_notif_status (status),
    CONSTRAINT fk_notif_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notification_recipients (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NULL,
    email           VARCHAR(190) NOT NULL,
    status          ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
    created_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_nr_notification (notification_id),
    CONSTRAINT fk_nr_notification FOREIGN KEY (notification_id) REFERENCES notifications (id) ON DELETE CASCADE,
    CONSTRAINT fk_nr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
