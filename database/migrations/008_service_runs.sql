-- =====================================================================
-- 008 Service runs (provider trips covering one or more towns)
-- =====================================================================

CREATE TABLE service_runs (
    id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id       INT UNSIGNED NOT NULL,
    title             VARCHAR(190) NOT NULL,
    slug              VARCHAR(210) NOT NULL,
    run_type          ENUM('proposed','forming','confirmed') NOT NULL DEFAULT 'proposed',
    status            ENUM('proposed','forming','confirmed','limited','fully_booked','completed','cancelled') NOT NULL DEFAULT 'proposed',
    start_date        DATE NULL,
    end_date          DATE NULL,
    booking_deadline  DATE NULL,
    start_town_id     INT UNSIGNED NULL,
    end_town_id       INT UNSIGNED NULL,
    region_id         INT UNSIGNED NULL,
    appointments_total SMALLINT UNSIGNED NULL,
    min_bookings      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    min_revenue_target DECIMAL(10,2) NULL,
    travel_fee_description VARCHAR(500) NULL,
    mobile_only       TINYINT(1) NOT NULL DEFAULT 0,
    notes             MEDIUMTEXT NULL,
    is_public         TINYINT(1) NOT NULL DEFAULT 1,
    is_featured       TINYINT(1) NOT NULL DEFAULT 0,
    is_demo           TINYINT(1) NOT NULL DEFAULT 0,
    bookings_count    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_by        INT UNSIGNED NULL,
    created_at        DATETIME NULL,
    updated_at        DATETIME NULL,
    deleted_at        DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_runs_slug (slug),
    KEY idx_runs_provider (provider_id),
    KEY idx_runs_status (status),
    KEY idx_runs_region (region_id),
    KEY idx_runs_dates (start_date, end_date),
    CONSTRAINT fk_runs_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_runs_start_town FOREIGN KEY (start_town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_runs_end_town FOREIGN KEY (end_town_id) REFERENCES towns (id) ON DELETE SET NULL,
    CONSTRAINT fk_runs_region FOREIGN KEY (region_id) REFERENCES regions (id) ON DELETE SET NULL,
    CONSTRAINT fk_runs_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_run_towns (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id      INT UNSIGNED NOT NULL,
    town_id     INT UNSIGNED NOT NULL,
    arrival_date DATE NULL,
    sort_order  SMALLINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_srt (run_id, town_id),
    KEY idx_srt_town (town_id),
    CONSTRAINT fk_srt_run FOREIGN KEY (run_id) REFERENCES service_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_srt_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_run_services (
    run_id      INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (run_id, category_id),
    KEY idx_srs_category (category_id),
    CONSTRAINT fk_srs_run FOREIGN KEY (run_id) REFERENCES service_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_srs_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_run_requests (
    run_id      INT UNSIGNED NOT NULL,
    request_id  INT UNSIGNED NOT NULL,
    added_by    INT UNSIGNED NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (run_id, request_id),
    KEY idx_srr_request (request_id),
    CONSTRAINT fk_srr_run FOREIGN KEY (run_id) REFERENCES service_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_srr_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
    CONSTRAINT fk_srr_user FOREIGN KEY (added_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_run_bookings (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id      INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NULL,
    request_id  INT UNSIGNED NULL,
    town_id     INT UNSIGNED NULL,
    status      ENUM('joined','confirmed','cancelled','completed','no_show') NOT NULL DEFAULT 'joined',
    notes       VARCHAR(500) NULL,
    created_at  DATETIME NULL,
    updated_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_srb_run (run_id),
    KEY idx_srb_customer (customer_id),
    KEY idx_srb_request (request_id),
    CONSTRAINT fk_srb_run FOREIGN KEY (run_id) REFERENCES service_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_srb_customer FOREIGN KEY (customer_id) REFERENCES customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_srb_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE SET NULL,
    CONSTRAINT fk_srb_town FOREIGN KEY (town_id) REFERENCES towns (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_run_status_history (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    run_id      INT UNSIGNED NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status   VARCHAR(40) NOT NULL,
    changed_by  INT UNSIGNED NULL,
    note        VARCHAR(500) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_srsh2_run (run_id),
    CONSTRAINT fk_srsh2_run FOREIGN KEY (run_id) REFERENCES service_runs (id) ON DELETE CASCADE,
    CONSTRAINT fk_srsh2_user FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
