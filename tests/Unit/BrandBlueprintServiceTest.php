<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\Brand\BrandRegistry;
use App\Services\BrandBlueprintService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class BrandBlueprintServiceTest extends TestCase
{
    public function testBuildsPrivateNonPersistentBlueprint(): void
    {
        $result = (new BrandBlueprintService())->build([
            'brand_key' => 'machine-assist',
            'name' => 'MachineAssist',
            'domain' => 'machineassist.com.au',
            'primary_colour' => '#123456',
            'accent_colour' => '#abcdef',
            'modules' => ['providers', 'memberships'],
        ], $this->registry());

        self::assertSame('private', $result['status']);
        self::assertSame('machineassist.com.au', $result['domains']['primary']);
        self::assertTrue($result['modules']['providers']);
        self::assertFalse($result['modules']['parks']);
        self::assertArrayNotHasKey('database_id', $result);
    }

    public function testRejectsConfiguredDomain(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BrandBlueprintService())->build([
            'brand_key' => 'another-brand',
            'name' => 'Another Brand',
            'domain' => 'vanassist.test',
            'modules' => ['providers'],
        ], $this->registry());
    }

    public function testRejectsUnknownOnlyModuleSelection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new BrandBlueprintService())->build([
            'brand_key' => 'another-brand',
            'name' => 'Another Brand',
            'domain' => 'another.example',
            'modules' => ['not-a-module'],
        ], $this->registry());
    }

    private function registry(): BrandRegistry
    {
        return BrandRegistry::fromArray([
            'vanassist' => [
                'database_id' => 1, 'name' => 'VanAssist', 'legal_name' => 'VanAssist',
                'short_name' => 'VanAssist', 'status' => 'active', 'url' => 'https://vanassist.test',
                'domains' => ['primary' => 'vanassist.test'], 'assets' => ['logo' => '/logo.svg'],
                'theme' => ['brand' => '#000000'], 'metadata' => ['description' => 'Test'],
                'contact' => [], 'legal' => [], 'navigation' => [], 'footer' => [],
                'features' => [], 'modules' => [], 'analytics' => [], 'search' => [],
                'storage_namespace' => 'vanassist',
            ],
        ]);
    }
}

