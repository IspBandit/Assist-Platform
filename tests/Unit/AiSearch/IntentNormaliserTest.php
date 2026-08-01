<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Intent\IntentNormaliser;
use PHPUnit\Framework\TestCase;

final class IntentNormaliserTest extends TestCase
{
    /** @return array<string,array{0:string,1:bool,2:?int,3:string}> */
    public static function analyseCases(): array
    {
        return [
            'near_me' => ['tyres near me', true, null, 'normal'],
            'nearby_sets_location' => ['caravan park nearby', true, null, 'normal'],
            'within_radius' => ['auto electrician within 50 km', false, 50, 'normal'],
            'bare_radius' => ['lpg refill 30 km', false, 30, 'normal'],
            'urgent' => ['urgent towing near me', true, null, 'urgent'],
            'breakdown' => ['broken down near Emerald', false, null, 'urgent'],
            'tyre_spelling' => ['tyres near Batemans Bay', false, null, 'normal'],
            'tire_spelling' => ['tires near Batemans Bay', false, null, 'normal'],
        ];
    }

    /** @dataProvider analyseCases */
    public function testAnalyseExtractsMetadata(
        string $query,
        bool $useCurrentLocation,
        ?int $radiusKm,
        string $urgency,
    ): void {
        $meta = IntentNormaliser::analyse($query);

        self::assertSame($useCurrentLocation, $meta['use_current_location']);
        self::assertSame($radiusKm, $meta['radius_km']);
        self::assertSame($urgency, $meta['urgency']);
        self::assertNotSame('', $meta['normalised']);
        self::assertSame($meta['normalised'], $meta['remainder']);
    }

    public function testNormalisedUsesAuTyreSpelling(): void
    {
        $meta = IntentNormaliser::analyse('tires near me');
        self::assertStringContainsString('tyre', $meta['normalised']);
        self::assertStringNotContainsString('tire', $meta['normalised']);
    }

    public function testRadiusIsClamped(): void
    {
        $meta = IntentNormaliser::analyse('mechanic within 999 km');
        self::assertSame(500, $meta['radius_km']);
    }

    public function testWhitespaceCollapsed(): void
    {
        $meta = IntentNormaliser::analyse("  dump   point   near   me  ");
        self::assertSame('dump point', $meta['normalised']);
        self::assertTrue($meta['use_current_location']);
    }

    public function testNearbyRemovedFromNormalisedText(): void
    {
        $meta = IntentNormaliser::analyse('caravan park nearby');
        self::assertSame('caravan park', $meta['normalised']);
        self::assertTrue($meta['use_current_location']);
    }
}
