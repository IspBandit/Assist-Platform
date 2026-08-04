<?php

declare(strict_types=1);

namespace App\Services\Api;

/**
 * Narrow gateway for facility import-candidate review (Option B Increment H.1).
 */
interface FacilityImportCandidateReviewGateway
{
    public function reviewCandidate(
        int $candidateId,
        string $action,
        ?int $reviewerId = null,
        ?string $notes = null
    ): void;
}
