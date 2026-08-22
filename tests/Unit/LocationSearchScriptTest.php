<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LocationSearchScriptTest extends TestCase
{
    public function testTypedLocationClearsPreviouslyResolvedGpsCoordinates(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/app.js');

        self::assertIsString($script);
        self::assertStringContainsString("loc.addEventListener('input'", $script);
        self::assertStringContainsString("setFormField(form, 'lat', '');", $script);
        self::assertStringContainsString("setFormField(form, 'lng', '');", $script);
        self::assertStringContainsString("setLocationStatus(form, '', false);", $script);
        self::assertStringContainsString('input[type="hidden"][name="town"]', $script);
        self::assertStringContainsString("if (resolvedTown) { resolvedTown.value = ''; }", $script);
        self::assertStringNotContainsString("form.querySelector('#town_id, input[name=\"town\"]')", $script);
    }

    public function testDirectionsUseNativeMobileMapHandlers(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/app.js');

        self::assertIsString($script);
        self::assertStringContainsString("document.querySelectorAll('[data-map-directions]')", $script);
        self::assertStringContainsString('https://maps.apple.com/?daddr=', $script);
        self::assertStringContainsString("'geo:0,0?q='", $script);
    }
}
