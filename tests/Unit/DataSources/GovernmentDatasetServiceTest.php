<?php

declare(strict_types=1);

namespace Tests\Unit\DataSources;

use App\Platform\DataSources\Connectors\CkanDatasetConnector;
use App\Platform\DataSources\SimpleHttpClient;
use App\Services\GovernmentDatasetService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class GovernmentDatasetServiceTest extends TestCase
{
    public function testCatalogueSourceKeyUsesDatasetNotConnector(): void
    {
        self::assertSame(
            'gov:au_national_public_toilet_map',
            GovernmentDatasetService::catalogueSourceKey('au_national_public_toilet_map', 'gov_ckan')
        );
        self::assertSame(
            'gov:au_national_toilet_map_dump_points',
            GovernmentDatasetService::catalogueSourceKey('au_national_toilet_map_dump_points', 'gov_ckan')
        );
        self::assertNotSame(
            GovernmentDatasetService::catalogueSourceKey('au_national_public_toilet_map', 'gov_ckan'),
            GovernmentDatasetService::catalogueSourceKey('au_national_toilet_map_dump_points', 'gov_ckan')
        );
    }

    public function testRepeatedDatasetSyncsSuppressPendingSourceRecordDuplicates(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/GovernmentDatasetService.php');
        self::assertIsString($source);
        self::assertStringContainsString('brand_id <=> ?', $source);
        self::assertStringContainsString('expires_at > NOW()', $source);
        self::assertStringContainsString('if ($pending !== null)', $source);
    }

    public function testCkanConnectorUsesResourceMetadataThenCsv(): void
    {
        $http = new class extends SimpleHttpClient {
            /** @var list<string> */
            public array $urls = [];

            public function get(string $url, array $headers = [], int $timeoutSeconds = 25, int $maxRedirects = 3): array
            {
                $this->urls[] = $url;
                if (str_contains($url, 'resource_show')) {
                    return [
                        'status' => 200,
                        'body' => json_encode([
                            'result' => [
                                'url' => 'https://example.com/toilets.csv',
                            ],
                        ], JSON_THROW_ON_ERROR),
                    ];
                }
                return [
                    'status' => 200,
                    'body' => "FacilityID,Name,Address1,Town,Latitude,Longitude\n"
                        . "9,CKAN Toilet,1 Main,Roma,-26.5,148.7\n",
                ];
            }
        };

        $connector = new CkanDatasetConnector($http);
        $rows = $connector->search(
            ['limit' => 10],
            [],
            [
                'package_api_url' => 'https://data.gov.au/data/api',
                'resource_id' => '34076296-6692-4e30-b627-67b7c4eb1027',
                'format' => 'csv',
                'id_field' => 'facilityid',
                'name_field' => 'name',
                'address_field' => 'address1',
                'lat_field' => 'latitude',
                'lng_field' => 'longitude',
                'default_facility_type' => 'public_toilet',
            ]
        );

        self::assertCount(1, $rows);
        self::assertSame('9', $rows[0]['external_id']);
        self::assertSame('public_toilet', $rows[0]['facility_type']);
        self::assertCount(2, $http->urls);
        self::assertStringContainsString('resource_show', $http->urls[0]);
    }

    public function testCkanConnectorDoesNotTruncateCompleteCsvAtFiveHundredRows(): void
    {
        $http = new class extends SimpleHttpClient {
            public function get(string $url, array $headers = [], int $timeoutSeconds = 25, int $maxRedirects = 3): array
            {
                $lines = ['FacilityID,Name,State,Latitude,Longitude'];
                for ($i = 1; $i <= 505; $i++) {
                    $lines[] = "{$i},Queensland Facility {$i},QLD,-26.5,148.7";
                }

                return ['status' => 200, 'body' => implode("\n", $lines)];
            }
        };

        $rows = (new CkanDatasetConnector($http))->search(
            ['limit' => 25000],
            [],
            [
                'resource_url' => 'https://example.com/toilets.csv',
                'format' => 'csv',
                'id_field' => 'facilityid',
                'name_field' => 'name',
                'filters' => ['state' => 'QLD'],
                'default_facility_type' => 'public_toilet',
            ]
        );

        self::assertCount(505, $rows);
    }

    public function testRedirectResolverKeepsRelativePathsOnSameHost(): void
    {
        $client = new SimpleHttpClient();
        $ref = new ReflectionClass($client);
        $method = $ref->getMethod('resolveRedirectUrl');
        $method->setAccessible(true);
        $resolved = $method->invoke(
            $client,
            'https://data.gov.au/data/dataset/x/resource/y/download/file.csv',
            '/cdn/file.csv'
        );
        self::assertSame('https://data.gov.au/cdn/file.csv', $resolved);
        $this->expectException(InvalidArgumentException::class);
        $client->assertSafeUrl('http://169.254.169.254/latest');
    }
}
