<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Helpers\Geo;
use RuntimeException;
use Throwable;

/**
 * Additively imports the publishable LocalTorque MDM pack into canonical
 * providers, then routes visibility from the taxonomy's category.brands list.
 * Claimed providers are evidence-linked but never modified by this importer.
 */
final class LocalTorquePackSeeder
{
    private const PACK_DIR = 'database/seeds/localtorque';
    private const MAX_DECLARED_TOWN_DISTANCE_KM = 150.0;
    private const MAX_NEAREST_AUSTRALIAN_TOWN_DISTANCE_KM = 150.0;

    /** @var array<string,array{name:string,group:string,brands:list<string>}> */
    private array $taxonomy = [];

    /** @var array<string,int> */
    private array $brandIds = [];

    /** @var array<string,array<string,int>> */
    private array $categoryIds = [];

    /** @return array<string,int|string|bool> */
    public function seedBatch(int $offset = 0, int $limit = 250): array
    {
        $providers = $this->loadJson('providers-publishable.json');
        if (!array_is_list($providers)) {
            throw new RuntimeException('LocalTorque providers-publishable.json must be a JSON array.');
        }
        $this->loadTaxonomy();
        $this->prepareBrandCategories();

        $total = count($providers);
        $offset = max(0, $offset);
        $limit = max(1, min(1000, $limit));
        $slice = array_slice($providers, $offset, $limit);
        $counts = [
            'total' => $total, 'offset' => $offset, 'processed' => 0,
            'created' => 0, 'enriched' => 0, 'linked' => 0,
            'public_listings' => 0, 'review_only' => 0, 'skipped' => 0,
            'location_corrected' => 0, 'location_conflicts' => 0,
            'location_sources_quarantined' => 0,
        ];

        Database::beginTransaction();
        try {
            foreach ($slice as $raw) {
                if (!is_array($raw)) {
                    $counts['skipped']++;
                    continue;
                }
                $this->importRecord($raw, $counts);
                $counts['processed']++;
            }
            Database::commit();
        } catch (Throwable $error) {
            Database::rollBack();
            throw $error;
        }

        $next = $offset + count($slice);
        $counts['next'] = $next < $total ? $next : -1;
        $counts['complete'] = $counts['next'] === -1;
        if ($counts['complete']) {
            $counts['location_sources_quarantined'] = $this->quarantineContradictorySourceLocations();
            $counts['retired_superseded_qld_fuel'] = $this->retireSupersededQueenslandFuelSeeds();
        }
        return $counts;
    }

    public static function fingerprint(): string
    {
        $file = base_path(self::PACK_DIR . '/providers-publishable.json');
        return is_file($file)
            ? hash('sha256', (string) filesize($file) . ':' . hash_file('sha256', $file))
            : '';
    }

