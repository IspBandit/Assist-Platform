<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use App\Services\Turnstile;
use PHPUnit\Framework\TestCase;

final class TurnstileWiringTest extends TestCase
{
    public function testDisabledTurnstileDoesNotBlockLocalForms(): void
    {
        $previous = Config::get('security.turnstile.enabled');
        Config::set('security.turnstile.enabled', false);
        try {
            self::assertTrue(Turnstile::verify(new Request([], [], ['REMOTE_ADDR' => '127.0.0.1'], [])));
        } finally {
            Config::set('security.turnstile.enabled', $previous);
        }
    }

    public function testHighRiskPublicPostRoutesUseServerVerification(): void
    {
        $routes = (string) file_get_contents(base_path('routes/web.php'));
        $auth = (string) file_get_contents(base_path('routes/auth.php'));
        $service = (string) file_get_contents(base_path('app/Services/Turnstile.php'));

        foreach (['public.provider-interest', 'public.provider-claim', 'public.park-application', 'public.assistance-request'] as $scope) {
            self::assertMatchesRegularExpression('/rate:' . preg_quote($scope, '/') . "[^']*', 'turnstile'/", $routes);
        }
        self::assertStringContainsString("rate:auth.register,10,3600,3600', 'turnstile'", $auth);
        self::assertStringContainsString('/turnstile/v0/siteverify', $service);
        self::assertStringContainsString("'remoteip' => \$request->ip()", $service);
    }
}
