<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

/**
 * Restartable, additive VanAssist-to-platform data backfills.
 *
 * This is intentionally separate from schema migrations so production
 * operators can run bounded batches, observe progress, and validate before
 * enabling brand-aware reads.
 */
final class PlatformBackfill
{
    public const VANASSIST_BRAND_ID = 1;

    /** @return array<string,int> inserted row counts */
    public function run(int $batchSize = 500): array
    {
        if ($batchSize < 1 || $batchSize > 5000) {
            throw new RuntimeException('Backfill batch size must be between 1 and 5000');
        }

        $this->assertSchemaReady();

        return [
            'provider_brand_listings' => $this->backfillProviderListings($batchSize),
            'user_brand_profiles' => $this->backfillUserBrandProfiles($batchSize),
            'provider_memberships' => $this->backfillProviderMemberships($batchSize),
        ];
    }

    /** @return array<string,array{expected:int,actual:int,valid:bool}> */
    public function validate(): array
    {
        $this->assertSchemaReady();

        $checks = [
            'provider_brand_listings' => [
                'expected' => (int) Database::scalar('SELECT COUNT(*) FROM providers'),
                'actual' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id = ?',
                    [self::VANASSIST_BRAND_ID]
                ),
            ],
            'provider_listing_slug_compatibility' => [
                'expected' => (int) Database::scalar('SELECT COUNT(*) FROM providers'),
                'actual' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM providers p '
                    . 'JOIN provider_brand_listings pbl ON pbl.provider_id = p.id AND pbl.brand_id = ? '
                    . 'WHERE pbl.slug = p.slug',
                    [self::VANASSIST_BRAND_ID]
                ),
            ],
            'user_brand_profiles' => [
                'expected' => (int) Database::scalar('SELECT COUNT(*) FROM users'),
                'actual' => (int) Database::scalar(
                    'SELECT COUNT(*) FROM user_brand_profiles WHERE brand_id = ?',
                    [self::VANASSIST_BRAND_ID]
                ),
            ],
            'provider_memberships' => [
                'expected' => (int) Database::scalar('SELECT COUNT(*) FROM providers WHERE user_id IS NOT NULL'),
                'actual' => (int) Database::scalar(
                    "SELECT COUNT(*) FROM providers p JOIN provider_memberships pm "
                    . "ON pm.provider_id = p.id AND pm.user_id = p.user_id AND pm.role = 'owner' "
                    . 'WHERE p.user_id IS NOT NULL'
                ),
            ],
        ];

        $result = [];
        foreach ($checks as $name => $check) {
            $result[$name] = [
                'expected' => $check['expected'],
                'actual' => $check['actual'],
                'valid' => $check['expected'] === $check['actual'],
            ];
        }

        return $result;
    }

    private function backfillProviderListings(int $batchSize): int
    {
        return $this->forEachIdBatch('providers', $batchSize, function (array $ids): int {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return Database::affecting(
                'INSERT IGNORE INTO provider_brand_listings '
                . '(brand_id, provider_id, slug, display_name, status, is_featured, is_verified, '
                . 'search_visible, seo_title, seo_description, created_at, updated_at, deleted_at) '
                . 'SELECT ?, id, slug, business_name, status, is_featured, is_verified, '
                . 'IF(status = \'active\' AND deleted_at IS NULL, 1, 0), seo_title, seo_description, '
                . 'COALESCE(created_at, NOW()), updated_at, deleted_at '
                . "FROM providers WHERE id IN ({$placeholders})",
                array_merge([self::VANASSIST_BRAND_ID], $ids)
            );
        });
    }

    private function backfillUserBrandProfiles(int $batchSize): int
    {
        return $this->forEachIdBatch('users', $batchSize, function (array $ids): int {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return Database::affecting(
                'INSERT IGNORE INTO user_brand_profiles '
                . '(brand_id, user_id, status, display_name, created_at, updated_at, deleted_at) '
                . 'SELECT ?, id, IF(status = \'suspended\', \'suspended\', \'active\'), name, '
                . 'COALESCE(created_at, NOW()), updated_at, deleted_at '
                . "FROM users WHERE id IN ({$placeholders})",
                array_merge([self::VANASSIST_BRAND_ID], $ids)
            );
        });
    }

    private function backfillProviderMemberships(int $batchSize): int
    {
        return $this->forEachIdBatch('providers', $batchSize, function (array $ids): int {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            return Database::affecting(
                'INSERT IGNORE INTO provider_memberships '
                . '(provider_id, user_id, role, status, accepted_at, created_at, updated_at) '
                . 'SELECT id, user_id, \'owner\', \'active\', COALESCE(approved_at, created_at, NOW()), '
                . 'COALESCE(created_at, NOW()), updated_at FROM providers '
                . "WHERE user_id IS NOT NULL AND id IN ({$placeholders})",
                $ids
            );
        });
    }

    /**
     * @param callable(array<int,int>):int $callback
     */
    private function forEachIdBatch(string $table, int $batchSize, callable $callback): int
    {
        if (!in_array($table, ['providers', 'users'], true)) {
            throw new RuntimeException('Unsupported backfill table');
        }

        $lastId = 0;
        $inserted = 0;
        while (true) {
            $rows = Database::select(
                "SELECT id FROM {$table} WHERE id > ? ORDER BY id LIMIT {$batchSize}",
                [$lastId]
            );
            if ($rows === []) {
                break;
            }

            $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
            $inserted += $callback($ids);
            $lastId = max($ids);
        }

        return $inserted;
    }

    private function assertSchemaReady(): void
    {
        foreach (['brands', 'provider_brand_listings', 'user_brand_profiles', 'provider_memberships'] as $table) {
            $exists = (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table]
            );
            if ($exists !== 1) {
                throw new RuntimeException("Required platform table {$table} is missing; run migrations first");
            }
        }

        $brand = Database::selectOne('SELECT brand_key FROM brands WHERE id = ?', [self::VANASSIST_BRAND_ID]);
        if (($brand['brand_key'] ?? null) !== 'vanassist') {
            throw new RuntimeException('VanAssist brand ID invariant is not satisfied');
        }
    }
}
