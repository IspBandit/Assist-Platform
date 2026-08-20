<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiSearchGapService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminApiSearchGapDualSourceWireTest extends TestCase
{
    public function testSearchGapServiceImportsDualSourceHelper(): void
    {
        $ref = new ReflectionClass(AdminApiSearchGapService::class);
        $source = (string) file_get_contents((string) $ref->getFileName());

        self::assertStringContainsString(
            'App\\Platform\\AiSearch\\Knowledge\\SearchGapDualSource',
            $source
        );
        self::assertStringContainsString('KnowledgeGapService', $source);
        self::assertStringContainsString('loadKnowledgeGapItems', $source);
        self::assertStringContainsString('SOURCE_PROVIDER', $source);
        self::assertStringContainsString('meta.source is "dual"', $source);
    }
}
