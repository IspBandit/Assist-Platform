# Dashboard operational integrity quality gate

**Backlog item:** DATA-004

**Affected brands:** VanAssist, TowSmart, TrailerWise and private LocalTorque

## Architecture — pass

- Brand switching continues to use the trusted-host session override and server-side `AdminBrandAccess` checks.
- Website Insights activation is an auditable forward migration and remains reversible through existing admin controls.
- Retired provider graphic-creation admin code is removed without changing clearly labelled sponsored-placement support.

## UX — pass

- A failed dashboard query now displays an unavailable state and a named warning rather than a misleading zero.
- Healthy metrics retain their existing concise cards and destinations.
- The warning uses the existing alert and colour system and remains readable on mobile layouts.

## Engineering — pass

- Dashboard failures are logged with section and brand context but no credentials or personal data.
- Brand switch persistence, dashboard failure presentation, analytics activation and retired-code removal have regression coverage.
- Migration 078 is additive/idempotent and includes the live privacy disclosure before enabling collection.

## Business — pass

- Administrators can distinguish genuine zero demand from unavailable data.
- First-party traffic and provider-interest collection starts with an accurate public disclosure and can still be disabled immediately.
- The product no longer carries unreachable admin screens for a provider graphic-creation service that is not offered.

## Deployment and rollback

- Deploy through the protected production workflow after CI and review.
- To stop collection immediately, disable first-party analytics under Settings and `demand_analytics` under Feature flags.
- Application rollback may leave migration 078 settings and disclosure in place; disable both controls before rollback if tracking must remain off.
