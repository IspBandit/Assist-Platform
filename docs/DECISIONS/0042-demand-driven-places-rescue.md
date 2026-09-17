# ADR 0042: Demand-driven Places rescue and unclaimed auto-create

- **Status:** accepted
- **Date:** 2026-09-17
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** VAN-011, DATA-013, DATA-004
- **Affected brands/modules:** VanAssist `/find`, Ask VanAssist, Data Sources Google Places connector

## Context

Travellers in sparse regions (for example Charters Towers fridge repair) were
receiving zero VanAssist results while public search engines returned nearby
businesses in seconds. Classic `/find` defaulted to town-only scope (~20 km).
Ask searched only the local provider database, recorded knowledge gaps, and
never called Google Places. Monetisation depends on proven results in the
traveller journey.

ADR 0028/0031 already allow labelled live external results. ADR 0029/0032 forbid
AI from publishing verified canonical rows. National import already ships
unclaimed public-source listings with honesty labels.

## Decision

1. **Radius ladder (always on for category + origin searches):** when the
   traveller does not set a distance, expand 25 → 75 → 150 → 300 km until a
   minimum result count is met. Explicit distances remain sacred.
2. **Places rescue (feature flag `provider_places_rescue`, default off):** on
   zero or weak (&lt; 3) provider results, VanAssist may call the existing Google
   Places connector once per search, subject to Control Centre daily quota and
   budget guards.
3. **Display:** rescue hits appear as labelled public-source / not-verified
   results (`assist_origin = external_live`). They are never shown as verified
   VanAssist specialists.
4. **Auto-create unclaimed (owner decision):** when the flag is on and
   `places_rescue.auto_publish_unclaimed` is true, strong non-duplicates are
   inserted as **unclaimed** providers with Place ID provenance, brand listing,
   category assignment, and discovery evidence. Strong duplicates enrich
   unclaimed rows only; claimed/owner fields are never overwritten.
5. **Verified status** remains human claim + verification. Places rescue does
   not set `is_verified` or claim ownership.
6. Dataset browsing in Ask still must not casually call Places
   (`DatasetTrustPolicy::ASK_BLOCKED_CONNECTORS`); rescue is a dedicated path.

## Alternatives considered

- Overnight gap fill only: rejected — does not rescue the caller who needs help
  now (the Charters Towers phone call failure mode).
- Show Google hits as verified listings: rejected — trust and ADR 0029/0032.
- Live Bing/OpenAI web search as primary directory: deferred; Places connector
  already exists with budget and provenance controls.

## Consequences

- Production enablement requires Places API key, budget alert, Quality Gate, and
  turning `provider_places_rescue` on — code merge alone does not authorise it.
- Google Maps Platform terms: attribution, Place ID retention, and public
  contact fields only. Listings remain claimable.
- Zero-result rates should fall; knowledge gaps still record residual misses.

## Quality Gate impact

- Architecture / Trust: labelled provenance + unclaimed honesty model.
- Engineering: flag, budget, duplicate tests.
- Business: owner acceptance of unclaimed auto-create for demand-driven Places.
- UX: separate public-source block; never empty without request/claim CTAs.

## Validation and rollback

Disable `provider_places_rescue`. Radius ladder remains. Unclaimed listings
created while the flag was on stay claimable public-source rows.
