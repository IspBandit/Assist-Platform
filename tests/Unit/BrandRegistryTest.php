<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Platform\Brand\BrandRegistry;
use App\Platform\Brand\BrandResolver;
use App\Platform\Feature\FeatureGate;
use App\Platform\Feature\PlatformFeature;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class BrandRegistryTest extends TestCase
{
    public function testRegistryResolvesExactNormalizedHost(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);

        self::assertSame('towsmart', $registry->forHost('TOWSMART.TEST:8080')?->id());
        self::assertNull($registry->forHost('unknown.test'));
    }

    public function testResolverPrefersExplicitDeploymentBrand(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);
        $resolver = new BrandResolver($registry, 'vanassist', 'towsmart');

        self::assertSame('towsmart', $resolver->resolve($this->request('vanassist.test'))->id());
    }

    public function testStrictProductionHostMustMatchExplicitDeploymentBrand(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);
        $resolver = new BrandResolver($registry, 'vanassist', 'towsmart', 'production', false, true);

        $this->expectException(RuntimeException::class);
        $resolver->resolve($this->request('vanassist.test'));
    }

    public function testResolverSupportsDevelopmentOnlyFallback(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);
        $resolver = new BrandResolver($registry, 'vanassist', null, 'local', true);
        $request = new Request(['_brand' => 'towsmart'], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => 'localhost',
        ], []);

        self::assertSame('towsmart', $resolver->resolve($request)->id());
    }

    public function testUnknownProductionHostFailsWhenStrict(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
        ]);
        $resolver = new BrandResolver($registry, 'vanassist', null, 'production', false, true);

        $this->expectException(RuntimeException::class);
        $resolver->resolve($this->request('unknown.example'));
    }

    public function testDuplicateDomainIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'shared.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'shared.test'),
        ]);
    }

    public function testDuplicateDatabaseIdIsRejected(): void
    {
        $towsmart = $this->brandConfig('TowSmart', 'towsmart.test');
        $towsmart['database_id'] = 1;

        $this->expectException(InvalidArgumentException::class);
        BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $towsmart,
        ]);
    }

    public function testLegacyTowWiseIdResolvesToTowSmart(): void
    {
        $registry = BrandRegistry::fromArray([
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);

        self::assertSame('towsmart', $registry->get('towwise')->id());
        self::assertSame('towsmart', $registry->find('towwise')?->id());
    }

    public function testRegistryResolvesBrandByDatabaseId(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
            'towsmart' => $this->brandConfig('TowSmart', 'towsmart.test'),
        ]);

        self::assertSame('towsmart', $registry->forDatabaseId(2)?->id());
        self::assertNull($registry->forDatabaseId(999));
    }

    public function testTypedFeatureGateUsesBrandConfiguration(): void
    {
        $registry = BrandRegistry::fromArray([
            'vanassist' => $this->brandConfig('VanAssist', 'vanassist.test'),
        ]);
        $brand = $registry->get('vanassist');

        self::assertFalse(FeatureGate::enabled(PlatformFeature::Reviews, $brand));
        self::assertTrue($brand->moduleEnabled('providers'));
    }

    private function request(string $host): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
            'HTTP_HOST' => $host,
        ], []);
    }

    /** @return array<string,mixed> */
    private function brandConfig(string $name, string $domain): array
    {
        return [
            'database_id' => $name === 'VanAssist' ? 1 : 2,
            'name' => $name,
            'legal_name' => $name,
            'short_name' => $name,
            'status' => 'active',
            'url' => 'https://' . $domain,
            'domains' => ['primary' => $domain],
            'assets' => ['logo' => '/logo.svg'],
            'theme' => ['brand' => '#000000'],
            'metadata' => ['description' => $name],
            'contact' => [
                'support_email' => 'support@example.com',
                'sender_email' => 'support@' . $domain,
                'sender_name' => $name,
            ],
            'legal' => ['privacy_path' => '/privacy'],
            'navigation' => [],
            'footer' => [],
            'features' => ['reviews.enabled' => false],
            'modules' => ['providers' => true],
            'analytics' => [],
            'search' => [],
            'storage_namespace' => strtolower($name),
        ];
    }
}
