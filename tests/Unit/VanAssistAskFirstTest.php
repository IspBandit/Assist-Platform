<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class VanAssistAskFirstTest extends TestCase
{
    public function testHomepagePresentsAskBeforeStructuredBrowse(): void
    {
        $home = $this->source('app/Views/public/home.php');
        $ask = $this->source('app/Views/partials/ask-vanassist.php');

        $askInclude = strpos($home, "include('partials.ask-vanassist')");
        $structuredForm = strpos($home, 'structured-search-form home-search-form');

        self::assertNotFalse($askInclude);
        self::assertNotFalse($structuredForm);
        self::assertLessThan($structuredForm, $askInclude);
        self::assertStringContainsString('Browse directly', $home);
        self::assertStringContainsString('Browse VanAssist directly', $home);
        self::assertStringNotContainsString('ask-vanassist-divider', $ask);
        self::assertStringNotContainsString('Start here', $ask);
        self::assertStringContainsString('Tell us what has happened or what you need', $ask);
        self::assertStringContainsString('Find the right help', $ask);
        self::assertStringContainsString('What do you need help finding?', $ask);
    }

    public function testAskFirstHierarchyPreservesStructuredSearchAndLocationControls(): void
    {
        $home = $this->source('app/Views/public/home.php');
        $ask = $this->source('app/Views/partials/ask-vanassist.php');

        self::assertStringContainsString("action=\"<?= e(url('find')) ?>\"", $home);
        self::assertStringContainsString('Service category', $home);
        self::assertStringContainsString('Town, suburb or postcode', $home);
        self::assertStringContainsString("url('locations/nearest')", $home);
        self::assertStringContainsString("action=\"<?= e(url('ask')) ?>\"", $ask);
        self::assertStringContainsString('name="lat"', $ask);
        self::assertStringContainsString('name="lng"', $ask);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
