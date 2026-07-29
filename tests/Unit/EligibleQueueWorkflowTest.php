<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\DataSources\BulkReviewPolicy;
use PHPUnit\Framework\TestCase;

final class EligibleQueueWorkflowTest extends TestCase
{
    public function testQueueChoosesSafeDuplicateBeforePublication(): void
    {
        $policy = new BulkReviewPolicy();
        $duplicate = $this->eligible();
        $duplicate['duplicate_provider_id'] = 42;
        $duplicate['duplicate_score'] = 70;
        $duplicate['duplicate_reasons_json'] = json_encode(['similar business name','same phone']);
        $duplicate['target_is_unclaimed'] = 1;
        $duplicate['target_has_brand_listing'] = 1;

        self::assertSame('merge',$policy->eligibleQueueAction($duplicate)['action']);
        self::assertSame('approve',$policy->eligibleQueueAction($this->eligible())['action']);
    }

    public function testQueueBlocksClaimedTargetsAndUnconfirmedPublication(): void
    {
        $policy = new BulkReviewPolicy();
        $duplicate = $this->eligible();
        $duplicate['duplicate_provider_id'] = 42;
        $duplicate['duplicate_score'] = 90;
        $duplicate['duplicate_reasons_json'] = json_encode(['same normalised name','same website']);
        $duplicate['target_is_unclaimed'] = 0;
        $duplicate['target_has_brand_listing'] = 1;
        self::assertSame('blocked',$policy->eligibleQueueAction($duplicate)['action']);
        self::assertContains('target provider is claimed',$policy->eligibleQueueAction($duplicate)['problems']);

        $unconfirmed = $this->eligible();
        $unconfirmed['evidence_status'] = 'required';
        self::assertSame('blocked',$policy->eligibleQueueAction($unconfirmed)['action']);
        self::assertContains('independent evidence not confirmed',$policy->eligibleQueueAction($unconfirmed)['problems']);
    }

    public function testRouteControllerAndAutoContinuationAreWired(): void
    {
        $root = dirname(__DIR__,2);
        self::assertStringContainsString("/data-sources/review/process-eligible",(string)file_get_contents($root.'/routes/admin.php'));
        self::assertStringContainsString('function processEligibleQueue',(string)file_get_contents($root.'/app/Controllers/Admin/DataSourcesController.php'));
        $view = (string)file_get_contents($root.'/app/Views/admin/data-sources/queue.php');
        self::assertStringContainsString('Process every eligible filtered record',$view);
        self::assertStringContainsString('data-auto-submit="1200"',$view);
    }

    public function testFinalMutationsRecheckPendingAndUnclaimedState(): void
    {
        $root = dirname(__DIR__,2);
        $service = (string)file_get_contents($root.'/app/Services/DataSourceService.php');
        self::assertStringContainsString("is_unclaimed=1 FOR UPDATE",$service);
        self::assertStringContainsString("review_status='pending' FOR UPDATE",$service);
        self::assertStringContainsString('p.is_unclaimed=1',$service);

        $import = (string)file_get_contents($root.'/app/Services/NationalRouteImportService.php');
        self::assertStringContainsString('connector_id=? AND brand_id=? AND external_id=?',$import);
        self::assertStringContainsString('p.is_unclaimed=1',$import);
    }

    /** @return array<string,mixed> */
    private function eligible(): array
    {
        return [
            'review_status'=>'pending','category_id'=>12,'evidence_status'=>'confirmed',
            'evidence_url'=>'https://provider.example/services','duplicate_provider_id'=>null,
        ];
    }
}
