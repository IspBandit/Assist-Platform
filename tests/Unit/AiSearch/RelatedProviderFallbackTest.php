<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Adapters\ProviderSearchAdapter;
use App\Platform\AiSearch\SearchOrchestrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class RelatedProviderFallbackTest extends TestCase
{
    public function testSpecialistMissCanRouteToClearlyLabelledRelatedHelp(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['refrigeration'], [], [], 'Emerald', false, 25,
            'normal', ['providers'], 0.9, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'relatedProviderFallbackIntent');
        $fallback = $method->invoke(new SearchOrchestrator(), $intent);

        self::assertInstanceOf(Intent::class, $fallback);
        self::assertSame(['general-caravan-repairs'], $fallback->providerCategoryKeys);
        self::assertSame(50, $fallback->radiusKm);
        self::assertSame('Emerald', $fallback->locationText);
        self::assertSame('related_provider_fallback', $fallback->source);
    }

    public function testGeneralCaravanRepairMissDoesNotWidenToGenericMechanics(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-caravan-repairs'], [], [], 'Roma', false, 25,
            'normal', ['providers'], 0.8, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'relatedProviderFallbackIntent');
        $fallback = $method->invoke(new SearchOrchestrator(), $intent);

        self::assertNull($fallback);
    }

    public function testImplicitProviderRadiusCanExpandWithoutChangingCategory(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-caravan-repairs'], [], [], 'Boyne Island', true, 25,
            'normal', ['providers'], 0.95, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'expandedExactProviderIntent');
        $expanded = $method->invoke(new SearchOrchestrator(), $intent, ['radius_km' => null], -23.95, 151.35);

        self::assertInstanceOf(Intent::class, $expanded);
        self::assertSame(['general-caravan-repairs'], $expanded->providerCategoryKeys);
        self::assertSame(150, $expanded->radiusKm);
        self::assertSame('expanded_exact_radius', $expanded->source);
    }

    public function testExplicitProviderRadiusIsNeverExpanded(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-caravan-repairs'], [], [], 'Boyne Island', true, 25,
            'normal', ['providers'], 0.95, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'expandedExactProviderIntent');

        self::assertNull($method->invoke(
            new SearchOrchestrator(),
            $intent,
            ['radius_km' => 25],
            -23.95,
            151.35,
        ));
    }

    public function testEverydayFacilityStyleProviderSearchIsNotExpandedRegionally(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['fuel-and-travel-stops'], [], [], 'Boyne Island', true, 25,
            'normal', ['providers'], 0.95, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'expandedExactProviderIntent');

        self::assertNull($method->invoke(
            new SearchOrchestrator(),
            $intent,
            ['radius_km' => null],
            -23.95,
            151.35,
        ));
    }

    public function testServicingMissOnlyWidensToMechanicalHelp(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['mobile-mechanics', 'mechanical-repairs'], [], [], 'Karratha', false, 25,
            'normal', ['providers'], 0.8, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'relatedProviderFallbackIntent');
        $fallback = $method->invoke(new SearchOrchestrator(), $intent);

        self::assertInstanceOf(Intent::class, $fallback);
        self::assertSame(
            ['general-servicing'],
            $fallback->providerCategoryKeys
        );
        self::assertSame(50, $fallback->radiusKm);
    }

    public function testUnresolvedNamedLocationNeverFallsBackToNationalResults(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['mobile-mechanics'], [], [], 'Not A Real Town', false, 50,
            'normal', ['providers'], 0.8, false, null);

        self::assertSame([], (new ProviderSearchAdapter())->search($intent, null, null, null));
    }

    public function testProviderIntentCanUseFallbacks(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-servicing'], [], [], 'Karratha', false, 25,
            'normal', ['providers'], 0.88, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'shouldUseProviderFallback');

        self::assertTrue($method->invoke(new SearchOrchestrator(), $intent));
    }

    public function testGeneralCaravanRepairIntentCannotUseFallbacks(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['general-caravan-repairs'], [], [], 'Boyne Island', true, 25,
            'normal', ['providers'], 0.95, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'shouldUseProviderFallback');

        self::assertFalse($method->invoke(new SearchOrchestrator(), $intent));
    }

    public function testCategorySearchesCannotUseAnUnfilteredRegionalProviderPool(): void
    {
        $orchestrator = (string) file_get_contents(base_path('app/Platform/AiSearch/SearchOrchestrator.php'));
        $structuredSearch = (string) file_get_contents(base_path('app/Controllers/Site/SearchController.php'));

        self::assertStringNotContainsString('searchRegionalTownPool', $orchestrator);
        self::assertStringNotContainsString("search_fallback'] = 'regional_provider_pool'", $structuredSearch);
    }

    public function testFacilityIntentCannotFallBackToUnrelatedRepairProviders(): void
    {
        $intent = new Intent(Intent::TYPE_FACILITY, ['dump-points'], [], ['dump_point'], 'Gladstone', false, 25,
            'normal', ['providers', 'traveller_facilities'], 0.92, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'shouldUseProviderFallback');

        self::assertFalse($method->invoke(new SearchOrchestrator(), $intent));
    }

    public function testLpgProviderIntentCannotFallBackToUnrelatedRepairProviders(): void
    {
        $intent = new Intent(Intent::TYPE_PROVIDER, ['lpg-refills-and-bottle-exchange'], [], [], 'Roma', false, 25,
            'normal', ['providers'], 0.92, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'shouldUseProviderFallback');

        self::assertFalse($method->invoke(new SearchOrchestrator(), $intent));
    }

    public function testMixedStayIntentCannotUseProviderFallbacks(): void
    {
        $intent = new Intent(Intent::TYPE_MIXED, ['general-servicing'], ['caravan_park'], [], 'Karratha', false, 25,
            'normal', ['providers', 'stays'], 0.7, false, null);
        $method = new ReflectionMethod(SearchOrchestrator::class, 'shouldUseProviderFallback');

        self::assertFalse($method->invoke(new SearchOrchestrator(), $intent));
    }
}
