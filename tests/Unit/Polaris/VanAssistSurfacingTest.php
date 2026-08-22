<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\VanAssistSurfacingService;
use PHPUnit\Framework\TestCase;

final class VanAssistSurfacingTest extends TestCase
{
    public function testRelatedServicesShapeAndDisclaimer(): void
    {
        $service = new VanAssistSurfacingService();
        $result = $service->relatedServices(null, 3);

        self::assertSame('vanassist', $result['brand']);
        self::assertIsArray($result['providers']);
        self::assertStringContainsString('does not duplicate', $result['disclaimer']);
        self::assertLessThanOrEqual(3, count($result['providers']));

        foreach ($result['providers'] as $provider) {
            self::assertArrayHasKey('vanassist_url', $provider);
            self::assertStringContainsString('/providers/', (string) $provider['vanassist_url']);
        }
    }
}
