-- CORE-011 / OPS-010: Admin API credentials and security events (Increment 2).
-- Human access/refresh tokens first; service-account clients are scaffolded for Increment 3.

CREATE TABLE api_oauth_clients (
    id CHAR(36) NOT NULL,
    name VARCHAR(120) NOT NULL,
    client_key VARCHAR(64) NOT NULL,
    secret_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled','revoked') NOT NULL DEFAULT 'disabled',
    scopes_json JSON NOT NULL,
    token_ttl_seconds INT UNSIGNED NOT NULL DEFAULT 3600,
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_oauth_client_key (client_key),
    KEY idx_api_oauth_clients_status (status),
    CONSTRAINT fk_api_oauth_clients_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_access_tokens (
    id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    actor_type ENUM('user','service') NOT NULL,
    user_id INT UNSIGNED NULL,
    client_id CHAR(36) NULL,
    scopes_json JSON NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    request_id_created VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_access_token_hash (token_hash),
    KEY idx_api_access_tokens_user (user_id, revoked_at, expires_at),
    KEY idx_api_access_tokens_client (client_id, revoked_at, expires_at),
    CONSTRAINT fk_api_access_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_access_tokens_client FOREIGN KEY (client_id) REFERENCES api_oauth_clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_refresh_tokens (
    id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    family_id CHAR(36) NOT NULL,
    access_token_id CHAR(36) NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    replaced_by CHAR(36) NULL,
    session_label VARCHAR(120) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_refresh_token_hash (token_hash),
    KEY idx_api_refresh_tokens_user (user_id, revoked_at, expires_at),
    KEY idx_api_refresh_tokens_family (family_id),
    CONSTRAINT fk_api_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_refresh_tokens_access FOREIGN KEY (access_token_id) REFERENCES api_access_tokens (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_login_throttle (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_start DATETIME NOT NULL,
    locked_until DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_login_throttle (email_hash, ip_address),
    KEY idx_api_login_throttle_locked (locked_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_security_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type VARCHAR(80) NOT NULL,
    actor_type ENUM('user','service','anonymous','system') NOT NULL DEFAULT 'anonymous',
    user_id INT UNSIGNED NULL,
    client_id CHAR(36) NULL,
    request_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    meta_json JSON NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_api_security_events_type (event_type, created_at),
    KEY idx_api_security_events_user (user_id, created_at),
    CONSTRAINT fk_api_security_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
