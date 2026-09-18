<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\ProviderSearchAdapter;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\SearchOrchestrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SurroundingTownExpansionTest extends TestCase
{
    public function testAskExpandsToSurroundingTownsBeforePlacesRescue(): void
    {
        $orchestrator = (string) file_get_contents(base_path('app/Platform/AiSearch/SearchOrchestrator.php'));
        self::assertStringContainsString('searchSurroundingTowns', $orchestrator);
        $surroundingAt = strpos($orchestrator, '->searchSurroundingTowns(');
        $rescueAt = strpos($orchestrator, 'new ZeroResultProviderRescueService()');
        self::assertNotFalse($surroundingAt);
        self::assertNotFalse($rescueAt);
        self::assertLessThan($rescueAt, $surroundingAt);
    }

    public function testSurroundingTownExpansionStaysCategoryScoped(): void
    {
        $adapter = (string) file_get_contents(base_path('app/Platform/AiSearch/Adapters/ProviderSearchAdapter.php'));
        self::assertStringContainsString('function searchSurroundingTowns', $adapter);
        self::assertStringContainsString("search_fallback'] = 'surrounding_town'", $adapter);
        self::assertStringContainsString('Town::neighbours', $adapter);
        self::assertStringContainsString('Provider::forCategory', $adapter);
        self::assertStringNotContainsString('Provider::inTown', $adapter);
    }

    public function testStructuredSearchAlsoUsesSurroundingTownExpansion(): void
    {
        $structured = (string) file_get_contents(base_path('app/Controllers/Site/SearchController.php'));
        self::assertStringContainsString('searchSurroundingTowns', $structured);
        self::assertStringNotContainsString("search_fallback'] = 'regional_provider_pool'", $structured);
    }

    public function testEmptyCategoriesOrEnoughResultsSkipSurroundingLookup(): void
    {
        $method = new ReflectionMethod(ProviderSearchAdapter::class, 'searchSurroundingTowns');
        $intent = new Intent(
            Intent::TYPE_PROVIDER,
            [],
            [],
            [],
            'Charters Towers',
            false,
            50,
            'normal',
            ['providers'],
            0.9,
            false,
            null
        );
        $result = $method->invoke(
            new ProviderSearchAdapter(),
            $intent,
            ['id' => 1, 'name' => 'Charters Towers'],
            null,
            null,
            [['id' => 10]],
            3
        );

        self::assertSame(0, $result['added']);
        self::assertNull($result['message']);
        self::assertSame([['id' => 10]], $result['rows']);
    }

    public function testAlreadyEnoughResultsDoNotExpand(): void
    {
        $method = new ReflectionMethod(ProviderSearchAdapter::class, 'searchSurroundingTowns');
        $intent = new Intent(
            Intent::TYPE_PROVIDER,
            ['refrigeration'],
            [],
            [],
            'Charters Towers',
            false,
            50,
            'normal',
            ['providers'],
            0.9,
            false,
            null
        );
        $already = [['id' => 1], ['id' => 2], ['id' => 3]];
        $result = $method->invoke(
            new ProviderSearchAdapter(),
            $intent,
            ['id' => 1, 'name' => 'Charters Towers'],
            null,
            null,
            $already,
            3
        );

        self::assertSame(0, $result['added']);
        self::assertSame($already, $result['rows']);
    }
}
