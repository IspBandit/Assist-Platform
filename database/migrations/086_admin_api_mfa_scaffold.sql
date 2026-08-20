-- CORE-011 / OPS-010: MFA method scaffold for Admin API (enforcement later).

CREATE TABLE user_mfa_methods (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    method ENUM('totp') NOT NULL DEFAULT 'totp',
    secret_encrypted MEDIUMTEXT NULL,
    label VARCHAR(120) NULL,
    enabled_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_mfa_method (user_id, method),
    KEY idx_user_mfa_enabled (user_id, enabled_at),
    CONSTRAINT fk_user_mfa_methods_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