    /** @param array<string,mixed> $record @param array<string,int|string|bool> $counts */
    private function importRecord(array $record, array &$counts): void
    {
        $externalId = trim((string) ($record['id'] ?? ''));
        $name = trim((string) ($record['name'] ?? ''));
        if ($externalId === '' || $name === '' || ($record['publishable'] ?? false) !== true) {
            $counts['skipped']++;
            return;
        }

        $validCategories = [];
        foreach ((array) ($record['categories'] ?? []) as $categoryKey) {
            $key = trim((string) $categoryKey);
            if (isset($this->taxonomy[$key])) {
                $validCategories[$key] = true;
            }
        }
        $validCategories = self::sanitiseCategories($record, array_keys($validCategories));

        $location = $this->resolveLocation($record);
        $confidence = max(0, min(100, (int) ($record['confidence'] ?? 0)));
        $operational = strtoupper(trim((string) ($record['operational_status'] ?? '')));
        $closed = $operational !== '' && preg_match('/CLOSED|DECOMMISSIONED|INACTIVE|NON.?OPERATIONAL/', $operational) === 1;
        $reviewOnly = ($record['needs_review'] ?? false) === true
            || $confidence < 40
            || $validCategories === []
            || $location['town_id'] === 0
            || $location['location_conflict']
            || $closed;
        if ($location['location_corrected']) {
            $counts['location_corrected']++;
        }
        if ($location['location_conflict']) {
            $counts['location_conflicts']++;
        }

        $sourceKey = strtolower(trim((string) ($record['source'] ?? 'other'))) ?: 'other';
        $providerId = $this->findProvider($sourceKey, $externalId, $record, $location['town_id']);
        $created = false;
        if ($providerId === 0) {
            $providerId = $this->insertProvider($record, $externalId, $location, !$reviewOnly);
            $counts['created']++;
            $created = true;
        }

        $isUnclaimed = (int) Database::scalar('SELECT is_unclaimed FROM providers WHERE id = ?', [$providerId]) === 1;
        if (!$created && $isUnclaimed && $this->enrichProvider($providerId, $record, $location)) {
            $counts['enriched']++;
        }

        $this->upsertSourceRecord($providerId, $sourceKey, $externalId, $record, $confidence, $reviewOnly);
        $counts['linked']++;

        // A claimed provider controls its own services and brand participation.
        if (!$isUnclaimed) {
            return;
        }

        $this->removeKnownBadFuelGasAssignments($providerId, $record);
        $this->removeUnsupportedBrandCategoryAssignments($providerId, $record, $validCategories);

        $brands = $this->brandsForCategories($validCategories);
        $hasPublicEvidence = (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_source_records WHERE provider_id=? AND publishable=1 AND needs_review=0',
            [$providerId]
        ) > 0;
        if (!$hasPublicEvidence) {
            $this->quarantineUnclaimedProvider($providerId);
        }
        if ($hasPublicEvidence) {
            Database::query(
                "UPDATE providers SET status='active', updated_at=NOW() WHERE id=? AND is_unclaimed=1 AND status IN ('draft','pending')",
                [$providerId]
            );
        }
        foreach ($brands as $brandKey) {
            $listingId = $this->upsertBrandListing($providerId, $brandKey, $record, $hasPublicEvidence);
            if (!$reviewOnly) {
                foreach ($validCategories as $categoryKey) {
                    if (!in_array($brandKey, $this->taxonomy[$categoryKey]['brands'], true)) {
                        continue;
                    }
                    $categoryId = $this->categoryIds[$brandKey][$categoryKey] ?? 0;
                    if ($categoryId > 0) {
                        Database::query(
                            'INSERT INTO provider_brand_category_assignments '
                            . '(listing_id, category_id, assignment_source, confidence, is_verified, created_at, updated_at) '
                            . "VALUES (?, ?, 'import', ?, 0, NOW(), NOW()) "
                            . "ON DUPLICATE KEY UPDATE confidence = VALUES(confidence), assignment_source = 'import', updated_at = NOW()",
                            [$listingId, $categoryId, $confidence]
                        );
                    }
                }
            }
            if ($reviewOnly) {
                $counts['review_only']++;
            } else {
                $counts['public_listings']++;
            }
        }

        if (!$reviewOnly) {
            $this->linkVanAssistCompatibility($providerId, $validCategories);
        }
        if ($location['town_id'] > 0) {
            if ($location['location_corrected']) {
                Database::query(
                    "DELETE FROM provider_service_areas WHERE provider_id=? AND area_type='town'",
                    [$providerId]
                );
            }
            Database::query(
                "INSERT IGNORE INTO provider_service_areas (provider_id, area_type, town_id, label, created_at) VALUES (?, 'town', ?, ?, NOW())",
                [$providerId, $location['town_id'], $location['town_name']]
            );
        }
    }

