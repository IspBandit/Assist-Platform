<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Platform\Support\RequestContext;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    protected function tearDown(): void
    {
        RequestContext::clear();
    }

    public function testAcceptsWellFormedCallerCorrelationId(): void
    {
        $request = new Request([], [], ['HTTP_X_REQUEST_ID' => 'edge-12345678'], []);
        self::assertSame('edge-12345678', RequestContext::begin($request));
    }

    public function testReplacesMalformedCorrelationId(): void
    {
        $request = new Request([], [], ['HTTP_X_REQUEST_ID' => "bad\nheader"], []);
        $id = RequestContext::begin($request);

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $id);
    }
}
