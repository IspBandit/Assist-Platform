-- =====================================================================
-- 001 Core authentication, roles and permissions (RBAC)
-- =====================================================================

CREATE TABLE roles (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(50) NOT NULL,
    name        VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    level       SMALLINT NOT NULL DEFAULT 0,
    is_system   TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug        VARCHAR(100) NOT NULL,
    name        VARCHAR(150) NOT NULL,
    perm_group  VARCHAR(60) NOT NULL DEFAULT 'general',
    description VARCHAR(255) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug),
    KEY idx_permissions_group (perm_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name              VARCHAR(150) NOT NULL,
    email             VARCHAR(190) NOT NULL,
    phone             VARCHAR(40) NULL,
    password_hash     VARCHAR(255) NOT NULL,
    status            ENUM('active','pending','suspended') NOT NULL DEFAULT 'active',
    email_verified_at DATETIME NULL,
    last_login_at     DATETIME NULL,
    marketing_opt_in  TINYINT(1) NOT NULL DEFAULT 0,
    internal_notes    TEXT NULL,
    created_at        DATETIME NULL,
    updated_at        DATETIME NULL,
    deleted_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_status (status),
    KEY idx_users_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_roles (
    user_id    INT UNSIGNED NOT NULL,
    role_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (user_id, role_id),
    KEY idx_ur_role (role_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_ur_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
    id            VARCHAR(128) NOT NULL,
    user_id       INT UNSIGNED NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    VARCHAR(500) NULL,
    last_activity INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sessions_user (user_id),
    KEY idx_sessions_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email      VARCHAR(190) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    created_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pr_email (email),
    KEY idx_pr_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ev_user (user_id),
    CONSTRAINT fk_ev_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_consents (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED NULL,
    consent_type VARCHAR(80) NOT NULL,
    granted      TINYINT(1) NOT NULL DEFAULT 1,
    document_version VARCHAR(40) NULL,
    ip_address   VARCHAR(45) NULL,
    user_agent   VARCHAR(500) NULL,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_consent_user (user_id),
    KEY idx_consent_type (consent_type),
    CONSTRAINT fk_consent_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_login_history (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        INT UNSIGNED NULL,
    ip_address     VARCHAR(45) NULL,
    user_agent     VARCHAR(500) NULL,
    was_successful TINYINT(1) NOT NULL DEFAULT 0,
    created_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_lh_user (user_id),
    KEY idx_lh_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
