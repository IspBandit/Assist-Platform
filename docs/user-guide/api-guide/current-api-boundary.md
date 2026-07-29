# Current API boundary

## Purpose

Describe the JSON endpoints used by the first-party web application without presenting them as a stable public partner API.

## Intended users

Developers maintaining the web client and integrators assessing, but not assuming, future API capability.

## Permissions

Public lookup endpoints use the current verified host and brand context plus any route-level rate limits or module checks. Browser mutations retain sessions and CSRF. No general token-authenticated `/api/v1` product exists in the current repository.

## Fields

Town search and nearest-location responses use the fields returned by `LocationController`. Nearby providers use the controller's selected-brand provider projection. TowSmart catalogue endpoints use the fields returned by `TowSmartController`. These are implementation responses, not a published compatibility contract.

## Actions

The first-party client can search towns, resolve a nearest town, request nearby providers, and load TowSmart catalogue data on the TowSmart brand. Consumers must handle validation and error responses implemented by each controller.

## Workflows

Use these endpoints only inside the current browser application. Resolve brand from the approved host rather than sending a client-selected `brand_id`. If a public integration is required, design a separately authenticated and versioned `/api/v1` contract with tests and an accepted architecture decision.

## Examples

`GET /locations/towns` supports the site's town lookup. `GET /calculator/catalogue/{type}` supports TowSmart catalogue selection. Neither route is a promise of long-term third-party compatibility.

## Common mistakes

- Calling these endpoints a supported public API.
- Reusing browser session cookies as machine credentials.
- Letting a client select private brand scope.
- Depending on response fields without a published contract.

## Related pages

See **Repository workflow** and the canonical `docs/API.md` architecture document.

## FAQ

**Where is `/api/v1`?** It is a documented future boundary, not a current route surface.

**Can a partner receive credentials today?** No token-authentication product is implemented or documented as supported.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
