<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\StayContactActionController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class StayContactActionControllerTest extends TestCase
{
    public function testPhoneWebsiteBookingAndDirectionsTargetsResolve(): void
    {
        $method = new ReflectionMethod(StayContactActionController::class, 'targetFor');
        $method->setAccessible(true);
        $controller = new StayContactActionController();

        $park = [
            'phone' => '(07) 1234 5678',
            'email' => 'park@example.com',
            'website' => 'example-park.com.au',
            'booking_url' => 'https://book.example.com/stay',
            'latitude' => -23.5,
            'longitude' => 148.1,
            'address' => '1 Lagoon Rd',
            'town_name' => 'Emerald',
        ];

        self::assertSame('tel:0712345678', $method->invoke($controller, 'phone', $park));
        self::assertSame('mailto:park@example.com', $method->invoke($controller, 'email', $park));
        self::assertSame('https://example-park.com.au', $method->invoke($controller, 'website', $park));
        self::assertSame('https://book.example.com/stay', $method->invoke($controller, 'booking', $park));
        $directions = $method->invoke($controller, 'directions', $park);
        self::assertIsString($directions);
        self::assertNotSame('', $directions);
    }

    public function testMissingContactDetailsFailClosedToNull(): void
    {
        $method = new ReflectionMethod(StayContactActionController::class, 'targetFor');
        $method->setAccessible(true);
        $controller = new StayContactActionController();

        self::assertNull($method->invoke($controller, 'phone', []));
        self::assertNull($method->invoke($controller, 'email', ['email' => 'not-an-email']));
        self::assertNull($method->invoke($controller, 'website', ['website' => '']));
        self::assertNull($method->invoke($controller, 'booking', []));
        self::assertNull($method->invoke($controller, 'directions', []));
        self::assertNull($method->invoke($controller, 'unknown', ['phone' => '0400000000']));
    }

    public function testStayContactRouteAndRecorderAreWired(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        $park = (string) file_get_contents(base_path('app/Views/public/park.php'));
        $recorder = (string) file_get_contents(base_path('app/Services/Demand/DemandRecorder.php'));
        $tracker = (string) file_get_contents(base_path('app/Services/Demand/ActivityTracker.php'));
        $migration = (string) file_get_contents(base_path('database/migrations/142_stay_contact_actions.sql'));
        $insights = (string) file_get_contents(base_path('app/Services/Demand/WebsiteInsightsService.php'));

        self::assertStringContainsString("\$router->get('/go/stay/{action}/{slug}'", $routes);
        self::assertStringContainsString("\$router->get('/go/{action}/{slug}'", $routes);
        self::assertLessThan(
            strpos($routes, "\$router->get('/go/{action}/{slug}'"),
            strpos($routes, "\$router->get('/go/stay/{action}/{slug}'"),
            'Stay go route must be registered before the provider go catch-all'
        );
        self::assertStringContainsString("url('go/stay/phone/' . \$park['slug'])", $park);
        self::assertStringContainsString("url('go/stay/website/' . \$park['slug'])", $park);
        self::assertStringContainsString("url('go/stay/directions/' . \$park['slug'])", $park);
        self::assertStringContainsString("url('go/stay/booking/' . \$park['slug'])", $park);
        self::assertStringContainsString('recordStayContactAction', $recorder);
        self::assertStringContainsString('stay_contact_actions', $recorder);
        self::assertStringContainsString("'stay_phone_clicked'", $tracker);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS stay_contact_actions', $migration);
        self::assertStringContainsString('stay_contact_actions', $insights);
    }
}
