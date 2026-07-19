<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Geo;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testSlug(): void
    {
        $this->assertSame('central-queensland', str_slug('Central Queensland'));
        $this->assertSame('wide-bay-burnett', str_slug('Wide Bay–Burnett'));
        $this->assertSame('gladstone', str_slug('  Gladstone!  '));
    }

    public function testEscape(): void
    {
        $this->assertSame('&lt;b&gt;', e('<b>'));
        $this->assertSame('', e(null));
    }

    public function testUrl(): void
    {
        $this->assertSame('http://localhost/find', url('find'));
        $this->assertSame('http://localhost/find', url('/find'));
    }

    public function testDistanceFilterUsesSubmittedValue(): void
    {
        $filter = Geo::resolveDistanceFilter('100', true);

        $this->assertSame('km', $filter['scope']);
        $this->assertSame(100, $filter['km']);
    }

    public function testDistanceFilterDefaultsToTownWhenOmitted(): void
    {
        $filter = Geo::resolveDistanceFilter(null, true);

        $this->assertSame(Geo::SCOPE_TOWN, $filter['scope']);
        $this->assertNull($filter['km']);
    }

    public function testRedirectLocationAllowsContactSchemesAndRejectsScriptUrls(): void
    {
        $this->assertSame('tel:+61712345678', redirect_location('tel:+61712345678'));
        $this->assertSame('mailto:test@example.com', redirect_location('mailto:test@example.com'));

        $this->expectException(\InvalidArgumentException::class);
        redirect_location('javascript:alert(1)');
    }

    public function testBackUrlRejectsExternalReferer(): void
    {
        $this->assertSame('http://localhost/', safe_back_url('https://attacker.example/phish'));
        $this->assertSame('http://localhost/account', safe_back_url('http://localhost/account'));
    }
}
