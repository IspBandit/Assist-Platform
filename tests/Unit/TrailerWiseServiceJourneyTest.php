<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TrailerWiseServiceJourneyTest extends TestCase
{
    public function testHomepageProvidesRepresentativeServiceFirstJourneys(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/trailerwise/home.php'));

        foreach (['trailer-repairs', 'mobile-trailer-services', 'parts-accessories', 'roadworthy-inspections', 'manufacturers-dealers', 'fabrication-engineering'] as $category) {
            self::assertStringContainsString("services/{$category}", $view);
        }
        self::assertStringContainsString('same brand-scoped provider directory', $view);
    }
}
