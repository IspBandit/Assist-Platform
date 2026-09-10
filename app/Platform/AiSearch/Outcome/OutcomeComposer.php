<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Outcome;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchResponse;
use App\Services\RoadDistance\RoadDistanceService;

/** Presentation-only explanation of facts returned by the existing search pipeline. */
final class OutcomeComposer
{
    /** @return array<string,mixed> */
    public function compose(SearchResponse $response): array
    {
        $groups = ['provider' => $response->providers, 'stay' => $response->stays, 'facility' => $response->facilities];
        $reasons = [];
        foreach ($groups as $type => $rows) {
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id > 0) {
                    $reasons[$type . '-' . $id] = $this->reasons($type, $row);
                }
            }
        }
        return [
            'understood' => [
                'need' => $this->need($response->intent),
                'location' => $this->location($response),
                'radius' => 'Within ' . max(1, $response->intent->radiusKm ?? (int) config('ai_search.default_radius_km', 25)) . ' km',
                'urgency' => $response->intent->urgency !== 'normal' ? ucfirst($response->intent->urgency) : null,
            ],
            'distance' => $this->distance($groups),
            'result_reasons' => $reasons,
            'next_action' => $this->nextAction($response),
        ];
    }

    private function need(Intent $intent): string
    {
        $keys = array_values(array_unique(array_merge($intent->providerCategoryKeys, $intent->stayTypeKeys, $intent->facilityTypeKeys)));
        if ($keys !== []) {
            return implode(', ', array_map([$this, 'humanise'], $keys));
        }
        return match ($intent->intentType) {
            Intent::TYPE_PROVIDER => 'A service provider', Intent::TYPE_STAY => 'A place to stay',
            Intent::TYPE_FACILITY => 'A traveller facility', Intent::TYPE_MIXED => 'Several types of traveller help',
            default => 'More detail needed',
        };
    }

    private function location(SearchResponse $response): string
    {
        if ($response->town !== null) {
            return trim((string) ($response->town['name'] ?? '') . (!empty($response->town['state_abbr']) ? ', ' . $response->town['state_abbr'] : ''));
        }
        if ($response->originLat !== null && $response->originLng !== null) {
            return 'Your device location';
        }
        return $response->intent->locationText ?? 'Location still needed';
    }

    /** @param array<string,list<array<string,mixed>>> $groups @return array{label:string,detail:string} */
    private function distance(array $groups): array
    {
        if (RoadDistanceService::groupsUseRoadDistance($groups)) {
            return ['label' => 'Road distance', 'detail' => 'Available results are filtered and sorted using Google Maps driving distance.'];
        }
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if (is_numeric($row['distance_km'] ?? null)) {
                    return ['label' => 'Straight-line estimate', 'detail' => 'Driving routes were unavailable, so displayed distances are clearly labelled straight-line estimates.'];
                }
            }
        }
        return ['label' => 'Distance unavailable', 'detail' => 'No reliable distance is shown for results without a measurable location.'];
    }

    /** @param array<string,mixed> $row @return list<string> */
    private function reasons(string $type, array $row): array
    {
        $out = [];
        if ($type === 'provider') {
            $out[] = !empty($row['is_inferred']) ? 'Related service that may fit; confirm the service before travelling.' : 'Direct match for the provider service you requested.';
            $model = (string) ($row['service_model'] ?? '');
            if (in_array($model, ['mobile', 'both', 'workshop'], true)) {
                $out[] = 'The listing identifies this as ' . match ($model) { 'mobile' => 'a mobile service.', 'both' => 'a mobile and workshop service.', default => 'a workshop service.' };
            }
            if (!empty($row['is_verified'])) {
                $out[] = 'Business identity is verified by VanAssist.';
            } elseif (!empty($row['is_unclaimed'])) {
                $out[] = 'Listing is unclaimed; confirm details directly with the business.';
            }
        } elseif ($type === 'stay') {
            $out[] = 'Matches your request for ' . strtolower($this->humanise((string) ($row['stay_type'] ?? 'a place to stay'))) . '.';
        } else {
            $out[] = 'Matches your request for ' . strtolower($this->humanise((string) ($row['facility_type'] ?? 'traveller facility'))) . '.';
            if (($row['verification_status'] ?? '') === 'verified') {
                $out[] = 'Facility record is verified.';
            } elseif (($row['verification_status'] ?? '') === 'reviewed') {
                $out[] = 'Imported facility record has been reviewed.';
            }
        }
        $distance = RoadDistanceService::displayLabel($row);
        if ($distance !== '') {
            $out[] = $distance . ' from the interpreted search location.';
        }
        $provenance = trim((string) ($row['assist_provenance_label'] ?? ''));
        if ($provenance !== '') {
            $out[] = 'Source status: ' . $provenance . '.';
        }
        return array_slice($out, 0, 4);
    }

    /** @return array{heading:string,body:string,url:?string,label:?string,tone:string} */
    private function nextAction(SearchResponse $response): array
    {
        if (!$response->searched || $response->intent->clarificationRequired) {
            return ['heading' => 'Next step', 'body' => 'Add what you need and a town, postcode or "near me" location.', 'url' => null, 'label' => null, 'tone' => 'neutral'];
        }
        if ($response->localResultCount() === 0) {
            return ['heading' => 'No suitable local result yet', 'body' => 'Try a larger radius or nearby town, or use category search to control the service and location directly.', 'url' => url('find'), 'label' => 'Adjust category search', 'tone' => 'neutral'];
        }
        if ($response->intent->intentType === Intent::TYPE_PROVIDER) {
            $urgent = in_array($response->intent->urgency, ['urgent', 'emergency', 'high'], true);
            return [
                'heading' => $urgent ? 'Make yourself safe, then confirm help' : 'Confirm the provider can help',
                'body' => $urgent ? 'If roadside, move away from traffic where safe, use hazard lights and contact emergency services for immediate danger. Then call a suitable provider and confirm availability.' : 'Open a suitable provider, check the listed services and confirm availability before travelling.',
                'url' => null, 'label' => null, 'tone' => $urgent ? 'urgent' : 'action',
            ];
        }
        if ($response->intent->intentType === Intent::TYPE_STAY) {
            return ['heading' => 'Check access before you travel', 'body' => 'Confirm availability, vehicle limits, arrival times and current access conditions with the operator.', 'url' => url('stays'), 'label' => 'View all places to stay', 'tone' => 'action'];
        }
        return ['heading' => 'Check the details, then navigate', 'body' => 'Review the source and any access or operating details before setting off.', 'url' => null, 'label' => null, 'tone' => 'action'];
    }

    private function humanise(string $value): string
    {
        return ucfirst(trim(str_replace(['_', '-'], ' ', $value)));
    }
}
