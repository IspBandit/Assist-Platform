<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiProviderWriteService;
use App\Services\Api\AdminApiStayWriteService;
use PHPUnit\Framework\TestCase;

final class AdminApiWriteValidationTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testProviderCreateRequiresBusinessName(): void
    {
        try {
            (new AdminApiProviderWriteService())->create([], $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
            self::assertArrayHasKey('business_name', $e->fields());
        }
    }

    public function testProviderPatchRejectsEmptyWritablePayload(): void
    {
        try {
            (new AdminApiProviderWriteService())->patch(1, ['unknown' => 'x'], $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('body', $e->fields());
        }
    }

    public function testProviderDeleteRequiresReason(): void
    {
        try {
            (new AdminApiProviderWriteService())->softDelete(1, 'no', $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('reason', $e->fields());
        }
    }

    public function testStayCreateRequiresNameWhenStaysEnabled(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiStayWriteService())->create([], $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('name', $e->fields());
        }
    }

    public function testStayPatchRejectsEmptyWritablePayloadWhenStaysEnabled(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiStayWriteService())->patch(1, [], $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('body', $e->fields());
        }
    }

    public function testStayDeleteRequiresReasonWhenStaysEnabled(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiStayWriteService())->softDelete(1, '', $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('reason', $e->fields());
        }
    }

    private function request(): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/providers',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);
    }

    private function vanAssistBrand(): Brand
    {
        return BrandRegistry::fromArray([
            'vanassist' => [
                'database_id' => 1,
                'name' => 'VanAssist',
                'legal_name' => 'VanAssist Australia',
                'short_name' => 'VanAssist',
                'status' => 'active',
                'url' => 'https://vanassist.test',
                'domains' => ['primary' => 'vanassist.test'],
                'assets' => [],
                'theme' => ['brand' => '#087f7d'],
                'metadata' => [],
                'contact' => [],
                'legal' => [],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true, 'parks' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'vanassist',
            ],
        ])->get('vanassist');
    }
}
