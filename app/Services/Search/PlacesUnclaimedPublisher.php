<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Core\Database;
use App\Models\Town;
use App\Platform\DataSources\BulkReviewPolicy;
use App\Platform\DataSources\DuplicateMatcher;
use Throwable;

/**
 * Creates or enriches unclaimed public-source providers from Google Places hits
 * (ADR 0042). Never marks listings verified or claimed.
 */
final class PlacesUnclaimedPublisher
{
    /**
     * @param array{
     *   external_id:string,
     *   business_name:string,
     *   formatted_address?:string,
     *   phone?:string,
     *   website?:string,
     *   latitude?:float|int|string|null,
     *   longitude?:float|int|string|null
     * } $place
     * @return array{provider_id:?int,action:string,row:?array<string,mixed>}
     */
    public function publishOrMerge(array $place, int $serviceCategoryId, int $brandId): array
    {
        $externalId = trim((string) ($place['external_id'] ?? ''));
        $name = trim((string) ($place['business_name'] ?? ''));
        if ($externalId === '' || $name === '' || $serviceCategoryId < 1 || $brandId < 1) {
            return ['provider_id' => null, 'action' => 'skipped', 'row' => null];
        }

        $existingByPlace = $this->findByPlaceId($externalId, $brandId);
        if ($existingByPlace !== null) {
            $this->ensureService((int) $existingByPlace['id'], $serviceCategoryId);
            $this->ensureBrandListing((int) $existingByPlace['id'], $brandId, $name);

            return [
                'provider_id' => (int) $existingByPlace['id'],
                'action' => 'existing_place',
                'row' => $this->publicRow((int) $existingByPlace['id']),
            ];
        }

        $candidate = [
            'business_name' => $name,
            'phone' => trim((string) ($place['phone'] ?? '')),
            'website' => trim((string) ($place['website'] ?? '')),
        ];
        $duplicate = $this->bestDuplicate($candidate);
        if ($duplicate !== null
            && (int) ($duplicate['score'] ?? 0) >= BulkReviewPolicy::STRONG_DUPLICATE_SCORE
            && !empty($duplicate['id'])) {
            $providerId = (int) $duplicate['id'];
            $provider = Database::selectOne(
                'SELECT id, is_unclaimed FROM providers WHERE id=? AND deleted_at IS NULL',
                [$providerId]
            );
            if ($provider !== null && (int) ($provider['is_unclaimed'] ?? 0) === 1) {
                $this->enrichUnclaimed($providerId, $place);
                $this->ensureService($providerId, $serviceCategoryId);
                $this->ensureBrandListing($providerId, $brandId, $name);
                $this->recordEvidence($providerId, $brandId, $externalId);

                return [
                    'provider_id' => $providerId,
                    'action' => 'merged_unclaimed',
                    'row' => $this->publicRow($providerId),
                ];
            }

            return ['provider_id' => $providerId, 'action' => 'duplicate_claimed', 'row' => null];
        }

        try {
            $providerId = $this->insertUnclaimed($place, $serviceCategoryId, $brandId);
        } catch (Throwable) {
            return ['provider_id' => null, 'action' => 'insert_failed', 'row' => null];
        }

        return [
            'provider_id' => $providerId,
            'action' => 'created',
            'row' => $this->publicRow($providerId),
        ];
    }

    /**
     * @param array<string,mixed> $place
     */
    private function insertUnclaimed(array $place, int $serviceCategoryId, int $brandId): int
    {
        $name = trim((string) $place['business_name']);
        $slug = $this->uniqueSlug($name);
        $phone = trim((string) ($place['phone'] ?? ''));
        $website = trim((string) ($place['website'] ?? ''));
        if (mb_strlen($website) > 255) {
            $website = '';
        }
        $address = trim((string) ($place['formatted_address'] ?? ''));
        $lat = is_numeric($place['latitude'] ?? null) ? (float) $place['latitude'] : null;
        $lng = is_numeric($place['longitude'] ?? null) ? (float) $place['longitude'] : null;
        $location = $this->resolveTown($lat, $lng);
        $externalId = trim((string) $place['external_id']);
        $sourceUrl = 'https://www.google.com/maps/place/?q=place_id:' . rawurlencode($externalId);

        Database::beginTransaction();
        try {
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
                    $location['town_id'],
                    $location['region_id'],
                    $address !== '' ? $address : null,
                    $lat,
                    $lng,
                    'Public-source listing discovered when travellers searched this area. Confirm services and contact details before travelling. Claim to manage this profile.',
                    'Google Places discovery (unclaimed public-source listing)',
                    $sourceUrl,
                ]
            );
            $this->ensureBrandListing($providerId, $brandId, $name);
            $this->ensureService($providerId, $serviceCategoryId);
            $this->recordEvidence($providerId, $brandId, $externalId);
            Database::commit();

