-- Separate global identity from brand and provider participation.
-- Existing users, user_roles, and providers.user_id remain unchanged.

CREATE TABLE user_brand_profiles (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    user_id                 INT UNSIGNED NOT NULL,
    status                  ENUM('active','pending','suspended','left') NOT NULL DEFAULT 'active',
    display_name            VARCHAR(150) NULL,
    onboarding_completed_at DATETIME NULL,
    terms_version           VARCHAR(40) NULL,
    terms_accepted_at       DATETIME NULL,
    privacy_version         VARCHAR(40) NULL,
    privacy_accepted_at     DATETIME NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    deleted_at              DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_brand_profile (brand_id, user_id),
    KEY idx_user_brand_profiles_user (user_id, status),
    CONSTRAINT fk_user_brand_profiles_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_brand_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_brand_roles (
    brand_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role_id     INT UNSIGNED NOT NULL,
    assigned_by INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL,
    PRIMARY KEY (brand_id, user_id, role_id),
    KEY idx_user_brand_roles_user (user_id, brand_id),
    KEY idx_user_brand_roles_role (role_id),
    CONSTRAINT fk_user_brand_roles_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_user_brand_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_brand_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_brand_roles_assigner FOREIGN KEY (assigned_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_memberships (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    role        ENUM('owner','manager','staff','viewer') NOT NULL DEFAULT 'staff',
    status      ENUM('invited','active','suspended','left') NOT NULL DEFAULT 'active',
    permissions JSON NULL,
    invited_by  INT UNSIGNED NULL,
    invited_at  DATETIME NULL,
    accepted_at DATETIME NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_provider_membership (provider_id, user_id),
    KEY idx_provider_memberships_user (user_id, status),
    KEY idx_provider_memberships_provider_role (provider_id, role, status),
    CONSTRAINT fk_provider_memberships_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_memberships_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_provider_memberships_inviter FOREIGN KEY (invited_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
