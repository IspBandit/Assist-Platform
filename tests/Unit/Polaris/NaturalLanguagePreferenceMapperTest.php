<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\NaturalLanguagePreferenceMapper;
use App\Services\Polaris\PreferenceProfile;
use PHPUnit\Framework\TestCase;

final class NaturalLanguagePreferenceMapperTest extends TestCase
{
    public function testMapsFamilyBudgetBathroomAndCategory(): void
    {
        $mapped = NaturalLanguagePreferenceMapper::map(
            'Family caravan with ensuite under $90k for free camping a week'
        );
        $profile = $mapped['profile'];
        self::assertGreaterThanOrEqual(2, $profile->children);
        self::assertSame(9000000, $profile->maxBudgetAudCents);
        self::assertTrue($profile->requireBathroom);
        self::assertGreaterThanOrEqual(7, $profile->offGridNights);
        self::assertContains('caravan', $profile->categories);
        self::assertNotEmpty($mapped['hints']);
        self::assertGreaterThan(0.3, $mapped['confidence']);
    }

    public function testMapsTowVehicleHint(): void
    {
        $mapped = NaturalLanguagePreferenceMapper::map('Lightweight hybrid for a Prado 250');
        self::assertNotNull($mapped['tow_query']);
        self::assertContains('hybrid_caravan', $mapped['profile']->categories);
        self::assertSame(2000, $mapped['profile']->maxAtmKg);
    }

    public function testEmptyPromptKeepsBaseProfile(): void
    {
        $base = PreferenceProfile::fromArray(['adults' => 3, 'children' => 1]);
        $mapped = NaturalLanguagePreferenceMapper::map('zzzz no keywords here', $base);
        self::assertSame(3, $mapped['profile']->adults);
        self::assertSame(1, $mapped['profile']->children);
        self::assertSame([], $mapped['hints']);
        self::assertLessThan(0.2, $mapped['confidence']);
    }
}
