<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Platform\Support\EnvironmentValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnvironmentValidatorTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $original = [];

    /** @var array<int,string> */
    private array $keys = [
        'app.env', 'app.url', 'app.key', 'app.debug', 'app.release', 'app.launch_mode',
        'database.name', 'database.user', 'database.port', 'security.session.secure',
        'security.trusted_proxies', 'security.turnstile.enabled', 'security.turnstile.site_key',
        'security.turnstile.secret_key', 'brands.allow_development_fallback', 'brands.strict_hosts',
        'brands.registry',
        'billing.enabled', 'mail.driver', 'mail.host', 'mail.encryption', 'mail.from_address',
        'mail.graph.tenant_id', 'mail.graph.client_id', 'mail.graph.certificate_path',
        'mail.graph.private_key_path', 'mail.graph.mailbox',
    ];

    protected function setUp(): void
    {
        foreach ($this->keys as $key) {
            $this->original[$key] = Config::get($key);
        }
        $this->validLocalConfiguration();
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            Config::set($key, $value);
        }
    }

    public function testValidLocalLogTransportPasses(): void
    {
        EnvironmentValidator::validateInstalledApplication();
        self::addToAssertionCount(1);
    }

    public function testInvalidIntegrationConfigurationIsReportedTogether(): void
    {
        Config::set('database.port', 70000);
        Config::set('app.launch_mode', 'surprise');
        Config::set('mail.driver', 'smtp');
        Config::set('mail.host', '');
        Config::set('mail.encryption', 'none');
        Config::set('mail.from_address', 'not-an-email');
        Config::set('security.turnstile.enabled', true);

        try {
            EnvironmentValidator::validateInstalledApplication();
            self::fail('Invalid environment should fail closed.');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('DB_PORT', $e->getMessage());
            self::assertStringContainsString('LAUNCH_MODE', $e->getMessage());
            self::assertStringContainsString('MAIL_HOST', $e->getMessage());
            self::assertStringContainsString('MAIL_ENCRYPTION', $e->getMessage());
            self::assertStringContainsString('MAIL_FROM_ADDRESS', $e->getMessage());
            self::assertStringContainsString('TURNSTILE_SITE_KEY', $e->getMessage());
        }
    }

    public function testProductionRequiresReleaseAndRealMailTransport(): void
    {
        Config::set('app.env', 'production');
        Config::set('app.url', 'https://vanassist.com.au');
        Config::set('app.key', str_repeat('k', 32));
        Config::set('app.release', '');
        Config::set('security.session.secure', true);
        Config::set('brands.strict_hosts', true);
        Config::set('mail.driver', 'log');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('APP_RELEASE is required in production');
        EnvironmentValidator::validateInstalledApplication();
    }

    private function validLocalConfiguration(): void
    {
        Config::set('app.env', 'local');
        Config::set('app.url', 'http://vanassist.test');
        Config::set('app.launch_mode', 'private');
        Config::set('database.name', 'assist_test');
        Config::set('database.user', 'assist_test');
        Config::set('database.port', 3306);
        Config::set('billing.enabled', false);
        Config::set('security.trusted_proxies', []);
        Config::set('security.turnstile.enabled', false);
        Config::set('mail.driver', 'log');
        $brands = require base_path('config/brands.php');
        Config::set('brands.registry', $brands['registry']);
    }
}
