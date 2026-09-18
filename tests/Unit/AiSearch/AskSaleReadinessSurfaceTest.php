<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use PHPUnit\Framework\TestCase;

final class AskSaleReadinessSurfaceTest extends TestCase
{
    public function testAskViewOffersAmbiguousTownChooser(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/public/assist-search.php'));
        self::assertStringContainsString('locationCandidates', $view);
        self::assertStringContainsString('Which place did you mean?', $view);
        self::assertStringContainsString('LocationDisambiguation::rewriteQuery', $view);
    }

    public function testAskViewPrefersNearerPublicSourceHits(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/public/assist-search.php'));
        self::assertStringContainsString('$showExternalsFirst', $view);
        self::assertStringContainsString('minExternalKm', $view);
    }

    public function testOrchestratorReturnsAmbiguousCandidates(): void
    {
        $src = (string) file_get_contents(base_path('app/Platform/AiSearch/SearchOrchestrator.php'));
        self::assertStringContainsString("fallback = 'location_ambiguous'", $src);
        self::assertStringContainsString('locationCandidates:', $src);
        self::assertStringContainsString('LocationDisambiguation::choices', $src);
    }

    public function testFindRequiresLocationForCategorySearch(): void
    {
        $src = (string) file_get_contents(base_path('app/Controllers/Site/SearchController.php'));
        self::assertStringContainsString('needsLocation', $src);
        self::assertStringContainsString("\$categoryId !== null && \$town === null && !\$hasOrigin", $src);
    }

    public function testLocationBoundServiceIntentDoesNotYieldToExactBusinessName(): void
    {
        $src = (string) file_get_contents(base_path('app/Platform/AiSearch/SearchOrchestrator.php'));
        self::assertStringContainsString('locationBoundServiceQuery', $src);
        self::assertStringContainsString(
            'if ($exactProviderRows !== [] && !$locationBoundServiceQuery)',
            $src
        );
    }

    public function testUserLockedRadiusReadsNormaliserRadiusField(): void
    {
        $src = (string) file_get_contents(base_path('app/Platform/AiSearch/SearchOrchestrator.php'));
        self::assertStringContainsString("(\$meta['radius_km'] ?? null) !== null", $src);
        self::assertStringNotContainsString("\$meta['normalised']['radius_km']", $src);
    }
}
