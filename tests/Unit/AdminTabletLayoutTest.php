<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminTabletLayoutTest extends TestCase
{
    public function testAdminShellHasTabletDrawerAndTouchSafeControls(): void
    {
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

        self::assertStringContainsString('class="admin-nav-scrim"', $layout);
        self::assertStringContainsString('@media (max-width: 1100px)', $css);
        self::assertStringContainsString('width:min(360px,100vw)', $css);
        self::assertStringContainsString('min-height:48px', $css);
        self::assertStringContainsString('overscroll-behavior-inline:contain', $css);
        self::assertStringContainsString('display:block; width:100%; max-width:100%; min-width:0; overflow-x:auto', $css);
        self::assertStringContainsString('.admin-content .grid-3,.admin-content .grid-4 { grid-template-columns:repeat(2,minmax(0,1fr)); }', $css);
        self::assertStringContainsString('@media (max-width: 720px)', $css);
    }

    public function testAdminDrawerSupportsTouchDismissalAndKeyboardContainment(): void
    {
        $script = (string) file_get_contents(base_path('public/assets/js/app.js'));

        self::assertStringContainsString("document.querySelector('.admin-nav-scrim')", $script);
        self::assertStringContainsString("adminScrim?.addEventListener('click'", $script);
        self::assertStringContainsString("event.key === 'Escape'", $script);
        self::assertStringContainsString("event.key === 'Tab' && sidebar.classList.contains('open')", $script);
        self::assertStringContainsString("document.querySelector('.admin-main')?.removeAttribute('inert')", $script);
    }
}
