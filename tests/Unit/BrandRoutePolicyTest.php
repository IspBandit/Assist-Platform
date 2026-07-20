<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandRoutePolicy;
use PHPUnit\Framework\TestCase;

final class BrandRoutePolicyTest extends TestCase
{
    public function test_vanassist_retains_existing_routes(): void
    {
        self::assertTrue((new BrandRoutePolicy())->allows($this->brand('vanassist', []), '/providers/example'));
    }

    public function test_towwise_only_allows_implemented_public_paths(): void
    {
        $brand = $this->brand('towwise', ['towing_tools' => true]);
        $policy = new BrandRoutePolicy();

        self::assertTrue($policy->allows($brand, '/'));
        self::assertTrue($policy->allows($brand, '/tools'));
        self::assertTrue($policy->allows($brand, '/commercial/go/1/2'));
        self::assertFalse($policy->allows($brand, '/providers/example'));
        self::assertFalse($policy->allows($brand, '/marketplace'));
    }

    public function test_trailerwise_marketplace_paths_are_scoped(): void
    {
        $brand = $this->brand('trailerwise', ['trailer_marketplace' => true]);
        $policy = new BrandRoutePolicy();

        self::assertTrue($policy->allows($brand, '/marketplace'));
        self::assertTrue($policy->allows($brand, '/marketplace/example'));
        self::assertFalse($policy->allows($brand, '/tools'));
        self::assertFalse($policy->allows($brand, '/admin'));
    }

    /** @param array<string,bool> $modules */
    private function brand(string $id, array $modules): Brand
    {
        return Brand::fromArray($id, [
            'database_id' => $id === 'vanassist' ? 1 : ($id === 'towwise' ? 2 : 3),
            'name' => ucfirst($id), 'legal_name' => ucfirst($id), 'short_name' => ucfirst($id),
            'status' => 'active', 'url' => 'https://' . $id . '.test',
            'domains' => ['primary' => $id . '.test'], 'assets' => ['logo' => '/logo.svg'],
            'theme' => ['brand' => '#000000'], 'metadata' => ['tagline' => 'Test'],
            'contact' => ['support_email' => ''], 'legal' => ['privacy_path' => '/privacy'],
            'modules' => array_merge(['public_application' => true], $modules),
            'storage_namespace' => $id,
        ]);
    }
}
