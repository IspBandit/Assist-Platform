<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiFacilityImportService;
use App\Services\GovernmentDatasetService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminApiFacilityImportServiceTest extends TestCase
{
    public function testFacilityImportServiceAndRicIngestExist(): void
    {
        $serviceFile = (string) (new ReflectionClass(AdminApiFacilityImportService::class))->getFileName();
        $govFile = (string) (new ReflectionClass(GovernmentDatasetService::class))->getFileName();
        $routes = (string) file_get_contents(base_path('routes/api_v1_admin.php'));

        self::assertStringContainsString('facility_imports.create', (string) file_get_contents($serviceFile));
        self::assertStringContainsString('ingestAssistRicRows', (string) file_get_contents($govFile));
        self::assertStringContainsString('findDatasetByKey', (string) file_get_contents($govFile));
        self::assertStringContainsString("/facility-imports", $routes);
        self::assertStringContainsString('FacilityImportController@store', $routes);
        self::assertStringContainsString('admin_api_scope:imports:write', $routes);

        $migration = (string) file_get_contents(
            base_path('database/migrations/127_ric_missing_ready_facility_import_datasets.sql')
        );
        foreach (
            [
                'nsw_rest_areas',
                'nsw_ev_charging_locations',
                'sa_rest_areas_state_maintained',
                'wa_major_rest_areas',
                'nsw_boat_ramps',
                'gold_coast_caravan_parks',
            ] as $key
        ) {
            self::assertStringContainsString($key, $migration);
        }
    }
}
