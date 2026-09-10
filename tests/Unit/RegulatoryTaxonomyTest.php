<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RegulatoryTaxonomy;
use PHPUnit\Framework\TestCase;

final class RegulatoryTaxonomyTest extends TestCase
{
    public function testStreetRodNormalizationRemainsDeterministicForHistoricalRecords(): void
    {
        self::assertSame(
            ['vehicle' => 'street-rod', 'kind' => 'street_rods'],
            RegulatoryTaxonomy::normalize('street-rod', 'modifications')
        );
    }

    public function testVanAssistNeverOffersStreetRodFilters(): void
    {
        self::assertArrayNotHasKey('street-rod', RegulatoryTaxonomy::vehiclesForBrand('vanassist'));
        self::assertArrayNotHasKey('street_rods', RegulatoryTaxonomy::kindsForBrand('vanassist'));
        self::assertArrayHasKey('modifications', RegulatoryTaxonomy::kindsForBrand('vanassist'));
    }
}
