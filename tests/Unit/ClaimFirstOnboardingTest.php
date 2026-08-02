<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ClaimFirstOnboardingService;
use PHPUnit\Framework\TestCase;

final class ClaimFirstOnboardingTest extends TestCase
{
    public function testNormalizeBusinessNameStripsLegalSuffixes(): void
    {
        self::assertSame(
            'acme caravan repairs',
            ClaimFirstOnboardingService::normalizeBusinessName('Acme Caravan Repairs Pty Ltd')
        );
    }

    public function testDuplicateScoreUsesNamePhoneAndTown(): void
    {
        $existing = [
            'business_name' => 'Outback RV Service Pty Ltd',
            'phone' => '0412 345 678',
            'website' => '',
            'base_town_id' => 42,
        ];
        $submission = [
            'business_name' => 'Outback RV Service',
            'phone' => '0412345678',
            'website' => '',
            'base_town_id' => 42,
        ];

        $score = ClaimFirstOnboardingService::duplicateScore($existing, $submission);

        self::assertGreaterThanOrEqual(70, $score);
    }

    public function testBestDuplicateMatchReturnsNullWhenBelowThreshold(): void
    {
        $match = ClaimFirstOnboardingService::bestDuplicateMatch([
            [
                'id' => 10,
                'business_name' => 'Totally Different Workshop',
                'phone' => '0299990000',
                'base_town_id' => 1,
            ],
        ], [
            'business_name' => 'Coastal Caravan Care',
            'phone' => '0411000111',
            'base_town_id' => 99,
        ]);

        self::assertNull($match);
    }

    public function testBestDuplicateMatchFlagsLikelyDuplicate(): void
    {
        $match = ClaimFirstOnboardingService::bestDuplicateMatch([
            [
                'id' => 55,
                'business_name' => 'Sunset Mobile Mechanics',
                'phone' => '0400111222',
                'website' => 'https://example.com',
                'base_town_id' => 7,
            ],
        ], [
            'business_name' => 'Sunset Mobile Mechanics',
            'phone' => '0400 111 222',
            'website' => 'https://www.example.com/',
            'base_town_id' => 7,
        ]);

        self::assertNotNull($match);
        self::assertTrue($match['likely']);
        self::assertSame(55, $match['provider_id']);
        self::assertContains('business_name', $match['reasons']);
    }

    public function testEvaluateSubmissionRequiresConfirmedNoneForProspect(): void
    {
        $service = new ClaimFirstOnboardingService();
        $result = $service->evaluateSubmission(['business_name' => 'New Co'], false);

        self::assertSame('needs_match_review', $result['outcome']);
    }
}
