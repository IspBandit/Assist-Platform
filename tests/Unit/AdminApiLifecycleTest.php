<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiLifecycle;
use PHPUnit\Framework\TestCase;

final class AdminApiLifecycleTest extends TestCase
{
    public function testProviderLifecycleMapping(): void
    {
        self::assertSame('draft', AdminApiLifecycle::forProvider(['status' => 'draft']));
        self::assertSame('pending_review', AdminApiLifecycle::forProvider(['status' => 'pending']));
        self::assertSame('rejected', AdminApiLifecycle::forProvider(['status' => 'rejected']));
        self::assertSame('inactive', AdminApiLifecycle::forProvider(['status' => 'suspended']));
        self::assertSame('published', AdminApiLifecycle::forProvider([
            'status' => 'active',
            'listing_status' => 'active',
            'search_visible' => 1,
        ]));
        self::assertSame('unpublished', AdminApiLifecycle::forProvider([
            'status' => 'active',
            'listing_status' => 'active',
            'search_visible' => 0,
        ]));
    }

    public function testStayLifecycleMapping(): void
    {
        self::assertSame('published', AdminApiLifecycle::forStay([
            'status' => 'active',
            'public_page_enabled' => 1,
        ]));
        self::assertSame('unpublished', AdminApiLifecycle::forStay([
            'status' => 'active',
            'public_page_enabled' => 0,
        ]));
        self::assertSame('pending_review', AdminApiLifecycle::forStay(['status' => 'pending']));
    }

    public function testFilterClauses(): void
    {
        $published = AdminApiLifecycle::providerFilterClause('published');
        self::assertNotNull($published);
        self::assertStringContainsString('search_visible', $published['clause']);

        $stayPublished = AdminApiLifecycle::stayFilterClause('published');
        self::assertNotNull($stayPublished);
        self::assertStringContainsString('public_page_enabled', $stayPublished['clause']);

        self::assertNull(AdminApiLifecycle::providerFilterClause('nope'));
    }
}
