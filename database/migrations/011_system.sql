-- =====================================================================
-- 011 System: audit logs, contact/complaints, reports, exports, tasks, health
-- =====================================================================

CREATE TABLE audit_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NULL,
    action        VARCHAR(120) NOT NULL,
    object_type   VARCHAR(80) NULL,
    object_id     VARCHAR(80) NULL,
    previous_value TEXT NULL,
    new_value     TEXT NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    VARCHAR(500) NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_object (object_type, object_id),
    KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE contact_submissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(190) NOT NULL,
    phone       VARCHAR(40) NULL,
    subject     VARCHAR(190) NULL,
    message     TEXT NOT NULL,
    status      ENUM('new','read','responded','spam') NOT NULL DEFAULT 'new',
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE complaints (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_id  INT UNSIGNED NULL,
    subject_type VARCHAR(60) NULL,
    subject_id   INT UNSIGNED NULL,
    details      TEXT NOT NULL,
    status       ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
    handled_by   INT UNSIGNED NULL,
    created_at   DATETIME NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_comp_status (status),
    CONSTRAINT fk_comp_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_comp_handler FOREIGN KEY (handled_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    report_key  VARCHAR(80) NOT NULL,
    name        VARCHAR(150) NOT NULL,
    config_json MEDIUMTEXT NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_reports_key (report_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_exports (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    export_type VARCHAR(80) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    row_count   INT UNSIGNED NULL,
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    expires_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_se_type (export_type),
    CONSTRAINT fk_se_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scheduled_tasks (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    task_key       VARCHAR(80) NOT NULL,
    description    VARCHAR(255) NULL,
    last_run_at    DATETIME NULL,
    last_status    ENUM('success','failed','running','never') NOT NULL DEFAULT 'never',
    last_message   VARCHAR(500) NULL,
    last_duration_ms INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_st_key (task_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_health_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_key   VARCHAR(80) NOT NULL,
    metric_value VARCHAR(190) NULL,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_shl_metric (metric_key),
    KEY idx_shl_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE page_views (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    route       VARCHAR(190) NOT NULL,
    event_type  VARCHAR(60) NOT NULL DEFAULT 'view',
    referrer_source VARCHAR(120) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pv_route (route),
    KEY idx_pv_event (event_type),
    KEY idx_pv_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
