<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Public website routes. All routes carry security headers and CSRF
 * verification (CSRF only enforces on state-changing methods).
 */
return static function (Router $router): void {
    $router->group(['middleware' => ['headers', 'csrf']], static function (Router $router): void {
        $router->get('/', 'Site\HomeController@index', 'home');

        // Informational landing pages.
        $router->get('/how-it-works', 'Site\PageController@howItWorks', 'how-it-works');
        $router->get('/for-providers', 'Site\PageController@forProviders', 'for-providers');
        $router->get('/for-providers/register', 'Site\PageController@providerInterest', 'for-providers.register');
        $router->group(['middleware' => ['rate:public.provider-interest,5,3600,3600']], static function (Router $router): void {
            $router->post('/for-providers/register', 'Site\PageController@submitProviderInterest');
        });
        $router->get('/for-caravan-parks', 'Site\PageController@forCaravanParks', 'for-caravan-parks');

        // FAQ page (Phase 8): grouped FAQs with FAQPage structured data.
        $router->get('/faqs', 'Site\FaqController@index', 'faqs');

        // Service-category pages (Phase 2), generated from the database.
        $router->get('/services', 'Site\CategoryController@index', 'services');
        $router->get('/services/{slug}', 'Site\CategoryController@show', 'services.show');

        // Location pages (Phase 2): region index/detail and town detail.
        $router->get('/regions', 'Site\LocationController@regionsIndex', 'regions');
        $router->get('/regions/{slug}', 'Site\LocationController@regionShow', 'regions.show');
        $router->get('/towns/{slug}', 'Site\LocationController@townShow', 'towns.show');
        // Town type-ahead (JSON) used by forms to resolve a town and its region.
        $router->get('/locations/towns', 'Site\LocationController@searchTowns', 'locations.towns');
        // Nearest active town for a GPS fix (used by "Use my location" on mobile).
        $router->get('/locations/nearest', 'Site\LocationController@nearestTown', 'locations.nearest');
        $router->get('/locations/nearby-providers', 'Site\LocationController@nearbyProviders', 'locations.nearby-providers');

        // Provider directory and profiles (Phase 3), generated from the database.
        $router->get('/providers', 'Site\ProviderController@index', 'providers');
        $router->get('/providers/{slug}', 'Site\ProviderController@show', 'providers.show');

        // Attributable provider contact actions (Phase 11): record then redirect
        // to phone/email/website/directions. GET-only; recording is best-effort.
        $router->get('/go/{action}/{slug}', 'Site\ContactActionController@go', 'provider.contact');

        // Provider invitation acceptance (Phase 3): tokenised onboarding entry point.
        $router->get('/provider/join/{token}', 'Provider\InvitationController@accept', 'provider.join');
        $router->group(['middleware' => ['rate:public.provider-invitation,10,3600,3600']], static function (Router $router): void {
            $router->post('/provider/join/{token}', 'Provider\InvitationController@store', 'provider.join.store');
        });
        // Self-serve claim for unclaimed directory listings.
        $router->get('/provider/claim/{token}', 'Provider\ClaimController@show', 'provider.claim');
        $router->group(['middleware' => ['rate:public.provider-claim,10,3600,3600']], static function (Router $router): void {
            $router->post('/provider/claim/{token}', 'Provider\ClaimController@store', 'provider.claim.store');
        });

        // Caravan park partners (Phase 7): public application and public park pages.
        // The literal /apply route must precede the {slug} catch-all.
        $router->get('/caravan-parks/apply', 'Site\ParkController@apply', 'caravan-parks.apply');
        $router->group(['middleware' => ['rate:public.park-application,5,3600,3600']], static function (Router $router): void {
            $router->post('/caravan-parks/apply', 'Site\ParkController@applyStore', 'caravan-parks.apply.store');
        });
        $router->get('/caravan-parks/{slug}', 'Site\ParkController@show', 'caravan-parks.show');

        // Customer service-request flow (Phase 4).
        $router->get('/request-assistance', 'Site\RequestController@form', 'request-assistance');
        $router->group(['middleware' => ['rate:public.assistance-request,10,3600,3600']], static function (Router $router): void {
            $router->post('/request-assistance', 'Site\RequestController@submit', 'request-assistance.submit');
        });
        $router->get('/request-assistance/submitted', 'Site\RequestController@submitted', 'request-assistance.submitted');
        $router->get('/request/verify', 'Site\RequestController@verify', 'request.verify');

        // Public service runs and the join-run flow (Phase 6).
        $router->get('/service-runs', 'Site\RunController@index', 'service-runs');
        $router->get('/service-runs/{slug}', 'Site\RunController@show', 'service-runs.show');
        $router->group(['middleware' => ['rate:public.run-join,10,3600,3600']], static function (Router $router): void {
            $router->post('/service-runs/{slug}/join', 'Site\RunController@join', 'service-runs.join');
        });

        // Homepage "Find a service" search (town/postcode + optional category).
        $router->get('/find', 'Site\SearchController@find', 'find');
        // Structured "couldn't find a suitable provider" feedback (Phase 11).
        $router->group(['middleware' => ['rate:public.search-feedback,30,3600,3600']], static function (Router $router): void {
            $router->post('/find/feedback', 'Site\SearchController@feedback', 'find.feedback');
        });

        // Login-free customer outcome follow-up landing (Phase 11).
        $router->get('/followup/{token}', 'Site\FollowupController@show', 'followup');
        $router->group(['middleware' => ['rate:public.followup,10,3600,3600']], static function (Router $router): void {
            $router->post('/followup/{token}', 'Site\FollowupController@submit', 'followup.submit');
        });

        // SEO endpoints (Phase 8): dynamic sitemap and robots, built from the catalogue.
        $router->get('/sitemap.xml', 'Site\SitemapController@xml', 'sitemap');
        $router->get('/robots.txt', 'Site\SitemapController@robots', 'robots');

        // CMS-managed static and legal pages (explicit slugs so they win over fallbacks).
        foreach ([
            'about', 'contact', 'privacy-policy', 'terms-of-use', 'provider-terms',
            'disclaimer', 'safety-information', 'complaints-process', 'accessibility-statement',
        ] as $slug) {
            $router->get('/' . $slug, 'Site\PageController@cms');
        }
    });

    // Billing gateway webhook. NO CSRF: authenticity comes from signature
    // verification. Returns 404 while billing is disabled. Always server-side
    // verified — return-page redirects are never trusted as proof of payment.
    $router->group(['middleware' => ['headers']], static function (Router $router): void {
        $router->post('/billing/webhook/stripe', 'Billing\WebhookController@stripe', 'billing.webhook.stripe');
    });
};
