<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\SavedCatalogueService;
use PHPUnit\Framework\TestCase;

final class SavedSearchWiringTest extends TestCase
{
    public function testNormaliseBrowseQueryDropsEmptyAndDefaultSort(): void
    {
        $query = SavedCatalogueService::normaliseBrowseQuery([
            'q' => ' Jayco ',
            'category' => 'caravan',
            'production_status' => '',
            'min_sleeps' => '4',
            'max_atm_kg' => '',
            'max_length_m' => '',
            'max_budget_aud' => '90000',
            'sort' => 'name',
            'noise' => 'ignored',
        ]);
        self::assertSame([
            'q' => 'Jayco',
            'category' => 'caravan',
            'min_sleeps' => '4',
            'max_budget_aud' => '90000',
        ], $query);
    }

    public function testBrowsePathAndSuggestedName(): void
    {
        $path = SavedCatalogueService::browsePathFromQuery([
            'category' => 'hybrid',
            'max_atm_kg' => '2500',
        ]);
        self::assertStringStartsWith('/rvs?', $path);
        self::assertStringContainsString('category=hybrid', $path);
        self::assertStringContainsString('max_atm_kg=2500', $path);

        $name = SavedCatalogueService::suggestSearchName([
            'category' => 'hybrid',
            'max_atm_kg' => '2500',
        ]);
        self::assertStringContainsString('hybrid', $name);
        self::assertStringContainsString('2500', $name);
    }

    public function testRoutesAndViewsWireSavedSearchCapture(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString('PolarisController@saveSearch', $routes);
        self::assertStringContainsString('PolarisController@unsaveSearch', $routes);

        $controller = (string) file_get_contents($root . '/app/Controllers/Site/PolarisController.php');
        self::assertStringContainsString('function saveSearch', $controller);
        self::assertStringContainsString("polaris.account-alerts", $controller);
        self::assertStringNotContainsString('Alert subscriptions are scaffolded', $controller);

        $browse = (string) file_get_contents($root . '/app/Views/polaris/browse.php');
        self::assertStringContainsString('Save this search', $browse);
        self::assertStringContainsString('saved/searches', $browse);

        $saved = (string) file_get_contents($root . '/app/Views/polaris/saved.php');
        self::assertStringContainsString('Email alerts are not sent yet', $saved);
        self::assertStringContainsString('saved/searches/remove', $saved);

        $service = (string) file_get_contents($root . '/app/Services/Polaris/SavedCatalogueService.php');
        self::assertStringContainsString('normaliseBrowseQuery', $service);
        self::assertStringContainsString('removeSearch', $service);
    }
}
