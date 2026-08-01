# AI testing

**Status:** design (Phase AI-0). Implement tests with each authorised increment.  
**Rule:** no automated test may use or modify production data.

## Deterministic intent engine

Toilets, dump points, drinking water, LPG, caravan parks, mobile caravan
repair, auto electrician, tyres, towing, ambiguous query, multiple-intent query.

## AI interpreter

Valid structured response, invalid schema, timeout, provider failure, budget
exhausted, cache hit, low confidence, prompt injection attempt, unsupported
intent.

## Routing

Provider-only, stay-only, facility-only, mixed; local adequate; external
fallback required; external unavailable; no result.

## Cost control

Daily/monthly request and currency limits; hard stop; soft warning; no silent
model upgrade; no paid fallback; graceful no-AI operation.

## Knowledge engine

Gap created; repeated gap grouped; priority increases; external staged;
duplicate detected; untrusted not staged; trusted_automatic only when
explicitly configured.

## Analytics

Search logged; AI usage logged; result click logged; contact action logged;
precise location privacy rule applied.

## Tooling

PHPUnit unit/feature tests; contract fixtures for vendor adapters (recorded
responses, never live paid calls in CI by default).
