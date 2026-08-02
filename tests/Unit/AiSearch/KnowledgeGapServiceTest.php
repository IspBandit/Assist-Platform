<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use PHPUnit\Framework\TestCase;

final class KnowledgeGapServiceTest extends TestCase
{
    public function testPriorityIncreasesWithClickAndContactSignals(): void
    {
        $svc = new KnowledgeGapService();
        $base = [
            'search_count' => 5,
            'zero_result_count' => 2,
            'weak_result_count' => 1,
            'urgency_urgent_count' => 0,
            'safety_relevant' => false,
            'remote_location' => false,
            'contact_action_count' => 0,
            'click_through_count' => 0,
        ];
        $low = $svc->scorePriority($base);
        $withClicks = $svc->scorePriority(array_merge($base, ['click_through_count' => 5]));
        $withContacts = $svc->scorePriority(array_merge($base, ['contact_action_count' => 5]));
        self::assertGreaterThan($low, $withClicks);
        self::assertGreaterThan($low, $withContacts);
    }

    public function testClassifyNoneForUnknownIntent(): void
    {
        $svc = new KnowledgeGapService();
        $intent = new Intent(
            intentType: Intent::TYPE_UNKNOWN,
            providerCategoryKeys: [],
            stayTypeKeys: [],
            facilityTypeKeys: [],
            locationText: null,
            useCurrentLocation: false,
            radiusKm: 25,
            urgency: 'normal',
            adapterKeys: [],
            confidence: 0.0,
            clarificationRequired: true,
            clarificationReason: 'x',
        );
        self::assertSame(KnowledgeGapService::QUALITY_NONE, $svc->classifyQuality($intent, 5));
    }

    public function testClassifyWeakAndAdequate(): void
    {
        $svc = new KnowledgeGapService();
        $intent = new Intent(
            intentType: Intent::TYPE_PROVIDER,
            providerCategoryKeys: ['dump-points'],
            stayTypeKeys: [],
            facilityTypeKeys: ['dump_point'],
            locationText: 'Batehaven',
            useCurrentLocation: false,
            radiusKm: 25,
            urgency: 'normal',
            adapterKeys: ['providers'],
            confidence: 0.9,
            clarificationRequired: false,
            clarificationReason: null,
        );
        self::assertSame(KnowledgeGapService::QUALITY_NONE, $svc->classifyQuality($intent, 0));
        self::assertSame(KnowledgeGapService::QUALITY_WEAK, $svc->classifyQuality($intent, 2));
        self::assertSame(KnowledgeGapService::QUALITY_ADEQUATE, $svc->classifyQuality($intent, 3));
    }

    public function testGapKeyGroupsRepeatedSearches(): void
    {
        $svc = new KnowledgeGapService();
        $tax = ['providers' => ['dump-points'], 'stays' => [], 'facilities' => ['dump_point']];
        $a = $svc->buildGapKey('vanassist', 'dump point near batehaven', 'find_provider', 12, $tax, 25);
        $b = $svc->buildGapKey('vanassist', 'dump point near batehaven', 'find_provider', 12, $tax, 25);
        $c = $svc->buildGapKey('towsmart', 'dump point near batehaven', 'find_provider', 12, $tax, 25);
        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertSame(64, strlen($a));
    }

    public function testPriorityIncreasesWithFrequencyAndSafety(): void
    {
        $svc = new KnowledgeGapService();
        $low = $svc->scorePriority([
            'search_count' => 1,
            'zero_result_count' => 0,
            'weak_result_count' => 1,
            'urgency_urgent_count' => 0,
            'safety_relevant' => false,
            'remote_location' => false,
            'contact_action_count' => 0,
            'click_through_count' => 0,
        ]);
        $high = $svc->scorePriority([
            'search_count' => 20,
            'zero_result_count' => 18,
            'weak_result_count' => 2,
            'urgency_urgent_count' => 3,
            'safety_relevant' => true,
            'remote_location' => true,
            'contact_action_count' => 4,
            'click_through_count' => 2,
        ]);
        self::assertGreaterThan($low, $high);
        self::assertLessThanOrEqual(100, $high);
    }

    public function testRadiusBucketStable(): void
    {
        $svc = new KnowledgeGapService();
        self::assertSame(25, $svc->radiusBucket(1));
        self::assertSame(25, $svc->radiusBucket(25));
        self::assertSame(50, $svc->radiusBucket(26));
        self::assertSame(50, $svc->radiusBucket(50));
    }

    public function testMigrationDefinesGapTables(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/105_assist_knowledge_gaps.sql');
        self::assertNotFalse($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS knowledge_gaps', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS knowledge_gap_events', $sql);
        self::assertStringContainsString('gap_key', $sql);
        self::assertStringContainsString('priority_score', $sql);
    }

    public function testAdminGapRoutesRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3) . '/routes/admin.php');
        self::assertNotFalse($routes);
        self::assertStringContainsString('/ai-search/gaps', $routes);
        self::assertStringContainsString('AiSearchAdminController@gaps', $routes);
        self::assertStringContainsString('AiSearchAdminController@exportGaps', $routes);
    }

    public function testToSearchGapItemsMapsLockedContractFields(): void
    {
        $svc = new KnowledgeGapService();
        $items = $svc->toSearchGapItems([
            [
                'id' => 42,
                'brand_key' => 'vanassist',
                'normalised_query' => 'public toilet near me',
                'original_query_sample' => 'Public toilets near me',
                'intent_type' => Intent::TYPE_FACILITY,
                'location_text' => null,
                'town_id' => 9,
                'local_result_count_last' => 0,
                'search_count' => 4,
                'first_seen_at' => '2026-08-01 10:00:00',
                'last_seen_at' => '2026-08-02 08:00:00',
                'priority_score' => 88,
                'result_quality' => KnowledgeGapService::QUALITY_NONE,
                'resolution_status' => KnowledgeGapService::STATUS_OPEN,
                'safety_relevant' => 1,
            ],
        ]);
        self::assertCount(1, $items);
        self::assertSame('public toilet near me', $items[0]['query_text']);
        self::assertSame(0, $items[0]['result_count']);
        self::assertSame(4, $items[0]['search_count']);
        self::assertSame(88, $items[0]['urgency_score']);
        self::assertSame(9, $items[0]['town_id']);
        self::assertNull($items[0]['category_id']);
        self::assertSame('knowledge_gaps', $items[0]['meta']['source']);
        self::assertSame(42, $items[0]['meta']['gap_id']);
    }
}
