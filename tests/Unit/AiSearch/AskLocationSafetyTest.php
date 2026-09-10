<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Models\CaravanPark;
use App\Models\Town;
use PHPUnit\Framework\TestCase;

final class AskLocationSafetyTest extends TestCase
{
    public function testCommonTownTypoHasAConservativeStrongScore(): void
    {
        self::assertGreaterThan(0.82, Town::localityMatchScore('Brisban', 'Brisbane'));
        self::assertLessThan(0.82, Town::localityMatchScore('Brisban', 'Bundaberg'));
    }

    public function testMisspeltCampgroundLandmarkMatchesItsCanonicalName(): void
    {
        self::assertGreaterThan(
            0.78,
            CaravanPark::landmarkMatchScore('Grffiths camping ground', 'Griffiths Creek Camping Area')
        );
        self::assertLessThan(
            0.78,
            CaravanPark::landmarkMatchScore('Grffiths camping ground', 'Bunya Mountains Camping Area')
        );
    }
}
