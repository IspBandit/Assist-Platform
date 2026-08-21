<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Migrator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MigratorTest extends TestCase
{
    public function testChecksumVariantsAcceptOnlyEquivalentLineEndings(): void
    {
        $method = new ReflectionMethod(Migrator::class, 'checksumVariants');
        $lf = "CREATE TABLE example (id INT);\nINSERT INTO example VALUES (1);\n";
        $crlf = str_replace("\n", "\r\n", $lf);

        /** @var array<int,string> $variants */
        $variants = $method->invoke(null, $lf);

        $this->assertContains(hash('sha256', $lf), $variants);
        $this->assertContains(hash('sha256', $crlf), $variants);
        $this->assertNotContains(hash('sha256', str_replace('(1)', '(2)', $lf)), $variants);
    }
}