            return $providerId;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string,mixed> $place
     */
    private function enrichUnclaimed(int $providerId, array $place): void
    {
        $phone = trim((string) ($place['phone'] ?? ''));
        $website = trim((string) ($place['website'] ?? ''));
        $address = trim((string) ($place['formatted_address'] ?? ''));
        $lat = is_numeric($place['latitude'] ?? null) ? (float) $place['latitude'] : null;
        $lng = is_numeric($place['longitude'] ?? null) ? (float) $place['longitude'] : null;
        Database::query(
            'UPDATE providers SET '
            . 'phone=COALESCE(NULLIF(phone,\'\'), ?), '
            . 'public_phone=COALESCE(NULLIF(public_phone,\'\'), ?), '
            . 'show_public_phone=CASE WHEN COALESCE(NULLIF(public_phone,\'\'), ?) IS NOT NULL AND COALESCE(NULLIF(public_phone,\'\'), ?) <> \'\' THEN 1 ELSE show_public_phone END, '
            . 'website=COALESCE(NULLIF(website,\'\'), ?), '
            . 'street_address=COALESCE(NULLIF(street_address,\'\'), ?), '
            . 'latitude=COALESCE(latitude, ?), longitude=COALESCE(longitude, ?), '
            . 'updated_at=NOW() WHERE id=? AND is_unclaimed=1 AND deleted_at IS NULL',
            [
                $phone !== '' ? $phone : null,
                $phone !== '' ? $phone : null,
                $phone !== '' ? $phone : null,
                $phone !== '' ? $phone : null,
                $website !== '' ? $website : null,
                $address !== '' ? $address : null,
                $lat,
                $lng,
                $providerId,
            ]
        );
    }

    private function ensureService(int $providerId, int $serviceCategoryId): void
    {
        Database::query(
            "INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, notes, created_at) "
            . "VALUES (?, ?, 0, 'Added from Places rescue (ADR 0042)', NOW())",
            [$providerId, $serviceCategoryId]
        );
    }

    private function ensureBrandListing(int $providerId, int $brandId, string $name): void
    {
        $existing = Database::selectOne(
            'SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',
            [$brandId, $providerId]
        );
        if ($existing !== null) {
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

    private function recordEvidence(int $providerId, int $brandId, string $placeId): void
    {
        Database::query(
            "INSERT IGNORE INTO provider_discovery_evidence "
            . "(provider_id, brand_id, source_type, connector_key, source_reference, verification_status, "
            . "discovered_at, last_checked_at, notes) "
            . "VALUES (?, ?, 'google_places', 'google_places', ?, 'discovered', NOW(), NOW(), ?)",
            [
                $providerId,
                $brandId,
                mb_substr($placeId, 0, 255),
                'Demand-driven Places rescue auto-created unclaimed listing (ADR 0042)',
            ]
        );
    }

    /** @return array<string,mixed>|null */
    private function findByPlaceId(string $placeId, int $brandId): ?array
    {
        return Database::selectOne(
            'SELECT p.id, p.business_name FROM provider_discovery_evidence e '
            . 'JOIN providers p ON p.id = e.provider_id '
            . "WHERE e.connector_key = 'google_places' AND e.source_reference = ? "
            . 'AND e.brand_id = ? AND p.deleted_at IS NULL LIMIT 1',
            [$placeId, $brandId]
        );
    }

    /**
     * @param array{business_name:string,phone:string,website:string} $candidate
     * @return array{id:int,score:int,reasons:list<string>}|null
     */
    private function bestDuplicate(array $candidate): ?array
    {
        $where = ['business_name LIKE ?'];
        $params = ['%' . $candidate['business_name'] . '%'];
        if ($candidate['phone'] !== '') {
            $where[] = 'phone = ?';
            $params[] = $candidate['phone'];
        }
        if ($candidate['website'] !== '') {
            $where[] = 'website = ?';
            $params[] = $candidate['website'];
        }
        $providers = Database::select(
            'SELECT id, business_name, phone, website, is_unclaimed FROM providers '
            . 'WHERE deleted_at IS NULL AND (' . implode(' OR ', $where) . ') LIMIT 40',
            $params
        );
        $matcher = new DuplicateMatcher();
        $best = null;
        foreach ($providers as $provider) {
            $match = $matcher->score($candidate, $provider);
            if ($best === null || $match['score'] > $best['score']) {
                $best = [
                    'id' => (int) $provider['id'],
                    'score' => (int) $match['score'],
                    'reasons' => array_values($match['reasons']),
                ];
            }
        }

        return $best;
    }

    /** @return array{town_id:?int,region_id:?int} */
    private function resolveTown(?float $lat, ?float $lng): array
    {
        if ($lat === null || $lng === null) {
            return ['town_id' => null, 'region_id' => null];
        }
        $town = Town::nearestActive($lat, $lng);
        if ($town === null) {
            return ['town_id' => null, 'region_id' => null];
        }

        return [
            'town_id' => (int) $town['id'],
            'region_id' => !empty($town['region_id']) ? (int) $town['region_id'] : null,
        ];
    }

    /** @return array<string,mixed>|null */
    private function publicRow(int $providerId): ?array
    {
        return Database::selectOne(
            'SELECT p.id, p.business_name, p.slug, p.service_model, p.is_verified, p.is_featured, p.street_address, '
            . 'p.public_phone, p.show_public_phone, p.is_founding_provider, p.is_unclaimed, p.source_note, p.source_url, '
            . '0 AS is_inferred, t.name AS town_name, t.slug AS town_slug, '
            . 'COALESCE(p.latitude, t.latitude) AS town_lat, COALESCE(p.longitude, t.longitude) AS town_lng, '
            . "CASE WHEN p.latitude IS NOT NULL AND p.longitude IS NOT NULL THEN 'provider_point' ELSE 'town_centre' END AS distance_basis, "
            . 's.abbreviation AS state_abbr '
            . 'FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE p.id = ? AND p.deleted_at IS NULL LIMIT 1',
            [$providerId]
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'provider';
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    private function uniqueBrandSlug(int $brandId, string $name): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'provider';
        $slug = $base;
        $i = 2;
        while ((int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id = ? AND slug = ?',
            [$brandId, $slug]
        ) > 0) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
