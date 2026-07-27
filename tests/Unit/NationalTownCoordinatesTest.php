<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class NationalTownCoordinatesTest extends TestCase
{
    public function testBluffAndEmuParkUseAuthoritativeDistinctCoordinates(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/towns_national.json';
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $towns = [];
        foreach ($data['towns'] as $town) {
            if ($town['state'] === 'QLD' && in_array($town['name'], ['Bluff', 'Emu Park'], true)) {
                $towns[$town['name']] = $town;
            }
        }

        self::assertSame(-23.57972, $towns['Bluff']['lat']);
        self::assertSame(149.07056, $towns['Bluff']['lng']);
        self::assertEqualsWithDelta(-23.2592568, $towns['Emu Park']['lat'], 0.0000001);
        self::assertEqualsWithDelta(150.82384347, $towns['Emu Park']['lng'], 0.0000001);
        self::assertGreaterThan(150.0, $this->distanceKm($towns['Bluff'], $towns['Emu Park']));
    }

    /** @param array<string,mixed> $from @param array<string,mixed> $to */
    private function distanceKm(array $from, array $to): float
    {
        $toRadians = static fn (float $degrees): float => $degrees * M_PI / 180;
        $latitudeDelta = $toRadians((float) $to['lat'] - (float) $from['lat']);
        $longitudeDelta = $toRadians((float) $to['lng'] - (float) $from['lng']);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($toRadians((float) $from['lat'])) * cos($toRadians((float) $to['lat']))
            * sin($longitudeDelta / 2) ** 2;

        return 6371 * 2 * asin(sqrt($a));
    }
}
