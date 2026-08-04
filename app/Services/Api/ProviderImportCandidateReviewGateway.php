<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Narrow gateway for provider import-candidate review (Option B Increment H.2).
 *
 * Wraps DataSourceService::review. Merge/hold/confirm remain out of Admin API.
 */
interface ProviderImportCandidateReviewGateway
{
    public function review(
        int $candidateId,
        int $brandId,
        string $decision,
        ?int $providerId,
        int $userId,
        bool $retentionConfirmed = false,
        ?int $categoryId = null,
        string $evidenceUrl = '',
        string $reviewNotes = ''
    ): int;
}
