<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Models\ServiceCategory;
use RuntimeException;

/**
 * Idempotent unclaimed listings for inland sale-demo corridors (VAN-011).
 * Never marks listings verified or claimed.
 */
final class SaleCorridorProviderSeeder
{
    private const PACK = 'database/seeds/vanassist-sale-corridor/providers.json';
    private const CONNECTOR_KEY = 'sale_corridor';

    /** @return array{created:int,updated:int,skipped:int,total:int} */
    public function seed(): array
    {
        $path = base_path(self::PACK);
        if (!is_file($path)) {
            throw new RuntimeException('Sale-corridor provider pack is missing: ' . self::PACK);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('Sale-corridor providers.json must be a JSON array.');
        }

        $brandId = (int) (Config::get('brands.registry.vanassist.database_id') ?? 0);
        if ($brandId < 1) {
            throw new RuntimeException('VanAssist brand database_id is not configured.');
        }

        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => count($decoded)];
        foreach ($decoded as $raw) {
            if (!is_array($raw)) {
                $counts['skipped']++;
                continue;
            }
            $action = $this->upsert($raw, $brandId);
            $counts[$action]++;
        }

        return $counts;
    }

    /**
     * @param array<string,mixed> $raw
     * @return 'created'|'updated'|'skipped'
     */
    private function upsert(array $raw, int $brandId): string
    {
        $key = trim((string) ($raw['key'] ?? ''));
        $name = trim((string) ($raw['business_name'] ?? ''));
        $townName = trim((string) ($raw['town'] ?? ''));
        $state = strtoupper(trim((string) ($raw['state'] ?? '')));
        if ($key === '' || $name === '' || $townName === '' || $state === '') {
            return 'skipped';
        }

        $town = Database::selectOne(
            'SELECT t.id, t.region_id FROM towns t '
            . 'JOIN states s ON s.id = t.state_id '
            . 'WHERE t.is_active = 1 AND LOWER(t.name) = ? AND s.abbreviation = ? '
            . 'ORDER BY t.is_launch_town DESC, t.is_featured DESC LIMIT 1',
            [mb_strtolower($townName), $state]
        );
        if ($town === null) {
            return 'skipped';
        }

        $reference = 'sale-corridor:' . $key;
        $existing = Database::selectOne(
            'SELECT p.id FROM provider_discovery_evidence e '
            . 'JOIN providers p ON p.id = e.provider_id '
            . 'WHERE e.connector_key = ? AND e.source_reference = ? '
            . 'AND e.brand_id = ? AND p.deleted_at IS NULL LIMIT 1',
            [self::CONNECTOR_KEY, $reference, $brandId]
        );

        $phone = preg_replace('/\D+/', '', (string) ($raw['phone'] ?? '')) ?? '';
        $website = trim((string) ($raw['website'] ?? ''));
        if (mb_strlen($website) > 255) {
            $website = '';
        }
        $address = trim((string) ($raw['street_address'] ?? ''));
        $lat = is_numeric($raw['latitude'] ?? null) ? (float) $raw['latitude'] : null;
        $lng = is_numeric($raw['longitude'] ?? null) ? (float) $raw['longitude'] : null;
        $sourceUrl = trim((string) ($raw['source_url'] ?? ''));
        $sourceNote = trim((string) ($raw['source_note'] ?? 'Sale-corridor seed (VAN-011)'));
        $categories = array_values(array_filter(array_map(
            static fn (mixed $v): string => trim((string) $v),
            (array) ($raw['categories'] ?? [])
        )));

        if ($existing !== null) {
            $providerId = (int) $existing['id'];
            Database::query(
                'UPDATE providers SET '
                . 'phone=?, public_phone=?, show_public_phone=?, website=?, '
                . 'street_address=?, latitude=?, longitude=?, base_town_id=?, region_id=?, '
                . "status='active', source_note=?, source_url=?, updated_at=NOW() "
                . 'WHERE id=? AND is_unclaimed=1 AND deleted_at IS NULL',
                [
                    $phone !== '' ? $phone : null,
                    $phone !== '' ? $phone : null,
                    $phone !== '' ? 1 : 0,
                    $website !== '' ? $website : null,
                    $address !== '' ? $address : null,
                    $lat,
                    $lng,
                    (int) $town['id'],
                    $town['region_id'] !== null ? (int) $town['region_id'] : null,
                    $sourceNote,
                    $sourceUrl !== '' ? $sourceUrl : null,
                    $providerId,
                ]
            );
            $this->ensureBrandListing($providerId, $brandId, $name);
            $this->ensureServices($providerId, $categories);
            return 'updated';
        }

        $slug = $this->uniqueSlug($name);
        $providerId = Database::insert(
            'INSERT INTO providers (business_name, slug, phone, public_phone, show_public_phone, website, '
            . 'base_town_id, region_id, street_address, latitude, longitude, description, service_model, '
            . "status, is_verified, is_unclaimed, auto_invite_opt_out, plan, source_note, source_url, source_type, "
            . "coverage_confidence, created_at, updated_at) VALUES ("
            . "?,?,?,?,?,?,?,?,?,?,?,?,'workshop','active',0,1,1,'standard_free',?,?, 'national','inferred',NOW(),NOW())",
            [
                $name,
                $slug,
                $phone !== '' ? $phone : null,
                $phone !== '' ? $phone : null,
                $phone !== '' ? 1 : 0,
                $website !== '' ? $website : null,
                (int) $town['id'],
                $town['region_id'] !== null ? (int) $town['region_id'] : null,
                $address !== '' ? $address : null,
                $lat,
                $lng,
                'Public-source listing curated for inland traveller coverage. Confirm services and contact details before travelling. Claim to manage this profile.',
                $sourceNote,
                $sourceUrl !== '' ? $sourceUrl : null,
            ]
        );
        $this->ensureBrandListing($providerId, $brandId, $name);
        $this->ensureServices($providerId, $categories);
        Database::query(
            "INSERT IGNORE INTO provider_discovery_evidence "
            . "(provider_id, brand_id, source_type, connector_key, source_reference, verification_status, "
            . "discovered_at, last_checked_at, notes) "
            . "VALUES (?, ?, 'other', ?, ?, 'discovered', NOW(), NOW(), ?)",
            [
                $providerId,
                $brandId,
                self::CONNECTOR_KEY,
                mb_substr($reference, 0, 255),
                mb_substr($sourceNote, 0, 500),
            ]
        );

        return 'created';
    }

    /** @param list<string> $categorySlugs */
    private function ensureServices(int $providerId, array $categorySlugs): void
    {
        foreach ($categorySlugs as $slug) {
            $category = ServiceCategory::findActiveBySlug($slug);
            if ($category === null) {
                continue;
            }
            Database::query(
                "INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, notes, created_at) "
                . "VALUES (?, ?, 0, 'Sale-corridor seed (VAN-011)', NOW())",
                [$providerId, (int) $category['id']]
            );
        }
    }

    private function ensureBrandListing(int $providerId, int $brandId, string $name): void
    {
        $existing = Database::selectOne(
            'SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',
            [$brandId, $providerId]
        );
        if ($existing !== null) {
            Database::query(
                "UPDATE provider_brand_listings SET status='active', search_visible=1, updated_at=NOW() "
                . "WHERE id=? AND status IN ('draft','pending')",
                [(int) $existing['id']]
            );
            return;
        }
        $slug = $this->uniqueBrandSlug($brandId, $name);
        Database::query(
            "INSERT IGNORE INTO provider_brand_listings "
            . "(brand_id, provider_id, slug, display_name, status, is_featured, is_verified, search_visible, created_at, updated_at) "
            . "VALUES (?,?,?,?,'active',0,0,1,NOW(),NOW())",
            [$brandId, $providerId, $slug, $name]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug=?', [$slug]) > 0) {
            $slug = $base . '-' . $i;
            ++$i;
        }

        return $slug;
    }

    private function uniqueBrandSlug(int $brandId, string $name): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND slug=?',
            [$brandId, $slug]
        ) > 0) {
            $slug = $base . '-' . $i;
            ++$i;
        }

        return $slug;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? 'provider';
        $slug = trim($slug, '-');

        return $slug !== '' ? mb_substr($slug, 0, 80) : 'provider';
    }
}
