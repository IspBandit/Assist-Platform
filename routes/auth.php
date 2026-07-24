<?php

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    $router->group(['middleware' => ['headers', 'csrf']], static function (Router $router): void {
        $router->get('/admin/brand-handoff', 'Admin\PlatformController@consumeHandoff', 'admin.brand-handoff');
        // Guest-only pages.
        $router->group(['middleware' => ['guest']], static function (Router $router): void {
            $router->get('/login', 'Auth\AuthController@showLogin', 'login');
            $router->post('/login', 'Auth\AuthController@login');
            $router->get('/register', 'Auth\AuthController@showRegister', 'register');
            $router->group(['middleware' => ['rate:auth.register,10,3600,3600']], static function (Router $router): void {
                $router->post('/register', 'Auth\AuthController@register');
            });
            $router->get('/forgot-password', 'Auth\PasswordController@showForgot', 'forgot-password');
            $router->post('/forgot-password', 'Auth\PasswordController@forgot');
            $router->get('/reset-password', 'Auth\PasswordController@showReset', 'reset-password');
            $router->post('/reset-password', 'Auth\PasswordController@reset');
        });

        $router->get('/verify-email', 'Auth\VerificationController@verify', 'verify-email');
        $router->post('/logout', 'Auth\AuthController@logout', 'logout');
    });
};
