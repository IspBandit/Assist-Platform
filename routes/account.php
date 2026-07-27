<?php

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    $router->group([
        'prefix'     => '/account',
        'middleware' => ['headers', 'csrf', 'auth'],
    ], static function (Router $router): void {
        $router->get('', 'AccountController@dashboard', 'account');
        $router->get('/garage', 'GarageController@index', 'account.garage');
        $router->post('/garage', 'GarageController@create', 'account.garage.create');
        $router->get('/garage/document', 'GarageController@downloadDocument', 'account.garage.document');
        $router->post('/garage/document/remove', 'GarageController@removeDocument', 'account.garage.document.remove');
        $router->get('/garage/{id}', 'GarageController@show', 'account.garage.show');
        $router->post('/garage/{id}', 'GarageController@update', 'account.garage.update');
        $router->post('/garage/{id}/remove', 'GarageController@remove', 'account.garage.remove');
        $router->post('/garage/{id}/documents', 'GarageController@uploadDocument', 'account.garage.documents.upload');
        $router->get('/towing-combinations', 'Site\TowSmartController@combinations', 'account.towing-combinations');
        $router->post('/towing-combinations', 'Site\TowSmartController@save', 'account.towing-combinations.save');

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
