<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QldCoverageImportDryRunService;
use PHPUnit\Framework\TestCase;

final class QldCoverageImportDryRunServiceTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/qld-dry-run-' . bin2hex(random_bytes(4));
        mkdir($this->tmp, 0777, true);
        file_put_contents($this->tmp . '/providers-publishable.json', json_encode([
            [
                'id' => 'brisbane-mechanic',
                'business_name' => 'Brisbane Mechanic',
                'phone' => '07 3000 0000',
                'public_email' => null,
                'website' => 'https://example.test',
                'street_address' => '1 Queen St',
                'town' => 'Brisbane',
                'suburb' => 'Brisbane',
                'postcode' => '4000',
                'state' => 'QLD',
                'region' => 'seq',
                'latitude' => -27.47,
                'longitude' => 153.03,
                'category_slugs' => ['general-mechanic'],
                'brand_visibility' => ['localtorque', 'vanassist'],
                'confidence' => 85,
                'claimed_status' => 'unclaimed',
                'source_records' => [['source_name' => 'openstreetmap', 'source_licence' => 'ODbL']],
                'field_evidence' => [
                    'categories' => [['category' => 'general-mechanic', 'evidence' => 'OSM']],
                ],
            ],
            [
                'id' => 'gold-coast-tyres',
                'business_name' => 'Gold Coast Tyres',
                'phone' => '07 5500 0000',
                'town' => 'Southport',
                'region' => 'seq',
                'latitude' => -27.97,
                'longitude' => 153.4,
                'category_slugs' => ['tyre-shop'],
                'brand_visibility' => ['localtorque'],
                'confidence' => 82,
                'source_records' => [],
                'field_evidence' => ['categories' => []],
            ],
            [
                'id' => 'regulated-gas',
                'business_name' => 'Regulated Gas',
                'town' => 'Brisbane',
                'region' => 'seq',
                'latitude' => -27.5,
                'longitude' => 153.0,
                'category_slugs' => ['gas-certification'],
                'brand_visibility' => ['localtorque'],
                'confidence' => 90,
                'source_records' => [],
                'field_evidence' => ['categories' => []],
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->tmp . '/regulated-missing-licence.json', json_encode([
            ['id' => 'regulated-gas', 'categories' => ['gas-certification']],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->tmp);
    }

    public function testBuildExcludesRegulatedAndNonBatchProviders(): void
    {
        $service = new QldCoverageImportDryRunService($this->tmp);
        $report = $service->build('brisbane-moreton-bay');
        self::assertSame(1, $report['batch_matched']);
        self::assertSame(1, $report['regulated_held_total']);
        self::assertSame(1, $report['regulated_excluded_from_publishable']);
        self::assertSame('brisbane-mechanic', $report['candidates'][0]['raw']['qld_coverage_id']);
        self::assertFalse($report['candidates'][0]['marketing_consent']);
        self::assertSame('qld-coverage:brisbane-mechanic', $report['candidates'][0]['external_id']);
        self::assertSame(1, $report['eligible_for_apply_estimate']);
    }
}