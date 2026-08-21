<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\QldCoverageReportService;
use PHPUnit\Framework\TestCase;

final class QldCoverageReportServiceTest extends TestCase
{
    private string $tmp = '';

    protected function setUp(): void
    {
        $this->tmp = sys_get_temp_dir() . '/qld-coverage-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmp . '/by-batch', 0777, true);
        file_put_contents($this->tmp . '/coverage-summary.json', json_encode([
            'towns_suburbs_processed' => 10,
            'service_categories_processed' => 2,
            'publishable_records' => 1,
            'held_for_review' => 1,
            'verified_coverage_cells' => 3,
            'zero_coverage_cells' => 5,
            'sources' => [['source_name' => 'openstreetmap', 'source_licence' => 'ODbL', 'count' => 1]],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->tmp . '/by-batch/brisbane-moreton-bay.json', json_encode([
            'batch_id' => 'brisbane-moreton-bay',
            'batch_name' => 'Brisbane and Moreton Bay',
            'towns' => 2,
            'verified_cells' => 1,
            'zero_coverage_cells' => 2,
            'weak_coverage_cells' => 0,
            'providers_referenced' => 1,
        ], JSON_THROW_ON_ERROR));
        file_put_contents(
            $this->tmp . '/zero-coverage.jsonl',
            json_encode([
                'Region' => 'Brisbane and Moreton Bay',
                'Town/suburb' => 'Wynnum',
                'Postcode' => '4178',
                'Service category' => 'Caravan Repairs',
                'Coverage status' => 'no_coverage',
                'Recommended action' => 'Discover',
            ], JSON_THROW_ON_ERROR) . "\n" .
            json_encode([
                'Region' => 'Gold Coast and Scenic Rim',
                'Town/suburb' => 'Nerang',
                'Postcode' => '4211',
                'Service category' => 'Tyre Shops',
                'Coverage status' => 'no_coverage',
                'Recommended action' => 'Discover',
            ], JSON_THROW_ON_ERROR) . "\n"
        );
        file_put_contents($this->tmp . '/providers-review-queue.json', json_encode([
            [
                'business_name' => 'Test Mobile',
                'town' => 'Wynnum',
                'category_slugs' => ['caravan-repairs'],
                'region' => 'seq',
                'source_records' => [['source_name' => 'openstreetmap', 'source_licence' => 'ODbL']],
                'field_evidence' => [
                    'categories' => [['category' => 'caravan-repairs', 'evidence' => 'OSM tag']],
                    'email' => ['marketing_consent' => false],
                ],
                'confidence' => 55,
                'last_checked_at' => '2026-07-29',
                'review_reasons' => ['confidence_below_80'],
            ],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->tmp . '/possible-duplicates.json', json_encode([
            ['key' => 'test mobile|4178', 'ids' => ['a', 'b']],
        ], JSON_THROW_ON_ERROR));
        file_put_contents($this->tmp . '/regulated-missing-licence.json', json_encode([
            ['name' => 'Gas Co', 'categories' => ['gas-certification'], 'note' => 'hold'],
        ], JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmp . '/*') ?: [] as $file) {
            if (is_dir($file)) {
                foreach (glob($file . '/*') ?: [] as $inner) {
                    @unlink($inner);
                }
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
        @rmdir($this->tmp);
    }

    public function testSummaryAndBatchFilters(): void
    {
        $service = new QldCoverageReportService($this->tmp);
        $summary = $service->summary();
        self::assertNotNull($summary);
        self::assertSame(10, $summary['towns_suburbs_processed']);
        self::assertCount(1, $service->batches());

        $rows = $service->coverageRows([
            'batch' => 'brisbane-moreton-bay',
            'town' => 'wyn',
            'category' => 'caravan',
            'limit' => 10,
        ]);
        self::assertCount(1, $rows);
        self::assertSame('Wynnum', $rows[0]['Town/suburb']);
    }

    public function testReviewCandidatesExposeEvidenceWithoutMarketingConsent(): void
    {
        $service = new QldCoverageReportService($this->tmp);
        $rows = $service->reviewCandidates(['town' => 'wynnum', 'category' => 'caravan', 'limit' => 5]);
        self::assertCount(1, $rows);
        self::assertFalse($rows[0]['field_evidence']['email']['marketing_consent']);
        self::assertSame('caravan-repairs', $rows[0]['field_evidence']['categories'][0]['category']);
        self::assertCount(1, $service->possibleDuplicates(10));
        self::assertCount(1, $service->regulatedMissingLicence(10));
    }
}
