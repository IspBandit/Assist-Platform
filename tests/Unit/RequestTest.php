<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    protected function tearDown(): void
    {
        Config::set('security.trusted_proxies', []);
    }

    public function testIgnoresForwardedIpFromUntrustedPeer(): void
    {
        Config::set('security.trusted_proxies', ['10.0.0.10']);
        $request = new Request([], [], [
            'REMOTE_ADDR' => '198.51.100.20',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.8',
        ], []);

        self::assertSame('198.51.100.20', $request->ip());
    }

    public function testAcceptsValidForwardedIpFromTrustedPeer(): void
    {
        Config::set('security.trusted_proxies', ['10.0.0.10']);
        $request = new Request([], [], [
            'REMOTE_ADDR' => '10.0.0.10',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.8, 10.0.0.20',
        ], []);

        self::assertSame('203.0.113.8', $request->ip());
    }

    public function testAcceptsForwardedIpFromTrustedProxyCidr(): void
    {
        Config::set('security.trusted_proxies', ['172.20.0.0/16']);
        $request = new Request([], [], [
            'REMOTE_ADDR' => '172.20.0.4',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 172.18.0.2',
        ], []);

        self::assertSame('203.0.113.9', $request->ip());
    }

    public function testRejectsForwardedIpOutsideTrustedProxyCidr(): void
    {
        Config::set('security.trusted_proxies', ['172.20.0.0/16']);
        $request = new Request([], [], [
            'REMOTE_ADDR' => '172.21.0.4',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
        ], []);

        self::assertSame('172.21.0.4', $request->ip());
    }
}
