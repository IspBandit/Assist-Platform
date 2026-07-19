<?php

declare(strict_types=1);

use App\Core\Router;

return static function (Router $router): void {
    $router->group([
        'prefix'     => '/provider',
        'middleware' => ['headers', 'csrf', 'auth', 'role:provider,administrator,super-administrator'],
    ], static function (Router $router): void {
        $router->get('', 'ProviderController@dashboard', 'provider');

        // Self-service profile management (Phase 3 part 2).
        $router->get('/profile', 'Provider\ProfileController@profile', 'provider.profile.edit');
        $router->post('/profile', 'Provider\ProfileController@saveProfile');

        $router->get('/services', 'Provider\ProfileController@services', 'provider.services');
        $router->post('/services/add', 'Provider\ProfileController@addService');
        $router->post('/services/remove', 'Provider\ProfileController@removeService');

        $router->get('/areas', 'Provider\ProfileController@areas', 'provider.areas');
        $router->post('/areas/add', 'Provider\ProfileController@addArea');
        $router->post('/areas/remove', 'Provider\ProfileController@removeArea');

        $router->get('/documents', 'Provider\ProfileController@documents', 'provider.documents');
        $router->post('/documents/upload', 'Provider\ProfileController@uploadDocument');
        $router->post('/documents/delete', 'Provider\ProfileController@deleteDocument');
        $router->get('/documents/download', 'Provider\ProfileController@downloadDocument');

        $router->get('/licences', 'Provider\ProfileController@licences', 'provider.licences');
        $router->post('/licences/save', 'Provider\ProfileController@saveLicence');
        $router->post('/licences/delete', 'Provider\ProfileController@deleteLicence');

        $router->get('/availability', 'Provider\ProfileController@availability', 'provider.availability');
        $router->post('/availability/add', 'Provider\ProfileController@addAvailability');
        $router->post('/availability/remove', 'Provider\ProfileController@removeAvailability');

        // Incoming matched requests (Phase 5). Literal routes before {match}.
        $router->get('/requests', 'Provider\RequestController@incoming', 'provider.requests');
        $router->get('/requests/image', 'Provider\RequestController@image');
        $router->get('/requests/{match}', 'Provider\RequestController@show', 'provider.requests.show');
        $router->post('/requests/{match}/respond', 'Provider\RequestController@respond');
        // Provider outcome/job-status update (Phase 11 demand analytics).
        $router->post('/requests/{match}/outcome', 'Provider\RequestController@outcome');

        // Provider analytics dashboard (Phase 11). Own data only.
        $router->get('/analytics', 'Provider\AnalyticsController@index', 'provider.analytics');

        $router->get('/promotion', 'Provider\PromotionController@index', 'provider.promotion');
        $router->post('/promotion', 'Provider\PromotionController@store');

        // Service runs self-service (Phase 6).
        $router->get('/runs', 'Provider\RunController@index', 'provider.runs');
        $router->get('/runs/form', 'Provider\RunController@form', 'provider.runs.form');
        $router->post('/runs/save', 'Provider\RunController@save');
        $router->get('/runs/show', 'Provider\RunController@show', 'provider.runs.show');
        $router->post('/runs/status', 'Provider\RunController@setStatus');
        $router->post('/runs/town/add', 'Provider\RunController@addTown');
        $router->post('/runs/town/remove', 'Provider\RunController@removeTown');
        $router->post('/runs/service/add', 'Provider\RunController@addService');
        $router->post('/runs/service/remove', 'Provider\RunController@removeService');

        // Billing portal. The controller returns 404 while ENABLE_BILLING=false,
        // so the portal stays hidden during the free launch.
        $router->get('/billing', 'ProviderController@billing', 'provider.billing');
    });
};
