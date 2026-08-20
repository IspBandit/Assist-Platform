<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\Api\AdminApiDuplicateService;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminApiDuplicateWorkflowTest extends TestCase
{
    public function testCheckRejectsInvalidPairWithoutDatabase(): void
    {
        $svc = new AdminApiDuplicateService();
        $request = new Request([], [], ['REQUEST_METHOD' => 'POST'], []);

        try {
            $svc->check(['record_a_id' => 1, 'record_b_id' => 1], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
        }

        try {
            $svc->check(['record_a_id' => 0, 'record_b_id' => 2], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
        }
    }

    public function testMergeDryRunReturnsBeforeTransaction(): void
    {
        $ref = new ReflectionClass(AdminApiDuplicateService::class);
        $source = (string) file_get_contents((string) $ref->getFileName());

        self::assertStringContainsString("\$dryRun = self::boolish(\$input['dry_run'] ?? false)", $source);
        self::assertMatchesRegularExpression(
            '/if \(\$dryRun\) \{\s*return \$preview;/s',
            $source
        );
        self::assertStringContainsString('soft_delete_absorbed_provider', $source);
        self::assertStringContainsString('beginTransaction', $source);
        self::assertLessThan(
            strpos($source, 'beginTransaction'),
            (int) strpos($source, 'if ($dryRun)'),
            'dry_run must short-circuit before beginTransaction'
        );
    }

    public function testMergeIsHumanOnlyScope(): void
    {
        self::assertContains('duplicates:merge', AdminApiScopes::ALL);
        self::assertContains('duplicates:merge', AdminApiScopes::NEVER_SERVICE);
    }
}
