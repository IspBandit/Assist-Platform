-- =====================================================================
-- 013 Owner Finance — double-entry general ledger layer
--
-- Adds the VanAssist platform-owner bookkeeping layer ON TOP of the existing
-- 012 billing tables (which remain the sales/AR + marketplace subledger).
-- This migration introduces only the general-ledger foundation: chart of
-- accounts, tax codes, financial periods, journal entries/lines, a durable
-- source-event table for idempotent posting, and a finance-specific audit log.
--
-- Monetary amounts here use DECIMAL(19,4) (never floats). Posted journals are
-- immutable; corrections are made by reversal/adjustment, never edits.
-- All tables are additive and reversible.
-- =====================================================================

CREATE TABLE owner_finance_accounts (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(20) NOT NULL,
    name          VARCHAR(190) NOT NULL,
    type          ENUM('asset','liability','equity','income','cost_of_sales','expense','other_income','other_expense') NOT NULL,
    parent_id     INT UNSIGNED NULL,
    description   VARCHAR(500) NULL,
    default_tax_code VARCHAR(40) NULL,
    is_system     TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    xero_code     VARCHAR(40) NULL,
    myob_code     VARCHAR(40) NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ofa_code (code),
    KEY idx_ofa_type (type),
    KEY idx_ofa_active (is_active),
    CONSTRAINT fk_ofa_parent FOREIGN KEY (parent_id) REFERENCES owner_finance_accounts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_tax_codes (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code        VARCHAR(40) NOT NULL,
    name        VARCHAR(120) NOT NULL,
    rate        DECIMAL(7,4) NOT NULL DEFAULT 0,
    applies_to  ENUM('sales','purchases','both','none') NOT NULL DEFAULT 'both',
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_oftc_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_financial_periods (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    period_code   VARCHAR(20) NOT NULL,
    label         VARCHAR(80) NOT NULL,
    start_date    DATE NOT NULL,
    end_date      DATE NOT NULL,
    status        ENUM('open','soft_locked','closed') NOT NULL DEFAULT 'open',
    locked_at     DATETIME NULL,
    locked_by     INT UNSIGNED NULL,
    reopened_at   DATETIME NULL,
    reopened_by   INT UNSIGNED NULL,
    reopen_reason VARCHAR(500) NULL,
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_offp_code (period_code),
    KEY idx_offp_dates (start_date, end_date),
    KEY idx_offp_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_journal_entries (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_number    VARCHAR(40) NOT NULL,
    transaction_date DATE NOT NULL,
    posting_date    DATE NULL,
    financial_year  SMALLINT UNSIGNED NULL,
    period_id       INT UNSIGNED NULL,
    description     VARCHAR(500) NULL,
    source_type     VARCHAR(60) NULL,
    source_id       VARCHAR(80) NULL,
    source_number   VARCHAR(80) NULL,
    source_event_id BIGINT UNSIGNED NULL,
    external_reference VARCHAR(120) NULL,
    status          ENUM('draft','approved','posted','reversed') NOT NULL DEFAULT 'draft',
    currency        CHAR(3) NOT NULL DEFAULT 'AUD',
    exchange_rate   DECIMAL(19,8) NOT NULL DEFAULT 1,
    base_currency   CHAR(3) NOT NULL DEFAULT 'AUD',
    total_debit     DECIMAL(19,4) NOT NULL DEFAULT 0,
    total_credit    DECIMAL(19,4) NOT NULL DEFAULT 0,
    provider_id     INT UNSIGNED NULL,
    customer_id     INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    reversal_of_entry_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(190) NULL,
    correlation_id  VARCHAR(120) NULL,
    created_by      INT UNSIGNED NULL,
    approved_by     INT UNSIGNED NULL,
    posted_by       INT UNSIGNED NULL,
    reversed_by     INT UNSIGNED NULL,
    created_at      DATETIME NULL,
    approved_at     DATETIME NULL,
    posted_at       DATETIME NULL,
    reversed_at     DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ofje_number (entry_number),
    UNIQUE KEY uq_ofje_idem (idempotency_key),
    KEY idx_ofje_status (status),
    KEY idx_ofje_date (transaction_date),
    KEY idx_ofje_period (period_id),
    KEY idx_ofje_source (source_type, source_id),
    KEY idx_ofje_provider (provider_id),
    CONSTRAINT fk_ofje_period FOREIGN KEY (period_id) REFERENCES owner_finance_financial_periods (id) ON DELETE SET NULL,
    CONSTRAINT fk_ofje_reversal FOREIGN KEY (reversal_of_entry_id) REFERENCES owner_finance_journal_entries (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_journal_lines (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entry_id      INT UNSIGNED NOT NULL,
    account_id    INT UNSIGNED NOT NULL,
    account_code  VARCHAR(20) NOT NULL,
    debit         DECIMAL(19,4) NOT NULL DEFAULT 0,
    credit        DECIMAL(19,4) NOT NULL DEFAULT 0,
    base_debit    DECIMAL(19,4) NOT NULL DEFAULT 0,
    base_credit   DECIMAL(19,4) NOT NULL DEFAULT 0,
    tax_code      VARCHAR(40) NULL,
    tax_rate      DECIMAL(7,4) NULL,
    tax_amount    DECIMAL(19,4) NULL,
    description   VARCHAR(500) NULL,
    provider_id   INT UNSIGNED NULL,
    customer_id   INT UNSIGNED NULL,
    supplier_id   INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    region_id     INT UNSIGNED NULL,
    service_category_id INT UNSIGNED NULL,
    tracking_category VARCHAR(80) NULL,
    cost_centre   VARCHAR(80) NULL,
    line_no       INT NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ofjl_entry (entry_id),
    KEY idx_ofjl_account (account_id),
    CONSTRAINT fk_ofjl_entry FOREIGN KEY (entry_id) REFERENCES owner_finance_journal_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_ofjl_account FOREIGN KEY (account_id) REFERENCES owner_finance_accounts (id),
    CONSTRAINT chk_ofjl_nonneg CHECK (debit >= 0 AND credit >= 0),
    CONSTRAINT chk_ofjl_one_side CHECK (NOT (debit > 0 AND credit > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_source_events (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type      VARCHAR(80) NOT NULL,
    source_system   VARCHAR(60) NOT NULL DEFAULT 'vanassist',
    source_record_id VARCHAR(80) NULL,
    provider_id     INT UNSIGNED NULL,
    customer_id     INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    payload_hash    VARCHAR(64) NULL,
    status          ENUM('pending','processed','failed','ignored') NOT NULL DEFAULT 'pending',
    processed_at    DATETIME NULL,
    resulting_journal_id INT UNSIGNED NULL,
    idempotency_key VARCHAR(190) NULL,
    error           VARCHAR(500) NULL,
    retry_count     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_ofse_idem (idempotency_key),
    KEY idx_ofse_type (event_type),
    KEY idx_ofse_status (status),
    KEY idx_ofse_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE owner_finance_audit_events (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED NULL,
    action        VARCHAR(120) NOT NULL,
    entity_type   VARCHAR(80) NULL,
    entity_id     VARCHAR(80) NULL,
    before_json   TEXT NULL,
    after_json    TEXT NULL,
    reason        VARCHAR(500) NULL,
    ip_address    VARCHAR(45) NULL,
    correlation_id VARCHAR(120) NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ofae_action (action),
    KEY idx_ofae_entity (entity_type, entity_id),
    KEY idx_ofae_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
