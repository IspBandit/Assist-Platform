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
        $service = (string) file_get_contents($serviceFile);
        $gov = (string) file_get_contents($govFile);

        self::assertStringContainsString('facility_imports.create', $service);
        self::assertStringContainsString('publishPendingAssistRicCandidates', $gov);
        self::assertStringContainsString('status\' => \'published\'', $service);
        self::assertStringContainsString('ADR 0034', $service);
        self::assertStringContainsString('ingestAssistRicRows', $gov);
        self::assertStringContainsString('findDatasetByKey', $gov);
        self::assertStringContainsString('/facility-imports', $routes);
        self::assertStringContainsString('/facility-imports/publish-pending', $routes);
        self::assertStringContainsString('FacilityImportController@store', $routes);
        self::assertStringContainsString('FacilityImportController@publishPending', $routes);
        self::assertStringContainsString('admin_api_scope:imports:write', $routes);
    }
}
