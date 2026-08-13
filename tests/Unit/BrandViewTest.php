<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Settings;
use App\Services\SeoSchema;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class BrandViewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->setSettingsCache(['site_name' => 'VanAssist', 'launch_mode' => 'private']);
    }

    protected function tearDown(): void
    {
        BrandContext::clear();
        $this->setSettingsCache(null);
        parent::tearDown();
    }

    public function testMinimalLayoutUsesCurrentBrandTitleAndWordmark(): void
    {
        BrandContext::set($this->brand());

        $html = View::render('layouts.minimal', ['title' => 'Sign in']);

        self::assertStringContainsString('<title>Sign in — TowSmart</title>', $html);
        self::assertStringContainsString('aria-label="TowSmart home"', $html);
        self::assertStringContainsString('href="https://towsmart.com.au/"', $html);
        self::assertStringContainsString('Tow<span class="assist">Smart</span>', $html);
        self::assertStringNotContainsString('— VanAssist</title>', $html);
    }

    public function testSeoMetadataUsesCurrentNonVanAssistBrandName(): void
    {
        BrandContext::set($this->brand());
        $_SERVER['REQUEST_URI'] = '/calculator';

        $html = View::render('partials.seo-meta', ['title' => 'Towing weight calculator']);

        self::assertStringContainsString('<title>Towing weight calculator — TowSmart</title>', $html);
        self::assertStringContainsString('property="og:site_name" content="TowSmart"', $html);
        self::assertStringContainsString('property="og:url" content="https://towsmart.com.au/calculator"', $html);
        self::assertStringContainsString('<link rel="canonical" href="https://towsmart.com.au/calculator">', $html);
        self::assertStringContainsString('property="og:locale" content="en_AU"', $html);
    }

    public function testSeoMetadataSupportsSearchEngineVerificationAndImagePreviews(): void
    {
        $this->setSettingsCache([
            'site_name' => 'VanAssist',
            'launch_mode' => 'public',
            'google_site_verification_vanassist' => 'google-token_123',
            'bing_site_verification_vanassist' => 'bing-token=456',
        ]);
        BrandContext::set($this->vanAssistBrand());
        $_SERVER['REQUEST_URI'] = '/services?distance=50';

        $html = View::render('partials.seo-meta', [
            'title' => 'Caravan services',
            'ogImage' => 'https://vanassist.test/assets/share.webp',
        ]);

        self::assertStringContainsString('<link rel="canonical" href="https://vanassist.test/services">', $html);
        self::assertStringContainsString('name="google-site-verification" content="google-token_123"', $html);
        self::assertStringContainsString('name="msvalidate.01" content="bing-token=456"', $html);
        self::assertStringContainsString('name="twitter:image" content="https://vanassist.test/assets/share.webp"', $html);
        self::assertStringContainsString('property="og:image:alt" content="Caravan services — VanAssist"', $html);
    }

    public function testNonVanAssistFooterUsesBrandSupportAddress(): void
    {
        $this->setSettingsCache([
            'site_name' => 'VanAssist',
            'launch_mode' => 'private',
            'contact_email' => 'support@vanassist.com.au',
        ]);
        BrandContext::set($this->brand());

        $html = View::render('partials.footer');

        self::assertStringContainsString('mailto:support@towsmart.com.au', $html);
        self::assertStringNotContainsString('mailto:support@vanassist.com.au', $html);
    }

    public function testBreadcrumbSchemaUsesVisibleHierarchyWithoutInventedFields(): void
    {
        $json=SeoSchema::breadcrumbs([
            ['name'=>'Home','url'=>'https://vanassist.test/'],
            ['name'=>'Services','url'=>'https://vanassist.test/services'],
            ['name'=>'12 volt electrical','url'=>'https://vanassist.test/services/12-volt-electrical'],
        ]);
        $schema=json_decode($json,true,512,JSON_THROW_ON_ERROR);
        self::assertSame('BreadcrumbList',$schema['@type']);
        self::assertSame(3,$schema['itemListElement'][2]['position']);
        self::assertSame('12 volt electrical',$schema['itemListElement'][2]['name']);
    }

    public function testBrandThemePublishesPlatformSemanticTokens(): void
    {
        BrandContext::set($this->brand());

        $html = View::render('partials.brand-theme');

        self::assertStringContainsString('--brand-primary: #1d4ed8;', $html);
        self::assertStringContainsString('--color-brand: var(--brand-primary);', $html);
        self::assertStringContainsString('--color-surface: var(--brand-surface);', $html);
        self::assertStringContainsString('--color-focus: var(--brand-focus);', $html);
        self::assertStringContainsString('--teal: var(--brand-primary);', $html);
    }

    public function testVanAssistHomePresentsTaskPathsWithoutStatisticsRibbon(): void
    {
        BrandContext::set($this->vanAssistBrand());

        $html = View::render('public.home', [
            'title' => 'Caravan help',
            'blocks' => [],
            'confirmedRuns' => [],
            'formingRuns' => [],
            'providerDirectoryCount' => 1248,
            'categories' => [['name' => 'Caravan repairs', 'slug' => 'caravan-repairs']],
            'categoryGroups' => ['Services' => [['name' => 'Caravan repairs', 'slug' => 'caravan-repairs']]],
            'popularCategories' => [['name' => 'Caravan repairs', 'slug' => 'caravan-repairs']],
            'homeEvidence' => [
                'directory_listings' => 1248,
                'verified_providers' => 0,
                'provider_towns' => 314,
                'service_categories' => 42,
            ],
            'freeMessage' => '',
        ]);

        self::assertStringContainsString('class="hero hero--visual"', $html);
        self::assertStringContainsString('aria-label="Find VanAssist help"', $html);
        self::assertStringNotContainsString('Service providers by location', $html);
        self::assertStringNotContainsString('data-nearby-providers', $html);
        self::assertStringNotContainsString('journey-launcher', $html);
        self::assertStringNotContainsString('evidence-ribbon', $html);
        self::assertStringNotContainsString('active service listings', $html);
        self::assertStringNotContainsString('Upcoming confirmed service runs', $html);
        self::assertStringNotContainsString('Know what each listing label means.', $html);
        self::assertStringContainsString('vanassist-coastal-hero-mobile-v1.webp', $html);
        self::assertStringContainsString('Your travel', $html);
        self::assertStringContainsString('companion.', $html);
    }

    public function testProviderDashboardPrioritisesOpenDemand(): void
    {
        BrandContext::set($this->vanAssistBrand());

        $html = View::render('provider.dashboard', [
            'title' => 'Provider dashboard',
            'provider' => [
                'id' => 7,
                'business_name' => 'Regional RV Service Co.',
                'slug' => 'regional-rv-service',
                'status' => 'active',
                'is_verified' => 1,
                'insurance_verified' => 1,
            ],
            'counts' => [
                'services' => 4,
                'areas' => 3,
                'documents' => 2,
                'open_requests' => 1,
                'active_runs' => 0,
                'profile_views_30d' => 20,
                'contact_actions_30d' => 3,
                'pending_documents' => 0,
                'expiring_licences' => 0,
            ],
            'checklist' => [
                'Add a business description' => true,
                'List at least one service' => true,
                'Define a service area' => true,
                'Upload a verification document' => true,
            ],
            'recentRequests' => [],
            'membershipState' => null,
            'foundingPromo' => null,
        ]);

        self::assertStringContainsString('Review incoming requests', $html);
        self::assertStringContainsString('Marketplace activity, without inflated claims.', $html);
        self::assertStringContainsString('Keep the public promise accurate.', $html);
    }

    private function brand(): \App\Platform\Brand\Brand
    {
        return BrandRegistry::fromArray([
            'towsmart' => [
                'database_id' => 2,
                'name' => 'TowSmart',
                'legal_name' => 'TowSmart',
                'short_name' => 'TowSmart',
                'status' => 'active',
                'url' => 'https://towsmart.com.au',
                'domains' => ['primary' => 'towsmart.com.au'],
                'assets' => ['logo' => '/logo.svg'],
                'theme' => ['brand' => '#1d4ed8'],
                'metadata' => [
                    'wordmark_prefix' => 'Tow',
                    'wordmark_accent' => 'Smart',
                    'description' => 'Towing guidance.',
                ],
                'contact' => ['support_email' => 'support@towsmart.com.au'],
                'legal' => ['privacy_path' => '/privacy'],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'towwise',
            ],
        ])->get('towsmart');
    }

    private function vanAssistBrand(): \App\Platform\Brand\Brand
    {
        return BrandRegistry::fromArray([
            'vanassist' => [
                'database_id' => 1,
                'name' => 'VanAssist',
                'legal_name' => 'VanAssist Australia',
                'short_name' => 'VanAssist',
                'status' => 'active',
                'url' => 'https://vanassist.test',
                'domains' => ['primary' => 'vanassist.test'],
                'assets' => [],
                'theme' => ['brand' => '#087f7d'],
                'metadata' => [
                    'wordmark_prefix' => 'Van',
                    'wordmark_accent' => 'Assist',
                    'header_descriptor' => 'RV service network',
                    'tagline' => 'Caravan and RV help for the road ahead.',
                    'description' => 'Find caravan and RV help.',
                ],
                'contact' => ['support_email' => 'support@vanassist.test'],
                'legal' => [],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'vanassist',
            ],
        ])->get('vanassist');
    }

    /** @param array<string,string>|null $values */
    private function setSettingsCache(?array $values): void
    {
        $property = new ReflectionProperty(Settings::class, 'cache');
        $property->setValue(null, $values);
    }
}
