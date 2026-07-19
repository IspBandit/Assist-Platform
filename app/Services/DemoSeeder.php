<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Seeds clearly-labelled demo records (is_demo = 1) so a fresh install has
 * something to show. Admins can remove all demo data with remove().
 * Demo providers must never be presented as real businesses.
 */
final class DemoSeeder
{
    public function seed(): void
    {
        $gladstone = $this->townId('gladstone');
        $rockhampton = $this->townId('rockhampton');
        $region = (int) Database::scalar('SELECT id FROM regions WHERE slug = ?', ['central-queensland']);
        $stateId = (int) Database::scalar('SELECT id FROM states WHERE slug = ?', ['qld']);

        // Demo provider
        if (!$this->exists('providers', 'slug', 'demo-coastal-caravan-care')) {
            $providerId = Database::insert(
                'INSERT INTO providers (business_name, slug, contact_name, base_town_id, region_id, description, '
                . 'service_model, max_travel_km, status, is_verified, is_demo, plan, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, ?, NOW(), NOW())',
                ['[DEMO] Coastal Caravan Care', 'demo-coastal-caravan-care', 'Demo Operator',
                 $gladstone, $region, 'This is a demonstration provider profile used for previewing the platform. It is not a real business.',
                 'both', 300, 'active', 'founding_free']
            );

            $catId = (int) Database::scalar('SELECT id FROM service_categories WHERE slug = ?', ['12-volt-electrical']);
            if ($catId > 0) {
                Database::query('INSERT IGNORE INTO provider_services (provider_id, category_id, created_at) VALUES (?, ?, NOW())', [$providerId, $catId]);
            }
            if ($gladstone) {
                Database::query(
                    'INSERT INTO provider_service_areas (provider_id, area_type, town_id, created_at) VALUES (?, ?, ?, NOW())',
                    [$providerId, 'town', $gladstone]
                );
            }

            // Demo runs
            $this->createRun($providerId, '[DEMO] Capricorn Coast service run', 'demo-capricorn-coast-run', 'confirmed', 'confirmed', $gladstone, $rockhampton, $region, 8, 6, 4);
            $this->createRun($providerId, '[DEMO] Gladstone region run (forming)', 'demo-gladstone-forming-run', 'forming', 'forming', $gladstone, $gladstone, $region, 8, 8, 3);

            // Provision billing-side records (dormant during the free launch):
            // assigned plan, complimentary subscription, entitlement snapshot,
            // usage counters, founding status and a billing-customer placeholder.
            (new SubscriptionService())->provisionProvider($providerId, [
                'founding' => true,
                'plan_slug' => 'founding_free',
                'founding_benefits' => [
                    'lifetime_standard_access' => true,
                    'terms_version' => 'founding-v1',
                    'badge' => 'Founding Provider',
                ],
            ]);
        }

        // Demo caravan park
        if (!$this->exists('caravan_parks', 'slug', 'demo-bayside-tourist-park')) {
            Database::insert(
                'INSERT INTO caravan_parks (name, slug, town_id, region_id, state_id, description, public_page_enabled, status, is_demo, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, 1, ?, 1, NOW(), NOW())',
                ['[DEMO] Bayside Tourist Park', 'demo-bayside-tourist-park', $gladstone, $region, $stateId,
                 'A demonstration caravan park partner profile. Not a real park.', 'active']
            );
        }

        // Demo service request
        if (!$this->exists('service_requests', 'reference', 'DEMO-0001')) {
            $catId = (int) Database::scalar('SELECT id FROM service_categories WHERE slug = ?', ['refrigeration']);
            Database::insert(
                'INSERT INTO service_requests (reference, contact_name, contact_email, town_id, region_id, state_id, '
                . 'primary_category_id, vehicle_type, title, description, urgency, status, is_demo, consent_terms, '
                . 'consent_privacy, consent_share, verified_at, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1, NOW(), NOW(), NOW())',
                ['DEMO-0001', '[DEMO] Sample Customer', 'demo@example.com', $gladstone, $region, $stateId,
                 $catId ?: null, 'caravan', '[DEMO] Three-way fridge not cooling on gas',
                 'Demonstration request. The fridge works on 240V but not on gas.', 'medium', 'open']
            );
        }
    }

    public function remove(): array
    {
        $counts = [];
        foreach (['service_runs', 'service_requests', 'providers', 'caravan_parks'] as $table) {
            $counts[$table] = Database::affecting("DELETE FROM {$table} WHERE is_demo = 1");
        }
        return $counts;
    }

    private function createRun(int $providerId, string $title, string $slug, string $type, string $status, ?int $startTown, ?int $endTown, int $region, int $total, int $minBookings, int $bookings): void
    {
        $runId = Database::insert(
            'INSERT INTO service_runs (provider_id, title, slug, run_type, status, start_date, end_date, booking_deadline, '
            . 'start_town_id, end_town_id, region_id, appointments_total, min_bookings, mobile_only, is_public, is_demo, bookings_count, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 21 DAY), DATE_ADD(NOW(), INTERVAL 24 DAY), DATE_ADD(NOW(), INTERVAL 14 DAY), '
            . '?, ?, ?, ?, ?, 1, 1, 1, ?, NOW(), NOW())',
            [$providerId, $title, $slug, $type, $status, $startTown, $endTown, $region, $total, $minBookings, $bookings]
        );
        foreach (array_filter([$startTown, $endTown]) as $i => $townId) {
            Database::query(
                'INSERT IGNORE INTO service_run_towns (run_id, town_id, sort_order) VALUES (?, ?, ?)',
                [$runId, $townId, $i]
            );
        }
    }

    private function townId(string $slug): ?int
    {
        $id = Database::scalar('SELECT id FROM towns WHERE slug = ?', [$slug]);
        return $id !== false && $id !== null ? (int) $id : null;
    }

    private function exists(string $table, string $column, string $value): bool
    {
        return (int) Database::scalar("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?", [$value]) > 0;
    }
}