    /**
     * @param array<string,mixed> $record
     * @return array{town_id:int,region_id:int,town_name:string,state:string,location_corrected:bool,location_conflict:bool}
     */
    private function resolveLocation(array $record): array
    {
        $state = strtoupper(trim((string) ($record['state'] ?? '')));
        $townName = trim((string) ($record['town'] ?? ''));
        $lat = is_numeric($record['lat'] ?? null) ? (float) $record['lat'] : null;
        $lng = is_numeric($record['lng'] ?? null) ? (float) $record['lng'] : null;
        $approximate = ($record['_coords_approx'] ?? false) === true;
        $town = null;
        $locationCorrected = false;
        $locationConflict = false;

        // Exact source locality normally wins. Where precise source coordinates
        // contradict it by a large margin, use the coordinate locality only if
        // it resolves close to a known Australian town; otherwise quarantine it.
        if ($townName !== '' && $state !== '') {
            $town = Database::selectOne(
                'SELECT t.id, t.name, t.region_id, t.latitude, t.longitude, s.abbreviation AS state_abbr FROM towns t '
                . 'JOIN states s ON s.id = t.state_id WHERE s.abbreviation = ? AND LOWER(t.name) = LOWER(?) AND t.is_active = 1 LIMIT 1',
                [$state, $townName]
            );
        }
        if ($town !== null && $lat !== null && $lng !== null && !$approximate
            && $town['latitude'] !== null && $town['longitude'] !== null) {
            $declaredDistance = Geo::haversineExactKm(
                $lat,
                $lng,
                (float) $town['latitude'],
                (float) $town['longitude']
            );
            if ($declaredDistance > self::MAX_DECLARED_TOWN_DISTANCE_KM) {
                $coordinateTown = $this->nearestTown($lat, $lng, '');
                if ($coordinateTown !== null
                    && (float) ($coordinateTown['distance_km'] ?? PHP_FLOAT_MAX)
                        <= self::MAX_NEAREST_AUSTRALIAN_TOWN_DISTANCE_KM) {
                    $town = $coordinateTown;
                    $locationCorrected = true;
                } else {
                    $locationConflict = true;
                }
            }
        }
        if ($town === null && $lat !== null && $lng !== null) {
            $coordinateTown = $this->nearestTown($lat, $lng, $state);
            if (!$approximate && $coordinateTown !== null
                && (float) ($coordinateTown['distance_km'] ?? PHP_FLOAT_MAX)
                    > self::MAX_NEAREST_AUSTRALIAN_TOWN_DISTANCE_KM) {
                $locationConflict = true;
            } else {
                $town = $coordinateTown;
            }
        }

        return [
            'town_id' => (int) ($town['id'] ?? 0),
            'region_id' => (int) ($town['region_id'] ?? 0),
            'town_name' => (string) ($town['name'] ?? $townName),
            'state' => (string) ($town['state_abbr'] ?? $state),
            'location_corrected' => $locationCorrected,
            'location_conflict' => $locationConflict,
        ];
    }

    private function quarantineUnclaimedProvider(int $providerId): void
    {
        Database::query(
            "UPDATE provider_brand_listings SET status='draft',search_visible=0,updated_at=NOW() "
            . 'WHERE provider_id=? AND EXISTS (SELECT 1 FROM providers p WHERE p.id=? AND p.is_unclaimed=1)',
            [$providerId, $providerId]
        );
        Database::query(
            "UPDATE providers SET status='pending',updated_at=NOW() WHERE id=? AND is_unclaimed=1",
            [$providerId]
        );
    }

