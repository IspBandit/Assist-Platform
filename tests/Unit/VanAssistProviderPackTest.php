<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\VanAssistProviderPackSeeder;
use PHPUnit\Framework\TestCase;

final class VanAssistProviderPackTest extends TestCase
{
    /** @return array<mixed> */
    private function json(string $name): array
    {
        $value = json_decode((string) file_get_contents(base_path('database/seeds/vanassist-provider-pack/' . $name)), true);
        self::assertIsArray($value);
        return $value;
    }

    public function testPublicPackContainsOnlyPublishableCategorisedRecords(): void
    {
        $providers = $this->json('providers-publishable.json');
        self::assertGreaterThan(8000, count($providers));
        foreach ($providers as $provider) {
            self::assertIsArray($provider);
            self::assertTrue($provider['publishable'] ?? false, (string) ($provider['id'] ?? 'unknown'));
            self::assertNotSame('', trim((string) ($provider['id'] ?? '')));
            self::assertNotSame('', trim((string) ($provider['name'] ?? '')));
            self::assertNotEmpty($provider['categories'] ?? []);
        }
    }

    public function testFuelTaxonomyRoutesToVanAssist(): void
    {
        $taxonomy = $this->json('categories.json');
        $found = [];
        foreach ((array) ($taxonomy['groups'] ?? []) as $group) {
            foreach ((array) ($group['categories'] ?? []) as $category) {
                $id = (string) ($category['id'] ?? '');
                if (in_array($id, ['fuel-station', 'ev-charging'], true)) {
                    $found[$id] = $category['brands'] ?? [];
                }
            }
        }
        self::assertSame(['vanassist'], $found['fuel-station'] ?? null);
        self::assertSame(['vanassist'], $found['ev-charging'] ?? null);
    }

    public function testLegacyFuelRowsAreNotPresentedAsGasCertifiers(): void
    {
        $record = ['source' => 'vanassist-osm'];
        self::assertSame(
            ['fuel-station', 'general-mechanic'],
            VanAssistProviderPackSeeder::sanitiseCategories(
                $record,
                ['gas-certification', 'fuel-station', 'general-mechanic']
            )
        );
        self::assertSame(
            ['gas-certification'],
            VanAssistProviderPackSeeder::sanitiseCategories(['source' => 'other'], ['gas-certification'])
        );
    }

    public function testEveryVanAssistPackCategoryBridgesToPublicSearchServices(): void
    {
        $taxonomy = $this->json('categories.json');
        $map = VanAssistProviderPackSeeder::vanAssistCompatibilityMap();
        $missing = [];
        foreach ((array) ($taxonomy['groups'] ?? []) as $group) {
            foreach ((array) ($group['categories'] ?? []) as $category) {
                if (!in_array('vanassist', (array) ($category['brands'] ?? []), true)) {
                    continue;
                }
                $id = (string) ($category['id'] ?? '');
                if (!isset($map[$id]) || $map[$id] === []) {
                    $missing[] = $id;
                }
            }
        }

        self::assertSame([], $missing);
        self::assertContains('general-caravan-repairs', $map['caravan-repairs']);
        self::assertContains('auto-electrical-and-batteries', $map['auto-electrician']);
        self::assertContains('mobile-mechanics', $map['mobile-mechanic']);
    }

    public function testCompatibilityBridgeKeepsKnownRetailersConservative(): void
    {
        self::assertSame(
            ['auto-electrical-and-batteries'],
            VanAssistProviderPackSeeder::vanAssistServiceSlugs(
                ['name' => 'Battery World Rockhampton'],
                ['battery-specialist', 'auto-electrician']
            )
        );
        self::assertSame(
            ['tyres-and-wheels'],
            VanAssistProviderPackSeeder::vanAssistServiceSlugs(
                ['name' => 'Rockhampton Tyrepower'],
                ['tyre-shop', 'general-mechanic']
            )
        );
        self::assertSame(
            ['general-caravan-repairs'],
            VanAssistProviderPackSeeder::vanAssistServiceSlugs(
                ['name' => 'Capricorn Caravan Centre'],
                ['caravan-repairs']
            )
        );
    }

    public function testKnownRetailChainsDoNotInheritUnsupportedWorkshopServices(): void
    {
        self::assertSame(
            ['auto-electrician', 'battery-specialist'],
            VanAssistProviderPackSeeder::sanitiseCategories(
                ['name' => 'Battery World Emerald', 'source' => 'public-source'],
                ['general-mechanic', 'tyre-shop', 'auto-electrician', 'battery-specialist', 'suspension']
            )
        );
        self::assertSame(
            ['tyre-shop'],
            VanAssistProviderPackSeeder::sanitiseCategories(
                ['name' => 'Emerald Tyrepower', 'source' => 'public-source'],
                ['general-mechanic', 'auto-electrician', 'tyre-shop']
            )
        );
        self::assertSame(
            ['fuel-station'],
            VanAssistProviderPackSeeder::sanitiseCategories(
                ['name' => 'Ampol Emerald', 'source' => 'public-source', 'categories' => ['fuel-station', 'general-mechanic']],
                ['fuel-station', 'general-mechanic']
            )
        );
    }

    public function testFuelRowsRetainRequiredSourceAttribution(): void
    {
        foreach ($this->json('providers-publishable.json') as $provider) {
            if (!in_array('fuel-station', (array) ($provider['categories'] ?? []), true)) {
                continue;
            }
            $source = strtolower((string) ($provider['source'] ?? ''));
            $licence = strtolower((string) ($provider['source_licence'] ?? ''));
            if ($source === 'geoscience-australia') {
                self::assertStringContainsString('cc by', $licence, (string) $provider['id']);
            }
            if ($source === 'openstreetmap') {
                self::assertStringContainsString('odbl', $licence, (string) $provider['id']);
            }
        }
    }
}
