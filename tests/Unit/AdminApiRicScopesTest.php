<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;

final class AdminApiRicScopesTest extends TestCase
{
    public function testRicServiceScopesAreServiceSafeAndNonEmpty(): void
    {
        $scopes = AdminApiScopes::RIC_SERVICE;
        self::assertNotSame([], $scopes);
        self::assertContains('imports:write', $scopes);
        self::assertContains('analytics:read', $scopes);
        self::assertContains('claims:read', $scopes);
        self::assertContains('datasets:read', $scopes);
        self::assertContains('facilities:read', $scopes);
        self::assertContains('sync:read', $scopes);
        self::assertContains('recycle_bin:restore', $scopes);
        self::assertNotContains('recycle_bin:purge', $scopes);
        self::assertNotContains('mfa:verify', $scopes);

        AdminApiScopes::rejectForbiddenForService($scopes);
        self::assertSame($scopes, AdminApiScopes::normalize($scopes));
    }

    public function testBootstrapScriptIsCliGuarded(): void
    {
        $source = (string) file_get_contents(base_path('scripts/admin-api-create-ric-service-account.php'));
        self::assertStringContainsString("PHP_SAPI !== 'cli'", $source);
        self::assertStringContainsString('AdminApiScopes::RIC_SERVICE', $source);
        self::assertStringContainsString('--i-understand-production', $source);
        self::assertFileExists(base_path('scripts/admin-api-probe.php'));
    }
}
