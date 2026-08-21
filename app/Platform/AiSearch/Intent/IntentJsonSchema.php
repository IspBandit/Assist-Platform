<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

use App\Platform\AiSearch\Dto\Intent;

/**
 * Strict JSON Schema for OpenAI Structured Outputs (intent_schema_v1).
 */
final class IntentJsonSchema
{
    public const NAME = 'assist_intent_v1';
    public const VERSION = 'intent_schema_v1';

    /** @return array<string,mixed> */
    public static function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'intent_type',
                'provider_category_keys',
                'stay_type_keys',
                'facility_type_keys',
                'location_text',
                'use_current_location',
                'radius_km',
                'urgency',
                'adapter_keys',
                'confidence',
                'clarification_required',
                'clarification_reason',
            ],
            'properties' => [
                'intent_type' => [
                    'type' => 'string',
                    'enum' => [
                        Intent::TYPE_PROVIDER,
                        Intent::TYPE_STAY,
                        Intent::TYPE_FACILITY,
                        Intent::TYPE_MIXED,
                        Intent::TYPE_UNKNOWN,
                    ],
                ],
                'provider_category_keys' => [
                    'type' => 'array',
                    'items' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
                    'maxItems' => 8,
                ],
                'stay_type_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => TaxonomyRegistry::STAY_TYPES,
                    ],
                    'maxItems' => 5,
                ],
                'facility_type_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => TaxonomyRegistry::FACILITY_TYPES,
                    ],
                    'maxItems' => 8,
                ],
                'location_text' => ['type' => ['string', 'null'], 'maxLength' => 120],
                'use_current_location' => ['type' => 'boolean'],
                'radius_km' => ['type' => ['integer', 'null'], 'minimum' => 1, 'maximum' => 500],
                'urgency' => ['type' => 'string', 'enum' => ['normal', 'urgent']],
                'adapter_keys' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'string',
                        'enum' => TaxonomyRegistry::ADAPTERS,
                    ],
                    'maxItems' => 4,
                ],
                'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                'clarification_required' => ['type' => 'boolean'],
                'clarification_reason' => ['type' => ['string', 'null'], 'maxLength' => 240],
            ],
        ];
    }
}
