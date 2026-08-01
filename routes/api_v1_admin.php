<?php

declare(strict_types=1);

/**
 * Versioned Admin API routes (CORE-011).
 *
 * Increment 1: system skeletons + envelope/error contracts + auth placeholder.
 * Provider/stay writes and real authentication arrive in later increments.
 */
return static function (\App\Core\Router $router): void {
    $router->group([
        'prefix' => '/api/v1/admin',
        'middleware' => ['admin_api_enabled', 'admin_api_request'],
    ], static function (\App\Core\Router $router): void {
        $router->get('/health', 'Api\\V1\\Admin\\SystemController@health', 'api.v1.admin.health');
        $router->get('/version', 'Api\\V1\\Admin\\SystemController@version', 'api.v1.admin.version');
        $router->get('/capabilities', 'Api\\V1\\Admin\\SystemController@capabilities', 'api.v1.admin.capabilities');

        $router->group([
            'middleware' => ['admin_api_bearer'],
        ], static function (\App\Core\Router $router): void {
            $router->get('/auth/me', 'Api\\V1\\Admin\\AuthPlaceholderController@me', 'api.v1.admin.auth.me');
        });
    });
};
