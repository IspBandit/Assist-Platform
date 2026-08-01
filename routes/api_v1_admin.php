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
                $router->get('/imports/{id}', 'Api\\V1\\Admin\\ImportController@show', 'api.v1.admin.imports.show');
            });

            $router->group([
                'middleware' => ['admin_api_scope:imports:write'],
            ], static function (\App\Core\Router $router): void {
                $router->post('/imports', 'Api\\V1\\Admin\\ImportController@store', 'api.v1.admin.imports.store');
                $router->post('/imports/{id}/validate', 'Api\\V1\\Admin\\ImportController@validateJob', 'api.v1.admin.imports.validate');
                $router->post('/imports/{id}/stage', 'Api\\V1\\Admin\\ImportController@stage', 'api.v1.admin.imports.stage');
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
            });
        });
    });
};
