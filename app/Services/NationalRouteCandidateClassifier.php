<?php

declare(strict_types=1);

namespace App\Services;

final class NationalRouteCandidateClassifier
{
    /** @return array{category_key:string,confidence:int,review_status:string,hold_reason:?string,state:string,route_hub:string} */
    public function classify(array $row): array
    {
        $name = strtolower((string) ($row['business_name'] ?? ''));
        $types = array_map('strtolower', array_map('strval', (array) ($row['place_types'] ?? [])));
        $slugs = array_map('strtolower', array_map('strval', (array) ($row['category_slugs'] ?? [])));
        $queries = array_map('strtolower', array_map('strval', (array) ($row['discovery_queries'] ?? [])));
        $category = $this->categoryKey($name, $types, $slugs, $queries);

        $confidence = 35;
        if (trim((string) ($row['phone'] ?? '')) !== '') {
            $confidence += 15;
        }
        if (trim((string) ($row['website'] ?? '')) !== '') {
            $confidence += 15;
        }
        if (($row['business_status'] ?? null) === 'OPERATIONAL') {
            $confidence += 10;
        }
        if ($this->nameSupportsCategory($name, $category)) {
            $confidence += 10;
        }

        $holdReason = null;
        if (($row['business_status'] ?? null) !== 'OPERATIONAL') {
            $holdReason = 'Google does not currently report this business as operational.';
        } elseif (trim((string) ($row['phone'] ?? '')) === '' && trim((string) ($row['website'] ?? '')) === '') {
            $holdReason = 'No phone or website is available for independent confirmation.';
        } elseif ($this->likelyRetailOnly($name) && !in_array($category, ['fuel-travel-stops', 'ev-charging'], true)) {
            $holdReason = 'Likely retail-only result; confirm it provides the selected service before reconsidering.';
        }

        $routeHubs = array_values(array_filter(array_map('strval', (array) ($row['route_hubs'] ?? []))));
        return [
            'category_key' => $category,
            'confidence' => min(85, $confidence),
            'review_status' => $holdReason === null ? 'pending' : 'held',
            'hold_reason' => $holdReason,
            'state' => strtoupper(substr((string) ($row['state'] ?? ''), 0, 3)),
            'route_hub' => mb_substr($routeHubs[0] ?? '', 0, 190),
        ];
    }

    /** @param array<int,string> $types @param array<int,string> $slugs @param array<int,string> $queries */
    private function categoryKey(string $name, array $types, array $slugs, array $queries): string
    {
        if (in_array('electric_vehicle_charging_station', $types, true) || $this->containsAny($name, ['supercharger', 'ev charging', 'ev charger'])) {
            return 'ev-charging';
        }
        if (in_array('gas_station', $types, true) || $this->containsAny($name, ['petrol', 'fuel', 'service station', 'truck stop', 'truckstop'])) {
            return 'fuel-travel-stops';
        }
        if ($this->containsAny($name, ['gas', 'refrigeration', 'air conditioning', 'airconditioning', 'appliance'])) {
            return 'caravan-gas-appliances';
        }
        if ($this->containsAny($name, ['auto electric', 'auto-electric', 'battery', '12 volt', '12v'])) {
            return 'auto-electrical';
        }
        if ($this->containsAny($name, ['tyre', 'tire', 'wheel'])) {
            return 'tyres-wheels-bearings';
        }
        if ($this->containsAny($name, ['tow', 'recovery', 'roadside'])) {
            return 'roadside-recovery';
        }
        if ($this->containsAny($name, ['trailer', 'brake', 'bearing', 'suspension', 'axle'])) {
            return 'trailer-brakes-suspension';
        }
        if ($this->containsAny($name, ['caravan', 'motorhome', 'camper', ' rv '])) {
            return 'caravan-rv-repairs';
        }
        if ($this->containsAny($name, ['diesel', 'mechanic', 'mechanical'])) {
            return 'mobile-diesel-mechanics';
        }

        $signals = array_merge($slugs, $queries);
        foreach ([
            'fuel-travel-stops' => ['fuel-station', 'fuel station diesel'],
            'ev-charging' => ['ev-charging', 'ev charging station'],
            'caravan-rv-repairs' => ['caravan-repairs', 'caravan rv repairs', 'mobile caravan technician'],
            'auto-electrical' => ['auto-electrician', '12-volt-electrical', 'auto electrician caravan 12 volt'],
            'tyres-wheels-bearings' => ['tyres', 'tyre service'],
            'roadside-recovery' => ['roadside-assistance', 'towing', 'roadside assistance towing'],
            'trailer-brakes-suspension' => ['trailer-brakes', 'trailer-bearings', 'suspension'],
            'caravan-gas-appliances' => ['gas-certification', 'caravan-appliances'],
            'mobile-diesel-mechanics' => ['mobile-mechanic', 'diesel-specialist'],
        ] as $category => $needles) {
            foreach ($needles as $needle) {
                if (in_array($needle, $signals, true)) {
                    return $category;
                }
            }
        }
        return 'caravan-rv-repairs';
    }

    private function nameSupportsCategory(string $name, string $category): bool
    {
        $hints = [
            'fuel-travel-stops' => ['petrol', 'fuel', 'service station', 'truck stop', 'truckstop'],
            'ev-charging' => ['charger', 'charging', 'supercharger'],
            'caravan-rv-repairs' => ['caravan', 'motorhome', 'camper', ' rv '],
            'auto-electrical' => ['electric', 'battery', '12v', '12 volt'],
            'tyres-wheels-bearings' => ['tyre', 'tire', 'wheel'],
            'roadside-recovery' => ['tow', 'recovery', 'roadside'],
            'trailer-brakes-suspension' => ['trailer', 'brake', 'bearing', 'suspension', 'axle'],
            'caravan-gas-appliances' => ['gas', 'refrigeration', 'air conditioning', 'appliance'],
            'mobile-diesel-mechanics' => ['diesel', 'mechanic', 'mechanical'],
        ];
        return $this->containsAny($name, $hints[$category] ?? []);
    }

    private function likelyRetailOnly(string $name): bool
    {
        return $this->containsAny($name, ['supercheap auto', 'repco', 'autobarn', 'battery world', 'bcf ', 'anaconda']);
    }

    /** @param array<int,string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }
}
