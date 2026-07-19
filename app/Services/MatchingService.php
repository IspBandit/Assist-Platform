<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Scores active providers against a service request to suggest the most
 * suitable matches. Scoring is transparent (each contribution is explained) so
 * an administrator can understand and adjust the suggestions in the console.
 */
final class MatchingService
{
    /**
     * Suggest providers for a request, ordered by descending score.
     *
     * @return array<int,array<string,mixed>> each row: provider fields + score + reasons[]
     */
    public function suggest(int $requestId, int $limit = 20): array
    {
        $req = Database::selectOne(
            'SELECT sr.id, sr.town_id, sr.region_id, sr.state_id, sr.primary_category_id, '
            . 'sr.mobile_preferred, sr.workshop_acceptable, sr.max_distance_km, sr.travel_deadline, '
            . 't.latitude AS lat, t.longitude AS lng '
            . 'FROM service_requests sr LEFT JOIN towns t ON t.id = sr.town_id WHERE sr.id = ?',
            [$requestId]
        );
        if ($req === null) {
            return [];
        }

        $categoryIds = array_map(
            static fn ($r) => (int) $r['category_id'],
            Database::select('SELECT category_id FROM service_request_categories WHERE request_id = ?', [$requestId])
        );
        if ($categoryIds === [] && $req['primary_category_id']) {
            $categoryIds = [(int) $req['primary_category_id']];
        }

        // Provider rows carry their base-town coordinates (for distance scoring)
        // plus the contact/automation columns the auto-matcher needs so it does
        // not have to re-query per provider.
        $providers = Database::select(
            "SELECT p.id, p.business_name, p.slug, p.base_town_id, p.region_id, p.service_model, "
            . "p.is_verified, p.insurance_verified, p.is_featured, p.max_travel_km, "
            . "p.auto_invite_opt_out, p.is_unclaimed, p.email, p.public_email, p.user_id, "
            . "bt.latitude AS base_lat, bt.longitude AS base_lng "
            . "FROM providers p LEFT JOIN towns bt ON bt.id = p.base_town_id "
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL"
        );

        $primaryCategory = (int) $req['primary_category_id'];
        $alreadyMatched = $this->existingProviderIds($requestId);
        $deadline = $req['travel_deadline'] !== null ? (string) $req['travel_deadline'] : null;

        $scored = [];
        foreach ($providers as $p) {
            $providerId = (int) $p['id'];
            [$score, $reasons] = $this->score($p, $req, $primaryCategory, $categoryIds);
            if ($score <= 0) {
                continue;
            }
            $p['score'] = $score;
            $p['reasons'] = $reasons;
            $p['already_matched'] = in_array($providerId, $alreadyMatched, true);
            $p['available'] = $this->availableForDeadline($providerId, $deadline);
            $scored[] = $p;
        }

        usort($scored, static fn ($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }

    /**
     * @param array<string,mixed> $p   provider row
     * @param array<string,mixed> $req request row
     * @param array<int,int>      $categoryIds
     * @return array{0:int,1:array<int,string>}
     */
    private function score(array $p, array $req, int $primaryCategory, array $categoryIds): array
    {
        $providerId = (int) $p['id'];
        $score = 0;
        $reasons = [];

        // --- Service category relevance ---
        // Direct matches (the business explicitly offers the service) score
        // highest; possible matches (inferred from the business's trade) score
        // lower so they widen the net without outranking exact matches.
        if ($primaryCategory > 0) {
            $primaryMatch = $this->categoryMatch($providerId, $primaryCategory);
            if ($primaryMatch === 'direct') {
                $score += 50;
                $reasons[] = 'Offers the main service';
            } elseif ($primaryMatch === 'possible') {
                $score += 25;
                $reasons[] = 'May offer the main service (related trade)';
            }
        }
        $otherDirect = 0;
        $otherPossible = 0;
        foreach ($categoryIds as $catId) {
            if ($catId === $primaryCategory) {
                continue;
            }
            $m = $this->categoryMatch($providerId, $catId);
            if ($m === 'direct') {
                $otherDirect++;
            } elseif ($m === 'possible') {
                $otherPossible++;
            }
        }
        if ($otherDirect > 0) {
            $score += min(20, $otherDirect * 10);
            $reasons[] = $otherDirect . ' related service' . ($otherDirect > 1 ? 's' : '');
        }
        if ($otherPossible > 0) {
            $score += min(10, $otherPossible * 5);
            $reasons[] = $otherPossible . ' possible related service' . ($otherPossible > 1 ? 's' : '');
        }

        // --- Location relevance ---
        $townId = (int) ($req['town_id'] ?? 0);
        $regionId = (int) ($req['region_id'] ?? 0);
        $stateId = (int) ($req['state_id'] ?? 0);

        if ($townId > 0 && (int) ($p['base_town_id'] ?? 0) === $townId) {
            $score += 30;
            $reasons[] = 'Based in the same town';
        } elseif ($townId > 0 && ProviderCoverage::servesTown($providerId, $townId)) {
            $score += 28;
            $reasons[] = 'Services this town';
        } elseif ($regionId > 0 && ProviderCoverage::servesRegion($providerId, $regionId)) {
            $score += 18;
            $reasons[] = 'Services this region';
        } elseif ($stateId > 0 && $this->coversState($providerId, $stateId)) {
            $score += 8;
            $reasons[] = 'Services this state';
        }

        // --- Proximity (when both points are geocoded) ---
        $reqLat = (float) ($req['lat'] ?? 0);
        $reqLng = (float) ($req['lng'] ?? 0);
        $pLat = (float) ($p['base_lat'] ?? 0);
        $pLng = (float) ($p['base_lng'] ?? 0);
        if ($reqLat !== 0.0 && $pLat !== 0.0) {
            $km = $this->haversineKm($reqLat, $reqLng, $pLat, $pLng);
            if ($km <= 50) {
                $score += 15;
                $reasons[] = 'Within 50 km';
            } elseif ($km <= 120) {
                $score += 10;
                $reasons[] = 'Within 120 km';
            } elseif ($km <= 250) {
                $score += 4;
                $reasons[] = 'Within 250 km';
            }
            $maxTravel = (int) ($p['max_travel_km'] ?? 0);
            if ($maxTravel > 0 && $km <= $maxTravel && in_array((string) $p['service_model'], ['mobile', 'both'], true)) {
                $score += 6;
                $reasons[] = 'Within stated travel range';
            }
        }

        // --- Service model compatibility ---
        $model = (string) $p['service_model'];
        if (!empty($req['mobile_preferred']) && in_array($model, ['mobile', 'both'], true)) {
            $score += 10;
            $reasons[] = 'Mobile service';
        }
        if (!empty($req['workshop_acceptable']) && in_array($model, ['workshop', 'both'], true)) {
            $score += 5;
            $reasons[] = 'Workshop available';
        }

        // --- Trust signals ---
        if (!empty($p['is_verified'])) {
            $score += 10;
            $reasons[] = 'Verified';
        }
        if (!empty($p['insurance_verified'])) {
            $score += 5;
        }
        if (!empty($p['is_featured'])) {
            $score += 5;
        }

        return [$score, $reasons];
    }

    /**
     * Returns how a provider matches a category:
     *  - 'direct'   : explicitly offers it (is_inferred = 0)
     *  - 'possible' : inferred from the provider's trade (is_inferred = 1)
     *  - null       : no link at all
     */
    private function categoryMatch(int $providerId, int $categoryId): ?string
    {
        $row = Database::selectOne(
            'SELECT MIN(is_inferred) AS inferred FROM provider_services WHERE provider_id = ? AND category_id = ?',
            [$providerId, $categoryId]
        );
        if ($row === null || $row['inferred'] === null) {
            return null;
        }
        return ((int) $row['inferred'] === 0) ? 'direct' : 'possible';
    }

    private function coversState(int $providerId, int $stateId): bool
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM provider_service_areas WHERE provider_id = ? AND state_id = ?",
            [$providerId, $stateId]
        ) > 0;
    }

    private function coversArea(int $providerId, string $column, int $value): bool
    {
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM provider_service_areas WHERE provider_id = ? AND {$column} = ?",
            [$providerId, $value]
        ) > 0;
    }

    /**
     * True unless the provider has an explicit "unavailable" window covering the
     * request's travel deadline. Absence of any window is treated as available.
     */
    private function availableForDeadline(int $providerId, ?string $deadline): bool
    {
        if ($deadline === null || $deadline === '' || $deadline === '0000-00-00') {
            return true;
        }
        $blocked = (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_availability WHERE provider_id = ? AND is_available = 0 '
            . 'AND start_date <= ? AND (end_date IS NULL OR end_date >= ?)',
            [$providerId, $deadline, $deadline]
        );
        return $blocked === 0;
    }

    /** Great-circle distance between two lat/long points, in kilometres. */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array<int,int> provider ids already linked to the request */
    private function existingProviderIds(int $requestId): array
    {
        return array_map(
            static fn ($r) => (int) $r['provider_id'],
            Database::select('SELECT provider_id FROM service_request_matches WHERE request_id = ?', [$requestId])
        );
    }
}
