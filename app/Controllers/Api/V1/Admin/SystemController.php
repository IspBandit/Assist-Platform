<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiScopes;

/**
 * System endpoints for the versioned Admin API.
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
        $mfaRequired = (bool) Config::get('admin_api.mfa_required', false);

        return AdminApiEnvelope::data([
            'api_version' => 'v1',
            'enabled' => (bool) Config::get('admin_api.enabled', false),
            'restricted' => (bool) Config::get('admin_api.restricted', true),
            'mfa_required' => $mfaRequired,
            'mfa_enforced' => $mfaRequired,
            'authentication' => [
                'human_password' => 'active',
                'refresh_tokens' => 'active',
                'sessions' => 'active',
                'service_accounts' => 'active',
                'service_token' => 'active',
                'mfa_verify' => 'active',
                'mfa_enroll' => 'active',
            ],
            'scopes' => AdminApiScopes::catalog(),
            'resources' => [
                'providers' => 'read_write',
                'stays' => 'read_write',
                'facilities' => 'read_write',
                'claims' => 'read_write',
                'corrections' => 'read_write',
                'duplicates' => 'read_write',
                'datasets' => 'read_write',
                'ai_usage' => 'read',
                'sync_conflicts' => 'read_write',
                'drafts' => 'read_write',
                'imports' => 'read_write',
                'recycle_bin' => 'read_write',
                'audit' => 'read',
                'search_gaps' => 'read',
                'search_analytics' => 'read',
                'overview' => 'read',
                'website_insights' => 'read',
            ],
            'limits' => [
                'max_batch_size' => (int) Config::get('admin_api.max_batch_size', 100),
                'recycle_retention_days' => (int) Config::get('admin_api.recycle_retention_days', 90),
                'access_token_ttl_seconds' => (int) Config::get('admin_api.access_token_ttl_seconds', 900),
                'refresh_token_ttl_seconds' => (int) Config::get('admin_api.refresh_token_ttl_seconds', 604800),
                'service_token_ttl_seconds' => (int) Config::get('admin_api.service_token_ttl_seconds', 3600),
                'mfa_challenge_ttl_seconds' => (int) Config::get('admin_api.mfa_challenge_ttl_seconds', 300),
            ],
            'notes' => [
                'OPS-010 ships TOTP enrollment and verify for human Admin API sessions.',
                'Enroll MFA while ADMIN_API_MFA_REQUIRED=false, then enable the flag for production.',
                'Login returns an mfa_token (scope mfa:verify) when MFA is enforced; complete with POST /auth/mfa/verify.',
                'Service accounts are not subject to human MFA.',
                'Brand scope is resolved from host/deployment context, not client brand_id.',
                'Restricted mode defaults on; empty ADMIN_API_ALLOWED_USER_IDS limits to super-administrator.',
                'Search gaps aggregate zero-result rows from provider_searches; empty when analytics is off.',
                'Standalone traveller facilities are exposed as /facilities (ADR 0019).',
                'Claims review covers caravan park claims and provider invite tokens.',
                'Duplicate merge requires duplicates:merge scope and a human session.',
                'GET /overview rolls up health, website KPIs, review queues and AI cost for RIC.',
                'Website visitors exclude bot/unknown page views; filtered bot views are labelled separately.',
            ],
        ]);
    }
}
