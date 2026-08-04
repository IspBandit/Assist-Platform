<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Narrow gateway for provider import-candidate review (Option B Increment H.2 / H.4).
 *
 * Wraps DataSourceService::review. Hold/confirm/bulk remain out of Admin API.
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
