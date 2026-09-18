<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use PHPUnit\Framework\TestCase;

final class AskExternalMapPinsTest extends TestCase
{
    public function testAskResultsViewMapsExternalLiveHitsWithoutNumericIds(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/public/assist-search.php'));
        self::assertStringContainsString("foreach (\$result->externals as \$index => \$item)", $view);
        self::assertStringContainsString("'external-' . \$safeId", $view);
        self::assertStringContainsString('assist_source_record_id', $view);
        self::assertStringContainsString('id="assist-result-', $view);
    }
}
