<?php

declare(strict_types=1);

/**
 * Versioned Admin API routes (CORE-011).
 *
 * Increment 1: system skeletons + envelopes.
 * Increment 2: human authentication sessions.
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

        $router->group([
            'middleware' => ['admin_api_bearer'],
        ], static function (\App\Core\Router $router): void {
            $router->post('/auth/logout', 'Api\\V1\\Admin\\AuthController@logout', 'api.v1.admin.auth.logout');
            $router->get('/auth/me', 'Api\\V1\\Admin\\AuthController@me', 'api.v1.admin.auth.me');
            $router->get('/auth/sessions', 'Api\\V1\\Admin\\AuthController@sessions', 'api.v1.admin.auth.sessions');
            $router->delete('/auth/sessions/{id}', 'Api\\V1\\Admin\\AuthController@revokeSession', 'api.v1.admin.auth.sessions.revoke');
        });
    });
};
