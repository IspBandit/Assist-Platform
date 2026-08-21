<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Core\Database;
use App\Helpers\Geo;
use App\Models\ServiceCategory;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Staging\DatasetTrustPolicy;
use App\Platform\AiSearch\Support\DatasetSearchFeature;
use Throwable;

/**
 * AI-5 dataset routing: surfaces staged DATA-006 candidates with provenance.
 * Does not call paid Places / Google from Ask VanAssist.
 */
final class DatasetSearchAdapter
{
    /**
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function search(Intent $intent, ?array $town, ?float $lat, ?float $lng, ?int $brandId): array
    {
        if (!DatasetSearchFeature::enabled() || $brandId === null || $brandId <= 0) {
            return [];
        }

        $limit = max(1, min(40, (int) config('ai_search.dataset_max_results', 12)));
        $radius = $intent->radiusKm ?? (int) config('ai_search.default_radius_km', 25);
        $categoryIds = $this->resolveCategoryIds($intent);

        try {
            $rows = $this->fetchPendingCandidates($brandId, $categoryIds, $limit * 3);
        } catch (Throwable) {
            return [];
        }

        $mapped = [];
        foreach ($rows as $row) {
            $card = $this->mapCandidateRow($row);
            if ($card === null) {
                continue;
            }
            if (DatasetTrustPolicy::isAskBlockedConnector((string) ($card['assist_source'] ?? ''))) {
                continue;
            }
            $enriched = $this->withLocationMatch($card, $town, $lat, $lng, $radius, $intent->locationText);
            if ($enriched === null) {
                continue;
            }
            $mapped[] = $enriched;
            if (count($mapped) >= $limit) {
                break;
            }
        }

        usort($mapped, static function (array $a, array $b): int {
            $da = $a['distance_km'] ?? null;
            $db = $b['distance_km'] ?? null;
            if ($da !== null && $db !== null) {
                return ((float) $da) <=> ((float) $db);
            }
            return ((float) ($b['assist_confidence'] ?? 0)) <=> ((float) ($a['assist_confidence'] ?? 0));
        });

        return $mapped;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    public function mapCandidateRow(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['business_name'] ?? ''));
        if ($id <= 0 || $name === '') {
            return null;
        }

        $confidence = isset($row['confidence']) ? ((float) $row['confidence']) / 100.0 : null;
        $card = [
            'id' => $id,
            'candidate_id' => $id,
            'business_name' => $name,
            'formatted_address' => $row['formatted_address'] ?? null,
            'phone' => $row['phone'] ?? null,
            'website' => $row['website'] ?? null,
            'latitude' => isset($row['latitude']) && is_numeric($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) && is_numeric($row['longitude']) ? (float) $row['longitude'] : null,
            'duplicate_provider_id' => isset($row['duplicate_provider_id']) ? (int) $row['duplicate_provider_id'] : null,
            'review_status' => (string) ($row['review_status'] ?? 'pending'),
            'connector_name' => (string) ($row['connector_name'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'slug' => null,
            'is_inferred' => 1,
        ];

        return ResultProvenance::annotate(
            $card,
            ResultProvenance::ORIGIN_STAGED,
            (string) ($row['connector_key'] ?? 'dataset'),
            (string) ($row['external_id'] ?? (string) $id),
            null,
            (string) ($row['connector_name'] ?? 'Imported dataset'),
            $confidence
        );
    }

    /**
     * @param list<int> $categoryIds
     * @return list<array<string,mixed>>
     */
    private function fetchPendingCandidates(int $brandId, array $categoryIds, int $limit): array
    {
        $sql = 'SELECT c.*, ds.connector_key, ds.name AS connector_name, bpc.name AS category_name
                FROM data_source_import_candidates c
                INNER JOIN data_source_connectors ds ON ds.id = c.connector_id
                LEFT JOIN brand_provider_categories bpc ON bpc.id = c.category_id
                WHERE c.brand_id = ?
                  AND c.review_status = \'pending\'
                  AND c.expires_at > NOW()';
        $params = [$brandId];

        if ($categoryIds !== []) {
            $placeholders = implode(',', array_fill(0, count($categoryIds), '?'));
            $sql .= ' AND (c.category_id IS NULL OR c.category_id IN (' . $placeholders . '))';
            foreach ($categoryIds as $id) {
                $params[] = $id;
            }
        }

        $sql .= ' ORDER BY c.confidence DESC, c.created_at DESC LIMIT ' . max(1, min(100, $limit));

        return Database::select($sql, $params);
    }

    /**
     * @return list<int>
     */
    private function resolveCategoryIds(Intent $intent): array
    {
        $ids = [];
        foreach ($intent->providerCategoryKeys as $slug) {
            $category = ServiceCategory::findActiveBySlug($slug);
            if ($category !== null) {
                $ids[] = (int) $category['id'];
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param array<string,mixed> $card
     * @param array<string,mixed>|null $town
     * @return array<string,mixed>|null
     */
    private function withLocationMatch(
        array $card,
        ?array $town,
        ?float $lat,
        ?float $lng,
        int $radiusKm,
        ?string $locationText,
    ): ?array {
        $cLat = $card['latitude'] ?? null;
        $cLng = $card['longitude'] ?? null;
        $hasCoords = is_numeric($cLat) && is_numeric($cLng);

        if ($lat !== null && $lng !== null && $hasCoords) {
            $distance = Geo::haversineExactKm($lat, $lng, (float) $cLat, (float) $cLng);
            if ($distance > $radiusKm) {
                return null;
            }
            $card['distance_km'] = $distance;
            return $card;
        }

        $haystack = strtolower(trim(
            (string) ($card['formatted_address'] ?? '') . ' ' . (string) ($card['business_name'] ?? '')
        ));

        if ($town !== null) {
            $townName = strtolower(trim((string) ($town['name'] ?? '')));
            if ($townName !== '' && str_contains($haystack, $townName)) {
                return $card;
            }
            return null;
        }

        if ($locationText !== null && $locationText !== '') {
            return str_contains($haystack, strtolower($locationText)) ? $card : null;
        }

        return ((float) ($card['assist_confidence'] ?? 0)) >= 0.7 ? $card : null;
    }
}
