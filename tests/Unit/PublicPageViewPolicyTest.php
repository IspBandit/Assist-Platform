<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BotTraffic;
use App\Services\Demand\PublicPageViewPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicPageViewPolicyTest extends TestCase
{
    #[DataProvider('paths')]
    public function testOnlyGenuinePublicDocumentsAreIncluded(string $path, bool $expected): void
    {
        self::assertSame($expected, PublicPageViewPolicy::includes($path), $path);
    }

    /** @return iterable<string,array{string,bool}> */
    public static function paths(): iterable
    {
        yield 'home' => ['/', true];
        yield 'ask' => ['/ask', true];
        yield 'provider profile' => ['/providers/example-repairs', true];
        yield 'runtime javascript' => ['/runtime-assets/js/app.js', false];
        yield 'legacy asset' => ['/assets/css/app.css', false];
        yield 'manifest' => ['/manifest.webmanifest', false];
        yield 'worker' => ['/service-worker.js', false];
        yield 'admin api' => ['/api/v1/admin/health', false];
        yield 'readiness' => ['/readyz', false];
        yield 'stray image' => ['/wordmark.png', false];
    }

    public function testSyntheticReleaseMarkerIsExcludedWithoutBlockingItsRequest(): void
    {
        $before = $_SERVER['HTTP_X_ASSIST_SYNTHETIC'] ?? null;
        $_SERVER['HTTP_X_ASSIST_SYNTHETIC'] = '1';
        try {
            self::assertTrue(BotTraffic::isSynthetic('Mozilla/5.0'));
            self::assertFalse(BotTraffic::isAbusiveAutomation('Mozilla/5.0 AssistPlatform-Production-Smoke/1.0'));
        } finally {
            if ($before === null) {
                unset($_SERVER['HTTP_X_ASSIST_SYNTHETIC']);
            } else {
                $_SERVER['HTTP_X_ASSIST_SYNTHETIC'] = $before;
            }
        }
    }

    public function testHistoricalSqlFilterUsesOnlyAValidatedColumnName(): void
    {
        $predicate = PublicPageViewPolicy::sqlPredicate('page_views.route');
        self::assertStringContainsString("page_views.route NOT LIKE '/runtime-assets/%'", $predicate);
        self::assertStringContainsString('NOT REGEXP', $predicate);

        $this->expectException(\InvalidArgumentException::class);
        PublicPageViewPolicy::sqlPredicate('route; DELETE');
    }
}
