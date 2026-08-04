# Current API boundary

## Purpose

Describe the JSON endpoints used by the first-party web application and the
restricted Admin API foundation without presenting either as a general public
partner API.

## Intended users

Developers maintaining the web client, Assist RIC operators, and integrators
assessing — but not assuming — future public API capability.

## Permissions

Public lookup endpoints use the current verified host and brand context plus any
route-level rate limits or module checks. Browser mutations retain sessions and
CSRF.

The versioned Admin API (`/api/v1/admin`) is a separate, token-authenticated
management surface for Assist RIC and service accounts. It is disabled by
default (`ADMIN_API_ENABLED=false`), brand-scoped, audited, and least-privilege
by scope. Human sessions support optional TOTP MFA (`ADMIN_API_MFA_REQUIRED`,
default false). It is not a public partner API and must not be enabled in
production until Platform Quality Gate evidence is recorded. See
`docs/LIVE_API.md`, `docs/PHASE1_ADMIN_API_DESIGN.md` and ADRs 0018–0020.

No general public token-authenticated `/api/v1` product for third parties exists
yet.

## Fields

Town search and nearest-location responses use the fields returned by
`LocationController`. Nearby providers use the controller's selected-brand
provider projection. TowSmart catalogue endpoints use the fields returned by
`TowSmartController`. These are implementation responses, not a published
compatibility contract.

Admin API success and error envelopes follow `docs/API.md` and
`docs/openapi/admin-v1.yaml`.

## Actions

The first-party client can search towns, resolve a nearest town, request nearby
providers, and load TowSmart catalogue data on the TowSmart brand. Consumers
must handle validation and error responses implemented by each controller.

When enabled, Assist RIC may authenticate as a service account, submit checksummed
import packages, manage drafts, browse Directory Management resources
(`GET /providers`, `/stays`, `/facilities`, `/claims`, `/corrections` with cursor
pagination), browse Data Review queues (`GET /drafts`, `/duplicates`,
`/recycle-bin` with cursor pagination), read datasets, audit, search analytics,
search-gaps, operational overview (`GET /overview`) and website insights
(`GET /website-insights`) through `/api/v1/admin` only — never by opening
production MariaDB. Claim/correction approve/reject, draft approve, duplicate
merge and recycle purge remain human-session Admin API actions and are also
available in the website admin. Categories, locations and import-candidate
queues stay on PHP admin routes. Ask Insights may also read
`GET /ai/usage/requests` (and related AI usage rollups) plus dual-source
`GET /search-gaps` for knowledge-gap engagement meta. Operations may read
`GET /health`, `/version`, `/capabilities`, dataset `/sync-history`,
`/sync-conflicts`, `GET /imports` and `/imports/{id}`, AI usage rollups
(including summary `budget`), `GET /feature-flags` (`flags:read`) and
`/audit` — never feature-flag writes or production toggles from RIC.

## Workflows

Use first-party endpoints only inside the current browser application. Resolve
brand from the approved host rather than sending a client-selected `brand_id`.

Use the Admin API only from approved management clients with vault-stored
credentials and least-privilege scopes. Prefer export-package hand-off while the
Admin API remains disabled.

If a public integration is required, design a separately authenticated and
versioned `/api/v1` contract with tests and an accepted architecture decision.

## Examples

`GET /locations/towns` supports the site's town lookup.
`GET /calculator/catalogue/{type}` supports TowSmart catalogue selection.
Neither route is a promise of long-term third-party compatibility.

`GET /api/v1/admin/health` and `GET /api/v1/admin/capabilities` are the Admin API
probe endpoints when the feature flag is on.

`GET /api/v1/admin/overview` returns the RIC operational rollup (range query
`7d`/`30d`/`90d`/`fy`/`pfy`/`custom`). `GET /api/v1/admin/website-insights`
returns detailed traffic and demand figures; genuine visitors exclude
bot/unknown page views and filtered bot views are labelled separately.

## Common mistakes

- Calling first-party browser endpoints a supported public API.
- Reusing browser session cookies as machine credentials.
- Letting a client select private brand scope.
- Depending on response fields without a published contract.
- Connecting Assist RIC or importers directly to production MariaDB.
- Enabling Admin API in production before MFA and Quality Gate evidence.

## Related pages

See **Repository workflow**, `docs/API.md`, `docs/LIVE_API.md` and
`docs/openapi/admin-v1.yaml`.

## FAQ

**Is `/api/v1/admin` a public partner API?** No. It is a restricted management
surface for Assist RIC and service accounts, disabled by default until MFA and
Quality Gate evidence are recorded.

**Can Assist RIC open production MariaDB?** No. Production writes and reads for
management clients must use `/api/v1/admin` only (ADR 0018).

## Version introduced

Current repository baseline.

## Last updated

2026-08-04 (RIC Operations Increment G).

## Owner

Assist Platform product and engineering.

## Changelog

| Date | Change |
| --- | --- |
| 2026-07-30 | Initial living-documentation page for the current first-party API boundary. |
| 2026-08-01 | Documented `/api/v1/admin` Phase 1 foundation as a restricted, default-off management API (CORE-011). |
| 2026-08-01 | Documented OPS-010 TOTP enrollment and MFA login challenge for Admin API humans. |
| 2026-08-02 | Documented Option B Admin API Increments B–G: claims, corrections, duplicates, datasets, AI usage, search analytics, sync conflicts and `/facilities`. |
| 2026-08-02 | Recorded Option B programme A–L conditional Quality Gate; production Admin API flags remain gated. |
| 2026-08-04 | Documented RIC Operations Increment G: `GET /imports`, summary `budget`, `GET /feature-flags` (`flags:read`; no writes). |
