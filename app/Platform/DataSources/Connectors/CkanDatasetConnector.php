<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Platform\DataSources\FacilityTypeMapper;
use App\Platform\DataSources\SimpleHttpClient;
use RuntimeException;

/**
 * CKAN package/resource fetch for government open data (DATA-012).
 * Expects settings: resource_url OR (package_api_url + resource_id).
 */
final class CkanDatasetConnector implements ConnectorInterface
{
    public function __construct(private readonly ?SimpleHttpClient $http = null)
    {
    }

    public function key(): string
    {
        return 'gov_ckan';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        unset($credentials);
        $limit = max(1, min(500, (int) ($request['limit'] ?? 100)));
        $defaultType = FacilityTypeMapper::normalise((string) ($settings['default_facility_type'] ?? 'other_essential'));
        $http = $this->http ?? new SimpleHttpClient();

        $resourceUrl = trim((string) ($settings['resource_url'] ?? $request['resource_url'] ?? ''));
        if ($resourceUrl === '') {
            $api = trim((string) ($settings['package_api_url'] ?? ''));
            $resourceId = trim((string) ($settings['resource_id'] ?? ''));
            if ($api === '' || $resourceId === '') {
                throw new RuntimeException('CKAN connector requires resource_url or package_api_url + resource_id.');
            }
            $meta = $http->get(rtrim($api, '/') . '/3/action/resource_show?id=' . rawurlencode($resourceId));
            $decoded = json_decode($meta['body'], true);
            if ($meta['status'] < 200 || $meta['status'] >= 300 || !is_array($decoded)) {
                throw new RuntimeException('CKAN resource metadata request failed.');
            }
            $resourceUrl = (string) ($decoded['result']['url'] ?? '');
            if ($resourceUrl === '') {
                throw new RuntimeException('CKAN resource URL missing.');
            }
        }

        $response = $http->get($resourceUrl);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('CKAN resource download failed.');
        }

        $body = $response['body'];
        $format = strtolower((string) ($settings['format'] ?? pathinfo(parse_url($resourceUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION)));
        if ($format === 'geojson' || str_contains(ltrim($body), '{"type"') || str_contains($body, '"FeatureCollection"')) {
            return (new GeoJsonDatasetConnector())->search(
                ['payload' => $body, 'limit' => $limit],
                [],
                ['default_facility_type' => $defaultType] + $settings
            );
        }
        return (new CsvDatasetConnector())->search(
            ['payload' => $body, 'limit' => $limit],
            [],
            ['default_facility_type' => $defaultType] + $settings
        );
    }
}
