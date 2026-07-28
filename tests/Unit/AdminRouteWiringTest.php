<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminRouteWiringTest extends TestCase
{
    /** @return iterable<string,array{string,string}> */
    public static function controllerActions(): iterable
    {
        $source = (string) file_get_contents(base_path('routes/admin.php'));
        preg_match_all(
            "/\\\$router->(?:get|post|put|patch|delete)\\('(?:[^']*)',\\s*'([^']+)'/",
            $source,
            $matches
        );

        foreach (array_unique($matches[1] ?? []) as $target) {
            [$controller, $method] = explode('@', $target, 2);
            yield $target => ['App\\Controllers\\' . $controller, $method];
        }
    }

    #[DataProvider('controllerActions')]
    public function testEveryAdminRouteTargetsARealControllerAction(string $controller, string $method): void
    {
        self::assertTrue(class_exists($controller), "Admin route controller {$controller} does not exist.");
        self::assertTrue(method_exists($controller, $method), "Admin route action {$controller}@{$method} does not exist.");
    }

    public function testEveryDashboardNavigationDestinationHasAGetRoute(): void
    {
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        preg_match_all("/'(\\/admin(?:\\/[^']*)?)'/", $layout, $matches);

        $routes = (string) file_get_contents(base_path('routes/admin.php'));
        preg_match_all("/\\\$router->get\\('([^']*)'/", $routes, $routeMatches);
        $getPaths = array_map(
            static fn (string $path): string => '/admin' . $path,
            $routeMatches[1] ?? []
        );

        foreach (array_unique($matches[1] ?? []) as $destination) {
            self::assertContains($destination, $getPaths, "Dashboard navigation destination {$destination} is not wired to a GET route.");
        }
    }

    public function testEmailDeliveryTestUsesTheActiveTransportRoute(): void
    {
        $routes = (string) file_get_contents(base_path('routes/admin.php'));
        $view = (string) file_get_contents(base_path('app/Views/admin/email-templates/index.php'));

        self::assertStringContainsString("'/email-templates/delivery-test'", $routes);
        self::assertStringContainsString('Delivery test', $view);
        self::assertStringNotContainsString('GoDaddy', $view);
        self::assertStringNotContainsString('cPanel webmail', $view);
    }
}
