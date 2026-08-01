<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminApiRouteWiringTest extends TestCase
{
    /** @return iterable<string,array{string,string}> */
    public static function controllerActions(): iterable
    {
        $source = (string) file_get_contents(base_path('routes/api_v1_admin.php'));
        preg_match_all(
            "/\\\$router->(?:get|post|put|patch|delete)\\('(?:[^']*)',\\s*'([^']+)'/",
            $source,
            $matches
        );

        foreach (array_unique($matches[1] ?? []) as $target) {
            $target = str_replace('\\\\', '\\', $target);
            [$controller, $method] = explode('@', $target, 2);
            yield $target => ['App\\Controllers\\' . $controller, $method];
        }
    }

    #[DataProvider('controllerActions')]
    public function testEveryAdminApiRouteTargetsARealControllerAction(string $controller, string $method): void
    {
        $relative = substr($controller, strlen('App\\Controllers\\'));
        $file = base_path('app/Controllers/' . str_replace('\\', '/', $relative) . '.php');
        self::assertFileExists($file, "Admin API controller file missing for {$controller}");
        require_once $file;
        self::assertTrue(
            class_exists($controller, false),
            "Admin API route controller {$controller} does not exist."
        );
        self::assertTrue(
            method_exists($controller, $method),
            "Admin API route action {$controller}@{$method} does not exist."
        );
    }

    public function testKernelLoadsAdminApiRouteFile(): void
    {
        $kernel = (string) file_get_contents(base_path('app/Core/Kernel.php'));
        self::assertStringContainsString("'api_v1_admin'", $kernel);
        self::assertStringContainsString('admin_api_enabled', $kernel);
        self::assertStringContainsString('admin_api_bearer', $kernel);
    }
}
