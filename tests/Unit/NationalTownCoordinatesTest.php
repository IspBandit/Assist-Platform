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
            if ($town['state'] === 'QLD' && in_array($town['name'], ['Bluff', 'Emerald', 'Emu Park'], true)) {
                $towns[$town['name']] = $town;
            }
        }

        self::assertSame(-23.57972, $towns['Bluff']['lat']);
        self::assertSame(149.07056, $towns['Bluff']['lng']);
        self::assertSame('authoritative', $towns['Bluff']['coordinate_confidence']);
        self::assertSame(-23.52083, $towns['Emerald']['lat']);
        self::assertSame(148.16194, $towns['Emerald']['lng']);
        self::assertEqualsWithDelta(-23.2592568, $towns['Emu Park']['lat'], 0.0000001);
        self::assertEqualsWithDelta(150.82384347, $towns['Emu Park']['lng'], 0.0000001);
        self::assertGreaterThan(150.0, $this->distanceKm($towns['Bluff'], $towns['Emu Park']));
        self::assertGreaterThanOrEqual(3181, (int) $data['coordinate_quality']['qld_authoritative_matches']);
    }

    public function testCapitalCityControlsAndConfidenceTotalsAreVersioned(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/towns_national.json';
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $expected = [
            'ACT:Canberra' => 'act-place-names-register',
            'NSW:Sydney' => 'nsw-geographical-name-register',
            'NT:Darwin' => 'nt-place-names-register',
            'QLD:Brisbane' => 'qld-place-names-gazetteer',
            'SA:Adelaide' => 'sa-state-gazetteer',
            'TAS:Hobart' => 'tas-list-place-names',
            'VIC:Melbourne' => 'vic-vicnames',
            'WA:Perth' => 'ga-composite-gazetteer-wa',
        ];
        foreach ($data['towns'] as $town) {
            $key = $town['state'] . ':' . $town['name'];
            if (isset($expected[$key])) {
                self::assertSame($expected[$key], $town['coordinate_source']);
                self::assertSame('authoritative', $town['coordinate_confidence']);
                unset($expected[$key]);
            }
        }
        self::assertSame([], $expected, 'Every lawful capital-city control must exist.');
        self::assertSame(count($data['towns']), (int) $data['count']);
        self::assertGreaterThan(13000, (int) $data['coordinate_quality']['confidence_counts']['authoritative']);
        self::assertLessThan(2500, (int) $data['coordinate_quality']['confidence_counts']['unverified']);
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
