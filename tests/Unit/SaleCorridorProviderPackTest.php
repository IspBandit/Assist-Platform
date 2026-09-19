<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SaleCorridorProviderPackTest extends TestCase
{
    public function testSaleCorridorPackHasRequiredFieldsAndInlandTowns(): void
    {
        $path = base_path('database/seeds/vanassist-sale-corridor/providers.json');
        self::assertFileExists($path);
        $decoded = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($decoded);
        self::assertNotSame([], $decoded);

        $keys = [];
        $towns = [];
        foreach ($decoded as $row) {
            self::assertIsArray($row);
            foreach (['key', 'business_name', 'town', 'state', 'categories', 'source_url', 'source_note'] as $field) {
                self::assertArrayHasKey($field, $row, $field);
            }
            $key = (string) $row['key'];
            self::assertNotSame('', $key);
            self::assertArrayNotHasKey($key, $keys, 'duplicate corridor key');
            $keys[$key] = true;
            self::assertSame('QLD', $row['state']);
            self::assertNotSame([], $row['categories']);
            self::assertTrue(is_numeric($row['latitude'] ?? null));
            self::assertTrue(is_numeric($row['longitude'] ?? null));
            $towns[mb_strtolower((string) $row['town'])] = true;
        }

        self::assertArrayHasKey('charters towers', $towns);
        self::assertArrayHasKey('longreach', $towns);
        self::assertArrayHasKey('emerald', $towns);
        self::assertArrayHasKey('mount isa', $towns);
        self::assertArrayHasKey('roma', $towns);
        self::assertArrayHasKey('charleville', $towns);
        self::assertArrayHasKey('birdsville', $towns);
    }
}
