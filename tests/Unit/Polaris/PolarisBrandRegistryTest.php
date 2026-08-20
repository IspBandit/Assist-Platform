<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class PolarisBrandRegistryTest extends TestCase
{
    public function testPolarisIsRegisteredAsPrivateFifthBrand(): void
    {
        /** @var array<string,array<string,mixed>> $registryConfig */
        $registryConfig = (array) require dirname(__DIR__, 3) . '/config/brands.php';
        $registry = BrandRegistry::fromArray($registryConfig['registry']);
        $polaris = $registry->get('polaris');

        self::assertSame(5, $polaris->databaseId());
        self::assertSame('private', $polaris->status());
        self::assertTrue($polaris->moduleEnabled('rv_catalogue'));
        self::assertFalse($polaris->moduleEnabled('providers'));
        self::assertSame('polaris', $polaris->storageNamespace());
        self::assertContains('polaris.test', $polaris->domains());
        self::assertSame('#1a3a4a', $polaris->theme()['brand']);
        self::assertSame('#c4a574', $polaris->theme()['accent']);
    }

    public function testPolarisNavigationIncludesDecisionPaths(): void
    {
        /** @var array<string,array<string,mixed>> $registryConfig */
        $registryConfig = (array) require dirname(__DIR__, 3) . '/config/brands.php';
        $registry = BrandRegistry::fromArray($registryConfig['registry']);
        $paths = array_column($registry->get('polaris')->navigation(), 'path');

        self::assertContains('/find', $paths);
        self::assertContains('/rvs', $paths);
        self::assertContains('/tow-match', $paths);
        self::assertContains('/manufacturers', $paths);
    }
}
