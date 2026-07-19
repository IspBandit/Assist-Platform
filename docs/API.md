# Assist Platform API Architecture

## Current status

The application is server-rendered. Existing JSON location/search endpoints are
first-party web endpoints, not a stable public API. There is no separately
versioned `/api/v1` contract and no token-authentication product today. This
must not be represented as a supported partner API.

## Future contract

New external APIs use `/api/v1/...` and separate route files/controllers from
HTML handlers. A version is additive within its published compatibility window;
breaking field, authentication, or semantic changes require a new major path.

The active brand is resolved from the verified deployment/host context. Clients
cannot select a private data scope by sending `brand_id` in a body or query.
Every repository query also applies brand and provider/organisation ownership
where relevant.

## Authentication and authorization

- Browser endpoints retain secure host-only sessions and CSRF protection.
- Future machine clients require scoped, revocable credentials; browser session
  cookies are not a general API credential.
- Authorization is checked server-side for every resource, including numeric
  IDs, slugs, aliases, exports, media, and nested resources.
- Cross-brand and cross-provider denials return a non-enumerating `404` where
  revealing existence would leak private data.

## Request and response standards

- JSON uses UTF-8 and `application/json`.
- Successful single resources use `{"data": {...}}`; collections use
  `{"data": [...], "meta": {...}, "links": {...}}`.
- Errors use `{"error": {"code": "...", "message": "...",
  "request_id": "..."}}`; validation may include a field-error map.
- Pagination is bounded and cursor-based for mutable/high-volume collections.
- Timestamps are ISO 8601 with an explicit offset; money is integer minor units
  plus ISO currency.
- Unknown fields are rejected for security-sensitive writes.
- Request and response correlation uses `X-Request-ID`.

## Mutation safety

State-changing endpoints validate content type, schema, feature/module gates,
brand scope, ownership, and current resource state. Retryable create/payment/
webhook operations require an idempotency key with a scoped uniqueness record.
Webhooks additionally require signature/timestamp verification and durable
event deduplication before side effects.

## Abuse controls and caching

Authentication and public-submission flows use persistent hashed rate-limit
buckets. API limits must return `429` and `Retry-After`. Authenticated/private
responses are `private, no-store`; explicitly public resources may opt into
short cache lifetimes with brand-aware keys and invalidation.

## Deprecation

Published fields or endpoints receive a documented replacement and sunset
window. Deprecation metadata should use `Deprecation`, `Sunset`, and `Link`
headers. Removal requires usage review, release notes, and contract tests.
