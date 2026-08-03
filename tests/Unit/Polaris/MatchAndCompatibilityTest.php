<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\CatalogueService;
use App\Services\Polaris\ComparisonService;
use App\Services\Polaris\MatchScorer;
use App\Services\Polaris\PreferenceProfile;
use App\Services\Polaris\TowCompatibilityService;
use PHPUnit\Framework\TestCase;

final class MatchAndCompatibilityTest extends TestCase
{
    public function testHardConstraintFailureMarksModelIneligible(): void
    {
        $profile = PreferenceProfile::fromArray([
            'adults' => 2,
            'children' => 0,
            'max_atm_kg' => 2000,
            'max_budget_aud' => 100000,
        ]);
        $score = (new MatchScorer())->score([
            'name' => 'Heavy',
            'category' => 'caravan',
            'sleeps' => 2,
            'atm_kg' => 3500,
            'tare_kg' => 2200,
            'price_status' => 'from',
            'price_aud_cents' => 8000000,
            'verification_status' => 'verified',
            'bathroom_type' => 'Ensuite',
        ], $profile);

        self::assertFalse($score['eligible']);
        self::assertSame('not_eligible', $score['band']);
        self::assertNotEmpty($score['failed']);
    }

    public function testMissingPriceDoesNotBeatVerifiedBudgetFit(): void
    {
        $profile = PreferenceProfile::fromArray([
            'adults' => 2,
            'max_budget_aud' => 100000,
            'priority_price' => 'strong',
        ]);
        $scorer = new MatchScorer();
        $known = $scorer->score([
            'name' => 'Priced',
            'category' => 'caravan',
            'sleeps' => 2,
            'atm_kg' => 2500,
            'tare_kg' => 1800,
            'price_status' => 'from',
            'price_aud_cents' => 8990000,
            'verification_status' => 'verified',
        ], $profile);
        $unknown = $scorer->score([
            'name' => 'Unknown price',
            'category' => 'caravan',
            'sleeps' => 2,
            'atm_kg' => 2500,
            'tare_kg' => 1800,
            'price_status' => 'unknown',
            'price_aud_cents' => null,
            'verification_status' => 'unverified',
        ], $profile);

        self::assertTrue($known['eligible']);
        self::assertTrue($unknown['eligible']);
        self::assertGreaterThan($unknown['overall'], $known['overall']);
        self::assertNotEmpty($unknown['missing']);
    }

    public function testPriceFreshnessFlagsStaleDates(): void
    {
        $fresh = CatalogueService::priceFreshness('2026-07-01', null, '2026-08-01');
        $stale = CatalogueService::priceFreshness('2025-01-01', null, '2026-08-01');
        $expired = CatalogueService::priceFreshness('2025-01-01', '2025-06-01', '2026-08-01');

        self::assertTrue($fresh['fresh']);
        self::assertTrue($stale['stale']);
        self::assertTrue($expired['stale']);
        self::assertSame('Price expired', $expired['label']);
    }

    public function testComparisonHighlightsDifferencesAndWinners(): void
    {
        $built = (new ComparisonService())->build([
            [
                'id' => 1,
                'name' => 'Light',
                'manufacturer_name' => 'A',
                'category_label' => 'Caravan',
                'production_status' => 'current',
                'verification_status' => 'verified',
                'sleeps' => 2,
                'body_length_m' => 5.0,
                'tare_kg' => 1400,
                'atm_kg' => 2000,
                'payload_kg' => 600,
                'price_label' => 'From $70,000',
                'price_aud_cents' => 7000000,
                'url' => '/rvs/a/light',
            ],
            [
                'id' => 2,
                'name' => 'Heavy',
                'manufacturer_name' => 'B',
                'category_label' => 'Caravan',
                'production_status' => 'current',
                'verification_status' => 'unverified',
                'sleeps' => 4,
                'body_length_m' => 6.5,
                'tare_kg' => 2200,
                'atm_kg' => 3000,
                'payload_kg' => 800,
                'price_label' => 'From $110,000',
                'price_aud_cents' => 11000000,
                'url' => '/rvs/b/heavy',
            ],
        ]);

        self::assertSame('Light', $built['winners']['lightest_tare']);
        self::assertSame('Heavy', $built['winners']['highest_payload']);
        $tareRow = null;
        foreach ($built['rows'] as $row) {
            if ($row['key'] === 'tare_kg') {
                $tareRow = $row;
            }
        }
        self::assertNotNull($tareRow);
        self::assertTrue($tareRow['differs']);
    }

    public function testTowCompatibilityUsesTowSmartFiguresWithoutInventingCertainty(): void
    {
        $service = new TowCompatibilityService();
        $result = $service->assess(
            [
                'name' => 'Demo Tow Vehicle',
                'gvm' => 3200,
                'gcm' => 6500,
                'kerb_weight' => 2200,
                'towing_capacity' => 3500,
                'towball_download_max' => 350,
            ],
            [
                'tare_kg' => 1800,
                'atm_kg' => 2500,
                'towball_mass_kg' => 180,
            ],
            ['trailer_loaded_mass' => 2200]
        );

        self::assertSame(TowCompatibilityService::RESULT_COMPATIBLE, $result['status']);
        self::assertStringContainsString('appears to remain within the checked limits', $result['summary']);
        self::assertNotNull($result['calculation']);
    }

    public function testInsufficientRvWeightsReturnInsufficientData(): void
    {
        $result = (new TowCompatibilityService())->assess(
            [
                'gvm' => 3200,
                'gcm' => 6500,
                'kerb_weight' => 2200,
                'towing_capacity' => 3500,
                'towball_download_max' => 350,
            ],
            ['tare_kg' => null, 'atm_kg' => null]
        );

        self::assertSame(TowCompatibilityService::RESULT_INSUFFICIENT, $result['status']);
        self::assertNotEmpty($result['missing']);
    }
}
