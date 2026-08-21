<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\DealerEnquiryService;
use PHPUnit\Framework\TestCase;

final class DealerEnquiryHandoffTest extends TestCase
{
    public function testShapeHandoffRequiresPublishedContactChannel(): void
    {
        self::assertNull(DealerEnquiryService::shapeHandoff([
            'id' => 1,
            'trading_name' => 'No Contact',
            'publication_status' => 'published',
            'email' => '',
            'website_url' => '',
        ]));

        self::assertNull(DealerEnquiryService::shapeHandoff([
            'id' => 2,
            'trading_name' => 'Draft',
            'publication_status' => 'draft',
            'email' => 'a@example.invalid',
            'website_url' => 'https://example.invalid',
        ]));

        $ok = DealerEnquiryService::shapeHandoff([
            'id' => 3,
            'trading_name' => 'Demo Coastal',
            'locality' => 'Geelong',
            'state_abbr' => 'VIC',
            'publication_status' => 'published',
            'email' => 'demo@example.invalid',
            'website_url' => 'https://example.invalid/demo',
        ]);
        self::assertNotNull($ok);
        self::assertSame('mailto:demo@example.invalid', $ok['mailto_url']);
        self::assertSame('https://example.invalid/demo', $ok['website_handoff']);
    }

    public function testInvalidEmailOrUrlIsRejected(): void
    {
        self::assertNull(DealerEnquiryService::shapeHandoff([
            'id' => 4,
            'trading_name' => 'Bad',
            'publication_status' => 'published',
            'email' => 'not-an-email',
            'website_url' => 'javascript:alert(1)',
        ]));
    }

    public function testRoutesAndViewsWireDealerHandoff(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = (string) file_get_contents($root . '/routes/web.php');
        self::assertStringContainsString("'/dealers/{id}/enquire'", $routes);
        self::assertStringContainsString('PolarisController@dealerEnquire', $routes);

        $controller = (string) file_get_contents($root . '/app/Controllers/Site/PolarisController.php');
        self::assertStringContainsString('DealerEnquiryService', $controller);
        self::assertStringContainsString('dealer_enquiry_click', $controller);
        self::assertStringContainsString("'dealers'", $controller);

        $view = (string) file_get_contents($root . '/app/Views/polaris/model.php');
        self::assertStringContainsString('Contact a dealer', $view);
        self::assertStringContainsString('does not send enquiries', $view);

        $sql = (string) file_get_contents($root . '/database/migrations/120_polaris_demo_dealer_contacts.sql');
        self::assertStringContainsString('example.invalid', $sql);
        self::assertStringContainsString('demo-horizon', $sql);
    }
}
