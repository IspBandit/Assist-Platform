<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\LocalTorquePackSeeder;
use PHPUnit\Framework\TestCase;

final class LocalTorquePackTest extends TestCase
{
    /** @return array<mixed> */
    private function json(string $name): array
    {
        $value = json_decode((string) file_get_contents(base_path('database/seeds/localtorque/' . $name)), true);
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

    public function testFuelTaxonomyRoutesOnlyToLocalTorqueAndVanAssist(): void
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
        self::assertSame(['localtorque', 'vanassist'], $found['fuel-station'] ?? null);
        self::assertSame(['localtorque', 'vanassist'], $found['ev-charging'] ?? null);
    }

    public function testLegacyFuelRowsAreNotPresentedAsGasCertifiers(): void
    {
        $record = ['source' => 'vanassist-osm'];
        self::assertSame(
            ['fuel-station', 'general-mechanic'],
            LocalTorquePackSeeder::sanitiseCategories(
                $record,
                ['gas-certification', 'fuel-station', 'general-mechanic']
            )
        );
        self::assertSame(
            ['gas-certification'],
            LocalTorquePackSeeder::sanitiseCategories(['source' => 'other'], ['gas-certification'])
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
