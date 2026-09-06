<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class PolarisBrandRegistryTest extends TestCase
{
    public function testPolarisIsNotAnActiveConfiguredBrand(): void
    {
        $config = require dirname(__DIR__, 3) . '/config/brands.php';
        $registry = BrandRegistry::fromArray($config['registry']);

        self::assertNull($registry->find('polaris'));
        self::assertNull($registry->find('localtorque'));
        self::assertNotNull($registry->find('vanassist'));
        self::assertNotNull($registry->find('towsmart'));
        self::assertNotNull($registry->find('trailerwise'));
    }
}
