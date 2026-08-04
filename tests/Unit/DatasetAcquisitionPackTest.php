<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DatasetAcquisitionPackTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function testCatalogueJsonSchemaAndNoDuplicates(): void
    {
        $path = $this->root() . '/data_catalogue/catalogue.json';
        self::assertFileExists($path);
        $payload = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('datasets', $payload);
        $rows = $payload['datasets'];
        self::assertGreaterThanOrEqual(40, count($rows));

        $required = [
            'dataset_id', 'name', 'description', 'category', 'publisher', 'jurisdiction',
            'geographic_coverage', 'source_url', 'api_url', 'download_url', 'portal_type',
            'format', 'licence', 'attribution_requirement', 'signup_required',
            'api_key_required', 'pricing_free_allowance', 'update_frequency', 'last_updated',
            'bulk_download_available', 'automated_access_allowed',
            'recommended_ric_integration_method', 'priority', 'expected_user_value',
            'import_difficulty', 'trust_policy', 'enabled_state', 'notes', 'current_status',
        ];

        $ids = [];
        foreach ($rows as $index => $row) {
            self::assertIsArray($row, 'row ' . $index);
            foreach ($required as $field) {
                self::assertArrayHasKey($field, $row, $field . ' missing on row ' . $index);
            }
            $id = (string) $row['dataset_id'];
            self::assertNotSame('', $id);
            self::assertArrayNotHasKey($id, $ids, 'duplicate dataset_id ' . $id);
            $ids[$id] = true;
            self::assertNotSame('', trim((string) $row['licence']));
            self::assertNotSame('', trim((string) $row['attribution_requirement']));
            $hasUrl = trim((string) $row['source_url']) !== '' || trim((string) $row['api_url']) !== '';
            self::assertTrue($hasUrl, $id . ' missing source/api url');
            self::assertContains((string) $row['current_status'], [
                'verified', 'download_ready', 'api_ready', 'manual_review', 'unavailable', 'prohibited',
            ], $id);
        }
    }

    public function testPaidSourcesDisabledByDefault(): void
    {
        $payload = json_decode(
            (string) file_get_contents($this->root() . '/data_catalogue/catalogue.json'),
            true
        );
        $paid = array_values(array_filter(
            $payload['datasets'],
            static fn (array $row): bool => ($row['cost_type'] ?? '') === 'paid'
        ));
        self::assertNotEmpty($paid);
        foreach ($paid as $row) {
            self::assertFalse((bool) $row['enabled_state'], (string) $row['dataset_id']);
            self::assertTrue((bool) $row['api_key_required'], (string) $row['dataset_id']);
            self::assertTrue((bool) $row['signup_required'], (string) $row['dataset_id']);
        }
    }

    public function testMigrationSeedsVerifiedChildrenAndKeepsPaidDisabled(): void
    {
        $sql = (string) file_get_contents(
            $this->root() . '/database/migrations/123_dataset_acquisition_pack.sql'
        );
        foreach ([
            'nsw_rest_areas',
            'nsw_boat_ramps',
            'nsw_ev_charging_locations',
            'gold_coast_caravan_parks',
            'sa_rest_areas_state_maintained',
            'wa_major_rest_areas',
            'theme_fuel_stations',
            'theme_hospitals',
            'theme_ev_charging',
            'paid_google_places',
            'paid_openchargemap',
            'portal_transport_nsw',
        ] as $key) {
            self::assertStringContainsString("'" . $key . "'", $sql, 'Missing seed ' . $key);
        }
        self::assertStringContainsString('is_enabled = 0', $sql);
        self::assertStringContainsString('auto_update_enabled = 0', $sql);
        self::assertStringNotContainsString('CREATE TABLE dataset_catalogue', $sql);
    }

    public function testDownloadMetadataSidecarsExist(): void
    {
        foreach ([
            'au_national_public_toilet_map',
            'nsw_rest_areas',
            'nsw_boat_ramps',
            'nsw_ev_charging_locations',
            'gold_coast_caravan_parks',
            'geofabrik_australia',
        ] as $key) {
            $base = $this->root() . '/data_catalogue/raw/' . $key;
            self::assertFileExists($base . '/metadata.json', $key);
            self::assertFileExists($base . '/licence.txt', $key);
            self::assertFileExists($base . '/attribution.txt', $key);
            $meta = json_decode((string) file_get_contents($base . '/metadata.json'), true);
            self::assertIsArray($meta);
            if ($key !== 'geofabrik_australia') {
                self::assertFileExists($base . '/checksum.sha256', $key);
                self::assertNotSame('', trim((string) ($meta['sha256'] ?? '')));
            }
        }
    }

    public function testBatehavenCoverageSample(): void
    {
        $path = $this->root() . '/data_catalogue/samples/batehaven_toilet_map_20km.json';
        self::assertFileExists($path);
        $payload = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($payload);
        self::assertGreaterThanOrEqual(1, (int) $payload['count']);
        self::assertGreaterThanOrEqual(1, (int) $payload['dump_point_count']);
        $dumps = array_values(array_filter(
            $payload['records'],
            static fn (array $row): bool => !empty($row['dump_point'])
        ));
        self::assertNotEmpty($dumps);
        self::assertNotSame('', (string) $dumps[0]['licence']);
        self::assertNotSame('', (string) $dumps[0]['attribution']);
        self::assertStringContainsString('Toilet Map', (string) $dumps[0]['attribution']);
    }

    public function testMissingUrlSourcesAreMarkedManualReviewOrApiReady(): void
    {
        $payload = json_decode(
            (string) file_get_contents($this->root() . '/data_catalogue/catalogue.json'),
            true
        );
        $wa = null;
        foreach ($payload['datasets'] as $row) {
            if (($row['dataset_id'] ?? '') === 'wa_major_rest_areas') {
                $wa = $row;
                break;
            }
        }
        self::assertIsArray($wa);
        self::assertSame('', (string) $wa['download_url']);
        self::assertSame('manual_review', (string) $wa['current_status']);
    }

    public function testScriptsAndDocsExist(): void
    {
        foreach ([
            '/scripts/download_datasets.py',
            '/scripts/check_dataset_updates.py',
            '/scripts/verify_dataset_checksums.py',
            '/scripts/data_catalogue/data_catalogue_lib.py',
            '/docs/DATASET_SOURCE_AUDIT.md',
            '/docs/DATASET_CATALOGUE.md',
            '/docs/DATASET_DOWNLOADS.md',
            '/docs/DATASET_LICENSING_AND_ATTRIBUTION.md',
            '/docs/PAID_DATA_SOURCES.md',
            '/docs/RIC_DATASET_AVAILABILITY.md',
        ] as $rel) {
            self::assertFileExists($this->root() . $rel, $rel);
        }
    }
}
