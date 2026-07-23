<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Admin portal routes. Protected by auth + role (moderator/administrator/
 * super-administrator). Individual modules add finer permission checks.
 */
return static function (Router $router): void {
    $router->group([
        'prefix'     => '/admin',
        'middleware' => ['headers', 'csrf', 'auth', 'role:moderator,administrator,super-administrator,platform-administrator,brand-administrator,editor,support,finance,marketing'],
    ], static function (Router $router): void {
        $router->get('', 'Admin\AdminController@dashboard', 'admin');
        $router->get('/control-centre', 'Admin\PlatformController@controlCentre', 'admin.control-centre');
        $router->post('/switch-brand', 'Admin\PlatformController@switchBrand', 'admin.switch-brand');
        $router->get('/brand-builder', 'Admin\PlatformController@brandBuilder', 'admin.brand-builder');
        $router->post('/brand-builder/preview', 'Admin\PlatformController@previewBrand', 'admin.brand-builder.preview');

        // Billing management (available even while billing is disabled, so plans
        // and entitlements can be configured privately ahead of launch).
        $router->get('/billing', 'Admin\BillingController@index', 'admin.billing');
        $router->get('/billing/plans/edit', 'Admin\BillingController@editPlan', 'admin.billing.plan.edit');
        $router->post('/billing/plans/update', 'Admin\BillingController@updatePlan', 'admin.billing.plan.update');

        // Owner finance — double-entry general-ledger layer (Finance module).
        // Sits on top of the 012 billing tables; gated per-permission in controllers.
        $router->get('/finance', 'Admin\Finance\DashboardController@index', 'admin.finance');
        $router->get('/finance/accounts', 'Admin\Finance\AccountsController@index', 'admin.finance.accounts');
        $router->get('/finance/accounts/new', 'Admin\Finance\AccountsController@form');
        $router->get('/finance/accounts/edit', 'Admin\Finance\AccountsController@form');
        $router->post('/finance/accounts/save', 'Admin\Finance\AccountsController@save');
        $router->post('/finance/accounts/toggle', 'Admin\Finance\AccountsController@toggle');
        $router->get('/finance/journals', 'Admin\Finance\JournalsController@index', 'admin.finance.journals');
        $router->get('/finance/journals/new', 'Admin\Finance\JournalsController@form');
        $router->get('/finance/journals/show', 'Admin\Finance\JournalsController@show', 'admin.finance.journals.show');
        $router->post('/finance/journals/save', 'Admin\Finance\JournalsController@store');
        $router->post('/finance/journals/reverse', 'Admin\Finance\JournalsController@reverse');

        // Locations (Phase 2): states, regions and towns.
        $router->get('/locations', 'Admin\LocationsController@index', 'admin.locations');
        $router->post('/locations/sync', 'Admin\LocationsController@syncFromSeed');
        $router->get('/locations/states/new', 'Admin\LocationsController@stateForm');
        $router->get('/locations/states/edit', 'Admin\LocationsController@stateForm');
        $router->post('/locations/states/save', 'Admin\LocationsController@saveState');
        $router->get('/locations/regions', 'Admin\LocationsController@regions');
        $router->get('/locations/regions/new', 'Admin\LocationsController@regionForm');
        $router->get('/locations/regions/edit', 'Admin\LocationsController@regionForm');
        $router->post('/locations/regions/save', 'Admin\LocationsController@saveRegion');
        $router->get('/locations/towns', 'Admin\LocationsController@towns');
        $router->get('/locations/towns/new', 'Admin\LocationsController@townForm');
        $router->get('/locations/towns/edit', 'Admin\LocationsController@townForm');
        $router->post('/locations/towns/save', 'Admin\LocationsController@saveTown');

        // Service categories (Phase 2).
        $router->get('/categories', 'Admin\CategoriesController@index', 'admin.categories');
        $router->get('/categories/new', 'Admin\CategoriesController@form');
        $router->get('/categories/edit', 'Admin\CategoriesController@form');
        $router->post('/categories/save', 'Admin\CategoriesController@save');
        $router->post('/categories/toggle', 'Admin\CategoriesController@toggle');

        // Providers (Phase 3): management, approval, verification, services & areas.
        $router->get('/providers', 'Admin\ProvidersController@index', 'admin.providers');
        $router->get('/providers/new', 'Admin\ProvidersController@form');
        $router->get('/providers/edit', 'Admin\ProvidersController@form');
        $router->get('/providers/show', 'Admin\ProvidersController@show', 'admin.providers.show');
        $router->post('/providers/save', 'Admin\ProvidersController@save');
        $router->post('/providers/status', 'Admin\ProvidersController@setStatus');
        $router->post('/providers/flag', 'Admin\ProvidersController@toggleFlag');
        $router->post('/providers/note', 'Admin\ProvidersController@addNote');
        $router->post('/providers/service/add', 'Admin\ProvidersController@addService');
        $router->post('/providers/service/remove', 'Admin\ProvidersController@removeService');
        $router->post('/providers/area/add', 'Admin\ProvidersController@addArea');
        $router->post('/providers/area/remove', 'Admin\ProvidersController@removeArea');
        $router->get('/providers/document/download', 'Admin\ProvidersController@downloadDocument');
        $router->post('/providers/document/verify', 'Admin\ProvidersController@verifyDocument');
        $router->post('/providers/licence/verify', 'Admin\ProvidersController@verifyLicence');
        $router->post('/providers/send-claim-invite', 'Admin\ProvidersController@sendClaimInvite');
        $router->post('/providers/bulk-claim-invites', 'Admin\ProvidersController@bulkClaimInvites');
        $router->get('/promotions', 'Admin\PromotionsController@index', 'admin.promotions');
        $router->get('/trailer-listings', 'Admin\TrailerListingsController@index', 'admin.trailer-listings');
        $router->post('/trailer-listings/status', 'Admin\TrailerListingsController@status', 'admin.trailer-listings.status');
        $router->get('/promotions/show', 'Admin\PromotionsController@show', 'admin.promotions.show');
        $router->post('/promotions/in-progress', 'Admin\PromotionsController@markInProgress');
        $router->post('/promotions/deliver', 'Admin\PromotionsController@deliver');
        $router->get('/providers/duplicates', 'Admin\ProvidersController@duplicates', 'admin.providers.duplicates');

        // Provider prospect CRM (Phase 3): outreach, notes, CSV import/export, invitations.
        $router->get('/prospects', 'Admin\ProspectsController@index', 'admin.prospects');
        $router->get('/prospects/new', 'Admin\ProspectsController@form');
        $router->get('/prospects/edit', 'Admin\ProspectsController@form');
        $router->get('/prospects/show', 'Admin\ProspectsController@show', 'admin.prospects.show');
        $router->get('/prospects/export', 'Admin\ProspectsController@export', 'admin.prospects.export');
        $router->post('/prospects/save', 'Admin\ProspectsController@save');
        $router->post('/prospects/note', 'Admin\ProspectsController@addNote');
        $router->post('/prospects/invite', 'Admin\ProspectsController@invite');
        $router->post('/prospects/import', 'Admin\ProspectsController@import');

        // Service requests (Phase 4): moderation, status workflow, notes, images.
        $router->get('/requests', 'Admin\RequestsController@index', 'admin.requests');
        $router->get('/requests/show', 'Admin\RequestsController@show', 'admin.requests.show');
        $router->get('/requests/image', 'Admin\RequestsController@downloadImage');
        $router->post('/requests/status', 'Admin\RequestsController@changeStatus');
        $router->post('/requests/spam', 'Admin\RequestsController@toggleSpam');
        $router->post('/requests/note', 'Admin\RequestsController@addNote');

        // Matching console (Phase 5): scored suggestions, invitations, contact release.
        $router->get('/matching', 'Admin\MatchingController@index', 'admin.matching');
        $router->get('/matching/request', 'Admin\MatchingController@request', 'admin.matching.request');
        $router->post('/matching/add', 'Admin\MatchingController@add');
        $router->post('/matching/update', 'Admin\MatchingController@update');
        $router->post('/matching/release', 'Admin\MatchingController@release');

        // Content management (Phase 8): pages, homepage blocks and FAQs.
        $router->get('/content', 'Admin\ContentController@pages', 'admin.content');
        $router->get('/content/pages/new', 'Admin\ContentController@pageForm');
        $router->get('/content/pages/edit', 'Admin\ContentController@pageForm');
        $router->post('/content/pages/save', 'Admin\ContentController@savePage');
        $router->post('/content/pages/delete', 'Admin\ContentController@deletePage');
        $router->get('/content/blocks', 'Admin\ContentController@blocks', 'admin.content.blocks');
        $router->get('/content/blocks/new', 'Admin\ContentController@blockForm');
        $router->get('/content/blocks/edit', 'Admin\ContentController@blockForm');
        $router->post('/content/blocks/save', 'Admin\ContentController@saveBlock');
        $router->post('/content/blocks/delete', 'Admin\ContentController@deleteBlock');
        $router->get('/content/faqs', 'Admin\ContentController@faqs', 'admin.content.faqs');
        $router->get('/content/faqs/new', 'Admin\ContentController@faqForm');
        $router->get('/content/faqs/edit', 'Admin\ContentController@faqForm');
        $router->post('/content/faqs/save', 'Admin\ContentController@saveFaq');
        $router->post('/content/faqs/delete', 'Admin\ContentController@deleteFaq');

        // Brand-aware social campaign artwork and post-copy studio.
        $router->get('/social-media', 'Admin\SocialMediaController@index', 'admin.social-media');
        $router->post('/social-media/generate', 'Admin\SocialMediaController@generate');
        $router->post('/social-media/status', 'Admin\SocialMediaController@status');
        $router->get('/social-media/preview', 'Admin\SocialMediaController@preview');
        $router->get('/social-media/download', 'Admin\SocialMediaController@download');

        // SEO settings (Phase 8): site meta, social image and the indexing switch.
        $router->get('/seo', 'Admin\SeoController@index', 'admin.seo');
        $router->post('/seo', 'Admin\SeoController@save');

        // Email templates (Phase 9): edit transactional emails, preview and test.
        $router->get('/email-templates', 'Admin\EmailTemplatesController@index', 'admin.email-templates');
        $router->get('/email-templates/edit', 'Admin\EmailTemplatesController@edit');
        $router->post('/email-templates/save', 'Admin\EmailTemplatesController@save');
        $router->post('/email-templates/test', 'Admin\EmailTemplatesController@sendTest');
        $router->post('/email-templates/smtp-test', 'Admin\EmailTemplatesController@sendSmtpTest');
        $router->post('/email-templates/process-queue', 'Admin\EmailTemplatesController@processQueueNow');

        // Notifications (Phase 9): targeted broadcasts with preview/schedule/send.
        $router->get('/notifications', 'Admin\NotificationsController@index', 'admin.notifications');
        $router->get('/notifications/compose', 'Admin\NotificationsController@compose', 'admin.notifications.compose');
        $router->post('/notifications/save', 'Admin\NotificationsController@store');
        $router->get('/notifications/show', 'Admin\NotificationsController@show', 'admin.notifications.show');
        $router->post('/notifications/send', 'Admin\NotificationsController@send');
        $router->post('/notifications/cancel', 'Admin\NotificationsController@cancel');

        // Caravan parks (Phase 7): applications, approval, documents, service-day requests.
        $router->get('/parks', 'Admin\ParksController@index', 'admin.parks');
        $router->get('/parks/show', 'Admin\ParksController@show', 'admin.parks.show');
        $router->get('/parks/form', 'Admin\ParksController@form');
        $router->post('/parks/save', 'Admin\ParksController@save');
        $router->post('/parks/status', 'Admin\ParksController@setStatus');
        $router->post('/parks/service-day', 'Admin\ParksController@serviceDayStatus');
        $router->post('/parks/claim', 'Admin\ParksController@reviewClaim');
        $router->get('/parks/document/download', 'Admin\ParksController@downloadDocument');

        // Service runs (Phase 6): create/edit, status, stops, services, link requests, registrations.
        $router->get('/runs', 'Admin\RunsController@index', 'admin.runs');
        $router->get('/runs/form', 'Admin\RunsController@form', 'admin.runs.form');
        $router->post('/runs/save', 'Admin\RunsController@save');
        $router->get('/runs/show', 'Admin\RunsController@show', 'admin.runs.show');
        $router->post('/runs/status', 'Admin\RunsController@setStatus');
        $router->post('/runs/town/add', 'Admin\RunsController@addTown');
        $router->post('/runs/town/remove', 'Admin\RunsController@removeTown');
        $router->post('/runs/service/add', 'Admin\RunsController@addService');
        $router->post('/runs/service/remove', 'Admin\RunsController@removeService');
        $router->post('/runs/request/link', 'Admin\RunsController@linkRequest');
        $router->post('/runs/request/unlink', 'Admin\RunsController@unlinkRequest');
        $router->post('/runs/booking', 'Admin\RunsController@setBookingStatus');

        // Reports & CSV export (Phase 10).
        $router->get('/reports', 'Admin\ReportsController@index', 'admin.reports');
        $router->get('/reports/export', 'Admin\ReportsController@export');

        // Demand & provider-usage analytics (Phase 11). Gated by demand.view /
        // demand.export inside the controller.
        $router->get('/demand', 'Admin\DemandController@index', 'admin.demand');
        $router->get('/demand/providers', 'Admin\DemandController@providers', 'admin.demand.providers');
        $router->get('/demand/funnel', 'Admin\DemandController@funnel', 'admin.demand.funnel');
        $router->get('/demand/coverage', 'Admin\DemandController@coverage', 'admin.demand.coverage');
        $router->get('/demand/map', 'Admin\DemandController@map', 'admin.demand.map');
        $router->get('/demand/export', 'Admin\DemandController@export', 'admin.demand.export');

        // Audit log viewer (Phase 10).
        $router->get('/audit', 'Admin\AuditController@index', 'admin.audit');
        $router->get('/audit/export', 'Admin\AuditController@export');

        // System logs viewer (storage/logs/*.log) — super administrators only.
        $router->get('/logs', 'Admin\LogsController@index', 'admin.logs');
        $router->post('/logs/clear', 'Admin\LogsController@clear');
        $router->post('/logs/repair', 'Admin\LogsController@repair');
        $router->post('/logs/test', 'Admin\LogsController@test');

        // Site settings & launch tools (Phase 10).
        $router->get('/settings', 'Admin\SettingsController@index', 'admin.settings');
        $router->post('/settings', 'Admin\SettingsController@save');
        $router->post('/settings/remove-demo', 'Admin\SettingsController@removeDemo');

        // Feature flags (Phase 10).
        $router->get('/feature-flags', 'Admin\FeatureFlagsController@index', 'admin.feature-flags');
        $router->post('/feature-flags', 'Admin\FeatureFlagsController@save');

        // Backups — super administrators only (Phase 10).
        $router->get('/backups', 'Admin\BackupsController@index', 'admin.backups');
        $router->post('/backups/generate', 'Admin\BackupsController@generate');
        $router->get('/backups/download', 'Admin\BackupsController@download');
        $router->post('/backups/delete', 'Admin\BackupsController@delete');

        // Maintenance (migrations + data import) — super administrators only.
        $router->get('/maintenance', 'Admin\MaintenanceController@index', 'admin.maintenance');
        $router->post('/maintenance/migrate', 'Admin\MaintenanceController@migrate');
        $router->post('/maintenance/reimport', 'Admin\MaintenanceController@reimport');
        $router->post('/maintenance/seed-emails', 'Admin\MaintenanceController@seedEmails');
        $router->post('/maintenance/sync-email-template', 'Admin\MaintenanceController@syncEmailTemplate');
        $router->post('/maintenance/seed-towns', 'Admin\MaintenanceController@seedTowns');
        $router->post('/maintenance/seed-osm', 'Admin\MaintenanceController@seedOsm');
        $router->post('/maintenance/seed-locality', 'Admin\MaintenanceController@seedLocality');
        $router->post('/maintenance/seed-providers', 'Admin\MaintenanceController@seedProviders');
        $router->get('/maintenance/auto', 'Admin\MaintenanceController@runAuto');
        $router->post('/maintenance/refresh-osm', 'Admin\MaintenanceController@refreshOsm');
        $router->post('/maintenance/feature-major-cities', 'Admin\MaintenanceController@featureMajorCities');
        $router->post('/maintenance/seed-content', 'Admin\MaintenanceController@seedContent');

        // Users management.
        $router->get('/users', 'Admin\UsersController@index', 'admin.users');
        $router->get('/users/export', 'Admin\UsersController@export', 'admin.users.export');
        $router->get('/users/new', 'Admin\UsersController@form');
        $router->get('/users/edit', 'Admin\UsersController@form');
        $router->get('/users/show', 'Admin\UsersController@show', 'admin.users.show');
        $router->post('/users/save', 'Admin\UsersController@save');
        $router->post('/users/status', 'Admin\UsersController@setStatus');
        $router->post('/users/send-reset', 'Admin\UsersController@sendReset');
        $router->post('/users/delete', 'Admin\UsersController@delete');

        // Customers management.
        $router->get('/customers', 'Admin\CustomersController@index', 'admin.customers');
        $router->get('/customers/export', 'Admin\CustomersController@export', 'admin.customers.export');
        $router->get('/customers/show', 'Admin\CustomersController@show', 'admin.customers.show');
        $router->post('/customers/save', 'Admin\CustomersController@save');
    });
};
