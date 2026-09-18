<?php

declare(strict_types=1);

namespace App\Services\Search;

use App\Core\Database;
use App\Helpers\Geo;
use App\Helpers\Env;
use App\Models\ServiceCategory;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Support\PlacesRescueFeature;
use App\Platform\DataSources\ConnectorInterface;
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
        private readonly ?ConnectorInterface $connector = null,
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
        $queries = $this->queriesForSlug($slug);
        if ($queries === []) {
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
            $apiKey = trim((string) Env::get('GOOGLE_PLACES_API_KEY', ''));
        }
        if ($apiKey === '') {
            return $empty;
        }

        $settings = json_decode((string) ($connectorRow['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }

        $limit = max(1, min(20, (int) config('places_rescue.max_results', 8)));
        $ladder = ProviderSearchRadiusLadder::stepsKm();
        $maxKm = max(
            1,
            $radiusKm ?? (int) ($ladder[count($ladder) - 1] ?? 300)
        );

        try {
            $connector = $this->connector
                ?? ($this->registry ?? new ConnectorRegistry())
                    ->resolve('google_places', GooglePlacesConnector::class);
            $placesById = [];
            foreach ($queries as $query) {
                if (!$this->withinBudget($connectorRow, $connectorId)) {
                    break;
                }
                $batch = $connector->search(
                    ['query' => $query, 'location' => $location, 'limit' => $limit],
                    ['api_key' => $apiKey],
                    $settings
                );
                $this->recordUsage($connectorId, (float) ($connectorRow['estimated_request_cost_aud'] ?? 0));
                foreach ($batch as $place) {
                    if (!is_array($place)) {
                        continue;
                    }
                    $externalId = trim((string) ($place['external_id'] ?? ''));
                    if ($externalId === '' || isset($placesById[$externalId])) {
                        continue;
                    }
                    $placesById[$externalId] = $place;
                }
                if (count($placesById) >= $limit) {
                    break;
                }
            }
            $places = array_slice(array_values($placesById), 0, $limit);
        } catch (Throwable) {
            return $empty;
        }

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

            $publishedAsProvider = false;
            if ($autoPublish) {
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
                    $row['assist_source_record_id'] = (string) ($place['external_id'] ?? '');
                    if ($lat !== null && $lng !== null) {
                        $row['distance_km'] = Geo::distanceKm(
                            $lat,
                            $lng,
                            $row['latitude'] ?? $row['town_lat'] ?? null,
                            $row['longitude'] ?? $row['town_lng'] ?? null
                        );
                    }
                    $providers[(int) $row['id']] = $row;
                    $publishedAsProvider = true;
                }
            }

            // Avoid showing the same Place ID as both a published listing and an
            // external card (any town — not Charters-specific).
            if (!$publishedAsProvider) {
                $externals[] = $card;
            }
        }

        $providers = $this->sortByDistance(array_values($providers));
        $externals = $this->sortByDistance($externals);

        if ($externals === [] && $providers === []) {
            return $empty;
        }

        $copy = $this->buildMessages($location, $providers, $externals);

        return [
            'providers' => $providers,
            'externals' => $externals,
            'created' => $created,
            'merged' => $merged,
            'message' => $copy['message'],
            'attribution' => $copy['attribution'],
        ];
    }

    /**
     * @return list<string>
     */
    public function queriesForSlug(string $slug): array
    {
        $map = config('places_rescue.queries', []);
        if (!is_array($map)) {
            return [];
        }
        $raw = $map[$slug] ?? str_replace('-', ' ', $slug);
        if (is_string($raw)) {
            $raw = [$raw];
        }
        if (!is_array($raw)) {
            return [];
        }
        $queries = [];
        foreach ($raw as $phrase) {
            $phrase = trim((string) $phrase);
            if ($phrase === '' || in_array($phrase, $queries, true)) {
                continue;
            }
            $queries[] = $phrase;
            if (count($queries) >= 3) {
                break;
            }
        }

        return $queries;
    }

    /**
     * Honest copy: only say “for this area” when something is actually nearby.
     *
     * @param list<array<string,mixed>> $providers
     * @param list<array<string,mixed>> $externals
     * @return array{message:?string,attribution:?string}
     */
    public function buildMessages(string $placeLabel, array $providers, array $externals): array
    {
        $localKm = max(1, (int) config('places_rescue.local_radius_km', 50));
        $nearest = null;
        foreach (array_merge($providers, $externals) as $row) {
            if (!is_numeric($row['distance_km'] ?? null)) {
                continue;
            }
            $distance = (float) $row['distance_km'];
            $nearest = $nearest === null ? $distance : min($nearest, $distance);
        }
        if ($nearest === null) {
            return ['message' => null, 'attribution' => null];
        }

        $attribution = (string) config(
            'places_rescue.attribution',
            'Results include public business details from Google. Confirm details before travelling.'
        );
        $place = trim($placeLabel) !== '' ? trim($placeLabel) : 'this area';
        if ($nearest <= $localKm) {
            return [
                'message' => 'No listed VanAssist specialist matched nearby. Showing public-source businesses found for this area—confirm they handle your issue before travelling.',
                'attribution' => $attribution,
            ];
        }

        $km = (int) max(1, round($nearest));

        return [
            'message' => "Nothing close in {$place}. Nearest public-source option is about {$km} km away—confirm before travelling.",
            'attribution' => $attribution,
        ];
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    private function sortByDistance(array $rows): array
    {
        usort($rows, static function (array $a, array $b): int {
            $da = is_numeric($a['distance_km'] ?? null) ? (float) $a['distance_km'] : null;
            $db = is_numeric($b['distance_km'] ?? null) ? (float) $b['distance_km'] : null;
            if ($da === null && $db === null) {
                return strcmp((string) ($a['business_name'] ?? ''), (string) ($b['business_name'] ?? ''));
            }
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }

            return $da <=> $db;
        });

        return array_values($rows);
    }
}