    /**
     * A provider can legitimately accumulate more than one public-source row.
     * Reconcile the complete evidence set after the final batch so an older or
     * duplicate row cannot remain trusted when it contradicts the provider's
     * displayed Australian town. Claimed providers are deliberately excluded.
     */
    private function quarantineContradictorySourceLocations(): int
    {
        $rows = Database::select(
            'SELECT psr.id,psr.provider_id,psr.payload_json,t.latitude AS town_latitude,t.longitude AS town_longitude '
            . 'FROM provider_source_records psr JOIN providers p ON p.id=psr.provider_id '
            . 'JOIN towns t ON t.id=p.base_town_id '
            . 'WHERE p.is_unclaimed=1 AND psr.publishable=1 AND psr.needs_review=0 '
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL'
        );
        $quarantined = 0;
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload) || !is_numeric($payload['lat'] ?? null) || !is_numeric($payload['lng'] ?? null)) {
                continue;
            }
            $distance = Geo::haversineExactKm(
                (float) $payload['lat'],
                (float) $payload['lng'],
                (float) $row['town_latitude'],
                (float) $row['town_longitude']
            );
            if ($distance <= self::MAX_DECLARED_TOWN_DISTANCE_KM) {
                continue;
            }

            Database::query(
                'UPDATE provider_source_records SET needs_review=1,last_seen_at=NOW() WHERE id=?',
                [(int) $row['id']]
            );
            $providerId = (int) $row['provider_id'];
            if ((int) Database::scalar(
                'SELECT COUNT(*) FROM provider_source_records WHERE provider_id=? AND publishable=1 AND needs_review=0',
                [$providerId]
            ) === 0) {
                $this->quarantineUnclaimedProvider($providerId);
            }
            $quarantined++;
        }
        return $quarantined;
    }

    /** @return array<string,mixed>|null */
    private function nearestTown(float $lat, float $lng, string $state): ?array
    {
        $where = ['t.is_active = 1', "t.coordinate_confidence IN ('authoritative','statistical')", 't.latitude IS NOT NULL', 't.longitude IS NOT NULL'];
        $params = [$lat, $lng, $lat];
        if ($state !== '') {
            $where[] = 's.abbreviation = ?';
            $params[] = $state;
        }
        return Database::selectOne(
            'SELECT t.id, t.name, t.region_id, s.abbreviation AS state_abbr, '
            . '(6371 * ACOS(LEAST(1, GREATEST(-1, COS(RADIANS(?)) * COS(RADIANS(t.latitude)) '
            . '* COS(RADIANS(t.longitude) - RADIANS(?)) + SIN(RADIANS(?)) * SIN(RADIANS(t.latitude)))))) AS distance_km '
            . 'FROM towns t JOIN states s ON s.id = t.state_id WHERE ' . implode(' AND ', $where)
            . ' ORDER BY distance_km ASC LIMIT 1',
            $params
        );
    }

    /**
     * Queensland's current mandatory reporting feed supersedes unclaimed GA/OSM
     * fuel seeds. Claimed providers and non-fuel assignments are never changed.
     */
    private function retireSupersededQueenslandFuelSeeds(): int
    {
        $rows = Database::select(
            "SELECT psr.id,psr.provider_id,psr.payload_json FROM provider_source_records psr "
            . "JOIN providers p ON p.id=psr.provider_id WHERE p.is_unclaimed=1 "
            . "AND psr.publishable=1 AND psr.source_key IN ('geoscience-australia','openstreetmap','osm')"
        );
        $retired = 0;
        foreach ($rows as $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
            if (!is_array($payload) || strtoupper((string) ($payload['state'] ?? '')) !== 'QLD'
                || !in_array('fuel-station', (array) ($payload['categories'] ?? []), true)) {
                continue;
            }
            $providerId = (int) $row['provider_id'];
            if ((int) Database::scalar(
                "SELECT COUNT(*) FROM provider_source_records WHERE provider_id=? AND source_key='qld-fuel-reporting' AND publishable=1",
                [$providerId]
            ) > 0) {
                continue;
            }
            Database::query('UPDATE provider_source_records SET publishable=0,needs_review=1,last_seen_at=NOW() WHERE id=?', [(int) $row['id']]);
            Database::query(
                "DELETE a FROM provider_brand_category_assignments a "
                . "JOIN provider_brand_listings l ON l.id=a.listing_id "
                . "JOIN brand_provider_categories c ON c.id=a.category_id "
                . "WHERE l.provider_id=? AND c.category_key='fuel-station'",
                [$providerId]
            );
            Database::query(
                "UPDATE provider_brand_listings l SET l.status='draft',l.search_visible=0,l.updated_at=NOW() "
                . 'WHERE l.provider_id=? AND NOT EXISTS (SELECT 1 FROM provider_brand_category_assignments a WHERE a.listing_id=l.id)',
                [$providerId]
            );
            Database::query(
                "UPDATE providers p SET p.status='pending',p.updated_at=NOW() WHERE p.id=? AND p.is_unclaimed=1 "
                . "AND NOT EXISTS (SELECT 1 FROM provider_brand_listings l WHERE l.provider_id=p.id AND l.status='active' AND l.search_visible=1)",
                [$providerId]
            );
            $retired++;
        }
        return $retired;
    }

    /** @param array<string,mixed> $record */
    private function findProvider(string $sourceKey, string $externalId, array $record, int $townId): int
    {
        $id = (int) Database::scalar(
            'SELECT provider_id FROM provider_source_records WHERE source_key = ? AND external_id = ?',
            [$sourceKey, $externalId]
        );
        if ($id > 0) {
            return $id;
        }

        $slug = str_slug($externalId);
        $id = (int) Database::scalar('SELECT id FROM providers WHERE slug IN (?, ?) ORDER BY slug = ? DESC LIMIT 1', [$slug, 'lt-' . $slug, $slug]);
        if ($id > 0) {
            return $id;
        }

        $name = trim((string) ($record['name'] ?? ''));
        $phone = $this->phone($record['phone'] ?? null);
        if ($phone !== null && $townId > 0) {
            $id = (int) Database::scalar(
                'SELECT id FROM providers WHERE base_town_id = ? AND LOWER(business_name) = LOWER(?) '
                . 'AND (phone = ? OR public_phone = ?) LIMIT 1',
                [$townId, $name, $phone, $phone]
            );
        }
        return $id;
    }

    /** @param array<string,mixed> $record @param array{town_id:int,region_id:int,town_name:string,state:string,location_corrected:bool,location_conflict:bool} $location */
    private function insertProvider(array $record, string $externalId, array $location, bool $public): int
    {
        $phone = $this->phone($record['phone'] ?? null);
        $email = $this->email($record['email'] ?? null);
        $website = $this->url($record['website'] ?? null, 255);
        $lat = is_numeric($record['lat'] ?? null) ? (float) $record['lat'] : null;
        $lng = is_numeric($record['lng'] ?? null) ? (float) $record['lng'] : null;
        $slug = str_slug($externalId);
        if ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug = ?', [$slug]) > 0) {
            $slug = 'lt-' . $slug;
        }
        return Database::insert(
            'INSERT INTO providers (business_name, trading_name, operator_name, slug, abn, phone, public_phone, '
            . 'email, public_email, website, base_town_id, region_id, street_address, latitude, longitude, '
            . 'coordinates_approximate, opening_hours, operational_status, fuel_types_json, description, service_model, '
            . 'status, is_verified, is_unclaimed, auto_invite_opt_out, is_demo, plan, show_public_phone, show_public_email, '
            . 'source_note, source_url, source_type, source_licence, coverage_confidence, created_at, updated_at) '
            . "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, 1, 0, "
            . "'standard_free', ?, ?, ?, ?, ?, ?, 'curated', NOW(), NOW())",
            [
                trim((string) $record['name']), $this->text($record['trading_name'] ?? null, 190),
                $this->text($record['operator'] ?? null, 190), $slug, $this->text($record['abn'] ?? null, 20),
                $phone, $phone, $email, $email, $website, $location['town_id'] ?: null,
                $location['region_id'] ?: null, $this->text($record['address'] ?? null, 255), $lat, $lng,
                ($record['_coords_approx'] ?? false) === true ? 1 : 0,
                $this->text($record['opening_hours'] ?? null, 500), $this->text($record['operational_status'] ?? null, 60),
                $this->jsonList($record['fuel_types'] ?? null), $this->description($record), $this->serviceModel($record),
                $public ? 'active' : 'pending',
                $phone !== null ? 1 : 0, $email !== null ? 1 : 0, $this->sourceNote($record),
                $this->sourceUrl($record), $this->legacySourceType($record),
                $this->text($record['source_licence'] ?? null, 80),
            ]
        );
    }

    /** @param array<string,mixed> $record @param array{town_id:int,region_id:int,town_name:string,state:string,location_corrected:bool,location_conflict:bool} $location */
    private function enrichProvider(int $providerId, array $record, array $location): bool
    {
        $phone = $this->phone($record['phone'] ?? null);
        $email = $this->email($record['email'] ?? null);
        $lat = is_numeric($record['lat'] ?? null) ? (float) $record['lat'] : null;
        $lng = is_numeric($record['lng'] ?? null) ? (float) $record['lng'] : null;
        return Database::query(
            'UPDATE providers SET trading_name=COALESCE(NULLIF(trading_name,\'\'),?), operator_name=COALESCE(NULLIF(operator_name,\'\'),?), '
            . 'abn=COALESCE(NULLIF(abn,\'\'),?), phone=COALESCE(NULLIF(phone,\'\'),?), public_phone=COALESCE(NULLIF(public_phone,\'\'),?), '
            . 'email=COALESCE(NULLIF(email,\'\'),?), public_email=COALESCE(NULLIF(public_email,\'\'),?), website=COALESCE(NULLIF(website,\'\'),?), '
            . 'base_town_id=IF(?=1,?,COALESCE(base_town_id,?)), region_id=IF(?=1,?,COALESCE(region_id,?)), '
            . 'street_address=COALESCE(NULLIF(street_address,\'\'),?), '
            . 'latitude=COALESCE(latitude,?), longitude=COALESCE(longitude,?), opening_hours=COALESCE(NULLIF(opening_hours,\'\'),?), '
            . 'operational_status=COALESCE(NULLIF(operational_status,\'\'),?), fuel_types_json=COALESCE(NULLIF(fuel_types_json,\'\'),?), '
            . 'source_licence=COALESCE(NULLIF(source_licence,\'\'),?), show_public_phone=GREATEST(show_public_phone,?), '
            . 'show_public_email=GREATEST(show_public_email,?), updated_at=NOW() WHERE id=? AND is_unclaimed=1',
            [
                $this->text($record['trading_name'] ?? null, 190), $this->text($record['operator'] ?? null, 190),
                $this->text($record['abn'] ?? null, 20), $phone, $phone, $email, $email,
                $this->url($record['website'] ?? null, 255),
                $location['location_corrected'] ? 1 : 0, $location['town_id'] ?: null, $location['town_id'] ?: null,
                $location['location_corrected'] ? 1 : 0, $location['region_id'] ?: null, $location['region_id'] ?: null,
                $this->text($record['address'] ?? null, 255), $lat, $lng,
                $this->text($record['opening_hours'] ?? null, 500), $this->text($record['operational_status'] ?? null, 60),
                $this->jsonList($record['fuel_types'] ?? null), $this->text($record['source_licence'] ?? null, 80),
                $phone !== null ? 1 : 0, $email !== null ? 1 : 0, $providerId,
            ]
        )->rowCount() > 0;
    }

    /** @param array<string,mixed> $record */
    private function upsertSourceRecord(int $providerId, string $sourceKey, string $externalId, array $record, int $confidence, bool $reviewOnly): void
    {
        $payload = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Database::query(
            'INSERT INTO provider_source_records (provider_id,source_key,external_id,source_url,source_licence,confidence,publishable,needs_review,payload_json,first_seen_at,last_seen_at) '
            . 'VALUES (?,?,?,?,?,?,1,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE provider_id=VALUES(provider_id), source_url=VALUES(source_url), '
            . 'source_licence=VALUES(source_licence), confidence=VALUES(confidence), publishable=1, needs_review=VALUES(needs_review), '
            . 'payload_json=VALUES(payload_json), last_seen_at=NOW()',
            [$providerId, $sourceKey, $externalId, $this->sourceUrl($record), $this->text($record['source_licence'] ?? null, 80), $confidence, $reviewOnly ? 1 : 0, $payload]
        );
    }

    /** @param array<string,mixed> $record */
    private function upsertBrandListing(int $providerId, string $brandKey, array $record, bool $public): int
    {
        $brandId = $this->brandIds[$brandKey];
        $existing = (int) Database::scalar(
            'SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',
            [$brandId, $providerId]
        );
        if ($existing > 0) {
            $slug = (string) Database::scalar('SELECT slug FROM provider_brand_listings WHERE id=?', [$existing]);
        } else {
            $slug = str_slug((string) $record['id']);
            if ((int) Database::scalar(
                'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND slug=?',
                [$brandId, $slug]
            ) > 0) {
                $slug .= '-' . $providerId;
            }
        }
        $name = trim((string) $record['name']);
        Database::query(
            'INSERT INTO provider_brand_listings (brand_id,provider_id,slug,display_name,status,is_featured,is_verified,search_visible,created_at,updated_at) '
            . "VALUES (?,?,?,?,?,0,0,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE display_name=IF(status='active' AND is_verified=1,display_name,VALUES(display_name)), "
            . "status=IF(is_verified=1,status,VALUES(status)), search_visible=IF(is_verified=1,search_visible,VALUES(search_visible)), updated_at=NOW()",
            [$brandId, $providerId, $slug, $name, $public ? 'active' : 'draft', $public ? 1 : 0]
        );
        return (int) Database::scalar('SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?', [$brandId, $providerId]);
    }

    /**
     * Remove a known legacy VanAssist OSM classification error. The old source
     * mapped service-station LPG/fuel terminology to licensed gas-appliance
     * certification. That is not evidence that a servo performs gas work.
     *
     * @param array<string,mixed> $record
     * @param list<string> $categories
     * @return list<string>
     */
    public static function sanitiseCategories(array $record, array $categories): array
    {
        $source = strtolower(trim((string) ($record['source'] ?? '')));
        if ($source === 'vanassist-osm' && in_array('fuel-station', $categories, true)) {
            $categories = array_values(array_filter(
                $categories,
                static fn (string $category): bool => $category !== 'gas-certification'
            ));
        }
        $allowlist = self::conservativeCategoryAllowlist($record);
        if ($allowlist !== null) {
            $categories = array_values(array_intersect($categories, $allowlist));
        }
        return $categories;
    }

    /** @param array<string,mixed> $record @return list<string>|null */
    private static function conservativeCategoryAllowlist(array $record): ?array
    {
        $name = strtolower(trim((string) ($record['name'] ?? '')));
        if (preg_match('/\bbattery world\b|\bbatter(?:y|ies)\b/', $name) === 1) {
            return ['battery-specialist', 'auto-electrician'];
        }
        if (preg_match('/\btyrepower\b|\bbob jane\b|\btyre\b|\btire\b/', $name) === 1) {
            return ['tyre-shop'];
        }
        if (preg_match('/\bsupercheap\b|\bauto parts\b|\bparts (?:store|centre)\b/', $name) === 1) {
            return ['vehicle-parts'];
        }
        if (preg_match('/\bwindscreen\b|\bauto(?:motive)? glass\b/', $name) === 1) {
            return ['windscreen', 'side-rear-glass'];
        }
        if (in_array('fuel-station', (array) ($record['categories'] ?? []), true)
            && preg_match('/\bampol\b|\bcaltex\b|\bservice station\b|\bfuel\b|\bpetroleum\b/', $name) === 1) {
            return ['fuel-station', 'ev-charging'];
        }
        return null;
    }

    /** @param array<string,mixed> $record @param list<string> $categories */
    private function removeUnsupportedBrandCategoryAssignments(int $providerId, array $record, array $categories): void
    {
        if (self::conservativeCategoryAllowlist($record) === null) {
            return;
        }
        if ($categories === []) {
            Database::query(
                'DELETE a FROM provider_brand_category_assignments a '
                . 'JOIN provider_brand_listings l ON l.id=a.listing_id '
                . "WHERE l.provider_id=? AND a.is_verified=0 AND a.assignment_source IN ('import','heuristic')",
                [$providerId]
            );
            return;
        }
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        Database::query(
            'DELETE a FROM provider_brand_category_assignments a '
            . 'JOIN provider_brand_listings l ON l.id=a.listing_id '
            . 'JOIN brand_provider_categories c ON c.id=a.category_id '
            . "WHERE l.provider_id=? AND a.is_verified=0 AND a.assignment_source IN ('import','heuristic') "
            . "AND c.category_key NOT IN ({$placeholders})",
            array_merge([$providerId], $categories)
        );
    }

    /** @param array<string,mixed> $record */
    private function removeKnownBadFuelGasAssignments(int $providerId, array $record): void
    {
        $categories = array_map('strval', (array) ($record['categories'] ?? []));
        if (strtolower((string) ($record['source'] ?? '')) !== 'vanassist-osm'
            || !in_array('fuel-station', $categories, true)
            || !in_array('gas-certification', $categories, true)) {
            return;
        }

        Database::query(
            'DELETE a FROM provider_brand_category_assignments a '
            . 'JOIN provider_brand_listings l ON l.id=a.listing_id '
            . 'JOIN brand_provider_categories c ON c.id=a.category_id '
            . "WHERE l.provider_id=? AND c.category_key='gas-certification' "
            . "AND a.is_verified=0 AND a.assignment_source IN ('import','heuristic')",
            [$providerId]
        );
        Database::query(
            'DELETE ps FROM provider_services ps JOIN service_categories c ON c.id=ps.category_id '
            . "WHERE ps.provider_id=? AND c.name='Gas appliance servicing' AND ps.is_inferred=0",
            [$providerId]
        );
    }

    /** @param list<string> $categories @return list<string> */
    private function brandsForCategories(array $categories): array
    {
        $brands = [];
        foreach ($categories as $category) {
            foreach ($this->taxonomy[$category]['brands'] as $brand) {
                if (isset($this->brandIds[$brand])) {
                    $brands[$brand] = true;
                }
            }
        }
        return array_keys($brands);
    }

    /** @param list<string> $categories */
    private function linkVanAssistCompatibility(int $providerId, array $categories): void
    {
        $map = ['fuel-station' => 'fuel-and-travel-stops', 'ev-charging' => 'ev-charging'];
        foreach ($map as $packCategory => $serviceSlug) {
            if (!in_array($packCategory, $categories, true)) {
                continue;
            }
            $categoryId = (int) Database::scalar('SELECT id FROM service_categories WHERE slug=? AND is_active=1', [$serviceSlug]);
            if ($categoryId > 0) {
                Database::query(
                    'INSERT IGNORE INTO provider_services (provider_id,category_id,is_inferred,created_at) VALUES (?,?,0,NOW())',
                    [$providerId, $categoryId]
                );
            }
        }
    }

    private function loadTaxonomy(): void
    {
        $data = $this->loadJson('categories.json');
        foreach ((array) ($data['groups'] ?? []) as $group) {
            if (!is_array($group)) {
                continue;
            }
            $groupId = trim((string) ($group['id'] ?? ''));
            foreach ((array) ($group['categories'] ?? []) as $category) {
                if (!is_array($category)) {
                    continue;
                }
                $id = trim((string) ($category['id'] ?? ''));
                $brands = array_values(array_unique(array_filter(array_map('strval', (array) ($category['brands'] ?? [])))));
                if ($id !== '' && $brands !== []) {
                    $this->taxonomy[$id] = ['name' => (string) ($category['name'] ?? $id), 'group' => $groupId, 'brands' => $brands];
                }
            }
        }
        if ($this->taxonomy === []) {
            throw new RuntimeException('LocalTorque categories.json contains no usable categories.');
        }
    }

    private function prepareBrandCategories(): void
    {
        foreach (Database::select('SELECT id,brand_key FROM brands') as $brand) {
            $this->brandIds[(string) $brand['brand_key']] = (int) $brand['id'];
        }
        foreach ($this->taxonomy as $key => $category) {
            foreach ($category['brands'] as $brandKey) {
                $brandId = $this->brandIds[$brandKey] ?? 0;
                if ($brandId === 0) {
                    continue;
                }
                Database::query(
                    'INSERT INTO brand_provider_categories (brand_id,category_key,name,description,sort_order,is_active,created_at,updated_at) '
                    . 'VALUES (?,?,?,?,100,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE name=VALUES(name),description=VALUES(description),is_active=1,updated_at=NOW()',
                    [$brandId, $key, $category['name'], 'LocalTorque taxonomy group: ' . $category['group']]
                );
                $this->categoryIds[$brandKey][$key] = (int) Database::scalar(
                    'SELECT id FROM brand_provider_categories WHERE brand_id=? AND category_key=?',
                    [$brandId, $key]
                );
            }
        }
    }

    /** @return array<mixed> */
    private function loadJson(string $name): array
    {
        $path = base_path(self::PACK_DIR . '/' . $name);
        if (!is_file($path)) {
            throw new RuntimeException('Missing LocalTorque pack file: ' . $name);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid LocalTorque pack JSON: ' . $name);
        }
        return $decoded;
    }

    /** @param array<string,mixed> $record */
    private function description(array $record): string
    {
        $value = trim((string) (($record['description'] ?? '') ?: ($record['services_note'] ?? '')));
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** @param array<string,mixed> $record */
    private function serviceModel(array $record): string
    {
        $modes = array_map('strval', (array) ($record['modes'] ?? []));
        return in_array('mobile', $modes, true) && in_array('workshop', $modes, true)
            ? 'both'
            : (in_array('mobile', $modes, true) ? 'mobile' : 'workshop');
    }

    private function phone(mixed $value): ?string
    {
        $phone = trim(explode(';', (string) $value)[0]);
        return $phone !== '' ? mb_substr($phone, 0, 40) : null;
    }

    private function email(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : null;
    }

    private function url(mixed $value, int $max): ?string
    {
        $url = trim((string) $value);
        return filter_var($url, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//i', $url) === 1 ? mb_substr($url, 0, $max) : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);
        return $text !== '' ? mb_substr($text, 0, $max) : null;
    }

    private function jsonList(mixed $value): ?string
    {
        if (!is_array($value) || $value === []) {
            return null;
        }
        $json = json_encode(array_values(array_unique(array_map('strval', $value))), JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    /** @param array<string,mixed> $record */
    private function sourceUrl(array $record): ?string
    {
        $url = $this->url($record['source_url'] ?? null, 1000);
        if ($url !== null) {
            return $url;
        }
        return match (strtolower((string) ($record['source'] ?? ''))) {
            'geoscience-australia' => 'https://ecat.ga.gov.au/geonetwork/srv/eng/catalog.search#/metadata/147830',
            'openstreetmap', 'vanassist-osm', 'localtorque-osm' => 'https://www.openstreetmap.org/copyright',
            default => null,
        };
    }

    /** @param array<string,mixed> $record */
    private function sourceNote(array $record): string
    {
        $source = trim((string) ($record['source'] ?? 'public source'));
        $licence = trim((string) ($record['source_licence'] ?? ''));
        return mb_substr('Unclaimed public-source listing — ' . $source . ($licence !== '' ? ' (' . $licence . ')' : ''), 0, 190);
    }

    /** @param array<string,mixed> $record */
    private function legacySourceType(array $record): string
    {
        $source = strtolower((string) ($record['source'] ?? ''));
        return str_contains($source, 'osm') || $source === 'openstreetmap' ? 'osm' : 'national';
    }
}
