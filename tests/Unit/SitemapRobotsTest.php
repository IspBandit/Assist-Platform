<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\SitemapController;
use App\Core\Request;
use App\Services\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SitemapRobotsTest extends TestCase
{
    private function robots(array $settings): string
    {
        $cache = new ReflectionProperty(Settings::class, 'cache');
        $previous = $cache->getValue();
        $cache->setValue(null, $settings);
        try {
            $response = (new SitemapController())->robots(new Request([], [], [], []));
            self::assertSame(200, $response->status());
            self::assertSame('text/plain; charset=UTF-8', $response->headers()['Content-Type']);
            self::assertStringContainsString('Sitemap: ' . url('sitemap.xml'), $response->content());
            return $response->content();
        } finally {
            $cache->setValue(null, $previous);
        }
    }

    /** Evaluate the emitted wildcard group using longest-match precedence. */
    private function canCrawl(string $robots, string $path): bool
    {
        $longest = -1;
        $allowed = true;
        foreach (explode("\n", $robots) as $line) {
            if (preg_match('/^(Allow|Disallow): (.+)$/', $line, $match) !== 1) {
                continue;
            }
            $rule = $match[2];
            $end = str_ends_with($rule, '$');
            $literal = $end ? substr($rule, 0, -1) : $rule;
            $pattern = '~^' . str_replace('\\*', '.*', preg_quote($literal, '~')) . ($end ? '$' : '') . '~';
            if (preg_match($pattern, $path) !== 1) {
                continue;
            }
            $length = strlen($literal);
            if ($length > $longest || ($length === $longest && $match[1] === 'Allow')) {
                $longest = $length;
                $allowed = $match[1] === 'Allow';
            }
        }
        return $allowed;
    }

    public function testPublicDirectoryAndTermsRemainCrawlable(): void
    {
        $robots = $this->robots(['launch_mode' => 'public']);
        foreach (['/', '/providers', '/providers?category=repairs', '/providers/lloyds-caravans-fyshwick', '/provider-terms', '/for-providers', '/caravan-parks/example', '/services', '/runtime-assets/css/app.css'] as $path) {
            self::assertTrue($this->canCrawl($robots, $path), $path);
        }
    }

    public function testPrivateRouteBoundariesRemainBlocked(): void
    {
        $robots = $this->robots(['seo_allow_indexing' => '1']);
        foreach (['/admin', '/account', '/provider', '/park', '/install', '/billing'] as $root) {
            foreach (['', '/', '?tab=profile', '/settings', '/settings?tab=profile'] as $suffix) {
                self::assertFalse($this->canCrawl($robots, $root . $suffix), $root . $suffix);
            }
        }
    }

    public function testPrivateLaunchAndExplicitIndexingOffStillBlockEverything(): void
    {
        foreach ([['launch_mode' => 'private'], ['launch_mode' => 'public', 'seo_allow_indexing' => '0']] as $settings) {
            $robots = $this->robots($settings);
            foreach (['/', '/providers', '/provider-terms', '/caravan-parks/example'] as $path) {
                self::assertFalse($this->canCrawl($robots, $path), $path);
            }
        }
    }
}
