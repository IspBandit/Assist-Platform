<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

use App\Platform\AiSearch\Dto\Intent;

/**
 * Validates structured intent against taxonomy allowlists (AI-1 rules path).
 */
final class IntentSchemaValidator
{
    /**
     * @return array{ok:bool,intent:Intent,errors:list<string>}
     */
    public static function validate(Intent $intent): array
    {
        $errors = [];
        $allowedTypes = [
            Intent::TYPE_PROVIDER,
            Intent::TYPE_STAY,
            Intent::TYPE_FACILITY,
            Intent::TYPE_MIXED,
            Intent::TYPE_UNKNOWN,
        ];
        if (!in_array($intent->intentType, $allowedTypes, true)) {
            $errors[] = 'invalid_intent_type';
        }

        $providers = [];
        foreach ($intent->providerCategoryKeys as $key) {
            if (TaxonomyRegistry::isProviderCategoryKey($key)) {
                $providers[] = $key;
            } else {
                $errors[] = 'unknown_provider_category:' . $key;
            }
        }

        $stays = [];
        foreach ($intent->stayTypeKeys as $key) {
            if (TaxonomyRegistry::isStayTypeKey($key)) {
                $stays[] = $key;
            } else {
                $errors[] = 'unknown_stay_type:' . $key;
            }
        }

        $facilities = [];
        foreach ($intent->facilityTypeKeys as $key) {
            if (TaxonomyRegistry::isFacilityTypeKey($key)) {
                $facilities[] = $key;
            } else {
                $errors[] = 'unknown_facility_type:' . $key;
            }
        }

        $adapters = [];
        foreach ($intent->adapterKeys as $key) {
            if (TaxonomyRegistry::isAdapterKey($key)) {
                // AI-1: traveller_facilities and datasets are not executable yet.
                if ($key === 'traveller_facilities' || $key === 'datasets') {
                    continue;
                }
                $adapters[] = $key;
            } else {
                $errors[] = 'unknown_adapter:' . $key;
            }
        }

        // Facility-only intents with provider category fallback keep providers adapter.
        if ($adapters === [] && $providers !== []) {
            $adapters[] = 'providers';
        }
        if ($adapters === [] && $stays !== []) {
            $adapters[] = 'stays';
        }

        $urgency = in_array($intent->urgency, ['normal', 'urgent'], true) ? $intent->urgency : 'normal';
        $confidence = max(0.0, min(1.0, $intent->confidence));
        $radius = $intent->radiusKm;
        if ($radius !== null) {
            $radius = max(1, min(500, $radius));
        }

        $clean = new Intent(
            intentType: in_array($intent->intentType, $allowedTypes, true) ? $intent->intentType : Intent::TYPE_UNKNOWN,
            providerCategoryKeys: array_values(array_unique($providers)),
            stayTypeKeys: array_values(array_unique($stays)),
            facilityTypeKeys: array_values(array_unique($facilities)),
            locationText: $intent->locationText !== null ? mb_substr($intent->locationText, 0, 120) : null,
            useCurrentLocation: $intent->useCurrentLocation,
            radiusKm: $radius,
            urgency: $urgency,
            adapterKeys: array_values(array_unique($adapters)),
            confidence: $confidence,
            clarificationRequired: $intent->clarificationRequired,
            clarificationReason: $intent->clarificationReason !== null
                ? mb_substr($intent->clarificationReason, 0, 240)
                : null,
            source: $intent->source,
        );

        // Fatal only when intent type itself is illegal; unknown taxonomy keys are stripped.
        $fatal = [];
        foreach ($errors as $error) {
            if (str_starts_with($error, 'invalid_')) {
                $fatal[] = $error;
            }
        }

        return [
            'ok' => $fatal === [],
            'intent' => $clean,
            'errors' => $errors,
        ];
    }
}
