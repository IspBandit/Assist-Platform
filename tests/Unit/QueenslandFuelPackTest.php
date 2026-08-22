<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QueenslandFuelPackTest extends TestCase
{
    public function testEmeraldHasEightCurrentOfficialFuelSites(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/localtorque/providers-publishable.json';
        $providers = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $emerald = array_values(array_filter($providers, static fn (array $provider): bool =>
            ($provider['state'] ?? null) === 'QLD'
            && strcasecmp((string) ($provider['town'] ?? ''), 'Emerald') === 0
            && in_array('fuel-station', (array) ($provider['categories'] ?? []), true)
        ));

        self::assertCount(8, $emerald);
        $siteIds = array_map(
            static fn (array $provider): string => substr((string) $provider['id'], strlen('qld-fuel-')),
            $emerald
        );
        sort($siteIds);
        self::assertSame(
            ['61402238', '61402740', '61402901', '61451637', '61470427', '61477053', '61477756', '61477852'],
            $siteIds
        );
        foreach ($emerald as $provider) {
            self::assertSame('qld-fuel-reporting', $provider['source']);
            self::assertSame('CC BY 4.0', $provider['source_licence']);
            self::assertTrue($provider['publishable']);
        }
    }
}
