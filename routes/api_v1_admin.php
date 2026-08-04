<?php

declare(strict_types=1);

/**
 * Versioned Admin API routes (CORE-011).
 *
 * Increment 1: system skeletons + envelopes.
 * Increment 2: human authentication sessions.
 * Increment 3: service accounts + machine tokens + scopes.
 * Increment 4: read-only providers + stays.
 * Increment 5: audited provider/stay writes + lifecycle.
 * Increment 6: recycle bin list/restore/purge.
 * Increment 7: drafts + import package ingest.
 * Increment 8: audit read + search-gap analytics + MFA scaffold.
 * Option B Increments B–G: claims, corrections, duplicates, datasets, AI usage,
 * search analytics, sync conflicts, facilities and import lifecycle extensions.
 * Option B Increment H: read-only facility/provider import-candidate queues.
 * Option B Increment H.1/H.2/H.3: human-only facility/provider review mutations.
 * Option B Increment H.4: human-only provider candidate merge.
 */
return static function (\App\Core\Router $router): void {
    $router->group([
        'prefix' => '/api/v1/admin',
        'middleware' => ['admin_api_enabled', 'admin_api_request'],
    ], static function (\App\Core\Router $router): void {
        $router->get('/health', 'Api\\V1\\Admin\\SystemController@health', 'api.v1.admin.health');
        $router->get('/version', 'Api\\V1\\Admin\\SystemController@version', 'api.v1.admin.version');
        $router->get('/capabilities', 'Api\\V1\\Admin\\SystemController@capabilities', 'api.v1.admin.capabilities');

        $router->post('/auth/login', 'Api\\V1\\Admin\\AuthController@login', 'api.v1.admin.auth.login');
        $router->post('/auth/refresh', 'Api\\V1\\Admin\\AuthController@refresh', 'api.v1.admin.auth.refresh');
        $router->post('/auth/token', 'Api\\V1\\Admin\\AuthController@token', 'api.v1.admin.auth.token');

        $router->group([
            'middleware' => ['admin_api_bearer'],
        ], static function (\App\Core\Router $router): void {
            $router->get('/auth/me', 'Api\\V1\\Admin\\AuthController@me', 'api.v1.admin.auth.me');

            $router->group([
                'middleware' => ['admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/auth/logout', 'Api\\V1\\Admin\\AuthController@logout', 'api.v1.admin.auth.logout');
                $router->get('/auth/sessions', 'Api\\V1\\Admin\\AuthController@sessions', 'api.v1.admin.auth.sessions');
                $router->delete('/auth/sessions/{id}', 'Api\\V1\\Admin\\AuthController@revokeSession', 'api.v1.admin.auth.sessions.revoke');
                $router->post('/auth/mfa/challenge', 'Api\\V1\\Admin\\AuthController@mfaChallenge', 'api.v1.admin.auth.mfa.challenge');
                $router->post('/auth/mfa/enroll/begin', 'Api\\V1\\Admin\\AuthController@mfaEnrollBegin', 'api.v1.admin.auth.mfa.enroll.begin');
                $router->post('/auth/mfa/enroll/confirm', 'Api\\V1\\Admin\\AuthController@mfaEnrollConfirm', 'api.v1.admin.auth.mfa.enroll.confirm');
                $router->post('/auth/mfa/verify', 'Api\\V1\\Admin\\AuthController@mfaVerify', 'api.v1.admin.auth.mfa.verify');
            });

            $router->group([
                'middleware' => ['admin_api_human', 'admin_api_scope:service_accounts:admin'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/service-accounts', 'Api\\V1\\Admin\\ServiceAccountController@index', 'api.v1.admin.service_accounts.index');
                $router->post('/service-accounts', 'Api\\V1\\Admin\\ServiceAccountController@store', 'api.v1.admin.service_accounts.store');
                $router->get('/service-accounts/{id}', 'Api\\V1\\Admin\\ServiceAccountController@show', 'api.v1.admin.service_accounts.show');
                $router->patch('/service-accounts/{id}', 'Api\\V1\\Admin\\ServiceAccountController@update', 'api.v1.admin.service_accounts.update');
                $router->post('/service-accounts/{id}/rotate', 'Api\\V1\\Admin\\ServiceAccountController@rotate', 'api.v1.admin.service_accounts.rotate');
                $router->delete('/service-accounts/{id}', 'Api\\V1\\Admin\\ServiceAccountController@destroy', 'api.v1.admin.service_accounts.destroy');
            });

            $router->group([
                'middleware' => ['admin_api_scope:analytics:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/overview', 'Api\\V1\\Admin\\OverviewController@overview', 'api.v1.admin.overview');
                $router->get('/website-insights', 'Api\\V1\\Admin\\OverviewController@websiteInsights', 'api.v1.admin.website_insights');
            });

            $router->group([
                'middleware' => ['admin_api_scope:providers:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/providers', 'Api\\V1\\Admin\\ProviderController@index', 'api.v1.admin.providers.index');
                $router->get('/providers/{id}', 'Api\\V1\\Admin\\ProviderController@show', 'api.v1.admin.providers.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:providers:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/providers', 'Api\\V1\\Admin\\ProviderController@store', 'api.v1.admin.providers.store');
                $router->patch('/providers/{id}', 'Api\\V1\\Admin\\ProviderController@update', 'api.v1.admin.providers.update');
            });

            $router->group([
                'middleware' => ['admin_api_scope:lifecycle:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/providers/{id}/publish', 'Api\\V1\\Admin\\ProviderController@publish', 'api.v1.admin.providers.publish');
                $router->post('/providers/{id}/unpublish', 'Api\\V1\\Admin\\ProviderController@unpublish', 'api.v1.admin.providers.unpublish');
                $router->post('/providers/{id}/archive', 'Api\\V1\\Admin\\ProviderController@archive', 'api.v1.admin.providers.archive');
                $router->post('/providers/{id}/restore', 'Api\\V1\\Admin\\ProviderController@restore', 'api.v1.admin.providers.restore');
                $router->delete('/providers/{id}', 'Api\\V1\\Admin\\ProviderController@destroy', 'api.v1.admin.providers.destroy');
            });

            $router->group([
                'middleware' => ['admin_api_scope:stays:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/stays', 'Api\\V1\\Admin\\StayController@index', 'api.v1.admin.stays.index');
                $router->get('/stays/{id}', 'Api\\V1\\Admin\\StayController@show', 'api.v1.admin.stays.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:stays:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/stays', 'Api\\V1\\Admin\\StayController@store', 'api.v1.admin.stays.store');
                $router->patch('/stays/{id}', 'Api\\V1\\Admin\\StayController@update', 'api.v1.admin.stays.update');
            });

            $router->group([
                'middleware' => ['admin_api_scope:lifecycle:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/stays/{id}/publish', 'Api\\V1\\Admin\\StayController@publish', 'api.v1.admin.stays.publish');
                $router->post('/stays/{id}/unpublish', 'Api\\V1\\Admin\\StayController@unpublish', 'api.v1.admin.stays.unpublish');
                $router->post('/stays/{id}/archive', 'Api\\V1\\Admin\\StayController@archive', 'api.v1.admin.stays.archive');
                $router->post('/stays/{id}/restore', 'Api\\V1\\Admin\\StayController@restore', 'api.v1.admin.stays.restore');
                $router->delete('/stays/{id}', 'Api\\V1\\Admin\\StayController@destroy', 'api.v1.admin.stays.destroy');
            });

            $router->group([
                'middleware' => ['admin_api_scope:recycle_bin:restore,recycle_bin:purge'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/recycle-bin', 'Api\\V1\\Admin\\RecycleBinController@index', 'api.v1.admin.recycle_bin.index');
                $router->get('/recycle-bin/{entity_type}/{id}', 'Api\\V1\\Admin\\RecycleBinController@show', 'api.v1.admin.recycle_bin.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:recycle_bin:restore'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/recycle-bin/{entity_type}/{id}/restore', 'Api\\V1\\Admin\\RecycleBinController@restore', 'api.v1.admin.recycle_bin.restore');
                $router->post('/recycle-bin/bulk-restore', 'Api\\V1\\Admin\\RecycleBinController@bulkRestore', 'api.v1.admin.recycle_bin.bulk_restore');
            });

            $router->group([
                'middleware' => ['admin_api_scope:recycle_bin:purge'],
            ], static function (\App\Core\Router $router): void {
                $router->delete('/recycle-bin/{entity_type}/{id}/purge', 'Api\\V1\\Admin\\RecycleBinController@purge', 'api.v1.admin.recycle_bin.purge');
                $router->post('/recycle-bin/bulk-purge', 'Api\\V1\\Admin\\RecycleBinController@bulkPurge', 'api.v1.admin.recycle_bin.bulk_purge');
            });

            $router->group([
                'middleware' => ['admin_api_scope:drafts:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/drafts', 'Api\\V1\\Admin\\DraftController@index', 'api.v1.admin.drafts.index');
                $router->get('/drafts/{id}', 'Api\\V1\\Admin\\DraftController@show', 'api.v1.admin.drafts.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:drafts:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/drafts', 'Api\\V1\\Admin\\DraftController@store', 'api.v1.admin.drafts.store');
                $router->patch('/drafts/{id}', 'Api\\V1\\Admin\\DraftController@update', 'api.v1.admin.drafts.update');
            });

            $router->group([
                'middleware' => ['admin_api_scope:drafts:approve'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/drafts/{id}/approve', 'Api\\V1\\Admin\\DraftController@approve', 'api.v1.admin.drafts.approve');
                $router->post('/drafts/{id}/reject', 'Api\\V1\\Admin\\DraftController@reject', 'api.v1.admin.drafts.reject');
            });

            $router->group([
                'middleware' => ['admin_api_scope:imports:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/imports', 'Api\\V1\\Admin\\ImportController@index', 'api.v1.admin.imports.index');
                $router->get('/imports/{id}', 'Api\\V1\\Admin\\ImportController@show', 'api.v1.admin.imports.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:imports:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/imports', 'Api\\V1\\Admin\\ImportController@store', 'api.v1.admin.imports.store');
                $router->post('/imports/{id}/validate', 'Api\\V1\\Admin\\ImportController@validateJob', 'api.v1.admin.imports.validate');
                $router->post('/imports/{id}/stage', 'Api\\V1\\Admin\\ImportController@stage', 'api.v1.admin.imports.stage');
                $router->post('/imports/{id}/publish', 'Api\\V1\\Admin\\ImportController@publish', 'api.v1.admin.imports.publish');
                $router->post('/imports/{id}/cancel', 'Api\\V1\\Admin\\ImportController@cancel', 'api.v1.admin.imports.cancel');
                $router->post('/imports/{id}/retry', 'Api\\V1\\Admin\\ImportController@retry', 'api.v1.admin.imports.retry');
            });

            $router->group([
                'middleware' => ['admin_api_scope:import_candidates:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/facility-import-candidates', 'Api\\V1\\Admin\\FacilityImportCandidateController@index', 'api.v1.admin.facility_import_candidates.index');
                $router->get('/facility-import-candidates/{id}', 'Api\\V1\\Admin\\FacilityImportCandidateController@show', 'api.v1.admin.facility_import_candidates.show');
                $router->get('/provider-import-candidates', 'Api\\V1\\Admin\\ProviderImportCandidateController@index', 'api.v1.admin.provider_import_candidates.index');
                $router->get('/provider-import-candidates/{id}', 'Api\\V1\\Admin\\ProviderImportCandidateController@show', 'api.v1.admin.provider_import_candidates.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:import_candidates:review', 'admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/facility-import-candidates/bulk-approve', 'Api\\V1\\Admin\\FacilityImportCandidateController@bulkApprove', 'api.v1.admin.facility_import_candidates.bulk_approve');
                $router->post('/facility-import-candidates/bulk-reject', 'Api\\V1\\Admin\\FacilityImportCandidateController@bulkReject', 'api.v1.admin.facility_import_candidates.bulk_reject');
                $router->post('/facility-import-candidates/{id}/approve', 'Api\\V1\\Admin\\FacilityImportCandidateController@approve', 'api.v1.admin.facility_import_candidates.approve');
                $router->post('/facility-import-candidates/{id}/reject', 'Api\\V1\\Admin\\FacilityImportCandidateController@reject', 'api.v1.admin.facility_import_candidates.reject');
                $router->post('/provider-import-candidates/{id}/approve', 'Api\\V1\\Admin\\ProviderImportCandidateController@approve', 'api.v1.admin.provider_import_candidates.approve');
                $router->post('/provider-import-candidates/{id}/reject', 'Api\\V1\\Admin\\ProviderImportCandidateController@reject', 'api.v1.admin.provider_import_candidates.reject');
                $router->post('/provider-import-candidates/{id}/merge', 'Api\\V1\\Admin\\ProviderImportCandidateController@merge', 'api.v1.admin.provider_import_candidates.merge');
            });

            $router->group([
                'middleware' => ['admin_api_scope:claims:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/claims', 'Api\\V1\\Admin\\ClaimController@index', 'api.v1.admin.claims.index');
                $router->get('/claims/{id}', 'Api\\V1\\Admin\\ClaimController@show', 'api.v1.admin.claims.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:claims:write', 'admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/claims/{id}/approve', 'Api\\V1\\Admin\\ClaimController@approve', 'api.v1.admin.claims.approve');
                $router->post('/claims/{id}/reject', 'Api\\V1\\Admin\\ClaimController@reject', 'api.v1.admin.claims.reject');
                $router->post('/claims/{id}/request-evidence', 'Api\\V1\\Admin\\ClaimController@requestEvidence', 'api.v1.admin.claims.request_evidence');
            });

            $router->group([
                'middleware' => ['admin_api_scope:corrections:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/corrections', 'Api\\V1\\Admin\\CorrectionController@index', 'api.v1.admin.corrections.index');
                $router->get('/corrections/{id}', 'Api\\V1\\Admin\\CorrectionController@show', 'api.v1.admin.corrections.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:corrections:write', 'admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/corrections/{id}/approve', 'Api\\V1\\Admin\\CorrectionController@approve', 'api.v1.admin.corrections.approve');
                $router->post('/corrections/{id}/reject', 'Api\\V1\\Admin\\CorrectionController@reject', 'api.v1.admin.corrections.reject');
            });

            $router->group([
                'middleware' => ['admin_api_scope:duplicates:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/duplicates', 'Api\\V1\\Admin\\DuplicateController@index', 'api.v1.admin.duplicates.index');
                $router->get('/duplicates/merge-history', 'Api\\V1\\Admin\\DuplicateController@mergeHistory', 'api.v1.admin.duplicates.merge_history');
                $router->get('/duplicates/{id}', 'Api\\V1\\Admin\\DuplicateController@show', 'api.v1.admin.duplicates.show');
                $router->post('/duplicates/check', 'Api\\V1\\Admin\\DuplicateController@check', 'api.v1.admin.duplicates.check');
                $router->post('/duplicates/{id}/defer', 'Api\\V1\\Admin\\DuplicateController@defer', 'api.v1.admin.duplicates.defer');
            });

            $router->group([
                'middleware' => ['admin_api_scope:duplicates:merge', 'admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/duplicates/{id}/merge', 'Api\\V1\\Admin\\DuplicateController@merge', 'api.v1.admin.duplicates.merge');
                $router->post('/duplicates/{id}/not-duplicate', 'Api\\V1\\Admin\\DuplicateController@notDuplicate', 'api.v1.admin.duplicates.not_duplicate');
            });

            $router->group([
                'middleware' => ['admin_api_scope:datasets:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/datasets', 'Api\\V1\\Admin\\DatasetController@index', 'api.v1.admin.datasets.index');
                $router->get('/datasets/{id}', 'Api\\V1\\Admin\\DatasetController@show', 'api.v1.admin.datasets.show');
                $router->get('/datasets/{id}/sync-history', 'Api\\V1\\Admin\\DatasetController@syncHistory', 'api.v1.admin.datasets.sync_history');
            });

            $router->group([
                'middleware' => ['admin_api_scope:datasets:write'],
            ], static function (\App\Core\Router $router): void {
                $router->patch('/datasets/{id}', 'Api\\V1\\Admin\\DatasetController@update', 'api.v1.admin.datasets.update');
                $router->post('/datasets/{id}/sync', 'Api\\V1\\Admin\\DatasetController@sync', 'api.v1.admin.datasets.sync');
            });

            $router->group([
                'middleware' => ['admin_api_scope:ai:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/ai/usage/summary', 'Api\\V1\\Admin\\AiUsageController@summary', 'api.v1.admin.ai.usage.summary');
                $router->get('/ai/usage/costs', 'Api\\V1\\Admin\\AiUsageController@costs', 'api.v1.admin.ai.usage.costs');
                $router->get('/ai/usage/requests', 'Api\\V1\\Admin\\AiUsageController@requests', 'api.v1.admin.ai.usage.requests');
                $router->get('/ai/cache-performance', 'Api\\V1\\Admin\\AiUsageController@cachePerformance', 'api.v1.admin.ai.cache_performance');
            });

            $router->group([
                'middleware' => ['admin_api_scope:flags:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/feature-flags', 'Api\\V1\\Admin\\FeatureFlagController@index', 'api.v1.admin.feature_flags.index');
            });

            $router->group([
                'middleware' => ['admin_api_scope:sync:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/sync-conflicts', 'Api\\V1\\Admin\\SyncConflictController@index', 'api.v1.admin.sync_conflicts.index');
                $router->get('/sync-conflicts/{id}', 'Api\\V1\\Admin\\SyncConflictController@show', 'api.v1.admin.sync_conflicts.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:sync:read', 'admin_api_human'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/sync-conflicts/{id}/resolve', 'Api\\V1\\Admin\\SyncConflictController@resolve', 'api.v1.admin.sync_conflicts.resolve');
            });

            $router->group([
                'middleware' => ['admin_api_scope:facilities:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/facilities', 'Api\\V1\\Admin\\FacilityController@index', 'api.v1.admin.facilities.index');
                $router->get('/facilities/{id}', 'Api\\V1\\Admin\\FacilityController@show', 'api.v1.admin.facilities.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:facilities:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/facilities', 'Api\\V1\\Admin\\FacilityController@store', 'api.v1.admin.facilities.store');
                $router->patch('/facilities/{id}', 'Api\\V1\\Admin\\FacilityController@update', 'api.v1.admin.facilities.update');
            });

            $router->group([
                'middleware' => ['admin_api_scope:lifecycle:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/facilities/{id}/publish', 'Api\\V1\\Admin\\FacilityController@publish', 'api.v1.admin.facilities.publish');
                $router->post('/facilities/{id}/unpublish', 'Api\\V1\\Admin\\FacilityController@unpublish', 'api.v1.admin.facilities.unpublish');
                $router->post('/facilities/{id}/archive', 'Api\\V1\\Admin\\FacilityController@archive', 'api.v1.admin.facilities.archive');
                $router->post('/facilities/{id}/restore', 'Api\\V1\\Admin\\FacilityController@restore', 'api.v1.admin.facilities.restore');
                $router->delete('/facilities/{id}', 'Api\\V1\\Admin\\FacilityController@destroy', 'api.v1.admin.facilities.destroy');
            });

            $router->group([
                'middleware' => ['admin_api_scope:audit:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/audit', 'Api\\V1\\Admin\\AuditController@index', 'api.v1.admin.audit.index');
                $router->get('/audit/{id}', 'Api\\V1\\Admin\\AuditController@show', 'api.v1.admin.audit.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:analytics:read'],
            ], static function (\App\Core\Router $router): void {
                $router->get('/search-gaps', 'Api\\V1\\Admin\\SearchGapController@index', 'api.v1.admin.search_gaps.index');
                $router->get('/searches', 'Api\\V1\\Admin\\SearchAnalyticsController@searches', 'api.v1.admin.searches.index');
                $router->get('/search-intents', 'Api\\V1\\Admin\\SearchAnalyticsController@searchIntents', 'api.v1.admin.search_intents.index');
                $router->get('/search-results-performance', 'Api\\V1\\Admin\\SearchAnalyticsController@searchResultsPerformance', 'api.v1.admin.search_results_performance.index');
            });
        });
    });
};
