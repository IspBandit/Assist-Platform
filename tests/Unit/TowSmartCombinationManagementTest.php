<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class TowSmartCombinationManagementTest extends TestCase
{
    public function testSavedCombinationRoutesRemainInsideAuthenticatedAccountGroup(): void
    {
        $routes = $this->source('routes/account.php');

        self::assertStringContainsString("'middleware' => ['headers', 'csrf', 'auth']", $routes);
        self::assertStringContainsString("/towing-combinations/{id}'", $routes);
        self::assertStringContainsString("/towing-combinations/{id}/remove'", $routes);
    }

    public function testReadAndDeleteRequireUserAndBrandOwnership(): void
    {
        $controller = $this->source('app/Controllers/Site/TowSmartController.php');

        self::assertStringContainsString('WHERE id = ? AND user_id = ? AND brand_id = ?', $controller);
        self::assertStringContainsString('DELETE FROM towing_combinations WHERE id = ? AND user_id = ? AND brand_id = ?', $controller);
        self::assertStringContainsString("current_brand()->databaseId()", $controller);
        self::assertStringContainsString("current_user()['id']", $controller);
    }

    public function testSavedReportIsPrivateAndRetainsGuidanceBoundary(): void
    {
        $controller = $this->source('app/Controllers/Site/TowSmartController.php');
        $view = $this->source('app/Views/towsmart/combination.php');

        self::assertStringContainsString("'metaRobots' => 'noindex,nofollow'", $controller);
        self::assertStringContainsString('not current certification', $view);
        self::assertStringContainsString("csrf_field()", $view);
        self::assertStringNotContainsString('onsubmit=', $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }
}
