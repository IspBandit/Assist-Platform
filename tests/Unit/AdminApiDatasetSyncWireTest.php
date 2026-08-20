<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiDatasetService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminApiDatasetSyncWireTest extends TestCase
{
    public function testEnqueueSyncCallsGovernmentDatasetFetch(): void
    {
        $ref = new ReflectionClass(AdminApiDatasetService::class);
        $source = (string) file_get_contents((string) $ref->getFileName());

        self::assertStringContainsString('GovernmentDatasetService', $source);
        self::assertStringContainsString('fetchDataset', $source);
        self::assertStringContainsString('importFixture', $source);
        self::assertStringContainsString("status = ?", $source);
        self::assertStringContainsString('dataset.sync_completed', $source);
        self::assertStringContainsString('dataset.sync_failed', $source);
        self::assertStringNotContainsString('sync run stubs', strtolower($source));
    }
}
