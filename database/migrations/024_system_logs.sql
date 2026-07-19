-- =====================================================================
-- 024 Database-backed system logs (fallback when storage/logs is not
-- writable on shared hosting). The admin System logs page reads from
-- files when possible, otherwise from this table.
-- =====================================================================

CREATE TABLE system_logs (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel      VARCHAR(40) NOT NULL,
    level        VARCHAR(20) NOT NULL,
    message      VARCHAR(500) NOT NULL,
    context_json MEDIUMTEXT NULL,
    created_at   DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_system_logs_channel (channel),
    KEY idx_system_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
