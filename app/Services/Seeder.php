<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Idempotent database seeder. Safe to run multiple times: existing rows
 * (matched on their unique keys) are left untouched.
 */
final class Seeder
{
    private array $data;
    private array $content;
    private array $emailTemplates;
    private array $billing;
    private array $ownerFinance;

    public function __construct()
    {
        $this->data = require base_path('database/seeds/data.php');
        $this->content = require base_path('database/seeds/content.php');
        $this->emailTemplates = require base_path('database/seeds/email_templates.php');
        $this->billing = require base_path('database/seeds/billing.php');
        $this->ownerFinance = require base_path('database/seeds/owner_finance.php');
    }

    public function seedAll(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedLocations();
        $this->seedServiceCategories();
        $this->seedNationalTowns();
        $this->seedNationalImport();
        $this->seedSettings();
        $this->seedFeatureFlags();
        $this->seedScheduledTasks();
        $this->seedEmailTemplates();
        $this->seedContent();
        $this->seedTaxSettings();
        $this->seedBillingPlans();
        $this->seedOwnerFinance();
    }

    public function seedRolesAndPermissions(): void
    {
        foreach ($this->data['roles'] as $role) {
            Database::query(
                'INSERT IGNORE INTO roles (slug, name, description, level, is_system, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                [$role['slug'], $role['name'], $role['description'], $role['level']]
            );
        }

        foreach ($this->data['permissions'] as $slug => [$name, $group]) {
            Database::query(
                'INSERT IGNORE INTO permissions (slug, name, perm_group, created_at) VALUES (?, ?, ?, NOW())',
                [$slug, $name, $group]
            );
        }

        $roleIds = $this->slugMap('roles');
        $permIds = $this->slugMap('permissions');
        $allPerms = array_keys($this->data['permissions']);

        foreach ($this->data['role_permissions'] as $roleSlug => $perms) {
            if (!isset($roleIds[$roleSlug])) {
                continue;
            }
            $list = $perms === 'ALL' ? $allPerms : $perms;
            foreach ($list as $permSlug) {
                if (!isset($permIds[$permSlug])) {
                    continue;
                }
                Database::query(
                    'INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at) VALUES (?, ?, NOW())',
                    [$roleIds[$roleSlug], $permIds[$permSlug]]
                );
            }
        }
    }

    public function seedLocations(): void
    {
        $c = $this->data['country'];
        Database::query(
            'INSERT IGNORE INTO countries (name, slug, iso_code, is_active, created_at, updated_at) '
            . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
            [$c['name'], $c['slug'], $c['iso_code']]
        );
        $countryId = (int) Database::scalar('SELECT id FROM countries WHERE slug = ?', [$c['slug']]);

        $s = $this->data['state'];
        Database::query(
            'INSERT IGNORE INTO states (country_id, name, slug, abbreviation, is_active, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW(), NOW())',
            [$countryId, $s['name'], $s['slug'], $s['abbreviation'], $s['is_active']]
        );
        $stateId = (int) Database::scalar('SELECT id FROM states WHERE slug = ?', [$s['slug']]);

        $regionIds = [];
        foreach ($this->data['regions'] as $regionName) {
            $slug = str_slug($regionName);
            Database::query(
                'INSERT IGNORE INTO regions (state_id, name, slug, is_active, created_at, updated_at) '
                . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
                [$stateId, $regionName, $slug]
            );
            $regionIds[$regionName] = (int) Database::scalar(
                'SELECT id FROM regions WHERE state_id = ? AND slug = ?',
                [$stateId, $slug]
            );
        }

        foreach ($this->data['towns'] as $townName => [$region, $postcode, $lat, $lng, $launch]) {
            $slug = str_slug($townName);
            $regionId = $regionIds[$region] ?? null;
            Database::query(
                'INSERT IGNORE INTO towns '
                . '(state_id, region_id, name, slug, primary_postcode, latitude, longitude, is_active, is_launch_town, noindex, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, 0, NOW(), NOW())',
                [$stateId, $regionId, $townName, $slug, $postcode, $lat, $lng, $launch]
            );
        }
    }

    public function seedServiceCategories(): void
    {
        $order = 0;
        foreach ($this->data['service_categories'] as $name) {
            $order += 10;
            Database::query(
                'INSERT IGNORE INTO service_categories (name, slug, sort_order, is_active, created_at, updated_at) '
                . 'VALUES (?, ?, ?, 1, NOW(), NOW())',
                [$name, str_slug($name), $order]
            );
        }
    }

