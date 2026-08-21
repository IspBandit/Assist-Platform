<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\ManufacturerDataQualityService;
use PHPUnit\Framework\TestCase;

final class ManufacturerDataQualityTest extends TestCase
{
    public function testAssessVariantRequiresCoreWeightsAndPrice(): void
    {
        $complete = ManufacturerDataQualityService::assessVariant([
            'sleeps' => 2,
            'body_length_m' => 5.5,
            'tare_kg' => 1850,
            'atm_kg' => 2500,
            'price_status' => 'from',
            'price_aud_cents' => 8990000,
            'price_effective_on' => '2026-01-01',
        ]);
        self::assertTrue($complete['complete']);
        self::assertSame([], $complete['gaps']);

        $gaps = ManufacturerDataQualityService::assessVariant([
            'sleeps' => null,
            'body_length_m' => null,
            'tare_kg' => null,
            'atm_kg' => null,
            'price_status' => 'unknown',
            'price_aud_cents' => null,
        ]);
        self::assertFalse($gaps['complete']);
        self::assertContains('Missing ATM', $gaps['gaps']);
        self::assertContains('Missing price guidance', $gaps['gaps']);
    }

    public function testContactDealerPriceCountsAsCompleteWithoutCents(): void
    {
        $result = ManufacturerDataQualityService::assessVariant([
            'sleeps' => 2,
            'body_length_m' => 5.0,
            'tare_kg' => 1000,
            'atm_kg' => 1500,
            'price_status' => 'contact_dealer',
            'price_aud_cents' => null,
        ]);
        self::assertTrue($result['complete']);
    }

    public function testShapeReportCoverageAndModelGaps(): void
    {
        $report = ManufacturerDataQualityService::shapeReport(
            [
                [
                    'id' => 1,
                    'name' => 'Southern Cross',
                    'slug' => 'southern-cross',
                    'description' => 'Demo caravan',
                    'publication_status' => 'published',
                    'verification_status' => 'verified',
                ],
                [
                    'id' => 2,
                    'name' => 'Draft Model',
                    'slug' => 'draft-model',
                    'description' => '',
                    'publication_status' => 'draft',
                    'verification_status' => 'pending',
                ],
            ],
            [
                [
                    'id' => 10,
                    'model_id' => 1,
                    'name' => '18ft',
                    'sleeps' => 2,
                    'body_length_m' => 5.5,
                    'tare_kg' => 1850,
                    'atm_kg' => 2500,
                    'price_status' => 'from',
                    'price_aud_cents' => 8990000,
                    'price_effective_on' => '2026-01-01',
                ],
                [
                    'id' => 11,
                    'model_id' => 1,
                    'name' => 'Incomplete',
                    'sleeps' => 2,
                    'body_length_m' => 5.0,
                    'tare_kg' => null,
                    'atm_kg' => null,
                    'price_status' => 'unknown',
                ],
            ]
        );

        self::assertSame(2, $report['model_count']);
        self::assertSame(2, $report['variant_count']);
        self::assertSame(1, $report['complete_variants']);
        self::assertSame(50, $report['coverage_percent']);
        self::assertContains('Missing description', $report['models'][1]['gaps']);
        self::assertContains('No variants', $report['models'][1]['gaps']);
    }

    public function testPortalDataQualityWiring(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root . '/app/Controllers/Site/ManufacturerPortalController.php');
        self::assertStringContainsString('ManufacturerDataQualityService', $controller);
        self::assertStringContainsString("polaris.portal.data-quality", $controller);
        self::assertStringNotContainsString(
            "portalSection(\$request, 'data-quality'",
            $controller
        );

        $view = (string) file_get_contents($root . '/app/Views/polaris/portal/data-quality.php');
        self::assertStringContainsString('Complete variants', $view);
        self::assertStringContainsString('not a Platform Quality Gate', $view);
    }
}
