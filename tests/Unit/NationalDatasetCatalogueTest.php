<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NationalDatasetCatalogueTest extends TestCase
{
    public function testMigrationDefinesRequiredCatalogueFieldsAndSeeds(): void
    {
        $path = dirname(__DIR__, 2) . '/database/migrations/117_national_dataset_catalogue.sql';
        self::assertFileExists($path);
        $sql = (string) file_get_contents($path);

        foreach ([
            'jurisdiction',
            'source_url',
            'source_format',
            'update_frequency',
            'last_downloaded_at',
            'record_count',
            'auto_update_enabled',
            'catalogue_status',
            'duplicate_rules_json',
            'notes',
        ] as $column) {
            self::assertStringContainsString($column, $sql);
        }

        foreach ([
            'portal_data_gov_au',
            'portal_osm_australia',
            'portal_qld_open_data',
            'portal_nsw_open_data',
            'portal_vic_open_data',
            'portal_sa_data_directory',
            'portal_wa_open_data',
            'portal_tas_open_data',
            'portal_act_open_data',
            'portal_nt_open_data',
            'theme_council_open_data_portals',
            'theme_visitor_information_centres',
            'theme_rest_areas',
            'theme_drinking_water',
            'theme_dump_points',
            'theme_caravan_parks',
            'theme_campgrounds',
            'au_national_public_toilet_map',
        ] as $key) {
            self::assertStringContainsString($key, $sql, 'Missing catalogue seed ' . $key);
        }

        self::assertStringContainsString('auto_update_enabled', $sql);
        self::assertStringNotContainsString('CREATE TABLE dataset_catalogue', $sql);
    }

    public function testAdminApiDatasetDetailExposesCatalogueAliases(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 2) . '/app/Services/Api/AdminApiDatasetService.php'
        );
        self::assertStringContainsString("'name'", $source);
        self::assertStringContainsString("'api_url'", $source);
        self::assertStringContainsString("'trust_level'", $source);
        self::assertStringContainsString("'entity_types'", $source);
        self::assertStringContainsString("'import_mapping'", $source);
        self::assertStringContainsString("'auto_update_enabled'", $source);
        self::assertStringContainsString("'catalogue_status'", $source);
        self::assertStringContainsString('last_downloaded_at', $source);
    }

    public function testAdr0033AndDocsExist(): void
    {
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/DECISIONS/0033-ric-national-dataset-acquisition.md');
        self::assertFileExists(dirname(__DIR__, 2) . '/docs/DATA_011A.md');
        $doc = (string) file_get_contents(dirname(__DIR__, 2) . '/docs/DATA_011A.md');
        self::assertStringContainsString('before writing additional dataset-specific importers', $doc);
        self::assertStringContainsString('RIC is the single acquisition engine', $doc);
        self::assertStringContainsString('never', strtolower($doc));
        self::assertStringContainsString('/api/v1/admin', $doc);
    }
}
