-- Stay contact actions for monetisation shortlists (DATA-004).
-- Mirrors provider_contact_actions for caravan park / place-to-stay listings.

CREATE TABLE IF NOT EXISTS stay_contact_actions (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id     INT UNSIGNED NULL,
    park_id      INT UNSIGNED NOT NULL,
    session_id   BIGINT UNSIGNED NULL,
    user_id      INT UNSIGNED NULL,
    action_type  ENUM('phone','email','website','directions','booking') NOT NULL,
    source_route VARCHAR(190) NULL,
    is_excluded  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_sca_brand_created (brand_id, created_at),
    KEY idx_sca_park (park_id),
    KEY idx_sca_action (action_type),
    KEY idx_sca_created (created_at),
    KEY idx_sca_session (session_id),
    KEY idx_sca_park_action (park_id, action_type),
    KEY idx_sca_brand_action_created (brand_id, action_type, created_at),
    CONSTRAINT fk_sca_park FOREIGN KEY (park_id) REFERENCES caravan_parks (id) ON DELETE CASCADE,
    CONSTRAINT fk_sca_session FOREIGN KEY (session_id) REFERENCES tracking_sessions (id) ON DELETE SET NULL,
    CONSTRAINT fk_sca_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
