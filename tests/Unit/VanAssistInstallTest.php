<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class VanAssistInstallTest extends TestCase
{
    public function testPublicShellPublishesVanAssistInstallMetadataOnly(): void
    {
        $layout = $this->source('app/Views/layouts/public.php');
        self::assertStringContainsString("id() === 'vanassist'", $layout);
        self::assertStringContainsString('manifest.webmanifest', $layout);
        self::assertStringContainsString('apple-touch-icon', $layout);
        self::assertStringContainsString('data-brand=', $layout);
    }

    public function testMobileInstallControlSupportsAndroidAndAppleInstructions(): void
    {
        $footer = $this->source('app/Views/partials/footer.php');
        $script = $this->source('public/assets/js/app.js');
        self::assertStringContainsString('Save VanAssist to your phone', $footer);
        self::assertStringContainsString('Add to Home Screen', $footer);
        self::assertStringContainsString('beforeinstallprompt', $script);
        self::assertStringContainsString("serviceWorker.register('/service-worker.js')", $script);
        self::assertStringContainsString('display-mode: standalone', $script);
    }

    public function testManifestAndWorkerAvoidCachingPrivatePages(): void
    {
        $manifest = json_decode($this->source('public/manifest.webmanifest'), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('standalone', $manifest['display']);
        self::assertSame('VanAssist', $manifest['short_name']);
        self::assertCount(3, $manifest['icons']);
        $worker = $this->source('public/service-worker.js');
        self::assertStringContainsString("url.pathname.startsWith('/assets/')", $worker);
        self::assertStringNotContainsString("cache.add('/')", $worker);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);
        return $contents;
    }
}
