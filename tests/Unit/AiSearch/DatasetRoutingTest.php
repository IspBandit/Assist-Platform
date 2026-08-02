<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\DatasetSearchAdapter;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Staging\DatasetTrustPolicy;
use PHPUnit\Framework\TestCase;

final class DatasetRoutingTest extends TestCase
{
    public function testProvenanceLabelsCoverOrigins(): void
    {
        self::assertStringContainsString('Pending review', ResultProvenance::label(ResultProvenance::ORIGIN_STAGED));
        self::assertStringContainsString('VanAssist', ResultProvenance::label(ResultProvenance::ORIGIN_CANONICAL));
        $row = ResultProvenance::annotate(['business_name' => 'Test'], ResultProvenance::ORIGIN_STAGED, 'osm_seed', 'ext-1', null, 'OSM', 0.8);
        self::assertTrue($row['assist_pending_review']);
        self::assertSame(0.8, $row['assist_confidence']);
        self::assertSame('osm_seed', $row['assist_source']);
    }

    public function testTrustPolicyBlocksPaidAskConnectorsAndAutoPublish(): void
    {
        self::assertTrue(DatasetTrustPolicy::isAskBlockedConnector('google_places'));
        self::assertFalse(DatasetTrustPolicy::isAskBlockedConnector('qld_coverage_offline'));
        self::assertTrue(DatasetTrustPolicy::mayStage(DatasetTrustPolicy::TRUSTED_REVIEW));
        self::assertFalse(DatasetTrustPolicy::mayStage(DatasetTrustPolicy::PROHIBITED));
        self::assertFalse(DatasetTrustPolicy::mayAutoPublish(DatasetTrustPolicy::TRUSTED_AUTOMATIC));
    }

    public function testMapCandidateRowAddsStagedProvenance(): void
    {
        $adapter = new DatasetSearchAdapter();
        $card = $adapter->mapCandidateRow([
            'id' => 42,
            'business_name' => 'Demo Dump Point',
            'formatted_address' => '1 Main St, Batemans Bay NSW',
            'external_id' => 'osm-123',
            'connector_key' => 'qld_coverage_offline',
            'connector_name' => 'QLD coverage offline',
            'confidence' => 85,
            'review_status' => 'pending',
            'latitude' => -35.7,
            'longitude' => 150.2,
        ]);
        self::assertNotNull($card);
        self::assertSame(ResultProvenance::ORIGIN_STAGED, $card['assist_origin']);
        self::assertTrue($card['assist_pending_review']);
        self::assertSame('osm-123', $card['assist_source_record_id']);
        self::assertSame(0.85, $card['assist_confidence']);
    }

    public function testAggregatorDedupesExternalsAgainstCanonicalProviders(): void
    {
        $agg = new ResultAggregator();
        $result = $agg->aggregate(
            [
                [
                    'id' => 10,
                    'business_name' => 'Canonical Co',
                    'is_inferred' => 0,
                ],
            ],
            [],
            [
                [
                    'id' => 99,
                    'business_name' => 'Duplicate Candidate',
                    'duplicate_provider_id' => 10,
                    'assist_origin' => ResultProvenance::ORIGIN_STAGED,
                    'assist_source' => 'offline',
                    'assist_source_record_id' => 'x1',
                ],
                [
                    'id' => 100,
                    'business_name' => 'Fresh Candidate',
                    'duplicate_provider_id' => null,
                    'assist_origin' => ResultProvenance::ORIGIN_STAGED,
                    'assist_source' => 'offline',
                    'assist_source_record_id' => 'x2',
                ],
            ]
        );

        self::assertCount(1, $result['providers']);
        self::assertCount(1, $result['externals']);
        self::assertSame([], $result['facilities']);
        self::assertSame('Fresh Candidate', $result['externals'][0]['business_name']);
        self::assertSame('VanAssist listing', $result['providers'][0]['assist_provenance_label']);
    }
}
