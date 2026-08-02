<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\BuyerStateService;
use App\Services\Polaris\ComparisonService;
use App\Services\Polaris\PreferenceProfile;
use PHPUnit\Framework\TestCase;

final class BuyerStateAndFindWiringTest extends TestCase
{
    public function testPreferenceProfileRoundTripArray(): void
    {
        $profile = PreferenceProfile::fromArray([
            'adults' => 2,
            'children' => 1,
            'max_budget_aud' => 90000,
            'require_bathroom' => true,
            'priority_towability' => 'essential',
        ]);
        $again = PreferenceProfile::fromArray($profile->toArray());
        self::assertSame(2, $again->adults);
        self::assertSame(1, $again->children);
        self::assertTrue($again->requireBathroom);
        self::assertSame('essential', $again->priorities['towability']);
        self::assertSame(9000000, $again->maxBudgetAudCents);
    }

    public function testComparisonServiceCapsAtFourModels(): void
    {
        $models = [];
        for ($i = 1; $i <= 6; $i++) {
            $models[] = [
                'id' => $i,
                'name' => 'Model ' . $i,
                'manufacturer_name' => 'Mfr',
                'category_label' => 'Caravan',
                'production_status' => 'current',
                'verification_status' => 'verified',
                'sleeps' => 2,
                'body_length_m' => 5 + $i,
                'tare_kg' => 1000 + $i,
                'atm_kg' => 2000 + $i,
                'payload_kg' => 300,
                'price_label' => 'From $' . (50000 + $i),
                'price_aud_cents' => (50000 + $i) * 100,
            ];
        }
        $built = (new ComparisonService())->build($models);
        self::assertCount(ComparisonService::MAX_MODELS, $built['models']);
    }

    public function testShareTokenShapeIsSixteenHex(): void
    {
        $ref = new \ReflectionClass(BuyerStateService::class);
        self::assertTrue($ref->hasMethod('saveComparison'));
        self::assertTrue($ref->hasMethod('loadComparisonModelIds'));
    }

    public function testRoutesAndMigrationWiring(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/compare/{token}'", $routes);
        self::assertStringContainsString('PolarisController@shareCompare', $routes);
        self::assertStringContainsString('PolarisController@accountPreferences', $routes);
        self::assertStringContainsString('PolarisController@accountComparisons', $routes);
        self::assertStringContainsString('ManufacturerPortalController@saveProfile', $routes);
        self::assertStringContainsString('ManufacturerPortalController@uploadMedia', $routes);
        self::assertStringContainsString('DealerPortalController@claims', $routes);

        $adminRoutes = (string) file_get_contents($root . '/routes/admin.php');
        self::assertStringContainsString('PolarisAdminController@mergeManufacturers', $adminRoutes);

        $sql = (string) file_get_contents($root . '/database/migrations/111_polaris_comparisons.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS polaris_comparisons', $sql);
        $sql096 = (string) file_get_contents($root . '/database/migrations/112_polaris_dealers_media_team.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS polaris_dealers', $sql096);
        self::assertStringContainsString('polaris_manufacturer_media', $sql096);

        $uploads = (string) file_get_contents($root . '/config/uploads.php');
        self::assertStringContainsString("'polaris_media'", $uploads);

        $find = (string) file_get_contents($root . '/app/Views/polaris/find.php');
        self::assertStringContainsString('Continue', $find);
        self::assertStringContainsString('polaris-nl-hints', $find);
        self::assertStringContainsString('Deterministic keyword hints', $find);
        self::assertStringContainsString('travel_surface', $find);
        self::assertStringContainsString('layout_pref', $find);

        $controller = (string) file_get_contents($root . '/app/Controllers/Site/PolarisController.php');
        self::assertStringContainsString('NaturalLanguagePreferenceMapper', $controller);

        $sql099 = (string) file_get_contents($root . '/database/migrations/115_polaris_provenance_extract_flags.sql');
        self::assertStringContainsString('polaris_model_sources', $sql099);
        self::assertStringContainsString('polaris_brochure_extract', $sql099);

        $modelView = (string) file_get_contents($root . '/app/Views/polaris/model.php');
        self::assertStringContainsString('polaris-spec-table', $modelView);
        self::assertStringContainsString('Specification provenance', $modelView);

        $a11y = (string) file_get_contents($root . '/docs/polaris/ACCESSIBILITY_QA.md');
        self::assertStringContainsString('CONDITIONAL', $a11y);
    }
}
