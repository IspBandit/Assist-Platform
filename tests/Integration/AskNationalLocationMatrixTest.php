<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Town;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AskNationalLocationMatrixTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 with a disposable database');
        }
    }

    /** @return array<string,array{string,string}> */
    public static function qualifiedLocations(): array
    {
        return [
            'Sydney' => ['Sydney NSW', 'NSW'],
            'Melbourne' => ['Melbourne VIC', 'VIC'],
            'Brisbane' => ['Brisbane QLD', 'QLD'],
            'Perth' => ['Perth WA', 'WA'],
            'Adelaide' => ['Adelaide SA', 'SA'],
            'Hobart' => ['Hobart TAS', 'TAS'],
            'Darwin' => ['Darwin NT', 'NT'],
            'Canberra' => ['Canberra ACT', 'ACT'],
            'Batehaven' => ['Batehaven NSW', 'NSW'],
            'Dubbo' => ['Dubbo NSW', 'NSW'],
            'Broken Hill' => ['Broken Hill NSW', 'NSW'],
            'Ballarat' => ['Ballarat VIC', 'VIC'],
            'Mildura' => ['Mildura VIC', 'VIC'],
            'Mallacoota' => ['Mallacoota VIC', 'VIC'],
            'Emerald' => ['Emerald QLD', 'QLD'],
            'Roma' => ['Roma QLD', 'QLD'],
            'Longreach' => ['Longreach QLD', 'QLD'],
            'Birdsville' => ['Birdsville QLD', 'QLD'],
            'Cairns' => ['Cairns QLD', 'QLD'],
            'Port Augusta' => ['Port Augusta SA', 'SA'],
            'Coober Pedy' => ['Coober Pedy SA', 'SA'],
            'Mount Gambier' => ['Mount Gambier SA', 'SA'],
            'Albany' => ['Albany WA', 'WA'],
            'Broome' => ['Broome WA', 'WA'],
            'Kununurra' => ['Kununurra WA', 'WA'],
            'Kalgoorlie' => ['Kalgoorlie WA', 'WA'],
            'Launceston' => ['Launceston TAS', 'TAS'],
            'Strahan' => ['Strahan TAS', 'TAS'],
            'Queenstown Tasmania' => ['Queenstown TAS', 'TAS'],
            'Alice Springs' => ['Alice Springs NT', 'NT'],
            'Katherine' => ['Katherine NT', 'NT'],
            'Tennant Creek' => ['Tennant Creek NT', 'NT'],
        ];
    }

    #[DataProvider('qualifiedLocations')]
    public function testQualifiedNationalLocationResolvesToRequestedState(string $query, string $state): void
    {
        $matches = Town::searchActive($query, 5);

        self::assertNotEmpty($matches, $query);
        self::assertSame($state, $matches[0]['state_abbr'], $query);
        self::assertIsNumeric($matches[0]['latitude'], $query);
        self::assertIsNumeric($matches[0]['longitude'], $query);
    }

    /** @return array<string,array{string,string,string}> */
    public static function ambiguousLocations(): array
    {
        return [
            'Richmond NSW' => ['Richmond NSW', 'Richmond', 'NSW'],
            'Richmond VIC' => ['Richmond VIC', 'Richmond', 'VIC'],
            'Richmond TAS' => ['Richmond TAS', 'Richmond', 'TAS'],
            'Springfield QLD' => ['Springfield QLD', 'Springfield', 'QLD'],
            'Perth Tasmania' => ['Perth TAS', 'Perth', 'TAS'],
        ];
    }

    #[DataProvider('ambiguousLocations')]
    public function testStateQualifierPreventsSilentWrongTown(
        string $query,
        string $name,
        string $state,
    ): void {
        $matches = Town::searchActive($query, 5);

        self::assertNotEmpty($matches, $query);
        self::assertSame($name, $matches[0]['name'], $query);
        self::assertSame($state, $matches[0]['state_abbr'], $query);
    }

    /** @return array<string,array{string,string}> */
    public static function safeTypos(): array
    {
        return [
            'Batemans Bay' => ['Batemns Bay NSW', 'Batemans Bay'],
            'Batehaven' => ['Batehavn NSW', 'Batehaven'],
            'Emerald' => ['Emrald QLD', 'Emerald'],
            'Toowoomba' => ['Toowomba QLD', 'Toowoomba'],
        ];
    }

    #[DataProvider('safeTypos')]
    public function testUniqueMinorTypoCanBeCorrectedSafely(string $query, string $expected): void
    {
        $matches = Town::searchActiveFuzzy($query, 1);

        self::assertCount(1, $matches, $query);
        self::assertSame($expected, $matches[0]['name'], $query);
    }
}
