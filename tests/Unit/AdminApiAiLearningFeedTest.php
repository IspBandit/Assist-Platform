<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiAiUsageService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class AdminApiAiLearningFeedTest extends TestCase
{
    public function testUsageFeedLinksQuestionsInterpretationsAndAnswerSummaries(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(AdminApiAiUsageService::class))->getFileName());
        self::assertStringContainsString('LEFT JOIN assist_searches', $source);
        self::assertStringContainsString("'question'", $source);
        self::assertStringContainsString("'interpretation'", $source);
        self::assertStringContainsString("'answer_summary'", $source);
    }

    public function testResponseSnapshotMigrationIsPrivacyBounded(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/migrations/121_assist_search_response_summary.sql');
        self::assertStringContainsString('response_summary JSON', $sql);
        self::assertStringNotContainsString('latitude', strtolower($sql));
        self::assertStringNotContainsString('longitude', strtolower($sql));
    }
}
