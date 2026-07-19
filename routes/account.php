<?php

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    $router->group([
        'prefix'     => '/account',
        'middleware' => ['headers', 'csrf', 'auth'],
    ], static function (Router $router): void {
        $router->get('', 'AccountController@dashboard', 'account');

        // Saved providers (Phase 11).
        $router->get('/saved', 'AccountController@saved', 'account.saved');
        $router->post('/providers/save', 'AccountController@saveProvider', 'account.providers.save');

        // Customer service requests (Phase 4). The literal image + list routes
        // are registered before the dynamic {reference} route.
        $router->get('/requests', 'AccountController@requests', 'account.requests');
        $router->get('/requests/image', 'AccountController@requestImage', 'account.requests.image');
        // Outcome confirmation (Phase 11) — literal suffixes before {reference}.
        $router->get('/requests/{reference}/outcome', 'AccountController@outcomeForm', 'account.requests.outcome');
        $router->post('/requests/{reference}/outcome', 'AccountController@outcomeSubmit', 'account.requests.outcome.submit');
        $router->get('/requests/{reference}', 'AccountController@showRequest', 'account.requests.show');
    });
};