    /**
     * Import the researched, public-source businesses (all states/territories)
     * as clearly-marked unclaimed directory listings, creating any missing
     * states, regions and towns. Idempotent; no-op if the import file is absent.
     */
    public function seedNationalImport(): void
    {
        (new NationalImportSeeder())->seed();
    }

    /**
     * Create the complete national town list (every Australian locality) for all
     * states/territories. Idempotent; no-op if the seed file is absent.
     */
    public function seedNationalTowns(): void
    {
        (new NationalTownSeeder())->seed();
    }

    public function seedSettings(): void
    {
        foreach ($this->data['settings'] as $key => [$value, $group]) {
            Database::query(
                'INSERT IGNORE INTO site_settings (setting_key, setting_value, setting_group, updated_at) '
                . 'VALUES (?, ?, ?, NOW())',
                [$key, $value, $group]
            );
        }
    }

    public function seedFeatureFlags(): void
    {
        foreach ($this->data['feature_flags'] as $key => [$enabled, $description]) {
            Database::query(
                'INSERT IGNORE INTO feature_flags (flag_key, is_enabled, description, updated_at) '
                . 'VALUES (?, ?, ?, NOW())',
                [$key, $enabled ? 1 : 0, $description]
            );
        }
    }

    public function seedScheduledTasks(): void
    {
        foreach ($this->data['scheduled_tasks'] as $key => $description) {
            Database::query(
                'INSERT IGNORE INTO scheduled_tasks (task_key, description, last_status) VALUES (?, ?, ?)',
                [$key, $description, 'never']
            );
        }
    }

    public function seedEmailTemplates(): void
    {
        foreach ($this->emailTemplates as $tpl) {
            Database::query(
                'INSERT IGNORE INTO email_templates (template_key, name, subject, html_body, text_body, is_enabled, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())',
                [$tpl['template_key'], $tpl['name'], $tpl['subject'], $tpl['html_body'], $tpl['text_body']]
            );
        }
    }

    public function seedContent(): void
    {
        foreach ($this->content['homepage_blocks'] as $block) {
            Database::query(
                'INSERT IGNORE INTO content_blocks '
                . '(block_group, block_key, title, subtitle, body, button_label, button_url, sort_order, is_active, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())',
                ['homepage', $block['block_key'], $block['title'], $block['subtitle'] ?? null,
                 $block['body'] ?? null, $block['button_label'] ?? null, $block['button_url'] ?? null,
                 $block['sort_order'] ?? 0]
            );
        }

        foreach ($this->content['pages'] as $page) {
            $pageKey = (string) ($page['page_key'] ?? '');
            $slug    = (string) ($page['slug'] ?? '');
            if ($pageKey === '' || $slug === '') {
                continue;
            }
            Database::query(
                'DELETE FROM content_pages WHERE page_key = ? OR slug = ?',
                [$pageKey, $slug]
            );
            Database::query(
                'INSERT INTO content_pages (page_key, title, slug, body, is_published, is_system, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, 1, 1, NOW(), NOW())',
                [$pageKey, $page['title'], $slug, $page['body']]
            );
        }

        foreach ($this->content['faqs'] as $faq) {
            // FAQs have no natural unique key; only seed when the table is empty.
            $exists = (int) Database::scalar('SELECT COUNT(*) FROM faqs WHERE question = ?', [$faq['question']]);
            if ($exists === 0) {
                Database::query(
                    'INSERT INTO faqs (category, question, answer, sort_order, is_active, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
                    [$faq['category'], $faq['question'], $faq['answer'], $faq['sort_order'] ?? 0]
                );
            }
        }
    }

    public function seedTaxSettings(): void
    {
        foreach ($this->data['tax_settings'] as $key => $value) {
            Database::query(
                'INSERT IGNORE INTO tax_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())',
                [$key, (string) $value]
            );
        }
    }

