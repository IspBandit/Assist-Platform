<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimit as RateLimitMiddleware;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\EmailQueue;
use App\Services\Mailer;
use App\Services\PlatformBackfill;
use App\Services\RateLimiter;
use PHPUnit\Framework\TestCase;

final class PlatformDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 with a disposable database');
        }
    }

    public function testMigrationHistoryIsCleanAndChecksummed(): void
    {
        $dirty = (int) Database::scalar("SELECT COUNT(*) FROM migrations WHERE status <> 'succeeded'");
        $missingChecksums = (int) Database::scalar(
            "SELECT COUNT(*) FROM migrations WHERE checksum IS NULL OR CHAR_LENGTH(checksum) <> 64"
        );

        self::assertSame(0, $dirty);
        self::assertSame(0, $missingChecksums);
    }

    public function testPlatformBrandsAndBackfillIntegrity(): void
    {
        $brands = Database::select('SELECT id, brand_key, status FROM brands ORDER BY id');
        self::assertSame(['vanassist', 'towsmart', 'trailerwise', 'localtorque'], array_column($brands, 'brand_key'));
        self::assertSame('active', $brands[0]['status']);

        foreach ((new PlatformBackfill())->validate() as $check) {
            self::assertTrue($check['valid'], "Backfill count {$check['actual']} did not match {$check['expected']}");
        }
    }

    public function testPersistentRateLimitBlocksAndClears(): void
    {
        $subjects = ['email:integration-rate-limit@example.com', 'ip:192.0.2.10'];
        RateLimiter::clear('test.integration', $subjects);

        RateLimiter::hit('test.integration', $subjects, 2, 60, 60);
        self::assertFalse(RateLimiter::blocked('test.integration', $subjects));

        RateLimiter::hit('test.integration', $subjects, 2, 60, 60);
        self::assertTrue(RateLimiter::blocked('test.integration', $subjects));

        RateLimiter::clear('test.integration', $subjects);
        self::assertFalse(RateLimiter::blocked('test.integration', $subjects));
    }

    public function testRateLimitMiddlewareReturns429AfterQuota(): void
    {
        $subjects = ['platform|ip:192.0.2.11'];
        RateLimiter::clear('test.middleware', $subjects);
        $middleware = new RateLimitMiddleware('test.middleware', '1', '60', '60');
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test',
            'REMOTE_ADDR' => '192.0.2.11',
        ], []);

        $first = $middleware->handle($request, static fn (): Response => new Response('', 204));
        $second = $middleware->handle($request, static fn (): Response => new Response('', 204));

        self::assertSame(204, $first->status());
        self::assertSame(429, $second->status());
        self::assertSame('60', $second->headers()['Retry-After']);
        RateLimiter::clear('test.middleware', $subjects);
    }

    public function testEmailQueueClaimsAreLeasedAtomically(): void
    {
        $id = Database::insert(
            "INSERT INTO email_queue (brand_id, recipient_email, subject, html_body, status, created_at) "
            . "VALUES (1, 'lease-test@example.com', 'Lease test', '<p>test</p>', 'pending', NOW())"
        );
        $claim = new \ReflectionMethod(Mailer::class, 'claimBatch');

        try {
            /** @var array<int,array<string,mixed>> $first */
            $first = $claim->invoke(null, 100, 3);
            $row = null;
            foreach ($first as $candidate) {
                if ((int) $candidate['id'] === $id) {
                    $row = $candidate;
                    break;
                }
            }
            self::assertNotNull($row);
            self::assertSame('processing', $row['status']);
            self::assertSame(1, (int) $row['attempts']);
            self::assertSame(32, strlen((string) $row['lease_token']));

            /** @var array<int,array<string,mixed>> $second */
            $second = $claim->invoke(null, 100, 3);
            self::assertNotContains($id, array_map(
                static fn (array $candidate): int => (int) $candidate['id'],
                $second
            ));
        } finally {
            Database::query('DELETE FROM email_queue WHERE id = ?', [$id]);
        }
    }

    public function testEmailQueuePersistsCurrentBrandContext(): void
    {
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        BrandContext::set($registry->get('towsmart'));
        $queueId = null;

        try {
            EmailQueue::queueRaw(
                'brand-test@example.com',
                'Brand test',
                'Brand context',
                '<p>test</p>'
            );
            $row = Database::selectOne(
                "SELECT id, brand_id FROM email_queue WHERE recipient_email = 'brand-test@example.com' "
                . 'ORDER BY id DESC LIMIT 1'
            );
            self::assertNotNull($row);
            $queueId = (int) $row['id'];
            self::assertSame(2, (int) $row['brand_id']);
        } finally {
            if ($queueId !== null) {
                Database::query('DELETE FROM email_queue WHERE id = ?', [$queueId]);
            }
            BrandContext::clear();
        }
    }

    public function testQueuedBrandsUseTheirOwnSenderDomains(): void
    {
        $towSmart = Mailer::config(2);
        $trailerWise = Mailer::config(3);

        self::assertSame('support@towsmart.com.au', $towSmart['from_address']);
        self::assertSame('TowSmart', $towSmart['from_name']);
        self::assertSame('support@trailerwise.com.au', $trailerWise['from_address']);
        self::assertSame('TrailerWise', $trailerWise['from_name']);
        self::assertStringNotContainsString('vanassist.com.au', (string) $towSmart['from_address']);
        self::assertStringNotContainsString('vanassist.com.au', (string) $trailerWise['from_address']);
    }

    public function testTemplateQueueInjectsCurrentBrandIdentity(): void
    {
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        BrandContext::set($registry->get('towsmart'));
        $templateKey = 'integration_brand_placeholders';
        $queueId = null;
        Database::query(
            "INSERT INTO email_templates (template_key, name, subject, html_body, text_body, is_enabled, created_at) VALUES (?, 'Integration', '{{brand_name}} notice', '<p>{{brand_domain}} {{support_email}}</p>', '{{site_url}}', 1, NOW())",
            [$templateKey]
        );

        try {
            self::assertTrue(EmailQueue::queueTemplate($templateKey, 'brand-template@example.com'));
            $row = Database::selectOne('SELECT * FROM email_queue WHERE template_key = ? ORDER BY id DESC LIMIT 1', [$templateKey]);
            self::assertNotNull($row);
            $queueId = (int) $row['id'];
            self::assertSame(2, (int) $row['brand_id']);
            self::assertSame('TowSmart notice', $row['subject']);
            self::assertStringContainsString($registry->get('towsmart')->primaryDomain(), (string) $row['html_body']);
            self::assertStringNotContainsString('vanassist.com.au', (string) $row['html_body']);
        } finally {
            if ($queueId !== null) { Database::query('DELETE FROM email_queue WHERE id = ?', [$queueId]); }
            Database::query('DELETE FROM email_templates WHERE template_key = ?', [$templateKey]);
            BrandContext::clear();
        }
    }
}
