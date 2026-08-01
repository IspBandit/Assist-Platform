<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;

/**
 * System endpoints for the versioned Admin API (Increment 1 skeletons).
 */
final class SystemController extends Controller
{
    public function health(Request $request): Response
    {
        return AdminApiEnvelope::data([
            'status' => 'ok',
            'service' => 'assist-platform-admin-api',
            'api_version' => 'v1',
        ]);
    }

    public function version(Request $request): Response
    {
        $release = trim((string) Config::get('app.release', ''));

        return AdminApiEnvelope::data([
            'api_version' => 'v1',
            'product' => 'Assist Platform Enterprise',
            'release' => $release !== '' ? $release : null,
            'php' => PHP_VERSION,
        ]);
    }

    public function capabilities(Request $request): Response
    {
        return AdminApiEnvelope::data([
            'api_version' => 'v1',
            'enabled' => (bool) Config::get('admin_api.enabled', false),
            'restricted' => (bool) Config::get('admin_api.restricted', true),
            'mfa_required' => (bool) Config::get('admin_api.mfa_required', false),
            'mfa_enforced' => false,
            'authentication' => [
                'human_password' => 'planned',
                'refresh_tokens' => 'planned',
                'service_accounts' => 'planned',
                'bearer_placeholder' => 'active',
            ],
            'resources' => [
                'providers' => 'planned',
                'stays' => 'planned',
                'traveller_facilities' => 'planned',
                'drafts' => 'planned',
                'imports' => 'planned',
                'recycle_bin' => 'planned',
                'audit' => 'planned',
                'search_gaps' => 'planned',
            ],
            'limits' => [
                'max_batch_size' => (int) Config::get('admin_api.max_batch_size', 100),
                'recycle_retention_days' => (int) Config::get('admin_api.recycle_retention_days', 90),
                'access_token_ttl_seconds' => (int) Config::get('admin_api.access_token_ttl_seconds', 900),
            ],
            'notes' => [
                'Phase 1 Increment 1 provides routing, envelopes and system skeletons only.',
                'Standalone traveller facilities are not exposed as /facilities (ADR 0016).',
            ],
        ]);
    }
}
