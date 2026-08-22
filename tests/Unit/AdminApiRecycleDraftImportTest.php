<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiDraftService;
use App\Services\Api\AdminApiImportService;
use App\Services\Api\AdminApiRecycleBinService;
use PHPUnit\Framework\TestCase;

final class AdminApiRecycleDraftImportTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testListRejectsUnknownEntityTypeFilter(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiRecycleBinService())->list(new Request(
                ['entity_type' => 'widget'],
                [],
                ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/v1/admin/recycle-bin', 'REMOTE_ADDR' => '127.0.0.1'],
                []
            ));
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('entity_type', $e->fields());
        }
    }

    public function testPurgeRequiresConfirmAndReason(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiRecycleBinService())->purge('provider', 1, ['confirm' => false], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('confirm', $e->fields());
        }

        try {
            (new AdminApiRecycleBinService())->purge('provider', 1, ['confirm' => true, 'reason' => 'no'], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('reason', $e->fields());
        }
    }

    public function testBulkRestoreRequiresItemsAndConfirm(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiRecycleBinService())->bulkRestore(['confirm' => true], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('items', $e->fields());
        }
    }

    public function testDraftCreateRequiresEntityTypeAndBusinessName(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiDraftService())->create([], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('entity_type', $e->fields());
        }

        try {
            (new AdminApiDraftService())->create([
                'entity_type' => 'provider',
                'payload' => ['contact_name' => 'Only contact'],
            ], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('payload.business_name', $e->fields());
        }
    }

    public function testStayDraftRequiresNameWhenStaysEnabled(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiDraftService())->create([
                'entity_type' => 'stay',
                'payload' => ['address' => 'Somewhere'],
            ], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('payload.name', $e->fields());
        }
    }

    public function testImportCreateRequiresIdempotencyKeyAndValidChecksum(): void
    {
        BrandContext::set($this->vanAssistBrand());

        try {
            (new AdminApiImportService())->create(['checksum' => 'bad', 'items' => []], $this->postRequest());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('Idempotency-Key', $e->fields());
        }

        try {
            (new AdminApiImportService())->create([
                'checksum' => 'not-a-sha256',
                'items' => [['entity_type' => 'provider', 'payload' => ['business_name' => 'Test Co']]],
            ], $this->postRequest(['HTTP_IDEMPOTENCY_KEY' => 'import-test-key-1']));
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('checksum', $e->fields());
        }
    }

    private function getRequest(string $uri = '/api/v1/admin/recycle-bin'): Request
    {
        return new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => $uri,
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);
    }

    /** @param array<string,string> $headers */
    private function postRequest(array $headers = []): Request
    {
        return new Request([], [], array_merge([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/drafts',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $headers), []);
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
