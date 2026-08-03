<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Staging\DatasetTrustPolicy;
use App\Platform\AiSearch\Staging\DraftCandidateService;
use App\Platform\DataSources\Connectors\OsmOfflineSeedConnector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DraftCandidateServiceTest extends TestCase
{
    public function testProhibitedTrustPolicyRejected(): void
    {
        $svc = new DraftCandidateService();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Trust policy does not allow staging');
        $svc->stageHits(1, 'osm_offline_seed', [
            ['external_id' => 'x', 'business_name' => 'Test'],
        ], DatasetTrustPolicy::PROHIBITED);
    }

    public function testAskBlockedConnectorRejected(): void
    {
        $svc = new DraftCandidateService();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('blocked from Ask VanAssist staging');
        $svc->stageHits(1, 'google_places', [
            ['external_id' => 'x', 'business_name' => 'Test'],
        ], DatasetTrustPolicy::TRUSTED_REVIEW);
    }

    public function testEmptyHitsReturnZerosWithoutDatabase(): void
    {
        $svc = new DraftCandidateService();
        $out = $svc->stageHits(1, 'osm_offline_seed', [], DatasetTrustPolicy::TRUSTED_REVIEW);
        self::assertSame(['staged' => 0, 'skipped' => 0, 'job_id' => null], $out);
    }

    public function testTrustPolicyHelpers(): void
    {
        self::assertTrue(DatasetTrustPolicy::mayStage(DatasetTrustPolicy::TRUSTED_REVIEW));
        self::assertFalse(DatasetTrustPolicy::mayStage(DatasetTrustPolicy::PROHIBITED));
        self::assertFalse(DatasetTrustPolicy::mayAutoPublish(DatasetTrustPolicy::TRUSTED_AUTOMATIC));
        self::assertTrue(DatasetTrustPolicy::isAskBlockedConnector('google_places'));
        self::assertFalse(DatasetTrustPolicy::isAskBlockedConnector(OsmOfflineSeedConnector::KEY));
    }
}
