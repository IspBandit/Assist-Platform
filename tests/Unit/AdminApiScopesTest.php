<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AdminApiException;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;

final class AdminApiScopesTest extends TestCase
{
    public function testNormalizeDedupesAndFiltersUnknownScopes(): void
    {
        $scopes = AdminApiScopes::normalize([
            'providers:read',
            'providers:read',
            ' unknown ',
            'stays:read',
            '',
        ]);

        self::assertSame(['providers:read', 'stays:read'], $scopes);
    }

    public function testRejectForbiddenForServiceThrowsForNeverScopes(): void
    {
        try {
            AdminApiScopes::rejectForbiddenForService(['providers:read', 'recycle_bin:purge']);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
            self::assertArrayHasKey('scopes', $e->fields());
        }
    }

    public function testSubsetAllowsGrantedScopes(): void
    {
        $result = AdminApiScopes::subset(
            ['providers:read', 'drafts:write'],
            AdminApiScopes::DEFAULT_SERVICE
        );

        self::assertSame(['providers:read', 'drafts:write'], $result);
    }

    public function testSubsetReturnsGrantedWhenRequestedEmpty(): void
    {
        self::assertSame(
            AdminApiScopes::DEFAULT_SERVICE,
            AdminApiScopes::subset([], AdminApiScopes::DEFAULT_SERVICE)
        );
    }

    public function testSubsetRejectsExcessScopes(): void
    {
        try {
            AdminApiScopes::subset(['providers:write'], AdminApiScopes::DEFAULT_SERVICE);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(403, $e->getStatusCode());
            self::assertSame('forbidden', $e->errorCode());
        }
    }

    public function testCatalogMarksNeverServiceScopes(): void
    {
        $catalog = AdminApiScopes::catalog();

        self::assertFalse($catalog['recycle_bin:purge']['service']);
        self::assertFalse($catalog['service_accounts:admin']['service']);
        self::assertTrue($catalog['providers:read']['service']);
    }

    public function testDefaultServiceExcludesNeverScopes(): void
    {
        self::assertSame([], array_intersect(AdminApiScopes::DEFAULT_SERVICE, AdminApiScopes::NEVER_SERVICE));
    }
}
