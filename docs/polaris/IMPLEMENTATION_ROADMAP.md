# Polaris — Implementation Roadmap

- **Status:** Phases 0–9 foundation implemented as private vertical slices; public launch blocked
- **Date:** 2026-08-01
- **Backlog:** POL-001 through POL-009

---

## Phase overview

| Phase | Name | Outcome | Status |
| --- | --- | --- | --- |
| 0 | Discovery & docs | Audit, ADRs, doc suite | Existing |
| 1 | Foundation | Brand, schema, homepage, browse shell | Partially implemented |
| 2 | Catalogue UX | Filters, provenance, SEO foundations | Partially implemented |
| 3 | Find & matching | Guided Find + recommendation engine | Partially implemented |
| 4 | TowSmart | Compatibility service + tow-match UX | Partially implemented |
| 5 | Compare & search+ | Comparison sets, FULLTEXT, optional NL | Partially implemented (compare); FULLTEXT/NL Planned |
| 6 | Data acquisition | Import drafts, merge, AI extraction | Partially implemented (CSV draft-first); paid AI Planned |
| 7 | Portals | Manufacturer + dealer portals | Partially implemented (manufacturer claim); dealer Planned |
| 8 | VanAssist | Provider surfacing, dealer enquiry | Partially implemented (related services); enquiry Planned |
| 9 | Hardening & launch | Analytics, SEO, release criteria | Partially implemented (events/saved/noindex); launch Blocked |

---

## Phase 0 — Discovery & documentation

**Backlog:** POL-001  
**Status:** Existing

Deliverables:

- [x] Repository audit (`POLARIS_REPOSITORY_AUDIT.md`)
- [x] Product and architecture doc suite (this folder)
- [x] Polaris ADRs 0001–0013
- [x] Platform ADR 0031
- [x] Backlog entries POL-001–POL-009 in `PRODUCT_BACKLOG.md`

Exit: Owner sign-off on boundaries and roadmap.

---

## Phase 1 — Foundation

**Backlog:** POL-001 / POL-002  
**Status:** Partially implemented

Deliverables:

- [x] Brand row + `config/brands.php` entry (`private`)
- [x] Migration `087`: core catalogue tables + demo fixtures
- [x] Admin nav module `rv_catalogue`
- [x] Public homepage with hero
- [x] `/rvs`, `/manufacturers`, model detail
- [x] `/find` guided shell
- [x] PHPUnit smoke tests

Exit: Local `ASSIST_BRAND=polaris` vertical slice demoable.

Non-goals: production DNS, used listings, AI publish.

---

## Phase 2 — Catalogue UX

**Backlog:** POL-002  
**Status:** Partially implemented

Deliverables:

- [x] Working filters and sort on `/rvs`
- [x] Provenance chips (source-level)
- [x] Field-level provenance on model pages
- [x] Model year selector (`?year=` on model detail)
- [x] Price display with staleness warnings
- [x] Canonical URLs + noindex while private
- [ ] FULLTEXT name search
- [x] Buying guides shell

---

## Phase 3 — Find & matching

**Backlog:** POL-003  
**Status:** Partially implemented

Deliverables:

- [x] Deterministic `MatchScorer` with explanations
- [x] Preference profile from guided form
- [x] Persisted preference profiles UI + Find hydration from saved prefs
- [ ] Optional NL interpretation via Assist AI (gated)

---

## Phase 4 — TowSmart

**Backlog:** POL-004  
**Status:** Partially implemented

Deliverables:

- [x] `TowCompatibilityService` wrapping TowSmart calculator
- [x] `/tow-match` UX
- [x] No duplicated tow vehicle catalogue

---

## Phase 5 — Compare

**Backlog:** POL-005  
**Status:** Partially implemented

Deliverables:

- [x] Compare up to four models with diffs and winners
- [x] Account comparison history (`/account/comparisons` lists signed-in share links)
- [x] Saved browse searches (capture + reopen; no email alerts)
- [ ] FULLTEXT / advanced search
- [ ] Alert delivery for saved searches

---

## Phase 6 — Data acquisition

**Backlog:** POL-006  
**Status:** Partially implemented

Deliverables:

- [x] Migration `088` import jobs + drafts
- [x] CSV upload → draft rows only
- [x] Admin review queue approve/reject publish
- [x] DuplicateDetection helpers
- [ ] Brochure / webpage AI extraction (paid, gated)
- [ ] Automated merge workflows UI

---

## Phase 7 — Manufacturer portal

**Backlog:** POL-007  
**Status:** Partially implemented

Deliverables:

- [x] `/portal/manufacturer` claim-first flow
- [x] Admin claim approval
- [x] Claimed manufacturer model edit (pending verification)
- [x] Portal analytics for model views/saves (find impressions still planned)
- [x] Portal data-quality completeness checklist (ATM/length/berths/price gaps)
- [ ] Dealer portal / stock inventory (out of scope for launch)

---

## Phase 8 — VanAssist surfacing

**Backlog:** POL-008  
**Status:** Partially implemented

Deliverables:

- [x] Related VanAssist services on model pages
- [x] Links to VanAssist URLs; no provider record duplication
- [ ] Dealer enquiry handoff

---

## Phase 9 — Hardening & launch

**Backlog:** POL-009  
**Status:** Blocked (public launch)

Deliverables:

- [x] Floorplans index
- [x] Saved shortlist
- [x] Fail-closed analytics events (no free-text prompts)
- [x] Private brand `noindex`
- [ ] Production domain + Quality Gate evidence
- [ ] Non-demo catalogue readiness

---

## Explicitly not done

- Production DNS / deploy
- Live web scraping
- Paid AI extraction publishing
- Full dealer portal
- Email alerts for saved searches
- Representing demo fixtures as live catalogue
