<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * Versioned Admin API feature flags (CORE-011 / OPS-010).
 * Disabled by default until MFA-gated enablement is approved.
 */
return [
    'enabled' => (bool) Env::get('ADMIN_API_ENABLED', false),
    'restricted' => (bool) Env::get('ADMIN_API_RESTRICTED', true),
    'mfa_required' => (bool) Env::get('ADMIN_API_MFA_REQUIRED', false),
    'mfa_challenge_ttl_seconds' => max(60, min(900, (int) Env::get('ADMIN_API_MFA_CHALLENGE_TTL', 300))),
    'access_token_ttl_seconds' => max(60, (int) Env::get('ADMIN_API_ACCESS_TOKEN_TTL', 900)),
    'refresh_token_ttl_seconds' => max(300, (int) Env::get('ADMIN_API_REFRESH_TOKEN_TTL', 604800)),
    'service_token_ttl_seconds' => max(60, min(3600, (int) Env::get('ADMIN_API_SERVICE_TOKEN_TTL', 3600))),
    'max_batch_size' => max(1, min(500, (int) Env::get('ADMIN_API_MAX_BATCH_SIZE', 100))),
    'recycle_retention_days' => max(1, (int) Env::get('ADMIN_API_RECYCLE_RETENTION_DAYS', 90)),
    // Comma-separated user IDs for restricted mode. Empty → super-administrator only.
    'allowed_user_ids' => array_values(array_filter(array_map(
        static fn (string $id): int => (int) trim($id),
        explode(',', (string) Env::get('ADMIN_API_ALLOWED_USER_IDS', ''))
    ), static fn (int $id): bool => $id > 0)),
];
