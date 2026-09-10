<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductBrandHomepageFinderTest extends TestCase
{
    public function testBothProductHomepagesUseOneSharedFinder(): void
    {
        foreach (['towsmart/home.php', 'trailerwise/home.php'] as $view) {
            $source = $this->source('app/Views/' . $view);
            self::assertStringContainsString("partials.brand-directory-finder", $source);
        }
    }

    public function testFinderUsesExistingBrandDirectoryAndLocationServices(): void
    {
        $source = $this->source('app/Views/partials/brand-directory-finder.php');

        self::assertStringContainsString("action=\"<?= e(url('providers')) ?>\"", $source);
        self::assertStringContainsString("data-nearest-url=\"<?= e_attr(url('locations/nearest')) ?>\"", $source);
        self::assertStringContainsString("data-town-search=\"<?= e_attr(url('locations/towns')) ?>\"", $source);
        self::assertStringContainsString('data-auto-location', $source);
        self::assertStringContainsString('aria-live="polite"', $source);
    }

    public function testFinderResolvesDeviceLocationWithoutSubmittingDirectory(): void
    {
        $source = $this->source('app/Views/partials/brand-directory-finder.php');

        self::assertSame(2, substr_count($source, "'autoSubmit' => 'false'"));
    }

    public function testFinderSubmitsCuratedCategoryIdsAndTrustCopy(): void
    {
        $source = $this->source('app/Views/partials/brand-directory-finder.php');

        self::assertStringContainsString("name=\"category\"", $source);
        self::assertStringContainsString('(int) $category[\'id\']', $source);
        self::assertStringContainsString('may be unclaimed or unverified', $source);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }
}
