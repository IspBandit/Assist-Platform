<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Core\Database;
use App\Helpers\Geo;
use App\Models\ServiceCategory;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Support\PlacesRescueFeature;
use App\Platform\DataSources\ConnectorRegistry;
use App\Platform\DataSources\Connectors\GooglePlacesConnector;
use App\Services\SecretCipher;
use Throwable;

/**
 * On zero/weak provider searches, query Google Places (budget-gated), show
 * labelled public-source results, and auto-create unclaimed listings (ADR 0042).
 */
final class ZeroResultProviderRescueService
{
    public function __construct(
        private readonly ?ConnectorRegistry $registry = null,
        private readonly ?PlacesUnclaimedPublisher $publisher = null,
        private readonly ?GooglePlacesConnector $connector = null,
    ) {
    }

    /**
     * @param list<string> $categorySlugs
     * @param array<string,mixed>|null $town
     * @return array{
     *   providers:list<array<string,mixed>>,
     *   externals:list<array<string,mixed>>,
     *   created:int,
     *   merged:int,
     *   message:?string,
     *   attribution:?string
     * }
     */
    public function rescue(
        array $categorySlugs,
        ?array $town,
        ?float $lat,
        ?float $lng,
        int $brandId,
        ?int $radiusKm = null,
    ): array {
        $empty = [
            'providers' => [],
            'externals' => [],
            'created' => 0,
            'merged' => 0,
            'message' => null,
            'attribution' => null,
        ];
        if (!PlacesRescueFeature::enabled() || $brandId < 1 || $categorySlugs === []) {
            return $empty;
        }

        $slug = $categorySlugs[0];
        $category = ServiceCategory::findActiveBySlug($slug);
        if ($category === null) {
            return $empty;
        }
        $query = $this->queryForSlug($slug);
        if ($query === '') {
            return $empty;
        }

        $location = $this->locationLabel($town, $lat, $lng);
        if ($location === '') {
            return $empty;
        }

        $connectorRow = Database::selectOne(
            "SELECT * FROM data_source_connectors WHERE connector_key = 'google_places' LIMIT 1"
        );
        if ($connectorRow === null || ($connectorRow['status'] ?? '') !== 'active') {
            return $empty;
        }
        $connectorId = (int) $connectorRow['id'];
        if (!$this->withinBudget($connectorRow, $connectorId)) {
            return $empty;
        }

        $credential = Database::selectOne(
            "SELECT encrypted_value FROM data_source_credentials WHERE connector_id = ? AND credential_key = 'api_key'",
            [$connectorId]
        );
        $apiKey = SecretCipher::decrypt((string) ($credential['encrypted_value'] ?? ''));
        if ($apiKey === '') {
            return $empty;
        }

        $settings = json_decode((string) ($connectorRow['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }

        $limit = max(1, min(20, (int) config('places_rescue.max_results', 8)));
        try {
            $connector = $this->connector
                ?? ($this->registry ?? new ConnectorRegistry())
                    ->resolve('google_places', GooglePlacesConnector::class);
            $places = $connector->search(
                ['query' => $query, 'location' => $location, 'limit' => $limit],
                ['api_key' => $apiKey],
                $settings
            );
            $this->recordUsage($connectorId, (float) ($connectorRow['estimated_request_cost_aud'] ?? 0));
        } catch (Throwable) {
            return $empty;
        }

        $maxKm = $radiusKm ?? (int) (ProviderSearchRadiusLadder::stepsKm()[count(ProviderSearchRadiusLadder::stepsKm()) - 1] ?? 300);
        $autoPublish = (bool) config('places_rescue.auto_publish_unclaimed', true);
        $publisher = $this->publisher ?? new PlacesUnclaimedPublisher();
        $providers = [];
        $externals = [];
        $created = 0;
        $merged = 0;

        foreach ($places as $place) {
            if (!is_array($place)) {
                continue;
            }
            $card = $this->externalCard($place, $lat, $lng, $maxKm);
            if ($card === null) {
                continue;
            }
            $externals[] = $card;

            if (!$autoPublish) {
                continue;
            }
            $result = $publisher->publishOrMerge($place, (int) $category['id'], $brandId);
            if ($result['action'] === 'created') {
                ++$created;
            }
            if ($result['action'] === 'merged_unclaimed' || $result['action'] === 'existing_place') {
                ++$merged;
            }
            if (is_array($result['row'])) {
                $row = $result['row'];
                $row['assist_origin'] = ResultProvenance::ORIGIN_CANONICAL;
                $row['assist_source'] = 'providers';
                $row['assist_category_slug'] = $slug;
                $row['search_fallback'] = 'places_rescue';
                $row['is_inferred'] = 0;
                if ($lat !== null && $lng !== null) {
                    $row['distance_km'] = Geo::distanceKm(
                        $lat,
                        $lng,
                        $row['town_lat'] ?? null,
                        $row['town_lng'] ?? null
                    );
                }
                $providers[(int) $row['id']] = $row;
            }
        }

        if ($externals === [] && $providers === []) {
            return $empty;
        }

        return [
            'providers' => array_values($providers),
            'externals' => $externals,
            'created' => $created,
            'merged' => $merged,
            'message' => 'No listed VanAssist specialist matched nearby. Showing public-source businesses found for this area—confirm they handle your issue before travelling.',
            'attribution' => (string) config(
                'places_rescue.attribution',
                'Results include public business details from Google. Confirm details before travelling.'
            ),
        ];
    }

    private function queryForSlug(string $slug): string
    {
        $map = config('places_rescue.queries', []);
        if (!is_array($map)) {
            return '';
        }
        $query = trim((string) ($map[$slug] ?? ''));
        if ($query !== '') {
            return $query;
        }

        return str_replace('-', ' ', $slug);
    }

    /** @param array<string,mixed>|null $town */
    private function locationLabel(?array $town, ?float $lat, ?float $lng): string
    {
        if ($town !== null) {
            $name = trim((string) ($town['name'] ?? ''));
            $state = trim((string) ($town['state_abbr'] ?? ''));
            if ($name !== '') {
                return $state !== '' ? $name . ', ' . $state : $name;
            }
        }
        if ($lat !== null && $lng !== null) {
            return round($lat, 4) . ', ' . round($lng, 4);
        }

        return '';
    }

    /** @param array<string,mixed> $connectorRow */
    private function withinBudget(array $connectorRow, int $connectorId): bool
    {
        $usage = Database::selectOne(
            'SELECT requests_used, estimated_cost_aud FROM data_source_usage_daily '
            . 'WHERE connector_id = ? AND usage_date = CURRENT_DATE',
            [$connectorId]
        ) ?? ['requests_used' => 0, 'estimated_cost_aud' => 0];
        if ((int) $usage['requests_used'] >= (int) ($connectorRow['daily_request_limit'] ?? 0)) {
            return false;
        }
        $budget = (float) ($connectorRow['daily_budget_aud'] ?? 0);
        if ($budget > 0
            && (float) $usage['estimated_cost_aud'] + (float) ($connectorRow['estimated_request_cost_aud'] ?? 0) > $budget) {
            return false;
        }

        return true;
    }

    private function recordUsage(int $connectorId, float $cost): void
    {
        Database::query(
            'INSERT INTO data_source_usage_daily (connector_id, usage_date, requests_used, estimated_cost_aud, updated_at) '
            . 'VALUES (?, CURRENT_DATE, 1, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE requests_used = requests_used + 1, '
            . 'estimated_cost_aud = estimated_cost_aud + VALUES(estimated_cost_aud), updated_at = NOW()',
            [$connectorId, $cost]
        );
        Database::query(
            'UPDATE data_source_connectors SET last_used_at = NOW(), last_error = NULL WHERE id = ?',
            [$connectorId]
        );
    }

    /**
     * @param array<string,mixed> $place
     * @return array<string,mixed>|null
     */
    private function externalCard(array $place, ?float $lat, ?float $lng, int $maxKm): ?array
    {
        $name = trim((string) ($place['business_name'] ?? ''));
        $externalId = trim((string) ($place['external_id'] ?? ''));
        if ($name === '' || $externalId === '') {
            return null;
        }
        $placeLat = is_numeric($place['latitude'] ?? null) ? (float) $place['latitude'] : null;
        $placeLng = is_numeric($place['longitude'] ?? null) ? (float) $place['longitude'] : null;
        $distance = Geo::distanceKm($lat, $lng, $placeLat, $placeLng);
        if ($distance !== null && $distance > $maxKm) {
            return null;
        }

        $card = [
            'id' => null,
            'candidate_id' => null,
            'business_name' => $name,
            'formatted_address' => (string) ($place['formatted_address'] ?? ''),
            'phone' => (string) ($place['phone'] ?? ''),
            'website' => (string) ($place['website'] ?? ''),
            'latitude' => $placeLat,
            'longitude' => $placeLng,
            'town_lat' => $placeLat,
            'town_lng' => $placeLng,
            'distance_km' => $distance,
            'connector_name' => 'Google Places',
            'slug' => null,
            'is_inferred' => 1,
        ];

        return ResultProvenance::annotate(
            $card,
            ResultProvenance::ORIGIN_EXTERNAL_LIVE,
            'google_places',
            $externalId,
            null,
            'Google',
            0.7
        );
    }
}
