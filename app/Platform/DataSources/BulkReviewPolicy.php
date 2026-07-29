<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

final class BulkReviewPolicy
{
    public const STRONG_DUPLICATE_SCORE = 70;

    /** @return array<int,string> */
    public function approvalProblems(array $candidate): array
    {
        $problems = $this->publicationProblems($candidate);
        if (!empty($candidate['duplicate_provider_id'])) {
            $problems[] = 'possible duplicate';
        }
        return $problems;
    }

    /** @return array<int,string> */
    public function exactMergeProblems(array $candidate): array
    {
        $problems = $this->publicationProblems($candidate);
        return array_values(array_unique([...$problems, ...$this->exactIdentityProblems($candidate)]));
    }

    /** @return array<int,string> */
    public function exactIdentityProblems(array $candidate): array
    {
        $problems = [];
        if (empty($candidate['duplicate_provider_id'])) {
            $problems[] = 'no merge target';
        }
        if ((int) ($candidate['duplicate_score'] ?? 0) < self::STRONG_DUPLICATE_SCORE) {
            $problems[] = 'duplicate confidence below ' . self::STRONG_DUPLICATE_SCORE . '%';
        }
        $reasons = json_decode((string) ($candidate['duplicate_reasons_json'] ?? '[]'), true);
        $reasons = is_array($reasons) ? array_map('strval', $reasons) : [];
        if (!in_array('same normalised name', $reasons, true) && !in_array('similar business name', $reasons, true)) {
            $problems[] = 'business name is not a strong match';
        }
        if (!in_array('same phone', $reasons, true) && !in_array('same website', $reasons, true)) {
            $problems[] = 'phone or website is not an exact match';
        }
        if ((int) ($candidate['target_is_unclaimed'] ?? 0) !== 1) {
            $problems[] = 'target provider is claimed';
        }
        return array_values(array_unique($problems));
    }

    /** @return array<int,string> */
    public function automaticLinkProblems(array $candidate): array
    {
        $problems = $this->exactIdentityProblems($candidate);
        if ((int) ($candidate['target_has_brand_listing'] ?? 0) !== 1) {
            $problems[] = 'target is not yet listed in this workspace';
        }
        return array_values(array_unique($problems));
    }

    /** @return array{action:string,problems:array<int,string>} */
    public function eligibleQueueAction(array $candidate): array
    {
        if (!empty($candidate['duplicate_provider_id'])) {
            $problems = $this->automaticLinkProblems($candidate);
            return ['action'=>$problems === [] ? 'merge' : 'blocked','problems'=>$problems];
        }
        $problems = $this->approvalProblems($candidate);
        return ['action'=>$problems === [] ? 'approve' : 'blocked','problems'=>$problems];
    }

    /** @return array<int,string> */
    private function publicationProblems(array $candidate): array
    {
        $problems = [];
        if ((string) ($candidate['review_status'] ?? '') !== 'pending') {
            $problems[] = 'not pending';
        }
        if (empty($candidate['category_id'])) {
            $problems[] = 'service category not confirmed';
        }
        if (!in_array((string) ($candidate['evidence_status'] ?? ''), ['confirmed', 'claimed'], true)) {
            $problems[] = 'independent evidence not confirmed';
        }
        if (!$this->validEvidenceUrl((string) ($candidate['evidence_url'] ?? ''))) {
            $problems[] = 'valid independent evidence URL missing';
        }
        return $problems;
    }

    private function validEvidenceUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && !str_contains($host, 'google.') && !str_contains($host, 'goo.gl');
    }
}
