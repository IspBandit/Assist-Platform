# Polaris — Release Criteria

- **Status:** Phase 9 checklist (public launch blocked)
- **Date:** 2026-08-02
- **Backlog:** POL-009

Definition of done for **public production launch** of Polaris. Until all items
pass, brand remains `private` with `noindex`.

Private vertical-slice foundation (Phases 0–8 partial) is documented in
`IMPLEMENTATION_STATUS.md` and is **not** a production launch authorisation.

---

## Business

| # | Criterion | Status |
| --- | --- | --- |
| B1 | Production domain and DNS confirmed by owner | Blocked |
| B2 | Product boundaries signed off (no used marketplace) | Existing (docs) |
| B3 | ≥10 real manufacturers with published current-year models | Planned |
| B4 | Manufacturer claim path tested with pilot org | Partially implemented |
| B5 | Privacy/legal review for enquiry flows | Planned |
| B6 | Release notes and user-facing guide published | Planned |

---

## Architecture

| # | Criterion | Status |
| --- | --- | --- |
| A1 | Polaris ADRs 0001–0013 accepted | Existing |
| A2 | Platform ADR 0031 records fifth brand | Existing |
| A3 | No TowSmart tow vehicle table duplication verified | Existing (service boundary) |
| A4 | No VanAssist provider duplication verified | Existing (URL surfacing) |
| A5 | Migration forward-only applied in staging | Planned |
| A6 | Rollback plan documented in operations runbook addendum | Planned |

---

## UX

| # | Criterion | Status |
| --- | --- | --- |
| U1 | Homepage, browse, detail, find, compare responsive at 390px and 1440px | Partially implemented |
| U2 | Hero meets premium visual contract (AVIF/WebP, no baked text) | Partially implemented |
| U3 | Unknown specs and stale prices visible per UX spec | Partially implemented |
| U4 | Tow guidance disclaimers on all compatibility surfaces | Existing |
| U5 | WCAG 2.2 AA spot-check passed (critical paths) | Planned |
| U6 | Design tokens use Polaris theme without purple/AI clichés | Partially implemented |

---

## Engineering

| # | Criterion | Status |
| --- | --- | --- |
| E1 | `composer validate`, `composer analyse` clean | Existing (branch gate) |
| E2 | PHPUnit Polaris suite green in CI | Partially implemented |
| E3 | Permission tests for admin and portal routes | Planned |
| E4 | Import pipeline SSRF tests pass | Planned |
| E5 | AI flags off default; site usable with flags off | Existing |
| E6 | Security review for catalogue import path | Planned |
| E7 | Staging soak test ≥72 hours without fatal errors | Planned |

---

## Data quality

| # | Criterion | Status |
| --- | --- | --- |
| D1 | ≥80% published variants have ATM, length, berths with source | Planned |
| D2 | All prices show effective date and source | Partially implemented |
| D3 | Demo seed data not present in production | Planned |
| D4 | Duplicate manufacturer merge policy exercised | Partially implemented |
| D5 | Soft-delete lifecycle aligned with OPS-011 | Partially implemented |

---

## SEO & analytics

| # | Criterion | Status |
| --- | --- | --- |
| S1 | Public pages crawlable when brand leaves private | Blocked (noindex while private) |
| S2 | Sitemaps/canonicals for manufacturers and models | Partially implemented |
| S3 | First-party analytics events for core journeys | Partially implemented |
| S4 | No fabricated live catalogue statistics on marketing surfaces | Existing |

---

## Gate decision

| Environment | Decision |
| --- | --- |
| Non-production / private brand | Allowed for demo fixtures and operator testing |
| Production public launch | **FAIL / Blocked** until B1, B3, U5, E6/E7, D1–D3 pass |
