<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Knowledge\SearchGapDualSource;
use PHPUnit\Framework\TestCase;

final class SearchGapDualSourceTest extends TestCase
{
    public function testMergeStampsSourcesAndSortsByUrgency(): void
    {
        $merger = new SearchGapDualSource();
        $result = $merger->merge(
            [
                [
                    'query_text' => 'plumber in Ballarat',
                    'location_text' => 'Ballarat, VIC',
                    'result_count' => 0,
                    'search_count' => 12,
                    'first_seen' => '2026-07-01 10:00:00',
                    'last_seen' => '2026-08-01 10:00:00',
                    'intent' => 'plumbing',
                    'urgency_score' => 18.0,
                    'town_id' => 1,
                    'category_id' => 2,
                ],
            ],
            [
                [
                    'query_text' => 'public toilet near me',
                    'location_text' => null,
                    'result_count' => 0,
                    'search_count' => 4,
                    'first_seen' => '2026-08-01 10:00:00',
                    'last_seen' => '2026-08-02 08:00:00',
                    'intent' => 'facility',
                    'urgency_score' => 88,
                    'town_id' => 9,
                    'category_id' => null,
                    'meta' => [
                        'source' => SearchGapDualSource::SOURCE_KNOWLEDGE,
                        'gap_id' => 42,
                    ],
                ],
            ],
            50,
            ['brand_key' => 'vanassist', 'from' => '2026-07-01', 'to' => '2026-08-02']
        );

        self::assertSame(SearchGapDualSource::SOURCE_DUAL, $result['meta']['source']);
        self::assertSame(
            [SearchGapDualSource::SOURCE_PROVIDER, SearchGapDualSource::SOURCE_KNOWLEDGE],
            $result['meta']['sources']
        );
        self::assertSame(1, $result['meta']['provider_searches_count']);
        self::assertSame(1, $result['meta']['knowledge_gaps_count']);
        self::assertSame(2, $result['meta']['count']);
        self::assertFalse($result['meta']['sparse']);
        self::assertSame('vanassist', $result['meta']['brand_key']);

        self::assertSame('public toilet near me', $result['items'][0]['query_text']);
        self::assertSame(SearchGapDualSource::SOURCE_KNOWLEDGE, $result['items'][0]['meta']['source']);
        self::assertSame(SearchGapDualSource::SOURCE_PROVIDER, $result['items'][1]['meta']['source']);
    }

    public function testMergeRespectsLimitAndMarksTruncated(): void
    {
        $merger = new SearchGapDualSource();
        $provider = [];
        for ($i = 0; $i < 3; $i++) {
            $provider[] = [
                'query_text' => 'q' . $i,
                'result_count' => 0,
                'search_count' => $i + 1,
                'first_seen' => '2026-08-01',
                'last_seen' => '2026-08-0' . ($i + 1),
                'urgency_score' => $i,
            ];
        }
        $result = $merger->merge($provider, [], 2);

        self::assertCount(2, $result['items']);
        self::assertTrue($result['meta']['truncated']);
        self::assertSame(2, $result['meta']['limit']);
    }

    public function testFilterByDateWindow(): void
    {
        $merger = new SearchGapDualSource();
        $items = [
            ['query_text' => 'a', 'result_count' => 0, 'search_count' => 1, 'first_seen' => '2026-07-01', 'last_seen' => '2026-07-15 12:00:00'],
            ['query_text' => 'b', 'result_count' => 0, 'search_count' => 1, 'first_seen' => '2026-08-01', 'last_seen' => '2026-08-02 08:00:00'],
        ];
        $filtered = $merger->filterByDateWindow($items, '2026-08-01', '2026-08-31');
        self::assertCount(1, $filtered);
        self::assertSame('b', $filtered[0]['query_text']);
    }

    public function testEmptyMergeIsSparseDual(): void
    {
        $merger = new SearchGapDualSource();
        $result = $merger->merge([], []);
        self::assertSame([], $result['items']);
        self::assertTrue($result['meta']['sparse']);
        self::assertSame(SearchGapDualSource::SOURCE_DUAL, $result['meta']['source']);
    }
}
