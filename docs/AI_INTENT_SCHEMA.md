# AI intent schema (v1)

**Status:** implemented (AI-1 rules + AI-3 Structured Outputs).  
**Version id:** `intent_schema_v1`  
**Code:** `App\Platform\AiSearch\Intent\IntentJsonSchema`  
**Related:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md) §7, ADR 0022/0021.

## JSON Schema (strict / Structured Outputs compatible)

```json
{
  "$id": "assist.intent_schema_v1",
  "type": "object",
  "additionalProperties": false,
  "required": [
    "intent_type",
    "provider_category_keys",
    "stay_type_keys",
    "facility_type_keys",
    "location_text",
    "use_current_location",
    "radius_km",
    "urgency",
    "adapter_keys",
    "confidence",
    "clarification_required",
    "clarification_reason"
  ],
  "properties": {
    "intent_type": {
      "type": "string",
      "enum": [
        "find_provider",
        "find_stay",
        "find_traveller_facility",
        "mixed",
        "unknown"
      ]
    },
    "provider_category_keys": {
      "type": "array",
      "items": { "type": "string", "minLength": 1, "maxLength": 80 },
      "maxItems": 8
    },
    "stay_type_keys": {
      "type": "array",
      "items": {
        "type": "string",
        "enum": [
          "caravan_park",
          "campground",
          "free_camp",
          "showground",
          "rest_area",
          "farm_stay",
          "other"
        ]
      },
      "maxItems": 5
    },
    "facility_type_keys": {
      "type": "array",
      "items": {
        "type": "string",
        "enum": [
          "public_toilet",
          "dump_point",
          "drinking_water",
          "public_shower",
          "laundry",
          "rest_area",
          "visitor_information",
          "fuel",
          "lpg_refill",
          "hospital",
          "medical_centre",
          "pharmacy",
          "emergency_services",
          "boat_ramp",
          "picnic_area",
          "barbecue",
          "waste_disposal",
          "ev_charging",
          "weighbridge",
          "other_essential"
        ]
      },
      "maxItems": 8
    },
    "location_text": { "type": ["string", "null"], "maxLength": 120 },
    "use_current_location": { "type": "boolean" },
    "radius_km": {
      "type": ["integer", "null"],
      "minimum": 1,
      "maximum": 500
    },
    "urgency": {
      "type": "string",
      "enum": ["normal", "urgent"]
    },
    "adapter_keys": {
      "type": "array",
      "items": {
        "type": "string",
        "enum": [
          "providers",
          "stays",
          "traveller_facilities",
          "datasets"
        ]
      },
      "maxItems": 4
    },
    "confidence": { "type": "number", "minimum": 0, "maximum": 1 },
    "clarification_required": { "type": "boolean" },
    "clarification_reason": { "type": ["string", "null"], "maxLength": 240 }
  }
}
```

## Post-parse platform validation

1. Reject unknown `provider_category_keys` not in active `service_categories.slug`.
2. Allow `traveller_facilities` adapter when feature flag
   `assist_ai_traveller_facilities` is on (AI-6); strip when off.
3. `datasets` adapter is valid from AI-5; execution still requires feature flag
   `assist_ai_datasets` (off by default).
4. If `confidence < 0.55` (configurable): prefer clarification or rules-only.
5. If `clarification_required`: do not call paid external search.
6. Strip any unexpected keys; never accept free-form “answer” fields.

## Example — dump point near Batehaven

```json
{
  "intent_type": "mixed",
  "provider_category_keys": ["dump-points"],
  "stay_type_keys": [],
  "facility_type_keys": ["dump_point"],
  "location_text": "Batehaven",
  "use_current_location": false,
  "radius_km": 25,
  "urgency": "normal",
  "adapter_keys": ["providers"],
  "confidence": 0.9,
  "clarification_required": false,
  "clarification_reason": null
}
```

Until traveller facilities exist, router uses `providers` + `dump-points` (and
logs facility key as desired taxonomy for gap analysis).
