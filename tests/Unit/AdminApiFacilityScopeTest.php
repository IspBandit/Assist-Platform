<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Api\V1\Admin\FacilityContributionController;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class AdminApiFacilityScopeTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testFacilityContributionQueueIsUnavailableOutsideAStaysWorkspace(): void
    {
        BrandContext::set(BrandRegistry::fromArray([
            'towsmart' => [
                'database_id' => 2,
                'name' => 'TowSmart',
                'legal_name' => 'TowSmart Australia',
                'short_name' => 'TowSmart',
                'status' => 'active',
                'url' => 'https://towsmart.test',
                'domains' => ['primary' => 'towsmart.test'],
                'assets' => [],
                'theme' => ['brand' => '#123456'],
                'metadata' => [],
                'contact' => [],
                'legal' => [],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => ['parks' => false],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'towsmart',
            ],
        ])->get('towsmart'));

        try {
            (new FacilityContributionController())->index(new Request([], [], [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/v1/admin/facility-contributions',
                'REMOTE_ADDR' => '127.0.0.1',
            ], []));
            self::fail('Expected the stays-module scope guard to reject this workspace.');
        } catch (AdminApiException $e) {
            self::assertSame(404, $e->getStatusCode());
            self::assertSame('not_found', $e->errorCode());
        }
    }
}
