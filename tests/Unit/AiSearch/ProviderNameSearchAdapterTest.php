<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\ProviderNameSearchAdapter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProviderNameSearchAdapterTest extends TestCase
{
    public function testBareProviderNameDoesNotBecomeItsOwnLocation(): void
    {
        $adapter = new ProviderNameSearchAdapter();

        self::assertNull($adapter->explicitLocationText('Marshall Batteries', 'Marshall Batteries'));
        self::assertNull($adapter->explicitLocationText('Marshall Batteries near me', 'me'));
        self::assertSame(
            'Brisbane, Queensland',
            $adapter->explicitLocationText(
                'Marshall Batteries near Brisbane, Queensland',
                'Brisbane, Queensland'
            )
        );
    }

    #[DataProvider('candidateCases')]
    public function testItExtractsOnlyTheBusinessName(string $query, ?string $location, ?string $expected): void
    {
        self::assertSame($expected, (new ProviderNameSearchAdapter())->candidate($query, $location));
    }

    /** @return iterable<string,array{string,?string,?string}> */
    public static function candidateCases(): iterable
    {
        yield 'exact name' => ['Marshall Batteries', null, 'Marshall Batteries'];
        yield 'name and explicit town' => ['Marshall Batteries near Brisbane, Queensland', 'Brisbane, Queensland', 'Marshall Batteries'];
        yield 'direct lookup wording' => ['find Battery World Greenslopes', null, 'Battery World Greenslopes'];
        yield 'current location suffix' => ['Marshall Batteries near me', null, 'Marshall Batteries'];
        yield 'radius suffix' => ['Marshall Batteries within 25 km', null, 'Marshall Batteries'];
        yield 'quoted name' => ['"Bob’s Caravan Repairs"', null, 'Bob’s Caravan Repairs'];
        yield 'long legitimate directory name' => [
            'Startamotive - Mechanic Sunbury | Roadworthy Certificate RWC | Tyre Shop & Repair | Gisborne, Woodend, Kyneton, Diggers Rest',
            null,
            'Startamotive - Mechanic Sunbury | Roadworthy Certificate RWC | Tyre Shop & Repair | Gisborne, Woodend, Kyneton, Diggers Rest',
        ];
        yield 'empty' => ['?', null, null];
    }

    public function testOnlyRankZeroRowsAreExactBusinessNames(): void
    {
        $rows = [
            ['business_name' => 'Marshall Batteries', 'name_match_rank' => 0],
            ['business_name' => 'Marshall Battery Centre', 'name_match_rank' => 1],
        ];

        self::assertSame([$rows[0]], (new ProviderNameSearchAdapter())->exactMatches($rows));
    }
}
