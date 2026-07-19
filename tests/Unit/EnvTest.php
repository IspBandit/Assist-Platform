<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Env;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class EnvTest extends TestCase
{
    public function testProcessEnvironmentOverridesDotEnvValues(): void
    {
        $vars = new ReflectionProperty(Env::class, 'vars');
        /** @var array<string,string> $original */
        $original = $vars->getValue();
        $vars->setValue(null, array_merge($original, ['ASSIST_TEST_PRECEDENCE' => 'file-value']));
        putenv('ASSIST_TEST_PRECEDENCE=process-value');

        try {
            self::assertSame('process-value', Env::get('ASSIST_TEST_PRECEDENCE'));
        } finally {
            putenv('ASSIST_TEST_PRECEDENCE');
            $vars->setValue(null, $original);
        }
    }
}
