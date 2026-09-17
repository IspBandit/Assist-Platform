<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\CaravanPark;
use App\Models\Town;
use RuntimeException;
use Throwable;

/**
 * Idempotent CPAQ 2026 directory import (DATA-001 / VAN-001).
 *
 * Parks → caravan_parks + stay_facility_claims.
 * Trade → providers + provider_services + provider_source_records + VanAssist listing.
 * Claimed providers and operator-verified parks are never downgraded.
 */
final class Cpaq2026ImportService
{
    public const SOURCE_NAME = 'CPAQ Explore Queensland Caravan Parks Directory 2026';
    public const SOURCE_ORGANISATION = 'Caravan Parks Association of Queensland Ltd';
    public const SOURCE_YEAR = 2026;
    public const SOURCE_URL = 'https://www.caravanqld.com.au/';
    public const PARKS_SOURCE_TYPE = 'cpaq';
    public const PROVIDERS_SOURCE_KEY = 'cpaq-2026';
    public const SOURCE_NOTE = 'CPAQ authorised 2026 directory import (Caravan Parks Association of Queensland Ltd)';

    private const SEED_DIR = 'database/seeds/cpaq-2026';
    private const PARK_EXTERNAL_ID_MAX = 100;

    /** CPAQ trade labels with no safe service_categories attach (payload only). */
    private const SCHEMA_GAP_CATEGORIES = [
        'Finance' => true,
        'Driver Instruction or Training' => true,
    ];

    /** @var array<string,list<string>> */
    private const TRADE_CATEGORY_MAP = [
        'Accessories' => ['caravan-and-rv-parts', 'vehicle-parts-and-accessories'],
        'Annexe & Awnings' => ['awning-repairs'],
        'Camper Trailer Sales' => ['caravan-and-rv-parts'],
        'Caravan Sales' => ['caravan-and-rv-parts'],
        'Communications' => ['starlink-and-communications'],
        'Conversions' => ['structural-repairs', 'general-caravan-repairs'],
        'Driver Instruction or Training' => [],
        'Engineering' => ['trailer-and-engineering', 'mobile-welding-and-fabrication'],
        'Fifth Wheeler Sales' => ['caravan-and-rv-parts'],
        'Finance' => [],
        'Gas' => ['gas-appliance-servicing', 'lpg-refills-and-bottle-exchange'],
        'Hire' => ['caravan-and-rv-parts'],
        'Hybrid Sales' => ['caravan-and-rv-parts'],
        'Insurance' => ['insurance-repairs'],
        'Motorhome / Campervan Sales' => ['caravan-and-rv-parts'],
        'Refrigeration' => ['refrigeration'],
        'Safety Certificate' => ['roadworthy-inspection'],
        'Service & Repair' => ['general-caravan-repairs', 'general-servicing'],
        'Slide-on Sales' => ['caravan-and-rv-parts'],
        'Solar Products' => ['solar-and-batteries', '12-volt-electrical'],
        'Tents' => ['caravan-and-rv-parts'],
        'Towing' => ['towing-and-vehicle-recovery', 'towing-equipment-and-accessories'],
        'Vehicle Modification' => ['vehicle-parts-and-accessories', 'structural-repairs'],
        'Weight' => ['weighbridges-and-mobile-weighing'],
    ];

    /** Park attribute keys that map to facility claims and/or columns. */
    private const MAPPED_PARK_ATTRIBUTES = [
        'dump_point' => true,
        'pets_allowed' => true,
        'powered_sites' => true,
        'unpowered_sites' => true,
        'camp_kitchen' => true,
        'wifi' => true,
        'rv_motorhome_sites' => true,
        'accessible' => true,
    ];

    private string $seedDir;

    /** @var array<string,int> */
    private array $serviceCategoryIds = [];

    private int $qldStateId = 0;

    public function __construct(?string $seedDir = null)
    {
        $this->seedDir = $seedDir ?? (BASE_PATH . '/' . self::SEED_DIR);
    }

    /**
     * @return array{
     *   mode:string,
     *   parks:array<string,int>,
     *   trade:array<string,int>,
     *   multi_category_businesses:int,
     *   lacking_websites:int,
     *   lacking_addresses:int,
     *   geocoding_failures:int,
     *   schema_fields_unmapped:int,
     *   source:array<string,mixed>
     * }
     */
    public function import(bool $apply, bool $importParks = true, bool $importTrade = true): array
    {
        $parksCounts = $this->emptyEntityCounts();
        $tradeCounts = $this->emptyEntityCounts();
        $multiCategory = 0;
        $lackingWebsites = 0;
        $lackingAddresses = 0;
        $schemaUnmapped = 0;
        $geocodingFailures = 0;

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            if ($importParks) {
                $result = $this->importParks();
                $parksCounts = $result['counts'];
                $lackingWebsites += $result['lacking_websites'];
                $lackingAddresses += $result['lacking_addresses'];
                $schemaUnmapped += $result['schema_fields_unmapped'];
                $geocodingFailures += $result['geocoding_failures'];
            }
            if ($importTrade) {
                $result = $this->importTrade();
                $tradeCounts = $result['counts'];
                $multiCategory = $result['multi_category_businesses'];
                $lackingWebsites += $result['lacking_websites'];
                $lackingAddresses += $result['lacking_addresses'];
                $schemaUnmapped += $result['schema_fields_unmapped'];
            }
            if ($apply) {
                $pdo->commit();
            } else {
                $pdo->rollBack();
            }
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $error;
        }