    /**
     * Seed the default, editable billing plans plus their limits and features.
     * Plans are never charged while ENABLE_BILLING=false; this just makes them
     * available for administrators to configure privately.
     */
    public function seedBillingPlans(): void
    {
        foreach ($this->billing['plans'] as $plan) {
            Database::query(
                'INSERT IGNORE INTO billing_plans '
                . '(internal_name, public_name, slug, description, monthly_price_cents, annual_price_cents, '
                . 'currency, gst_inclusive, trial_days, display_order, is_active, is_public, signup_available, '
                . 'is_legacy, is_recommended, terms_summary, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())',
                [
                    $plan['internal_name'], $plan['public_name'], $plan['slug'], $plan['description'] ?? null,
                    $plan['monthly_price_cents'] ?? 0, $plan['annual_price_cents'] ?? 0,
                    'AUD', 1, $plan['trial_days'] ?? 0, $plan['display_order'] ?? 0,
                    $plan['is_active'] ?? 1, $plan['is_public'] ?? 0, $plan['signup_available'] ?? 0,
                    $plan['is_recommended'] ?? 0, $plan['terms_summary'] ?? null,
                ]
            );

            $planId = (int) Database::scalar('SELECT id FROM billing_plans WHERE slug = ?', [$plan['slug']]);
            if ($planId === 0) {
                continue;
            }

            foreach (($plan['limits'] ?? []) as $limitKey => $limitValue) {
                Database::query(
                    'INSERT IGNORE INTO billing_plan_limits (plan_id, limit_key, limit_value, created_at) VALUES (?, ?, ?, NOW())',
                    [$planId, $limitKey, $limitValue === null ? null : (int) $limitValue]
                );
            }

            foreach (($plan['features'] ?? []) as $featureKey => $enabled) {
                Database::query(
                    'INSERT IGNORE INTO billing_plan_features (plan_id, feature_key, is_enabled, created_at) VALUES (?, ?, ?, NOW())',
                    [$planId, $featureKey, $enabled ? 1 : 0]
                );
            }

            // Mirror monthly/annual prices into billing_plan_prices for gateway refs.
            foreach (['monthly' => $plan['monthly_price_cents'] ?? 0, 'annual' => $plan['annual_price_cents'] ?? 0] as $interval => $amount) {
                $exists = (int) Database::scalar(
                    'SELECT COUNT(*) FROM billing_plan_prices WHERE plan_id = ? AND billing_interval = ?',
                    [$planId, $interval]
                );
                if ($exists === 0) {
                    Database::query(
                        'INSERT INTO billing_plan_prices (plan_id, billing_interval, amount_cents, currency, gst_inclusive, is_active, created_at) '
                        . 'VALUES (?, ?, ?, ?, 1, 1, NOW())',
                        [$planId, $interval, (int) $amount, 'AUD']
                    );
                }
            }
        }
    }

    /**
     * Seed the owner-finance starter chart of accounts, tax codes, and the
     * current financial period. Idempotent: matched on account code / tax code.
     */
    public function seedOwnerFinance(): void
    {
        $order = 0;
        foreach ($this->ownerFinance['accounts'] as [$code, $name, $type, $isSystem]) {
            $order += 10;
            Database::query(
                'INSERT IGNORE INTO owner_finance_accounts '
                . '(code, name, type, is_system, is_active, sort_order, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, 1, ?, NOW(), NOW())',
                [$code, $name, $type, $isSystem ? 1 : 0, $order]
            );
        }

        foreach ($this->ownerFinance['tax_codes'] as [$code, $name, $rate, $appliesTo]) {
            Database::query(
                'INSERT IGNORE INTO owner_finance_tax_codes (code, name, rate, applies_to, is_active, created_at) '
                . 'VALUES (?, ?, ?, ?, 1, NOW())',
                [$code, $name, $rate, $appliesTo]
            );
        }

        $this->seedCurrentFinancialPeriod();
    }

    /**
     * Ensure the financial period containing today exists and is open. Periods
     * are monthly and keyed YYYY-MM (e.g. 2026-06).
     */
    private function seedCurrentFinancialPeriod(): void
    {
        $code = date('Y-m');
        $exists = (int) Database::scalar(
            'SELECT COUNT(*) FROM owner_finance_financial_periods WHERE period_code = ?',
            [$code]
        );
        if ($exists > 0) {
            return;
        }
        $start = date('Y-m-01');
        $end = date('Y-m-t');
        Database::query(
            'INSERT INTO owner_finance_financial_periods '
            . '(period_code, label, start_date, end_date, status, created_at, updated_at) '
            . "VALUES (?, ?, ?, ?, 'open', NOW(), NOW())",
            [$code, date('F Y'), $start, $end]
        );
    }

    /** @return array<string,int> slug => id */
    private function slugMap(string $table): array
    {
        $rows = Database::select("SELECT id, slug FROM {$table}");
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['slug']] = (int) $row['id'];
        }
        return $map;
    }
}
