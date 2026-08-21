<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\Support\RequestContext;
use App\Services\Api\AdminApiEnvelope;
use PHPUnit\Framework\TestCase;

final class AdminApiEnvelopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RequestContext::clear();
        RequestContext::begin(new \App\Core\Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v1/admin/health',
            'HTTP_X_REQUEST_ID' => 'test-request-id-0001',
        ], []));
    }

    protected function tearDown(): void
    {
        RequestContext::clear();
        parent::tearDown();
    }

    public function testDataEnvelopeIncludesRequestIdHeader(): void
    {
        $response = AdminApiEnvelope::data(['status' => 'ok']);
        $payload = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(200, $response->status());
        self::assertSame(['status' => 'ok'], $payload['data']);
        self::assertSame('test-request-id-0001', $response->headers()['X-Request-ID']);
        self::assertStringContainsString('application/json', $response->headers()['Content-Type']);
    }

    public function testErrorEnvelopeUsesStableShape(): void
    {
        $response = AdminApiEnvelope::error(
            'validation_failed',
            'Invalid input.',
            422,
            ['email' => ['Required.']]
        );
        $payload = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->status());
        self::assertSame('validation_failed', $payload['error']['code']);
        self::assertSame('Invalid input.', $payload['error']['message']);
        self::assertSame('test-request-id-0001', $payload['error']['request_id']);
        self::assertSame(['email' => ['Required.']], $payload['error']['fields']);
    }

    public function testCollectionEnvelope(): void
    {
        $response = AdminApiEnvelope::collection(
            [['id' => 1]],
            ['count' => 1],
            ['next' => null]
        );
        $payload = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame([['id' => 1]], $payload['data']);
        self::assertSame(1, $payload['meta']['count']);
        self::assertNull($payload['links']['next']);
    }
}
