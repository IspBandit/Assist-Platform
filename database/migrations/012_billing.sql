-- =====================================================================
-- 012 Billing / monetisation architecture (inactive while ENABLE_BILLING=false)
-- Gateway-neutral table & field names. Stripe IDs stored as external refs.
-- Monetary amounts stored as integer cents to avoid float rounding.
-- =====================================================================

CREATE TABLE billing_plans (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    internal_name   VARCHAR(120) NOT NULL,
    public_name     VARCHAR(120) NOT NULL,
    slug            VARCHAR(140) NOT NULL,
    description     MEDIUMTEXT NULL,
    monthly_price_cents BIGINT NOT NULL DEFAULT 0,
    annual_price_cents  BIGINT NOT NULL DEFAULT 0,
    currency        CHAR(3) NOT NULL DEFAULT 'AUD',
    gst_inclusive   TINYINT(1) NOT NULL DEFAULT 1,
    trial_days      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    display_order   INT NOT NULL DEFAULT 0,
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    is_public       TINYINT(1) NOT NULL DEFAULT 0,
    signup_available TINYINT(1) NOT NULL DEFAULT 0,
    is_legacy       TINYINT(1) NOT NULL DEFAULT 0,
    is_recommended  TINYINT(1) NOT NULL DEFAULT 0,
    effective_start DATE NULL,
    effective_end   DATE NULL,
    terms_summary   MEDIUMTEXT NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_plans_slug (slug),
    KEY idx_plans_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_plan_prices (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id       INT UNSIGNED NOT NULL,
    billing_interval ENUM('monthly','annual') NOT NULL,
    amount_cents  BIGINT NOT NULL DEFAULT 0,
    currency      CHAR(3) NOT NULL DEFAULT 'AUD',
    gst_inclusive TINYINT(1) NOT NULL DEFAULT 1,
    external_price_ref VARCHAR(190) NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bpp_plan (plan_id),
    CONSTRAINT fk_bpp_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_plan_features (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id     INT UNSIGNED NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    is_enabled  TINYINT(1) NOT NULL DEFAULT 0,
    feature_value VARCHAR(190) NULL,
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bpf (plan_id, feature_key),
    CONSTRAINT fk_bpf_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_plan_limits (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_id     INT UNSIGNED NOT NULL,
    limit_key   VARCHAR(100) NOT NULL,
    limit_value INT NULL,  -- NULL = unlimited
    created_at  DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bpl (plan_id, limit_key),
    CONSTRAINT fk_bpl_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_customers (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NULL,
    external_customer_ref VARCHAR(190) NULL,
    business_name   VARCHAR(190) NULL,
    billing_email   VARCHAR(190) NULL,
    abn             VARCHAR(20) NULL,
    billing_address VARCHAR(500) NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bc_provider (provider_id),
    KEY idx_bc_extref (external_customer_ref),
    CONSTRAINT fk_bc_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_subscriptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NOT NULL,
    plan_id         INT UNSIGNED NULL,
    billing_customer_id INT UNSIGNED NULL,
    status          ENUM('free','trialling','active','past_due','payment_failed','paused',
                         'grace_period','cancelled','expired','complimentary','lifetime_founding','manually_managed')
                    NOT NULL DEFAULT 'complimentary',
    billing_interval ENUM('monthly','annual','none') NOT NULL DEFAULT 'none',
    current_period_start DATE NULL,
    current_period_end   DATE NULL,
    trial_ends_at   DATETIME NULL,
    grace_until     DATETIME NULL,
    cancel_at_period_end TINYINT(1) NOT NULL DEFAULT 0,
    cancelled_at    DATETIME NULL,
    external_subscription_ref VARCHAR(190) NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_psub_provider (provider_id),
    KEY idx_psub_status (status),
    CONSTRAINT fk_psub_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE,
    CONSTRAINT fk_psub_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE SET NULL,
    CONSTRAINT fk_psub_customer FOREIGN KEY (billing_customer_id) REFERENCES billing_customers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_subscription_history (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id   INT UNSIGNED NOT NULL,
    subscription_id INT UNSIGNED NULL,
    from_status   VARCHAR(40) NULL,
    to_status     VARCHAR(40) NOT NULL,
    from_plan_id  INT UNSIGNED NULL,
    to_plan_id    INT UNSIGNED NULL,
    reason        VARCHAR(255) NULL,
    changed_by    INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_psh_provider (provider_id),
    CONSTRAINT fk_psh_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_plan_overrides (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id   INT UNSIGNED NOT NULL,
    override_type ENUM('entitlement','limit','price') NOT NULL,
    override_key  VARCHAR(100) NOT NULL,
    override_value VARCHAR(190) NULL,
    expires_at    DATETIME NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ppo_provider (provider_id),
    CONSTRAINT fk_ppo_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_entitlements (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id     INT UNSIGNED NOT NULL,
    entitlement_key VARCHAR(100) NOT NULL,
    entitlement_value VARCHAR(190) NULL,
    source          ENUM('plan','override','founding','default') NOT NULL DEFAULT 'default',
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pe (provider_id, entitlement_key),
    CONSTRAINT fk_pe_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_usage_counters (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id  INT UNSIGNED NOT NULL,
    counter_key  VARCHAR(100) NOT NULL,
    current_value INT NOT NULL DEFAULT 0,
    period_start DATE NULL,
    period_end   DATE NULL,
    updated_at   DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_puc (provider_id, counter_key),
    CONSTRAINT fk_puc_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_methods (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    billing_customer_id INT UNSIGNED NOT NULL,
    method_type   VARCHAR(40) NOT NULL DEFAULT 'card',
    brand         VARCHAR(40) NULL,
    last_four     CHAR(4) NULL,
    exp_month     TINYINT UNSIGNED NULL,
    exp_year      SMALLINT UNSIGNED NULL,
    external_ref  VARCHAR(190) NULL,
    is_default    TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pm_customer (billing_customer_id),
    CONSTRAINT fk_pm_customer FOREIGN KEY (billing_customer_id) REFERENCES billing_customers (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_number  VARCHAR(40) NOT NULL,
    billing_customer_id INT UNSIGNED NULL,
    provider_id     INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    invoice_date    DATE NULL,
    due_date        DATE NULL,
    status          ENUM('draft','open','paid','void','uncollectible','refunded') NOT NULL DEFAULT 'draft',
    currency        CHAR(3) NOT NULL DEFAULT 'AUD',
    gst_inclusive   TINYINT(1) NOT NULL DEFAULT 1,
    subtotal_cents  BIGINT NOT NULL DEFAULT 0,
    gst_cents       BIGINT NOT NULL DEFAULT 0,
    total_cents     BIGINT NOT NULL DEFAULT 0,
    amount_paid_cents BIGINT NOT NULL DEFAULT 0,
    business_name   VARCHAR(190) NULL,
    billing_address VARCHAR(500) NULL,
    abn             VARCHAR(20) NULL,
    notes           MEDIUMTEXT NULL,
    external_invoice_ref VARCHAR(190) NULL,
    created_at      DATETIME NULL,
    updated_at      DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_inv_number (invoice_number),
    KEY idx_inv_provider (provider_id),
    KEY idx_inv_status (status),
    CONSTRAINT fk_inv_customer FOREIGN KEY (billing_customer_id) REFERENCES billing_customers (id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_items (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id    INT UNSIGNED NOT NULL,
    description   VARCHAR(255) NOT NULL,
    quantity      INT NOT NULL DEFAULT 1,
    unit_amount_cents BIGINT NOT NULL DEFAULT 0,
    amount_cents  BIGINT NOT NULL DEFAULT 0,
    gst_cents     BIGINT NOT NULL DEFAULT 0,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ii_invoice (invoice_id),
    CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    invoice_id    INT UNSIGNED NULL,
    provider_id   INT UNSIGNED NULL,
    amount_cents  BIGINT NOT NULL DEFAULT 0,
    currency      CHAR(3) NOT NULL DEFAULT 'AUD',
    status        ENUM('pending','succeeded','failed','refunded','partially_refunded') NOT NULL DEFAULT 'pending',
    method        ENUM('card','offline','bank','other') NOT NULL DEFAULT 'card',
    external_payment_ref VARCHAR(190) NULL,
    paid_at       DATETIME NULL,
    recorded_by   INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_pay_invoice (invoice_id),
    KEY idx_pay_provider (provider_id),
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refunds (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_id    INT UNSIGNED NULL,
    amount_cents  BIGINT NOT NULL DEFAULT 0,
    reason        VARCHAR(255) NULL,
    status        ENUM('pending','succeeded','failed') NOT NULL DEFAULT 'pending',
    external_refund_ref VARCHAR(190) NULL,
    created_by    INT UNSIGNED NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ref_payment (payment_id),
    CONSTRAINT fk_ref_payment FOREIGN KEY (payment_id) REFERENCES payments (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discount_codes (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    code          VARCHAR(60) NOT NULL,
    description   VARCHAR(255) NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    amount        DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency      CHAR(3) NOT NULL DEFAULT 'AUD',
    max_redemptions INT UNSIGNED NULL,
    times_redeemed INT UNSIGNED NOT NULL DEFAULT 0,
    valid_from    DATE NULL,
    valid_until   DATE NULL,
    applies_to_plan_id INT UNSIGNED NULL,
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dc_code (code),
    CONSTRAINT fk_dc_plan FOREIGN KEY (applies_to_plan_id) REFERENCES billing_plans (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE discount_redemptions (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    discount_code_id INT UNSIGNED NOT NULL,
    provider_id     INT UNSIGNED NULL,
    subscription_id INT UNSIGNED NULL,
    amount_applied_cents BIGINT NOT NULL DEFAULT 0,
    redeemed_at     DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_dr_code (discount_code_id),
    CONSTRAINT fk_dr_code FOREIGN KEY (discount_code_id) REFERENCES discount_codes (id) ON DELETE CASCADE,
    CONSTRAINT fk_dr_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_events (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_type    VARCHAR(80) NOT NULL,
    provider_id   INT UNSIGNED NULL,
    subject_type  VARCHAR(60) NULL,
    subject_id    VARCHAR(80) NULL,
    payload_json  MEDIUMTEXT NULL,
    processed     TINYINT(1) NOT NULL DEFAULT 0,
    processed_at  DATETIME NULL,
    error         VARCHAR(500) NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_be_type (event_type),
    KEY idx_be_provider (provider_id),
    KEY idx_be_processed (processed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_webhook_events (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    gateway          VARCHAR(40) NOT NULL DEFAULT 'stripe',
    external_event_id VARCHAR(190) NOT NULL,
    event_type       VARCHAR(120) NULL,
    signature_verified TINYINT(1) NOT NULL DEFAULT 0,
    payload_json     LONGTEXT NULL,
    status           ENUM('received','processed','failed','ignored') NOT NULL DEFAULT 'received',
    attempts         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    last_error       VARCHAR(500) NULL,
    received_at      DATETIME NULL,
    processed_at     DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_bwe_event (gateway, external_event_id),
    KEY idx_bwe_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commission_rules (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(150) NOT NULL,
    scope         ENUM('global','plan','provider','category') NOT NULL DEFAULT 'global',
    plan_id       INT UNSIGNED NULL,
    provider_id   INT UNSIGNED NULL,
    category_id   INT UNSIGNED NULL,
    rate_percent  DECIMAL(5,2) NOT NULL DEFAULT 0,
    fixed_amount_cents BIGINT NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 0,
    effective_start DATE NULL,
    effective_end DATE NULL,
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cr_scope (scope)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commission_transactions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    provider_id   INT UNSIGNED NULL,
    booking_fee_id INT UNSIGNED NULL,
    source_type   VARCHAR(60) NULL,
    source_id     INT UNSIGNED NULL,
    rate_percent  DECIMAL(5,2) NOT NULL DEFAULT 0,
    base_amount_cents BIGINT NOT NULL DEFAULT 0,
    commission_cents BIGINT NOT NULL DEFAULT 0,
    status        ENUM('pending','invoiced','paid','waived','reversed') NOT NULL DEFAULT 'pending',
    created_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_ct_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE booking_fees (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id    INT UNSIGNED NULL,
    run_id        INT UNSIGNED NULL,
    provider_id   INT UNSIGNED NULL,
    customer_id   INT UNSIGNED NULL,
    deposit_cents BIGINT NOT NULL DEFAULT 0,
    provider_booking_fee_cents BIGINT NOT NULL DEFAULT 0,
    platform_fee_cents BIGINT NOT NULL DEFAULT 0,
    commission_percent DECIMAL(5,2) NOT NULL DEFAULT 0,
    commission_cents BIGINT NOT NULL DEFAULT 0,
    refund_cents  BIGINT NOT NULL DEFAULT 0,
    cancellation_charge_cents BIGINT NOT NULL DEFAULT 0,
    payment_collection ENUM('platform','direct','offline') NOT NULL DEFAULT 'direct',
    payout_status ENUM('not_applicable','pending','paid','failed') NOT NULL DEFAULT 'not_applicable',
    status        ENUM('draft','pending','collected','refunded','cancelled') NOT NULL DEFAULT 'draft',
    created_at    DATETIME NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_bf_provider (provider_id),
    KEY idx_bf_request (request_id),
    KEY idx_bf_run (run_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE tax_settings (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(80) NOT NULL,
    setting_value VARCHAR(255) NULL,
    updated_at    DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tax_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --- Extend providers with subscription + founding-provider fields ---------

ALTER TABLE providers
    ADD COLUMN plan_id INT UNSIGNED NULL AFTER plan,
    ADD COLUMN subscription_state ENUM('free','trialling','active','past_due','payment_failed','paused',
                                       'grace_period','cancelled','expired','complimentary','lifetime_founding','manually_managed')
        NOT NULL DEFAULT 'complimentary' AFTER billing_status,
    ADD COLUMN billing_required TINYINT(1) NOT NULL DEFAULT 0 AFTER subscription_state,
    ADD COLUMN booking_fee_cents BIGINT NOT NULL DEFAULT 0 AFTER commission_rate,
    ADD COLUMN is_founding_provider TINYINT(1) NOT NULL DEFAULT 0 AFTER booking_fee_cents,
    ADD COLUMN founding_provider_joined_at DATETIME NULL AFTER is_founding_provider,
    ADD COLUMN founding_plan_id INT UNSIGNED NULL AFTER founding_provider_joined_at,
    ADD COLUMN founding_benefits_json TEXT NULL AFTER founding_plan_id,
    ADD COLUMN founding_discount_percent DECIMAL(5,2) NULL AFTER founding_benefits_json,
    ADD COLUMN founding_discount_expires_at DATE NULL AFTER founding_discount_percent,
    ADD COLUMN founding_free_until DATE NULL AFTER founding_discount_expires_at,
    ADD COLUMN founding_lifetime_standard_access TINYINT(1) NOT NULL DEFAULT 0 AFTER founding_free_until,
    ADD COLUMN founding_terms_version VARCHAR(40) NULL AFTER founding_lifetime_standard_access;

ALTER TABLE providers
    ADD CONSTRAINT fk_providers_plan FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_providers_founding_plan FOREIGN KEY (founding_plan_id) REFERENCES billing_plans (id) ON DELETE SET NULL;
