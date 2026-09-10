<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class RetiredBrandBoundaryTest extends TestCase
{
    public function testOnlySalePackageBrandsAreActivelyConfigured(): void
    {
        $config = require dirname(__DIR__, 2) . '/config/brands.php';
        $ids = array_keys($config['registry']);
        sort($ids);
        self::assertSame(['towsmart', 'trailerwise', 'vanassist'], $ids);
        $registry = BrandRegistry::fromArray($config['registry']);

        self::assertNull($registry->find('polaris'));
        self::assertNull($registry->find('localtorque'));
        self::assertNotNull($registry->find('vanassist'));
        self::assertNotNull($registry->find('towsmart'));
        self::assertNotNull($registry->find('trailerwise'));
    }

    public function testRetiredControllersCannotBeReachedThroughActiveRoutes(): void
    {
        foreach (['web', 'admin'] as $routes) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/routes/' . $routes . '.php');
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/polaris|localtorque/i', $source);
        }
    }
}
