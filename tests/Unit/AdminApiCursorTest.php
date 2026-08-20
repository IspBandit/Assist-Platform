<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AdminApiException;
use App\Services\Api\AdminApiCursor;
use PHPUnit\Framework\TestCase;

final class AdminApiCursorTest extends TestCase
{
    public function testLimitDefaultsAndBounds(): void
    {
        self::assertSame(25, AdminApiCursor::limit(null));
        self::assertSame(10, AdminApiCursor::limit('10'));

        try {
            AdminApiCursor::limit('0');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
        }

        try {
            AdminApiCursor::limit('abc');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
        }
    }

    public function testEncodeDecodeRoundTrip(): void
    {
        $cursor = AdminApiCursor::encode(42);
        self::assertSame(42, AdminApiCursor::decode($cursor));
        self::assertNull(AdminApiCursor::decode(null));
        self::assertNull(AdminApiCursor::decode(''));
    }

    public function testDecodeRejectsMalformedCursor(): void
    {
        try {
            AdminApiCursor::decode('not-a-cursor');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('cursor', $e->fields());
        }
    }

    public function testPageDetectsHasMoreAndNextCursor(): void
    {
        $rows = [
            ['id' => 5],
            ['id' => 4],
            ['id' => 3],
        ];
        $page = AdminApiCursor::page($rows, 2, static fn (array $row): int => (int) $row['id']);

        self::assertTrue($page['has_more']);
        self::assertSame(2, $page['count']);
        self::assertSame(4, AdminApiCursor::decode($page['next_cursor']));
        self::assertCount(2, $page['items']);
    }
}
