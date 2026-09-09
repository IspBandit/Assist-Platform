# Assist Platform Sale Candidate — Buyer journey acceptance (2026-09-06)

Scope: VanAssist, TowSmart and TrailerWise only. LocalTorque and Polaris are excluded.

## Environment

- Candidate SHA: `a9dda71`
- Candidate tag target: `assist-sale-candidate-2026-09-06`
- Live production domains: `vanassist.com.au`, `towsmart.com.au`, `trailerwise.com.au`

## Route smoke checks (desktop + mobile UA)

Executed against current production DNS:

- `GET /` (200) for all three brands
- `GET /providers` (VanAssist, 200)
- `GET /request-assistance` (VanAssist, 200)
- `GET /calculator` (TowSmart, 200)
- `GET /marketplace` (TrailerWise, 200)
- `/healthz` and `/readyz` returned 200 for all three brands
- `manifest.webmanifest` and `service-worker.js` returned 200 for VanAssist

## Manual browser rendering notes

- Core brand identity and canonical links load from live markup in each brand home page.
- Home journeys return expected core CTA structure (search/request/help flows for VanAssist;
  calculator/workflow for TowSmart; marketplace/rules for TrailerWise).
- No client-side `500` errors observed from smoke requests.

## Scope note

This test set is evidence of availability/route health, not full manual UX QA.
A complete parity review still needs browser-level screenshot review for mobile and desktop
across 100+ user flows (as referenced in prior rendered acceptance packs).

## 9 September flagship Ask addendum

The September 2026 candidate now includes deterministic Ask as VanAssist's
primary homepage journey when its flag is enabled, with category/town search
retained as the fallback. New automated evidence covers:

- 128 national intent questions and 41 database-backed location cases;
- state/postcode qualifiers, duplicate towns, safe typo correction and radius;
- reviewed, provenance-labelled facilities scoped to VanAssist;
- emergency and roadside guidance with paid AI disabled;
- Chromium, Firefox and WebKit public journeys at representative desktop,
  tablet and mobile viewports;
- 404 denial for VanAssist-only request, run and park routes on TowSmart and
  TrailerWise.

This addendum does not retrospectively convert the 6 September smoke record
into full production acceptance. Final results and all skipped checks belong in
`docs/evidence/flagship-ask-three-site-audit-2026-09-09/`.
