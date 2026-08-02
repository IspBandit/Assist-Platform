<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\BrochureTextExtractor;
use App\Services\Polaris\ExtractionCostEstimator;
use PHPUnit\Framework\TestCase;

final class BrochureExtractionTest extends TestCase
{
    public function testExtractsCoreFieldsFromBrochureWording(): void
    {
        $text = <<<TXT
Manufacturer: Demo Horizon
Model: Southern Cross
Category: caravan
Sleeps: 2
Tare mass: 1850 kg
ATM: 2500 kg
Body length: 5.80 m
Fresh water: 120 L
Solar: 300 W
Ensuite bathroom
From $89,900
TXT;
        $result = BrochureTextExtractor::extract($text);
        self::assertSame('Demo Horizon', $result['payload']['manufacturer_name']);
        self::assertSame('Southern Cross', $result['payload']['model_name']);
        self::assertSame(2, $result['payload']['sleeps']);
        self::assertSame(1850, $result['payload']['tare_kg']);
        self::assertSame(2500, $result['payload']['atm_kg']);
        self::assertSame(89900, $result['payload']['price_aud']);
        self::assertSame('ensuite', $result['payload']['bathroom_type']);
        self::assertSame([], $result['errors']);
        self::assertGreaterThan(40, $result['confidence']);
    }

    public function testPdfLiteralScrapeIgnoresNonPdf(): void
    {
        self::assertSame('', BrochureTextExtractor::extractTextFromPdf('not a pdf'));
    }

    public function testCostEstimatorMarksDeterministicAsFree(): void
    {
        $cost = ExtractionCostEstimator::forMode('brochure_text', 1, false);
        self::assertSame(0, $cost['aud_cents']);
        self::assertStringContainsString('AUD 0.00', $cost['label']);
    }

    public function testAiCostEstimateIsBlockedWhenFlagOff(): void
    {
        $cost = ExtractionCostEstimator::forMode('ai_brochure', 3, false);
        self::assertFalse($cost['ai_enabled']);
        self::assertSame(0, $cost['aud_cents']);
        self::assertNotNull($cost['tokens_estimate']);
    }
}
