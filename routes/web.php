<?php

declare(strict_types=1);

use App\Core\Router;

/**
 * Public website routes. All routes carry security headers and CSRF
 * verification (CSRF only enforces on state-changing methods).
 */
return static function (Router $router): void {
    $router->group(['middleware' => ['headers', 'csrf']], static function (Router $router): void {
        $router->get('/manifest.webmanifest', 'Site\AssetController@manifest', 'assets.manifest');
        $router->get('/service-worker.js', 'Site\AssetController@serviceWorker', 'assets.service-worker');
        $router->get('/runtime-assets/brands/{brand}/{name}', 'Site\AssetController@brand', 'assets.brand');
        $router->get('/runtime-assets/{group}/{name}', 'Site\AssetController@file', 'assets.file');
        $router->get('/', 'Site\HomeController@index', 'home');
        $router->get('/help', 'Site\DocumentationController@index', 'documentation.index');
        $router->get('/help/whats-new', 'Site\DocumentationController@whatsNew', 'documentation.whats-new');
        $router->get('/help/{guide}', 'Site\DocumentationController@guide', 'documentation.guide');
        $router->get('/help/{guide}/{article}', 'Site\DocumentationController@article', 'documentation.article');
        $router->get('/email/unsubscribe', 'Site\EmailPreferenceController@unsubscribe', 'email.unsubscribe');
        $router->get('/email/listing-notices/stop', 'Site\EmailPreferenceController@stopDirectoryNotices', 'email.directory-notices.stop');
        $router->get('/calculator', 'Site\TowSmartController@calculator', 'towsmart.calculator');
        $router->get('/calculator/catalogue/{type}', 'Site\TowSmartController@catalogue', 'towsmart.catalogue');
        $router->get('/calculator/catalogue/{type}/{id}', 'Site\TowSmartController@catalogueItem', 'towsmart.catalogue.item');
        $router->get('/tow-guide', 'Site\TowSmartController@guide', 'towsmart.guide');
        $router->get('/checklist', 'Site\TowSmartController@checklist', 'towsmart.checklist');
        $router->group(['middleware' => ['rate:public.towing-calculator,30,3600,3600']], static function (Router $router): void {
            $router->post('/calculator', 'Site\TowSmartController@calculate', 'towsmart.calculate');
        });
        $router->get('/marketplace', 'Site\TrailerWiseController@marketplace', 'trailerwise.marketplace');
        $router->get('/trailers/{slug}', 'Site\TrailerWiseController@show', 'trailerwise.show');

        // Polaris new-RV catalogue (brand-gated in controller via rv_catalogue module).
        // Note: /find is shared with VanAssist provider search — SearchController delegates for Polaris.
        $router->get('/rvs', 'Site\PolarisController@browse', 'polaris.browse');
        $router->get('/rvs/{manufacturer}/{model}', 'Site\PolarisController@showModel', 'polaris.model');
        $router->get('/dealers/{id}/enquire', 'Site\PolarisController@dealerEnquire', 'polaris.dealer.enquire');
        $router->get('/compare', 'Site\PolarisController@compare', 'polaris.compare');
        $router->get('/compare/{token}', 'Site\PolarisController@compare', 'polaris.compare.shared');
        $router->post('/compare/share', 'Site\PolarisController@shareCompare', 'polaris.compare.share');
        $router->get('/manufacturers', 'Site\PolarisController@manufacturers', 'polaris.manufacturers');
        $router->get('/manufacturers/{manufacturer}', 'Site\PolarisController@showManufacturer', 'polaris.manufacturer');
        $router->get('/tow-match', 'Site\PolarisController@towMatch', 'polaris.tow-match');
        $router->get('/floorplans', 'Site\PolarisController@floorplans', 'polaris.floorplans');
        $router->get('/buying-guides', 'Site\PolarisController@buyingGuides', 'polaris.buying-guides');
        $router->get('/buying-guides/{slug}', 'Site\PolarisController@buyingGuide', 'polaris.buying-guide');
        $router->get('/saved', 'Site\PolarisController@saved', 'polaris.saved');
        $router->post('/saved/models', 'Site\PolarisController@saveModel', 'polaris.saved.save');
        $router->post('/saved/models/remove', 'Site\PolarisController@unsaveModel', 'polaris.saved.remove');
        $router->post('/saved/searches', 'Site\PolarisController@saveSearch', 'polaris.saved.search');
        $router->post('/saved/searches/remove', 'Site\PolarisController@unsaveSearch', 'polaris.saved.search.remove');
        $router->get('/account/preferences', 'Site\PolarisController@accountPreferences', 'polaris.account.preferences');
        $router->post('/account/preferences', 'Site\PolarisController@saveAccountPreferences', 'polaris.account.preferences.save');
        $router->get('/account/comparisons', 'Site\PolarisController@accountComparisons', 'polaris.account.comparisons');
        $router->get('/account/alerts', 'Site\PolarisController@accountAlerts', 'polaris.account.alerts');
        $router->get('/account/tow-vehicles', 'Site\PolarisController@accountTowVehicles', 'polaris.account.tow-vehicles');

        $router->get('/portal/manufacturer', 'Site\ManufacturerPortalController@index', 'polaris.portal');
        $router->get('/portal/manufacturer/claims', 'Site\ManufacturerPortalController@claims', 'polaris.portal.claims');
        $router->post('/portal/manufacturer/claims', 'Site\ManufacturerPortalController@submitClaim', 'polaris.portal.claims.submit');
        $router->get('/portal/manufacturer/models', 'Site\ManufacturerPortalController@models', 'polaris.portal.models');
        $router->get('/portal/manufacturer/models/{id}', 'Site\ManufacturerPortalController@editModel', 'polaris.portal.models.edit');
        $router->post('/portal/manufacturer/models/save', 'Site\ManufacturerPortalController@saveModel', 'polaris.portal.models.save');
        $router->get('/portal/manufacturer/profile', 'Site\ManufacturerPortalController@profile', 'polaris.portal.profile');
        $router->post('/portal/manufacturer/profile', 'Site\ManufacturerPortalController@saveProfile', 'polaris.portal.profile.save');
        $router->get('/portal/manufacturer/media', 'Site\ManufacturerPortalController@media', 'polaris.portal.media');
        $router->post('/portal/manufacturer/media', 'Site\ManufacturerPortalController@uploadMedia', 'polaris.portal.media.upload');
        $router->get('/portal/manufacturer/dealers', 'Site\ManufacturerPortalController@dealers', 'polaris.portal.dealers');
        $router->post('/portal/manufacturer/dealers/link', 'Site\ManufacturerPortalController@linkDealer', 'polaris.portal.dealers.link');
        $router->get('/portal/manufacturer/analytics', 'Site\ManufacturerPortalController@analytics', 'polaris.portal.analytics');
        $router->get('/portal/manufacturer/team', 'Site\ManufacturerPortalController@team', 'polaris.portal.team');
        $router->post('/portal/manufacturer/team', 'Site\ManufacturerPortalController@addTeamMember', 'polaris.portal.team.add');
        $router->get('/portal/manufacturer/data-quality', 'Site\ManufacturerPortalController@dataQuality', 'polaris.portal.data-quality');
        $router->get('/portal/dealer/claims', 'Site\DealerPortalController@claims', 'polaris.dealer.claims');
        $router->post('/portal/dealer/claims', 'Site\DealerPortalController@submitClaim', 'polaris.dealer.claims.submit');

        // Informational landing pages.
        $router->get('/how-it-works', 'Site\PageController@howItWorks', 'how-it-works');
        $router->get('/for-providers', 'Site\PageController@forProviders', 'for-providers');
        $router->get('/for-providers/register', 'Site\PageController@providerInterest', 'for-providers.register');
        $router->group(['middleware' => ['rate:public.provider-interest,5,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/for-providers/register/search', 'Site\PageController@searchProviderMatches');
            $router->post('/for-providers/register/confirm-new', 'Site\PageController@confirmNewProviderListing');
            $router->post('/for-providers/register', 'Site\PageController@submitProviderInterest');
        });
        $router->get('/for-caravan-parks', 'Site\PageController@forCaravanParks', 'for-caravan-parks');

        // FAQ page (Phase 8): grouped FAQs with FAQPage structured data.
        $router->get('/faqs', 'Site\FaqController@index', 'faqs');

        // Service-category pages (Phase 2), generated from the database.
        $router->get('/services', 'Site\CategoryController@index', 'services');
        $router->get('/services/{slug}', 'Site\CategoryController@show', 'services.show');
        $router->get('/category/{slug}', 'Site\CategoryController@show', 'categories.show');
        $router->get('/rules', 'Site\RegulatoryLibraryController@index', 'rules.index');
        $router->get('/rules/guided', 'Site\RegulatoryLibraryController@guide', 'rules.guide');
        $router->get('/motorsport', 'Site\MotorsportController@index', 'motorsport.index');

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
        $router->get('/sponsor/{campaign}/click', 'Site\SponsoredCampaignController@click', 'sponsor.click');
        $router->get('/business/{slug}', 'Site\ProviderController@show', 'business.show');

        // Attributable provider contact actions (Phase 11): record then redirect
        // to phone/email/website/directions. GET-only; recording is best-effort.
        $router->get('/go/{action}/{slug}', 'Site\ContactActionController@go', 'provider.contact');

        // Provider invitation acceptance (Phase 3): tokenised onboarding entry point.
        $router->get('/provider/join/{token}', 'Provider\InvitationController@accept', 'provider.join');
        $router->group(['middleware' => ['rate:public.provider-invitation,10,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/provider/join/{token}', 'Provider\InvitationController@store', 'provider.join.store');
        });
        // Self-serve claim for unclaimed directory listings.
        $router->get('/provider/claim/{token}', 'Provider\ClaimController@show', 'provider.claim');
        $router->group(['middleware' => ['rate:public.provider-claim,10,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/provider/claim/{token}', 'Provider\ClaimController@store', 'provider.claim.store');
        });

        // Caravan park partners (Phase 7): public application and public park pages.
        // The literal /apply route must precede the {slug} catch-all.
        $router->get('/caravan-parks/apply', 'Site\ParkController@apply', 'caravan-parks.apply');
        $router->group(['middleware' => ['rate:public.park-application,5,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/caravan-parks/apply', 'Site\ParkController@applyStore', 'caravan-parks.apply.store');
        });
        $router->get('/caravan-parks/{slug}/claim', 'Site\ParkController@claim', 'caravan-parks.claim');
        $router->group(['middleware' => ['rate:public.park-claim,5,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/caravan-parks/{slug}/claim', 'Site\ParkController@claimStore', 'caravan-parks.claim.store');
        });
        $router->get('/caravan-parks/{slug}', 'Site\ParkController@show', 'caravan-parks.show');
        $router->get('/stays', 'Site\ParkController@directory', 'stays');

        // Customer service-request flow (Phase 4).
        $router->get('/request-assistance', 'Site\RequestController@form', 'request-assistance');
        $router->group(['middleware' => ['rate:public.assistance-request,10,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/request-assistance', 'Site\RequestController@submit', 'request-assistance.submit');
        });
        $router->get('/request-assistance/submitted', 'Site\RequestController@submitted', 'request-assistance.submitted');
        $router->get('/request/verify', 'Site\RequestController@verify', 'request.verify');

        // Public service runs and the join-run flow (Phase 6).
        $router->get('/service-runs', 'Site\RunController@index', 'service-runs');
        $router->get('/service-runs/{slug}', 'Site\RunController@show', 'service-runs.show');
        $router->group(['middleware' => ['rate:public.run-join,10,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/service-runs/{slug}/join', 'Site\RunController@join', 'service-runs.join');
        });

        // Homepage "Find a service" search (town/postcode + optional category).
        $router->get('/find', 'Site\SearchController@find', 'find');
        // Structured "couldn't find a suitable provider" feedback (Phase 11).
        $router->group(['middleware' => ['rate:public.search-feedback,30,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/find/feedback', 'Site\SearchController@feedback', 'find.feedback');
        });
        // Ask VanAssist (CORE-012 / AI-1) — parallel NL search; feature-flagged off by default.
        $router->group(['middleware' => ['ask_rate:public.ask-vanassist,20,3600,3600']], static function (Router $router): void {
            $router->get('/ask', 'Site\AssistSearchController@form', 'ask');
        });
        $router->get('/ask/click/{gapId}', 'Site\AssistOutcomeController@click', 'ask.click');
        $router->group(['middleware' => ['rate:public.ask-vanassist-unlock,10,3600,3600', 'turnstile']], static function (Router $router): void {
            $router->post('/ask/unlock', 'Site\AssistSearchController@unlock', 'ask.unlock');
        });

        // Login-free customer outcome follow-up landing (Phase 11).
        $router->get('/followup/{token}', 'Site\FollowupController@show', 'followup');
        $router->group(['middleware' => ['rate:public.followup,10,3600,3600', 'turnstile']], static function (Router $router): void {
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
