<?php

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    $router->group([
        'prefix'     => '/park',
        'middleware' => ['headers', 'csrf', 'auth', 'role:caravan-park-partner,administrator,super-administrator'],
    ], static function (Router $router): void {
        $router->get('', 'ParkController@dashboard', 'park');

        // Public profile (Phase 7).
        $router->get('/profile', 'ParkController@profile', 'park.profile');
        $router->post('/profile', 'ParkController@saveProfile');

        // Documents.
        $router->get('/documents', 'ParkController@documents', 'park.documents');
        $router->post('/documents/upload', 'ParkController@uploadDocument');
        $router->post('/documents/delete', 'ParkController@deleteDocument');
        $router->get('/documents/download', 'ParkController@downloadDocument');

        // Register a guest request on behalf of a visitor.
        $router->get('/register-request', 'ParkController@registerRequest', 'park.register-request');
        $router->post('/register-request', 'ParkController@storeRequest');

        // Nearby runs and service-day requests.
        $router->get('/runs', 'ParkController@runs', 'park.runs');
        $router->get('/service-day', 'ParkController@serviceDay', 'park.service-day');
        $router->post('/service-day', 'ParkController@storeServiceDay');

        // QR code and printable materials.
        $router->get('/materials', 'ParkController@materials', 'park.materials');
    });
};
