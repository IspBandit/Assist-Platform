<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\DataSources\BulkReviewPolicy;
use PHPUnit\Framework\TestCase;

final class BulkReviewPolicyTest extends TestCase
{
    public function testEligibleApprovalRequiresEvidenceAndNoDuplicate(): void
    {
        $candidate = $this->eligibleCandidate();
        self::assertSame([], (new BulkReviewPolicy())->approvalProblems($candidate));

        $candidate['duplicate_provider_id'] = 41;
        self::assertContains('possible duplicate', (new BulkReviewPolicy())->approvalProblems($candidate));
    }

    public function testExactMergeRequiresStrongIdentityMatchAndUnclaimedTarget(): void
    {
        $candidate = $this->eligibleCandidate();
        $candidate = array_replace($candidate, [
            'duplicate_provider_id' => 41,
            'duplicate_score' => 90,
            'duplicate_reasons_json' => json_encode(['same normalised name', 'same phone']),
            'target_is_unclaimed' => 1,
            'target_has_brand_listing' => 1,
        ]);
        self::assertSame([], (new BulkReviewPolicy())->exactMergeProblems($candidate));
        self::assertSame([], (new BulkReviewPolicy())->exactIdentityProblems($candidate));
        self::assertSame([], (new BulkReviewPolicy())->automaticLinkProblems($candidate));

        $candidate['target_is_unclaimed'] = 0;
        self::assertContains('target provider is claimed', (new BulkReviewPolicy())->exactMergeProblems($candidate));

        $candidate['target_is_unclaimed'] = 1;
        $candidate['target_has_brand_listing'] = 0;
        self::assertContains('target is not yet listed in this workspace', (new BulkReviewPolicy())->automaticLinkProblems($candidate));
    }

    public function testGoogleEvidenceAndFuzzyDuplicatesAreRejected(): void
    {
        $candidate = $this->eligibleCandidate();
        $candidate = array_replace($candidate, [
            'duplicate_provider_id' => 41,
            'duplicate_score' => 100,
            'duplicate_reasons_json' => json_encode(['similar business name', 'same phone', 'same website']),
            'target_is_unclaimed' => 1,
        ]);
        $candidate['evidence_url'] = 'https://maps.google.com/example';
        $problems = (new BulkReviewPolicy())->exactMergeProblems($candidate);
        self::assertContains('valid independent evidence URL missing', $problems);
        self::assertContains('business name is not an exact match', $problems);
    }

    /** @return array<string,mixed> */
    private function eligibleCandidate(): array
    {
        return [
            'review_status' => 'pending',
            'category_id' => 12,
            'evidence_status' => 'confirmed',
            'evidence_url' => 'https://example.test/services',
            'duplicate_provider_id' => null,
        ];
    }
}
