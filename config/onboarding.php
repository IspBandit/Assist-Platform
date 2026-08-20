<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * Public provider onboarding controls (VAN-010 / Option B Increment H).
 */
return [
    // Search-before-create on /for-providers/register. Safe fallback when tables are missing.
    'claim_first_enabled' => (bool) Env::get('CLAIM_FIRST_ONBOARDING', true),
    // Minimum duplicate score (0–100) before a submission is held instead of prospect-only.
    'duplicate_hold_threshold' => max(40, min(100, (int) Env::get('CLAIM_FIRST_DUPLICATE_THRESHOLD', 70))),
];
