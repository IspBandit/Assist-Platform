<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\ErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    public function testProductionErrorBodyDoesNotExposeExceptionMessage(): void
    {
        Config::set('app.debug', false);
        Config::set('security.headers', ['X-Content-Type-Options' => 'nosniff']);
        Config::set('security.csp', "default-src 'self'");
        Config::set('security.session.secure', false);

        ob_start();
        ErrorHandler::handleException(new RuntimeException('secret-database-password'));
        $body = (string) ob_get_clean();

        self::assertStringNotContainsString('secret-database-password', $body);
        self::assertStringContainsString('server error occurred', strtolower($body));
    }
}
