<?php

declare(strict_types=1);

/**
 * Versioned Admin API routes (CORE-011).
 *
 * Increment 1: system skeletons + envelopes.
 * Increment 2: human authentication sessions.
 * Increment 3: service accounts + machine tokens + scopes.
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
        });
    });
};
