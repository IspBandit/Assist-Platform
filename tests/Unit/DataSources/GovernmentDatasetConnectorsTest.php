<?php

declare(strict_types=1);

namespace Tests\Unit\DataSources;

use App\Platform\DataSources\Connectors\CsvDatasetConnector;
use App\Platform\DataSources\Connectors\GeoJsonDatasetConnector;
use App\Platform\DataSources\FacilityTypeMapper;
use App\Platform\DataSources\SimpleHttpClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GovernmentDatasetConnectorsTest extends TestCase
{
    public function testFacilityTypeMapperAliases(): void
    {
        self::assertSame('dump_point', FacilityTypeMapper::normalise('Dump Station'));
        self::assertSame('public_toilet', FacilityTypeMapper::normalise('toilets'));
        self::assertSame('drinking_water', FacilityTypeMapper::normalise('potable water'));
        self::assertSame('dump_point', FacilityTypeMapper::normalise('unknown', 'dump_point'));
        self::assertSame('other_essential', FacilityTypeMapper::normalise(''));
        self::assertSame('other_essential', FacilityTypeMapper::normalise('spaceship'));
    }

    public function testGeoJsonFixtureParsesDemoDumpPoints(): void
    {
        $path = dirname(__DIR__, 3) . '/storage/datasets/demo-dump-points.geojson';
        self::assertFileExists($path);
        $rows = (new GeoJsonDatasetConnector())->search(
            ['path' => $path, 'limit' => 50],
            [],
            ['default_facility_type' => 'dump_point', 'name_field' => 'name', 'type_field' => 'facility_type', 'id_field' => 'id']
        );
        self::assertCount(2, $rows);
        self::assertSame('demo-dump-1', $rows[0]['external_id']);
        self::assertSame('dump_point', $rows[0]['facility_type']);
        self::assertEqualsWithDelta(-35.7089, (float) $rows[0]['latitude'], 0.0001);
        self::assertEqualsWithDelta(150.1782, (float) $rows[0]['longitude'], 0.0001);
    }

    public function testCsvFixtureParsesDemoToilets(): void
    {
        $path = dirname(__DIR__, 3) . '/storage/datasets/demo-public-toilets.csv';
        self::assertFileExists($path);
        $rows = (new CsvDatasetConnector())->search(
            ['path' => $path, 'limit' => 50],
            [],
            [
                'default_facility_type' => 'public_toilet',
                'name_field' => 'name',
                'type_field' => 'facility_type',
                'id_field' => 'id',
                'lat_field' => 'latitude',
                'lng_field' => 'longitude',
                'address_field' => 'address',
            ]
        );
        self::assertCount(3, $rows);
        self::assertSame('demo-toilet-1', $rows[0]['external_id']);
        self::assertSame('public_toilet', $rows[0]['facility_type']);
        self::assertStringContainsString('Roma', (string) $rows[0]['name']);
        self::assertSame('demo-toilet-3', $rows[2]['external_id']);
        self::assertStringContainsString('Batehaven', (string) $rows[2]['name']);
    }

    public function testCsvPayloadParsesInlineRows(): void
    {
        $csv = "id,name,facility_type,latitude,longitude,address\n"
            . "t1,Demo Toilet,public_toilet,-26.5,148.7,1 Main St\n";
        $rows = (new CsvDatasetConnector())->search(['payload' => $csv, 'limit' => 10], []);
        self::assertCount(1, $rows);
        self::assertSame('t1', $rows[0]['external_id']);
        self::assertSame('public_toilet', $rows[0]['facility_type']);
        self::assertSame(-26.5, $rows[0]['latitude']);
    }

    public function testCsvConnectorFiltersAndToiletMapColumns(): void
    {
        $csv = "FacilityID,Name,Address1,Town,Latitude,Longitude,DumpPoint\n"
            . "1,Toilet Only,1 Main,Emerald,-26.1,148.1,False\n"
            . "2,Dump Site,2 Main,Emerald,-26.2,148.2,True\n";
        $rows = (new CsvDatasetConnector())->search(
            ['payload' => $csv, 'limit' => 10],
            [],
            [
                'id_field' => 'facilityid',
                'name_field' => 'name',
                'address_field' => 'address1',
                'lat_field' => 'latitude',
                'lng_field' => 'longitude',
                'filter_field' => 'dumppoint',
                'filter_value' => 'true',
                'default_facility_type' => 'dump_point',
            ]
        );
        self::assertCount(1, $rows);
        self::assertSame('2', $rows[0]['external_id']);
        self::assertSame('dump_point', $rows[0]['facility_type']);
        self::assertSame('2 Main', $rows[0]['formatted_address']);
    }

    public function testSimpleHttpClientBlocksPrivateHosts(): void
    {
        $client = new SimpleHttpClient();
        $this->expectException(InvalidArgumentException::class);
        $client->assertSafeUrl('http://127.0.0.1/secret');
    }

    public function testMigrationAdminAndCliWiring(): void
    {
        $root = dirname(__DIR__, 3);
        $sql = (string) file_get_contents($root . '/database/migrations/109_government_datasets.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS government_datasets', $sql);
        self::assertStringContainsString('traveller_facility_import_candidates', $sql);
        self::assertStringContainsString('gov_geojson', $sql);
        self::assertStringContainsString('demo_geojson_dump_points', $sql);
        self::assertStringNotContainsString('ALTER TABLE caravan_parks', $sql);

        $curated = (string) file_get_contents($root . '/database/migrations/110_government_dataset_au_toilet_map.sql');
        self::assertStringContainsString('au_national_public_toilet_map', $curated);
        self::assertStringContainsString('34076296-6692-4e30-b627-67b7c4eb1027', $curated);
        self::assertStringContainsString('filter_field', $curated);

        $routes = (string) file_get_contents($root . '/routes/admin.php');
        self::assertStringContainsString('GovernmentDatasetsController@index', $routes);
        self::assertStringContainsString('GovernmentDatasetsController@edit', $routes);
        self::assertStringContainsString('GovernmentDatasetsController@upsert', $routes);
        self::assertStringContainsString('/data-sources/facilities/review', $routes);

        self::assertFileExists($root . '/scripts/import-demo-traveller-facilities.php');
        self::assertFileExists($root . '/docs/DECISIONS/0032-stays-vs-traveller-facilities.md');
    }

    public function testCsvFilterFieldKeepsMatchingRowsOnly(): void
    {
        $csv = "id,name,facility_type,dumppoint,latitude,longitude\n"
            . "1,Has Dump,other,true,-26.5,148.7\n"
            . "2,No Dump,other,false,-26.6,148.8\n";
        $rows = (new CsvDatasetConnector())->search(
            ['payload' => $csv, 'limit' => 10],
            [],
            [
                'default_facility_type' => 'dump_point',
                'filter_field' => 'dumppoint',
                'filter_value' => 'true',
                'name_field' => 'name',
                'id_field' => 'id',
                'lat_field' => 'latitude',
                'lng_field' => 'longitude',
            ]
        );
        self::assertCount(1, $rows);
        self::assertSame('1', $rows[0]['external_id']);
        self::assertSame('dump_point', $rows[0]['facility_type']);
    }
}
