<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Dto;

/**
 * Structured intent produced by rules (AI-1) or later interpreters.
 *
 * @phpstan-type IntentArray array{
 *   intent_type:string,
 *   provider_category_keys:list<string>,
 *   stay_type_keys:list<string>,
 *   facility_type_keys:list<string>,
 *   location_text:?string,
 *   use_current_location:bool,
 *   radius_km:?int,
 *   urgency:string,
 *   adapter_keys:list<string>,
 *   confidence:float,
 *   clarification_required:bool,
 *   clarification_reason:?string
 * }
 */
final class Intent
{
    public const TYPE_PROVIDER = 'find_provider';
    public const TYPE_STAY = 'find_stay';
    public const TYPE_FACILITY = 'find_traveller_facility';
    public const TYPE_MIXED = 'mixed';
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * @param list<string> $providerCategoryKeys
     * @param list<string> $stayTypeKeys
     * @param list<string> $facilityTypeKeys
     * @param list<string> $adapterKeys
     */
    public function __construct(
        public readonly string $intentType,
        public readonly array $providerCategoryKeys,
        public readonly array $stayTypeKeys,
        public readonly array $facilityTypeKeys,
        public readonly ?string $locationText,
        public readonly bool $useCurrentLocation,
        public readonly ?int $radiusKm,
        public readonly string $urgency,
        public readonly array $adapterKeys,
        public readonly float $confidence,
        public readonly bool $clarificationRequired,
        public readonly ?string $clarificationReason,
        public readonly string $source = 'rules',
    ) {
    }

    /** @return IntentArray */
    public function toArray(): array
    {
        return [
            'intent_type' => $this->intentType,
            'provider_category_keys' => $this->providerCategoryKeys,
            'stay_type_keys' => $this->stayTypeKeys,
            'facility_type_keys' => $this->facilityTypeKeys,
            'location_text' => $this->locationText,
            'use_current_location' => $this->useCurrentLocation,
            'radius_km' => $this->radiusKm,
            'urgency' => $this->urgency,
            'adapter_keys' => $this->adapterKeys,
            'confidence' => $this->confidence,
            'clarification_required' => $this->clarificationRequired,
            'clarification_reason' => $this->clarificationReason,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data, string $source = 'rules'): self
    {
        $location = $data['location_text'] ?? null;
        $radius = $data['radius_km'] ?? null;
        $reason = $data['clarification_reason'] ?? null;

        return new self(
            intentType: (string) ($data['intent_type'] ?? self::TYPE_UNKNOWN),
            providerCategoryKeys: self::stringList($data['provider_category_keys'] ?? []),
            stayTypeKeys: self::stringList($data['stay_type_keys'] ?? []),
            facilityTypeKeys: self::stringList($data['facility_type_keys'] ?? []),
            locationText: is_string($location) && $location !== '' ? $location : null,
            useCurrentLocation: (bool) ($data['use_current_location'] ?? false),
            radiusKm: is_numeric($radius) ? (int) $radius : null,
            urgency: (string) ($data['urgency'] ?? 'normal'),
            adapterKeys: self::stringList($data['adapter_keys'] ?? []),
            confidence: (float) ($data['confidence'] ?? 0.0),
            clarificationRequired: (bool) ($data['clarification_required'] ?? false),
            clarificationReason: is_string($reason) && $reason !== '' ? $reason : null,
            source: $source,
        );
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $out[] = $item;
            }
        }
        return array_values(array_unique($out));
    }
}
