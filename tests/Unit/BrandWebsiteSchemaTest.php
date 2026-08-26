<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\Brand\BrandRegistry;
use App\Services\SeoSchema;
use PHPUnit\Framework\TestCase;

final class BrandWebsiteSchemaTest extends TestCase
{
    public function testOrganisationAndWebsiteSchemaUseTrustedBrandConfiguration(): void
    {
        $brand = BrandRegistry::fromArray(['towsmart' => [
            'database_id' => 2, 'name' => 'TowSmart', 'legal_name' => 'TowSmart',
            'short_name' => 'TowSmart', 'status' => 'active', 'url' => 'https://towsmart.test',
            'domains' => ['primary' => 'towsmart.test'], 'assets' => ['logo' => '/logo.svg'],
            'theme' => ['brand' => '#123456'], 'metadata' => ['description' => 'Towing guidance.'],
            'contact' => [], 'legal' => [], 'storage_namespace' => 'towwise',
        ]])->get('towsmart');

        $blocks = SeoSchema::brandWebsite($brand);
        self::assertCount(2, $blocks);
        $organisation = json_decode($blocks[0], true, 512, JSON_THROW_ON_ERROR);
        $website = json_decode($blocks[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Organization', $organisation['@type']);
        self::assertSame('https://towsmart.test/logo.svg', $organisation['logo']);
        self::assertSame('WebSite', $website['@type']);
        self::assertSame('TowSmart', $website['name']);
    }

    public function testBothProductHomeControllersUseSharedSchemaBuilder(): void
    {
        foreach (['TowSmartController.php', 'TrailerWiseController.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/app/Controllers/Site/' . $file);
            self::assertIsString($source);
            self::assertStringContainsString('SeoSchema::brandWebsite(current_brand())', $source);
        }
    }

    public function testPublicBrandSchemasUseCanonicalMarkUrls(): void
    {
        $configuration = require dirname(__DIR__, 2) . '/config/brands.php';
        self::assertIsArray($configuration['registry'] ?? null);
        $registry = BrandRegistry::fromArray($configuration['registry']);

        foreach (['vanassist', 'towsmart', 'trailerwise'] as $brandId) {
            $organisation = json_decode(
                SeoSchema::brandWebsite($registry->get($brandId))[0],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            self::assertSame(
                $registry->get($brandId)->url() . "/assets/brands/{$brandId}/mark.svg",
                $organisation['logo'] ?? null
            );
            self::assertStringNotContainsString('symbol-v2.svg', (string) ($organisation['logo'] ?? ''));
        }
    }
}
