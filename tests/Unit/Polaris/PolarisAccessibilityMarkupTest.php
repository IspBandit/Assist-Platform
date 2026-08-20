<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use PHPUnit\Framework\TestCase;

final class PolarisAccessibilityMarkupTest extends TestCase
{
    public function testCompareAndModelTablesHaveCaptionsAndDiffText(): void
    {
        $root = dirname(__DIR__, 3);
        $compare = (string) file_get_contents($root . '/app/Views/polaris/compare.php');
        self::assertStringContainsString('<caption class="sr-only">', $compare);
        self::assertStringContainsString('polaris-diff-marker', $compare);
        self::assertStringContainsString('Differs', $compare);
        self::assertStringContainsString('role="status"', $compare);

        $model = (string) file_get_contents($root . '/app/Views/polaris/model.php');
        self::assertStringContainsString('<caption class="sr-only">', $model);
        self::assertStringContainsString('aria-labelledby="polaris-year-selector-label"', $model);
        self::assertStringContainsString('aria-current="true"', $model);
    }

    public function testEmptyStatesAnnounceStatus(): void
    {
        $root = dirname(__DIR__, 3);
        foreach (['browse.php', 'saved.php', 'find.php', 'account-alerts.php', 'account-comparisons.php'] as $file) {
            $html = (string) file_get_contents($root . '/app/Views/polaris/' . $file);
            self::assertStringContainsString('role="status"', $html, $file . ' should announce empty/status messages');
        }
    }

    public function testAccessibilityQaRemainsConditional(): void
    {
        $root = dirname(__DIR__, 3);
        $doc = (string) file_get_contents($root . '/docs/polaris/ACCESSIBILITY_QA.md');
        self::assertStringContainsString('CONDITIONAL', $doc);
        self::assertStringContainsString('<caption>', $doc);
        self::assertStringNotContainsString('WCAG 2.2 AA PASS', $doc);
    }
}
