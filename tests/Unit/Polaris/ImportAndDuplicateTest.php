<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\DuplicateDetection;
use App\Services\Polaris\ImportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ImportAndDuplicateTest extends TestCase
{
    public function testDuplicateDetectionExactNormalisedMatch(): void
    {
        self::assertSame(100.0, DuplicateDetection::similarity('Demo Horizon Pty Ltd', 'Demo Horizon'));
        self::assertGreaterThan(70.0, DuplicateDetection::similarity('Demo Horizon Caravans', 'Demo Horizon'));
    }

    public function testDuplicateDetectionFindsLikelyMatches(): void
    {
        $matches = DuplicateDetection::findLikelyDuplicates('Demo Horizon', [
            ['id' => 1, 'trading_name' => 'Demo Horizon Caravans'],
            ['id' => 2, 'trading_name' => 'Completely Different Co'],
        ], 70.0);
        self::assertNotEmpty($matches);
        self::assertSame(1, $matches[0]['id']);
        self::assertSame([], DuplicateDetection::findLikelyDuplicates('zzz-unique', [
            ['id' => 1, 'trading_name' => 'Demo Horizon'],
        ], 90.0));
    }

    public function testCsvValidationRequiresCoreFields(): void
    {
        $service = new ImportService();
        $method = (new ReflectionClass($service))->getMethod('validateRow');

        $bad = $method->invoke($service, ['manufacturer' => '', 'model' => '', 'category' => 'spaceship']);
        self::assertNotEmpty($bad['errors']);

        $good = $method->invoke($service, [
            'manufacturer' => 'Demo Horizon',
            'model' => 'Southern Cross',
            'variant' => '18ft',
            'category' => 'caravan',
            'sleeps' => '2',
            'tare_kg' => '1850',
            'atm_kg' => '2500',
            'price_aud' => '89900',
            'price_status' => 'from',
        ]);
        self::assertSame([], $good['errors']);
        self::assertSame('demo-horizon', $good['payload']['manufacturer_slug']);
        self::assertSame(8990000, $good['payload']['price_aud_cents']);
        self::assertGreaterThanOrEqual(75, $good['confidence']);
    }

    public function testCsvParseSkipsBlankRows(): void
    {
        $service = new ImportService();
        $method = (new ReflectionClass($service))->getMethod('parseCsv');
        $csv = "manufacturer,model,category\nDemo Horizon,Southern Cross,caravan\n,,\n";
        $rows = $method->invoke($service, $csv);
        self::assertCount(1, $rows);
        self::assertSame('Demo Horizon', $rows[0]['manufacturer']);
    }

    public function testImportServiceExposesJsonImporter(): void
    {
        $service = new ImportService();
        self::assertTrue(method_exists($service, 'importJson'));
        self::assertSame('polaris-import-2', ImportService::EXTRACTOR_VERSION);
    }
}
