<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Geo;
use App\Models\Town;
use App\Services\Mailer;
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

    public function testAssetUsesCurrentReleaseEndpoint(): void
    {
        $this->assertStringStartsWith(
            'http://localhost/runtime-assets/css/app.css?v=',
            asset('css/app.css')
        );
        $this->assertStringStartsWith(
            'http://localhost/runtime-assets/brands/vanassist/symbol-v2.svg?v=',
            asset('/assets/brands/vanassist/symbol-v2.svg')
        );
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

    public function testStayDistanceDefaultsToOneHundredAndFiftyKilometres(): void
    {
        $this->assertSame(150, Geo::stayDistance(null));
        $this->assertSame(50, Geo::stayDistance('50'));
        $this->assertSame(150, Geo::stayDistance('not-valid'));
    }

    public function testMapDirectionsPreferCoordinatesAndKeepAddressFallback(): void
    {
        $this->assertSame('-27.47,153.025', map_destination(-27.47, 153.025, ['Brisbane']));
        $this->assertSame('1 Main Street, Gympie, QLD', map_destination(null, null, ['1 Main Street', 'Gympie', 'QLD']));
        $this->assertStringContainsString('destination=1%20Main%20Street', map_directions_url('1 Main Street'));
    }

    public function testDirectionsRequireARoutableStreetAddress(): void
    {
        $this->assertFalse(is_navigable_street_address('Sydney'));
        $this->assertFalse(is_navigable_street_address('Mobile service only'));
        $this->assertTrue(is_navigable_street_address('10 Main Street'));
        $this->assertTrue(is_navigable_street_address('Bruce Highway'));
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

    public function testTownSearchAcceptsCurrentAndLegacyStateLabels(): void
    {
        $this->assertSame(
            ['term' => 'Gladstone', 'state' => 'QLD'],
            Town::parseSearchQuery('Gladstone, QLD')
        );
        $this->assertSame(
            ['term' => 'Gladstone', 'state' => 'QLD'],
            Town::parseSearchQuery('Gladstone / QLD')
        );
        $this->assertSame(
            ['term' => 'Emerald', 'state' => 'QLD'],
            Town::parseSearchQuery('Emerald QLD')
        );
        $this->assertSame(
            ['term' => 'Parramatta', 'state' => 'NSW'],
            Town::parseSearchQuery('Parramatta, New South Wales')
        );
        $this->assertSame(
            ['term' => 'Perth', 'state' => 'WA'],
            Town::parseSearchQuery('  Perth   Western Australia  ')
        );
        $this->assertSame(
            ['term' => '4720', 'state' => 'QLD'],
            Town::parseSearchQuery('Emerald QLD 4720')
        );
        $this->assertSame(
            ['term' => '2150', 'state' => null],
            Town::parseSearchQuery('Parramatta 2150')
        );
        $this->assertSame(
            ['term' => 'Victoria Point', 'state' => null],
            Town::parseSearchQuery('Victoria Point')
        );
    }

    public function testGraphTransportDoesNotRequireAnSmtpHost(): void
    {
        $this->assertTrue(Mailer::transportConfigured([
            'driver' => 'graph',
            'graph_tenant_id' => 'tenant',
            'graph_client_id' => 'client',
            'graph_mailbox' => 'operations@vanassist.com.au',
            'host' => '',
        ]));
    }

    public function testBrandGraphMailboxOverridesFallbackWhenProvisioned(): void
    {
        $graph = [
            'mailbox' => 'operations@vanassist.com.au',
            'mailboxes' => [
                'vanassist' => 'support@vanassist.com.au',
                'towsmart' => 'support@towsmart.com.au',
                'trailerwise' => '',
            ],
        ];

        $this->assertSame(
            'support@towsmart.com.au',
            Mailer::graphMailboxForBrand($graph, 'towsmart')
        );
        $this->assertSame(
            'operations@vanassist.com.au',
            Mailer::graphMailboxForBrand($graph, 'trailerwise')
        );
    }
}
