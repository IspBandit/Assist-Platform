-- Global session revocation support for password/security changes.

ALTER TABLE users
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash;