        return [
            'mode' => $apply ? 'applied' : 'dry-run',
            'parks' => $parksCounts,
            'trade' => $tradeCounts,
            'multi_category_businesses' => $multiCategory,
            'lacking_websites' => $lackingWebsites,
            'lacking_addresses' => $lackingAddresses,
            'geocoding_failures' => $geocodingFailures,
            'schema_fields_unmapped' => $schemaUnmapped,
            'source' => [
                'source_name' => self::SOURCE_NAME,
                'source_organisation' => self::SOURCE_ORGANISATION,
                'source_year' => self::SOURCE_YEAR,
                'authorised_import' => true,
                'source_url' => self::SOURCE_URL,
                'parks_source_type' => self::PARKS_SOURCE_TYPE,
                'providers_source_key' => self::PROVIDERS_SOURCE_KEY,
            ],
        ];
    }

    /**
     * Map CPAQ trade category labels to VanAssist service_categories slugs.
     *
     * @param list<string> $categories
     * @return array{slugs:list<string>,unmapped:list<string>,schema_gap:list<string>}
     */
    public static function mapTradeCategories(array $categories): array
    {
        $slugs = [];
        $unmapped = [];
        $schemaGap = [];
        foreach ($categories as $raw) {
            $label = trim((string) $raw);
            if ($label === '') {
                continue;
            }
            if (!array_key_exists($label, self::TRADE_CATEGORY_MAP)) {
                $unmapped[] = $label;
                continue;
            }
            if (isset(self::SCHEMA_GAP_CATEGORIES[$label])) {
                $schemaGap[] = $label;
                continue;
            }
            foreach (self::TRADE_CATEGORY_MAP[$label] as $slug) {
                $slugs[$slug] = true;
            }
        }

        return [
            'slugs' => array_keys($slugs),
            'unmapped' => array_values(array_unique($unmapped)),
            'schema_gap' => array_values(array_unique($schemaGap)),
        ];
    }

    public static function normaliseName(string $name): string
    {
        $value = mb_strtolower(trim($name));
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';

        return $value;
    }

    public static function normalisePhone(mixed $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '61') && strlen($digits) >= 10) {
            $digits = '0' . substr($digits, 2);
        }

        return $digits !== '' ? mb_substr($digits, 0, 40) : null;
    }

    public static function websiteHost(mixed $url): ?string
    {
        $raw = trim((string) $url);
        if ($raw === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $host = strtolower((string) parse_url($raw, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        return $host !== '' ? $host : null;
    }

    /**
     * Tourist overnight stays only — pure residential without tourist detail is skipped.
     *
     * @param array<string,mixed> $record
     */
    public static function shouldImportPark(array $record): bool
    {
        if (!empty($record['pure_residential']) && empty($record['detail_matched'])) {
            $kind = (string) ($record['listing_kind'] ?? '');
            return $kind === 'caravan_holiday_park';
        }

        return !empty($record['detail_matched'])
            || (string) ($record['listing_kind'] ?? '') === 'caravan_holiday_park'
            || empty($record['pure_residential']);
    }

    /**
     * @param array<string,mixed> $attributes
     * @return list<array{facility_type:string,facility_status:string,facility_value:?string}>
     */
    public static function facilityClaimsFromAttributes(array $attributes): array
    {
        $claims = [];
        $boolFacilities = [
            'dump_point' => 'dump_point',
            'camp_kitchen' => 'camp_kitchen',
            'wifi' => 'wifi',
            'powered_sites' => 'powered_sites',
            'unpowered_sites' => 'unpowered_sites',
            'accessible' => 'accessibility',
        ];
        foreach ($boolFacilities as $attr => $type) {
            if (!array_key_exists($attr, $attributes)) {
                continue;
            }
            $value = $attributes[$attr];
            if ($value === true || $value === 1 || $value === '1') {
                $claims[] = ['facility_type' => $type, 'facility_status' => 'yes', 'facility_value' => null];
            } elseif ($value === false || $value === 0 || $value === '0') {
                $claims[] = ['facility_type' => $type, 'facility_status' => 'no', 'facility_value' => null];
            }
        }

        if (array_key_exists('pets_allowed', $attributes)) {
            $pets = $attributes['pets_allowed'];
            if ($pets === true || $pets === 1 || $pets === '1' || $pets === 'yes') {
                $claims[] = ['facility_type' => 'pet_friendly', 'facility_status' => 'yes', 'facility_value' => null];
            } elseif ($pets === false || $pets === 0 || $pets === '0' || $pets === 'no') {
                $claims[] = ['facility_type' => 'pet_friendly', 'facility_status' => 'no', 'facility_value' => null];
            } elseif (is_string($pets) && strtolower(trim($pets)) === 'on_application') {
                $claims[] = [
                    'facility_type' => 'pet_friendly',
                    'facility_status' => 'conditional',
                    'facility_value' => 'on_application',
                ];
            }
        }

        if (!empty($attributes['rv_motorhome_sites'])) {
            $claims[] = ['facility_type' => 'motorhome_suitable', 'facility_status' => 'yes', 'facility_value' => null];
            $claims[] = ['facility_type' => 'big_rig_suitable', 'facility_status' => 'yes', 'facility_value' => null];
        }

        return $claims;
    }

    /**
     * Column values for caravan_parks facility flags (null = leave / unknown).
     *
     * @param array<string,mixed> $attributes
     * @return array{dump_point:?int,pets_allowed:?int,powered_sites:?int,unpowered_sites:?int}
     */
    public static function parkColumnsFromAttributes(array $attributes): array
    {
        $bool = static function (mixed $value): ?int {
            if ($value === true || $value === 1 || $value === '1') {
                return 1;
            }
            if ($value === false || $value === 0 || $value === '0') {
                return 0;
            }

            return null;
        };

        $pets = $attributes['pets_allowed'] ?? null;
        $petsAllowed = null;
        if ($pets === true || $pets === 1 || $pets === '1' || $pets === 'yes') {
            $petsAllowed = 1;
        } elseif ($pets === false || $pets === 0 || $pets === '0' || $pets === 'no') {
            $petsAllowed = 0;
        }

        return [
            'dump_point' => $bool($attributes['dump_point'] ?? null),
            'pets_allowed' => $petsAllowed,
            'powered_sites' => $bool($attributes['powered_sites'] ?? null),
            'unpowered_sites' => $bool($attributes['unpowered_sites'] ?? null),
        ];
    }

    public static function truncateParkExternalId(string $externalId): string
    {
        if (strlen($externalId) <= self::PARK_EXTERNAL_ID_MAX) {
            return $externalId;
        }

        return substr($externalId, 0, self::PARK_EXTERNAL_ID_MAX);
    }

    /** @return array{counts:array<string,int>,lacking_websites:int,lacking_addresses:int,schema_fields_unmapped:int,geocoding_failures:int} */
    private function importParks(): array
    {
        $rows = $this->loadJsonList('parks.json');
        $counts = $this->emptyEntityCounts();
        $lackingWebsites = 0;
        $lackingAddresses = 0;
        $schemaUnmapped = 0;
        $geocodingFailures = 0;
        $seen = [];
        $this->qldStateId = $this->qldStateId();

        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                $counts['failed']++;
                continue;
            }
            $counts['parsed']++;
            $externalId = self::truncateParkExternalId(trim((string) ($raw['external_id'] ?? '')));
            $name = trim((string) ($raw['name'] ?? ''));
            if ($externalId === '' || $name === '') {
                $counts['failed']++;
                continue;
            }
            if (isset($seen[$externalId])) {
                $counts['skipped_duplicate']++;
                continue;
            }
            $seen[$externalId] = true;

            if (!self::shouldImportPark($raw)) {
                $counts['unresolved']++;
                continue;
            }

            $website = $this->url($raw['website'] ?? null, 255);
            $address = $this->composeAddress($raw);
            if ($website === null) {
                $lackingWebsites++;
            }
            if ($address === null) {
                $lackingAddresses++;
            }

            $attributes = is_array($raw['attributes'] ?? null) ? $raw['attributes'] : [];
            foreach (array_keys($attributes) as $key) {
                if (!isset(self::MAPPED_PARK_ATTRIBUTES[$key])) {
                    $schemaUnmapped++;
                }
            }

            try {
                $existingId = $this->findParkId($externalId, $raw);
                if ($existingId > 0) {
                    $this->updatePark($existingId, $raw, $externalId, $address, $website, $attributes);
                    $counts['updated']++;
                    $parkId = $existingId;
                } else {
                    $parkId = $this->insertPark($raw, $externalId, $address, $website, $attributes);
                    $counts['inserted']++;
                }
                $this->upsertFacilityClaims($parkId, $externalId, $attributes);
                $hasCoords = (int) Database::scalar(
                    'SELECT COUNT(*) FROM caravan_parks WHERE id=? AND latitude IS NOT NULL AND longitude IS NOT NULL',
                    [$parkId]
                ) > 0;
                if (!$hasCoords) {
                    $counts['geocode_skipped']++;
                    $geocodingFailures++;
                }
            } catch (Throwable) {
                $counts['failed']++;
            }
        }

        return [
            'counts' => $counts,
            'lacking_websites' => $lackingWebsites,
            'lacking_addresses' => $lackingAddresses,
            'schema_fields_unmapped' => $schemaUnmapped,
            'geocoding_failures' => $geocodingFailures,
        ];
    }

    /** @return array{counts:array<string,int>,multi_category_businesses:int,lacking_websites:int,lacking_addresses:int,schema_fields_unmapped:int} */
    private function importTrade(): array
    {
        $rows = $this->loadJsonList('trade.json');
        $counts = $this->emptyEntityCounts();
        $multiCategory = 0;
        $lackingWebsites = 0;
        $lackingAddresses = 0;
        $schemaUnmapped = 0;
        $seen = [];
        $this->loadServiceCategories();

        foreach ($rows as $raw) {
            if (!is_array($raw)) {
                $counts['failed']++;
                continue;
            }
            $counts['parsed']++;
            $externalId = mb_substr(trim((string) ($raw['external_id'] ?? '')), 0, 190);
            $name = trim((string) ($raw['name'] ?? ''));
            if ($externalId === '' || $name === '') {
                $counts['failed']++;
                continue;
            }
            if (isset($seen[$externalId])) {
                $counts['skipped_duplicate']++;
                continue;
            }
            $seen[$externalId] = true;

            $categories = array_values(array_filter(array_map('strval', (array) ($raw['categories'] ?? []))));
            if (count($categories) > 1) {
                $multiCategory++;
            }
            $mapped = self::mapTradeCategories($categories);
            $schemaUnmapped += count($mapped['unmapped']) + count($mapped['schema_gap']);

            $website = $this->url($raw['website'] ?? null, 255);
            if ($website === null) {
                $lackingWebsites++;
            }
            // Trade seed has suburb/region but no street address field today.
            $lackingAddresses++;

            try {
                $town = $this->resolveTradeTown($raw);
                $providerId = $this->findProviderId($externalId, $raw, $town['town_id']);
                $created = false;
                if ($providerId === 0) {
                    $providerId = $this->insertProvider($raw, $externalId, $town, $website);
                    $counts['inserted']++;
                    $created = true;
                } else {
                    $isUnclaimed = (int) Database::scalar('SELECT is_unclaimed FROM providers WHERE id=?', [$providerId]) === 1;
                    if ($isUnclaimed) {
                        $this->enrichProvider($providerId, $raw, $town, $website);
                    }
                    $counts['updated']++;
                }

                $this->upsertProviderSourceRecord($providerId, $externalId, $raw);
                $isUnclaimed = (int) Database::scalar('SELECT is_unclaimed FROM providers WHERE id=?', [$providerId]) === 1;
                if ($isUnclaimed || $created) {
                    $this->linkServices($providerId, $mapped['slugs']);
                    $this->ensureVanAssistListing($providerId, $raw, $externalId);
                }
            } catch (Throwable) {
                $counts['failed']++;
            }
        }

        return [
            'counts' => $counts,
            'multi_category_businesses' => $multiCategory,
            'lacking_websites' => $lackingWebsites,
            'lacking_addresses' => $lackingAddresses,
            'schema_fields_unmapped' => $schemaUnmapped,
        ];
    }

    /** @param array<string,mixed> $raw */
    private function findParkId(string $externalId, array $raw): int
    {
        $id = (int) Database::scalar(
            'SELECT id FROM caravan_parks WHERE source_type=? AND external_id=? AND deleted_at IS NULL',
            [self::PARKS_SOURCE_TYPE, $externalId]
        );
        if ($id > 0) {
            return $id;
        }
        if (Database::tableExists('caravan_park_source_aliases')) {
            $id = (int) Database::scalar(
                'SELECT park_id FROM caravan_park_source_aliases WHERE source_type=? AND external_id=?',
                [self::PARKS_SOURCE_TYPE, $externalId]
            );
            if ($id > 0) {
                return $id;
            }
        }

        if ($this->qldStateId < 1) {
            return 0;
        }

        $name = self::normaliseName((string) ($raw['name'] ?? ''));
        if ($name === '') {
            return 0;
        }

        $phone = self::normalisePhone($raw['phone'] ?? null);
        $host = self::websiteHost($raw['website'] ?? null);
        $postcode = preg_replace('/\D+/', '', (string) ($raw['postcode'] ?? '')) ?? '';
        $suburb = trim((string) ($raw['suburb'] ?? ''));

        $candidates = Database::select(
            'SELECT cp.id, cp.name, cp.phone, cp.website, cp.address, t.name AS town_name, t.primary_postcode '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'WHERE cp.state_id=? AND cp.deleted_at IS NULL',
            [$this->qldStateId]
        );

        foreach ($candidates as $candidate) {
            if (self::normaliseName((string) $candidate['name']) !== $name) {
                continue;
            }
            $matched = false;
            if ($phone !== null && self::normalisePhone($candidate['phone'] ?? null) === $phone) {
                $matched = true;
            }
            if (!$matched && $host !== null && self::websiteHost($candidate['website'] ?? null) === $host) {
                $matched = true;
            }
            if (!$matched && $suburb !== '' && strcasecmp((string) ($candidate['town_name'] ?? ''), $suburb) === 0) {
                $matched = true;
            }
            if (!$matched && $postcode !== '' && (
                (string) ($candidate['primary_postcode'] ?? '') === $postcode
                || str_contains((string) ($candidate['address'] ?? ''), $postcode)
            )) {
                $matched = true;
            }
            if ($matched) {
                return (int) $candidate['id'];
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $attributes
     */
    private function insertPark(array $raw, string $externalId, ?string $address, ?string $website, array $attributes): int
    {
        $town = $this->resolveParkTown($raw);
        $cols = self::parkColumnsFromAttributes($attributes);
        $description = $this->text($raw['description'] ?? null, 65000);
        $slug = CaravanPark::uniqueSlug(trim((string) $raw['name']) . '-' . trim((string) ($raw['suburb'] ?? $raw['postcode'] ?? 'qld')));

        return Database::insert(
            'INSERT INTO caravan_parks (
                name, slug, address, town_id, region_id, state_id, phone, email, website,
                description, stay_type, price_type, powered_sites, unpowered_sites, dump_point, pets_allowed,
                source_type, source_url, external_id, source_checked_at, verification_type, listing_plan,
                public_page_enabled, status, is_demo, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?,?,1,\'active\',0,NOW(),NOW())',
            [
                trim((string) $raw['name']),
                $slug,
                $address,
                $town['town_id'] ?: null,
                $town['region_id'] ?: null,
                $this->qldStateId ?: null,
                $this->phone($raw['phone'] ?? null),
                $this->email($raw['email'] ?? null),
                $website,
                $description,
                'caravan_park',
                'paid',
                $cols['powered_sites'],
                $cols['unpowered_sites'],
                $cols['dump_point'],
                $cols['pets_allowed'],
                self::PARKS_SOURCE_TYPE,
                self::SOURCE_URL,
                $externalId,
                'unverified',
                'free',
            ]
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @param array<string,mixed> $attributes
     */
    private function updatePark(int $parkId, array $raw, string $externalId, ?string $address, ?string $website, array $attributes): void
    {
        $town = $this->resolveParkTown($raw);
        $cols = self::parkColumnsFromAttributes($attributes);
        $description = $this->text($raw['description'] ?? null, 65000);

        Database::query(
            'UPDATE caravan_parks SET
                name = ?,
                address = COALESCE(NULLIF(?, \'\'), address),
                town_id = COALESCE(?, town_id),
                region_id = COALESCE(?, region_id),
                state_id = COALESCE(?, state_id),
                phone = COALESCE(NULLIF(?, \'\'), phone),
                email = COALESCE(NULLIF(?, \'\'), email),
                website = COALESCE(NULLIF(?, \'\'), website),
                description = IF(description IS NULL OR description = \'\', ?, description),
                stay_type = COALESCE(stay_type, \'caravan_park\'),
                price_type = IF(price_type IS NULL OR price_type = \'unknown\', \'paid\', price_type),
                powered_sites = COALESCE(?, powered_sites),
                unpowered_sites = COALESCE(?, unpowered_sites),
                dump_point = COALESCE(?, dump_point),
                pets_allowed = COALESCE(?, pets_allowed),
                source_url = COALESCE(NULLIF(source_url, \'\'), ?),
                source_checked_at = NOW(),
                verification_type = CASE
                    WHEN verification_type IN (\'operator\', \'authority\', \'community\') THEN verification_type
                    ELSE verification_type
                END,
                listing_plan = COALESCE(listing_plan, \'free\'),
                public_page_enabled = 1,
                status = IF(status IN (\'suspended\', \'rejected\'), status, \'active\'),
                updated_at = NOW()
            WHERE id = ? AND deleted_at IS NULL',
            [
                trim((string) $raw['name']),
                $address,
                $town['town_id'] ?: null,
                $town['region_id'] ?: null,
                $this->qldStateId ?: null,
                $this->phone($raw['phone'] ?? null),
                $this->email($raw['email'] ?? null),
                $website,
                $description,
                $cols['powered_sites'],
                $cols['unpowered_sites'],
                $cols['dump_point'],
                $cols['pets_allowed'],
                self::SOURCE_URL,
                $parkId,
            ]
        );

        $currentSource = Database::selectOne(
            'SELECT source_type, external_id FROM caravan_parks WHERE id=?',
            [$parkId]
        );
        if ($currentSource !== null
            && (string) ($currentSource['source_type'] ?? '') === self::PARKS_SOURCE_TYPE
            && (string) ($currentSource['external_id'] ?? '') === $externalId
        ) {
            return;
        }
        if ($currentSource !== null
            && (empty($currentSource['source_type']) || empty($currentSource['external_id']))
        ) {
            Database::query(
                'UPDATE caravan_parks SET source_type=?, external_id=?, updated_at=NOW() WHERE id=?',
                [self::PARKS_SOURCE_TYPE, $externalId, $parkId]
            );

            return;
        }
        if (Database::tableExists('caravan_park_source_aliases')) {
            Database::query(
                'INSERT IGNORE INTO caravan_park_source_aliases (park_id, source_type, external_id, source_url, created_at, updated_at)
                 VALUES (?,?,?,?,NOW(),NOW())',
                [$parkId, self::PARKS_SOURCE_TYPE, $externalId, self::SOURCE_URL]
            );
        }
    }

    /** @param array<string,mixed> $attributes */
    private function upsertFacilityClaims(int $parkId, string $externalId, array $attributes): void
    {
        if (!Database::tableExists('stay_facility_claims')) {
            return;
        }
        foreach (self::facilityClaimsFromAttributes($attributes) as $claim) {
            $existing = (int) Database::scalar(
                'SELECT id FROM stay_facility_claims
                 WHERE park_id=? AND facility_type=? AND source_type=\'trusted_import\'
                   AND source_record_id=? AND superseded_at IS NULL
                 ORDER BY id DESC LIMIT 1',
                [$parkId, $claim['facility_type'], $externalId]
            );
            if ($existing > 0) {
                Database::query(
                    'UPDATE stay_facility_claims SET facility_status=?, facility_value=?, source_name=?,
                        source_url=?, source_confidence=80, last_seen_at=NOW(), updated_at=NOW()
                     WHERE id=?',
                    [
                        $claim['facility_status'],
                        $claim['facility_value'],
                        self::SOURCE_NAME,
                        self::SOURCE_URL,
                        $existing,
                    ]
                );
                continue;
            }
            Database::query(
                'INSERT INTO stay_facility_claims (
                    park_id, facility_type, facility_status, facility_value, details,
                    source_type, source_name, source_url, source_record_id, source_confidence,
                    source_specificity, verified_at, last_seen_at, created_at, updated_at
                ) VALUES (?,?,?,?,NULL,\'trusted_import\',?,?,?,80,\'facility\',NULL,NOW(),NOW(),NOW())',
                [
                    $parkId,
                    $claim['facility_type'],
                    $claim['facility_status'],
                    $claim['facility_value'],
                    self::SOURCE_NAME,
                    self::SOURCE_URL,
                    $externalId,
                ]
            );
        }
    }

    /** @param array<string,mixed> $raw */
    private function findProviderId(string $externalId, array $raw, int $townId): int
    {
        $id = (int) Database::scalar(
            'SELECT provider_id FROM provider_source_records WHERE source_key=? AND external_id=?',
            [self::PROVIDERS_SOURCE_KEY, $externalId]
        );
        if ($id > 0) {
            return $id;
        }

        $slug = str_slug($externalId);
        $id = (int) Database::scalar(
            'SELECT id FROM providers WHERE slug IN (?, ?) AND deleted_at IS NULL ORDER BY slug = ? DESC LIMIT 1',
            [$slug, 'cpaq-' . $slug, $slug]
        );
        if ($id > 0) {
            return $id;
        }

        $name = trim((string) ($raw['name'] ?? ''));
        $phone = self::normalisePhone($raw['phone'] ?? null);
        $host = self::websiteHost($raw['website'] ?? null);

        if ($phone !== null && $name !== '') {
            $rows = Database::select(
                'SELECT id, business_name, phone, public_phone, website FROM providers WHERE deleted_at IS NULL
                 AND (phone = ? OR public_phone = ? OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,\'\'),\' \',\'\'),\'-\',\'\'),\'(\',\'\'),\')\',\'\') = ?
                     OR REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(public_phone,\'\'),\' \',\'\'),\'-\',\'\'),\'(\',\'\'),\')\',\'\') = ?)',
                [$phone, $phone, $phone, $phone]
            );
            foreach ($rows as $row) {
                if (self::normaliseName((string) $row['business_name']) === self::normaliseName($name)) {
                    return (int) $row['id'];
                }
            }
        }

        if ($host !== null && $name !== '') {
            $rows = Database::select(
                'SELECT id, business_name, website FROM providers WHERE deleted_at IS NULL AND website IS NOT NULL AND website != \'\''
            );
            foreach ($rows as $row) {
                if (self::websiteHost($row['website'] ?? null) === $host
                    && self::normaliseName((string) $row['business_name']) === self::normaliseName($name)
                ) {
                    return (int) $row['id'];
                }
            }
        }

        if ($townId > 0 && $name !== '') {
            $id = (int) Database::scalar(
                'SELECT id FROM providers WHERE base_town_id=? AND LOWER(business_name)=LOWER(?) AND deleted_at IS NULL LIMIT 1',
                [$townId, $name]
            );
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @param array<string,mixed> $raw
     * @param array{town_id:int,region_id:int} $town
     */
    private function insertProvider(array $raw, string $externalId, array $town, ?string $website): int
    {
        $phone = $this->phone($raw['phone'] ?? null);
        $slug = str_slug($externalId);
        if ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug=?', [$slug]) > 0) {
            $slug = 'cpaq-' . $slug;
        }
        $serviceModel = !empty($raw['is_mobile']) ? 'mobile' : 'workshop';
        if (!empty($raw['is_mobile']) && !empty($raw['is_online'])) {
            $serviceModel = 'both';
        }

        return Database::insert(
            'INSERT INTO providers (
                business_name, slug, phone, public_phone, website, base_town_id, region_id,
                description, service_model, status, is_verified, is_unclaimed, auto_invite_opt_out,
                show_public_phone, show_public_email, source_note, source_url, source_type,
                coverage_confidence, plan, created_at, updated_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,0,1,1,?,?,?,?,\'national\',\'curated\',\'standard_free\',NOW(),NOW())',
            [
                trim((string) $raw['name']),
                $slug,
                $phone,
                $phone,
                $website,
                $town['town_id'] ?: null,
                $town['region_id'] ?: null,
                null,
                $serviceModel,
                'active',
                $phone !== null ? 1 : 0,
                0,
                self::SOURCE_NOTE,
                self::SOURCE_URL,
            ]
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @param array{town_id:int,region_id:int} $town
     */
    private function enrichProvider(int $providerId, array $raw, array $town, ?string $website): void
    {
        $phone = $this->phone($raw['phone'] ?? null);
        Database::query(
            'UPDATE providers SET
                phone = COALESCE(NULLIF(phone, \'\'), ?),
                public_phone = COALESCE(NULLIF(public_phone, \'\'), ?),
                website = COALESCE(NULLIF(website, \'\'), ?),
                base_town_id = COALESCE(base_town_id, ?),
                region_id = COALESCE(region_id, ?),
                source_note = COALESCE(NULLIF(source_note, \'\'), ?),
                source_url = COALESCE(NULLIF(source_url, \'\'), ?),
                show_public_phone = GREATEST(show_public_phone, ?),
                status = IF(status IN (\'draft\', \'pending\'), \'active\', status),
                updated_at = NOW()
             WHERE id=? AND is_unclaimed=1',
            [
                $phone,
                $phone,
                $website,
                $town['town_id'] ?: null,
                $town['region_id'] ?: null,
                self::SOURCE_NOTE,
                self::SOURCE_URL,
                $phone !== null ? 1 : 0,
                $providerId,
            ]
        );
    }

    /** @param array<string,mixed> $raw */
    private function upsertProviderSourceRecord(int $providerId, string $externalId, array $raw): void
    {
        $payload = json_encode($raw, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Database::query(
            'INSERT INTO provider_source_records (
                provider_id, source_key, external_id, source_url, source_licence, confidence,
                publishable, needs_review, payload_json, first_seen_at, last_seen_at
            ) VALUES (?,?,?,?,?,85,1,0,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
                provider_id = VALUES(provider_id),
                source_url = VALUES(source_url),
                confidence = VALUES(confidence),
                publishable = 1,
                needs_review = 0,
                payload_json = VALUES(payload_json),
                last_seen_at = NOW()',
            [$providerId, self::PROVIDERS_SOURCE_KEY, $externalId, self::SOURCE_URL, 'CPAQ authorised', $payload]
        );
    }

    /** @param list<string> $slugs */
    private function linkServices(int $providerId, array $slugs): void
    {
        foreach ($slugs as $slug) {
            $categoryId = $this->serviceCategoryIds[$slug] ?? 0;
            if ($categoryId < 1) {
                continue;
            }
            Database::query(
                'INSERT IGNORE INTO provider_services (provider_id, category_id, is_inferred, created_at) VALUES (?,?,0,NOW())',
                [$providerId, $categoryId]
            );
        }
    }

    /** @param array<string,mixed> $raw */
    private function ensureVanAssistListing(int $providerId, array $raw, string $externalId): void
    {
        $brandId = (int) Database::scalar("SELECT id FROM brands WHERE brand_key='vanassist' LIMIT 1");
        if ($brandId < 1) {
            return;
        }
        $existing = (int) Database::scalar(
            'SELECT id FROM provider_brand_listings WHERE brand_id=? AND provider_id=?',
            [$brandId, $providerId]
        );
        $slug = $existing > 0
            ? (string) Database::scalar('SELECT slug FROM provider_brand_listings WHERE id=?', [$existing])
            : str_slug($externalId);
        if ($existing < 1 && (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND slug=?',
            [$brandId, $slug]
        ) > 0) {
            $slug .= '-' . $providerId;
        }
        $name = trim((string) $raw['name']);
        Database::query(
            'INSERT INTO provider_brand_listings (
                brand_id, provider_id, slug, display_name, status, is_featured, is_verified, search_visible, created_at, updated_at
            ) VALUES (?,?,?,?,\'active\',0,0,1,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
                display_name = IF(is_verified=1, display_name, VALUES(display_name)),
                status = IF(is_verified=1, status, \'active\'),
                search_visible = IF(is_verified=1, search_visible, 1),
                updated_at = NOW()',
            [$brandId, $providerId, $slug, $name]
        );
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{town_id:int,region_id:int}
     */
    private function resolveParkTown(array $raw): array
    {
        $postcode = trim((string) ($raw['postcode'] ?? ''));
        if ($postcode !== '') {
            $matches = Town::searchActive($postcode . ' QLD', 1);
            if ($matches !== []) {
                return [
                    'town_id' => (int) $matches[0]['id'],
                    'region_id' => (int) ($matches[0]['region_id'] ?? 0),
                ];
            }
        }
        $suburb = trim((string) ($raw['suburb'] ?? ''));
        if ($suburb !== '') {
            $matches = Town::searchActive($suburb . ' QLD', 1);
            if ($matches !== []) {
                return [
                    'town_id' => (int) $matches[0]['id'],
                    'region_id' => (int) ($matches[0]['region_id'] ?? 0),
                ];
            }
        }

        return ['town_id' => 0, 'region_id' => 0];
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{town_id:int,region_id:int}
     */
    private function resolveTradeTown(array $raw): array
    {
        if (!empty($raw['is_mobile']) || !empty($raw['is_online'])) {
            $suburb = trim((string) ($raw['suburb'] ?? ''));
            if ($suburb === '') {
                return ['town_id' => 0, 'region_id' => 0];
            }
        }
        $suburb = trim((string) ($raw['suburb'] ?? ''));
        if ($suburb === '') {
            return ['town_id' => 0, 'region_id' => 0];
        }
        $matches = Town::searchActive($suburb . ' QLD', 1);
        if ($matches === []) {
            return ['town_id' => 0, 'region_id' => 0];
        }

        return [
            'town_id' => (int) $matches[0]['id'],
            'region_id' => (int) ($matches[0]['region_id'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $raw */
    private function composeAddress(array $raw): ?string
    {
        $parts = array_filter([
            trim((string) ($raw['street_address'] ?? ''), " \t\n\r\0\x0B,"),
            trim((string) ($raw['suburb'] ?? '')),
            trim((string) ($raw['postcode'] ?? '')),
            trim((string) ($raw['state'] ?? 'QLD')),
        ], static fn (string $part): bool => $part !== '');

        if ($parts === []) {
            return null;
        }

        return mb_substr(implode(', ', $parts), 0, 255);
    }

    private function qldStateId(): int
    {
        return (int) Database::scalar("SELECT id FROM states WHERE abbreviation='QLD' LIMIT 1");
    }

    private function loadServiceCategories(): void
    {
        foreach (Database::select('SELECT id, slug FROM service_categories WHERE is_active=1') as $row) {
            $this->serviceCategoryIds[(string) $row['slug']] = (int) $row['id'];
        }
    }

    /** @return list<mixed> */
    private function loadJsonList(string $file): array
    {
        $path = $this->seedDir . '/' . $file;
        if (!is_file($path)) {
            throw new RuntimeException('Missing CPAQ seed file: ' . $path);
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new RuntimeException('CPAQ seed file must be a JSON array: ' . $file);
        }

        return $decoded;
    }

    /** @return array<string,int> */
    private function emptyEntityCounts(): array
    {
        return [
            'parsed' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped_duplicate' => 0,
            'unresolved' => 0,
            'failed' => 0,
            'geocode_skipped' => 0,
        ];
    }

    private function phone(mixed $value): ?string
    {
        $normalised = self::normalisePhone($value);

        return $normalised;
    }

    private function email(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? mb_substr($email, 0, 190) : null;
    }

    private function url(mixed $value, int $max): ?string
    {
        $url = trim((string) $value);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? mb_substr($url, 0, $max) : null;
    }

    private function text(mixed $value, int $max): ?string
    {
        $text = trim((string) $value);

        return $text !== '' ? mb_substr($text, 0, $max) : null;
    }
}
