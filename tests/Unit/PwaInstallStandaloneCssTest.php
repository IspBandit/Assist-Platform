<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PwaInstallStandaloneCssTest extends TestCase
{
    public function testFooterHidesInstallControlsInInstalledDisplayModes(): void
    {
        $footer = file_get_contents(__DIR__ . '/../../app/Views/partials/footer.php');

        self::assertIsString($footer);
        self::assertStringContainsString('@media (display-mode: standalone)', $footer);
        self::assertStringContainsString('(display-mode: fullscreen)', $footer);
        self::assertStringContainsString('(display-mode: minimal-ui)', $footer);
        self::assertStringContainsString('[data-install-app]', $footer);
        self::assertStringContainsString('[data-install-dialog]', $footer);
        self::assertStringContainsString('display: none !important;', $footer);
    }
}
