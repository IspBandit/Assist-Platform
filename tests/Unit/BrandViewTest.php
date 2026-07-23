<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Settings;
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

    /** @param array<string,string>|null $values */
    private function setSettingsCache(?array $values): void
    {
        $property = new ReflectionProperty(Settings::class, 'cache');
        $property->setValue(null, $values);
    }
}
