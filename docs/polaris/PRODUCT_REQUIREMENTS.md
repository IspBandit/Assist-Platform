# Polaris — Product Requirements

- **Status:** Partially implemented (requirements defined; delivery phased)
- **Date:** 2026-08-01
- **Backlog:** POL-001 through POL-009

Requirements use the status legend from [README.md](README.md).

---

## 4.1 Anonymous visitor (researcher)

**Goal:** Discover and evaluate new RV models without creating an account.

| ID | Requirement | Status |
| --- | --- | --- |
| VIS-01 | View Polaris homepage with category entry and Find CTA | Partially implemented |
| VIS-02 | Browse RV list with category, manufacturer and basic filters | Scaffolded |
| VIS-03 | Open model detail: specs, floorplans, price guidance, provenance | Scaffolded |
| VIS-04 | Browse manufacturers index and manufacturer profile | Scaffolded |
| VIS-05 | Start guided Find My RV (multi-stage questionnaire) | Scaffolded |
| VIS-06 | View match results with explainable scoring | Planned (Phase 3) |
| VIS-07 | Compare up to four models side-by-side | Planned (Phase 5) |
| VIS-08 | Run tow-match against saved or entered tow vehicle | Planned (Phase 4) |
| VIS-09 | Read buying guides (editorial, non-AI-generated facts) | Planned (Phase 2) |
| VIS-10 | See VanAssist provider suggestions on model pages | Planned (Phase 8) |
| VIS-11 | Use optional NL search alongside structured filters | Planned (Phase 5+) |
| VIS-12 | No account required for browse, find or compare | Planned |

**Acceptance:** Public routes work on `private` brand hosts without indexing
until release; all factual claims show source or “unknown” state.

---

## 4.2 Registered buyer (account holder)

**Goal:** Save progress, models and comparisons across sessions.

| ID | Requirement | Status |
| --- | --- | --- |
| BUY-01 | Register / sign in via shared platform auth | Existing |
| BUY-02 | Save models to shortlist (`/saved`) | Planned (Phase 5) |
| BUY-03 | Save Find answers and resume later | Planned (Phase 3) |
| BUY-04 | Save comparison sets | Planned (Phase 5) |
| BUY-05 | Link tow vehicle from shared Garage / TowSmart | Planned (Phase 4) |
| BUY-06 | Manage account profile and notification prefs | Existing (shared `/account/*`) |
| BUY-07 | Export or share comparison summary (read-only link) | Planned (Phase 5) |

**Acceptance:** Saved items scoped to user; no cross-brand data leakage.

---

## 4.3 Manufacturer representative

**Goal:** Claim and maintain authoritative model data for their brand.

| ID | Requirement | Status |
| --- | --- | --- |
| MFG-01 | Claim manufacturer organisation via platform claim flow | Existing (pattern) |
| MFG-02 | Manufacturer portal: dashboard, models, variants | Planned (Phase 7) |
| MFG-03 | Submit spec corrections with audit trail | Planned (Phase 7) |
| MFG-04 | Upload floorplans and media within upload policy | Planned (Phase 7) |
| MFG-05 | Preview unpublished changes before admin publish | Planned (Phase 7) |
| MFG-06 | View analytics: views, saves, find appearances | Planned (Phase 9) |
| MFG-07 | Cannot edit TowSmart tow ratings or VanAssist listings | Existing (boundary) |

**Acceptance:** Object-level permissions; manufacturer edits create reviewable
drafts until publish workflow exists.

---

## 4.4 Dealer representative

**Goal:** Associate with manufacturers and guide buyers — not list used stock.

| ID | Requirement | Status |
| --- | --- | --- |
| DLR-01 | Claim dealer organisation | Existing (pattern) |
| DLR-02 | Dealer portal: profile, territories, contact routing | Planned (Phase 7) |
| DLR-03 | Link to manufacturer models (no independent spec authority) | Planned (Phase 7) |
| DLR-04 | Optional “contact dealer” CTA on model pages | Partially implemented (mailto/website handoff) |
| DLR-05 | No dealer inventory, pricing or used listings | Planned (boundary enforced) |

**Acceptance:** Dealers cannot create parallel model records; they reference
catalogue entities only.

---

## 4.5 Platform administrator

**Goal:** Govern catalogue quality, imports and publication.

| ID | Requirement | Status |
| --- | --- | --- |
| ADM-01 | Polaris section in shared `/admin` (module `rv_catalogue`) | Scaffolded |
| ADM-02 | CRUD manufacturers, families, years, variants | Scaffolded |
| ADM-03 | Manage spec definitions and values | Scaffolded |
| ADM-04 | Review import drafts and provenance | Planned (Phase 6) |
| ADM-05 | Merge duplicate manufacturers/models | Planned (Phase 6) |
| ADM-06 | Soft-delete and restore via shared lifecycle | Partially implemented |
| ADM-07 | Feature-flag NL search and AI extraction | Existing (platform flags) |
| ADM-08 | Audit log for catalogue mutations | Existing |

See [ADMINISTRATION.md](ADMINISTRATION.md).

---

## 4.6 Platform integrator (internal / future API)

**Goal:** Read catalogue data through governed boundaries — not ad-hoc SQL.

| ID | Requirement | Status |
| --- | --- | --- |
| INT-01 | Read-only catalogue access via future Admin API / RIC | Planned (depends on CORE-011) |
| INT-02 | TowSmart compatibility checks via service boundary | Planned (Phase 4) |
| INT-03 | Webhook or sync for manufacturer-approved updates | Planned (post Phase 7) |
| INT-04 | No public unauthenticated bulk export at launch | Planned |

**Acceptance:** Integrations respect brand scope and provenance fields; no
secrets in client bundles.

---

## Cross-cutting requirements

| ID | Requirement | Status |
| --- | --- | --- |
| X-01 | Australian English UI copy | Partially implemented |
| X-02 | Mobile-responsive public pages | Partially implemented |
| X-03 | Accessibility: keyboard nav, labels, contrast (WCAG 2.1 AA target) | Planned |
| X-04 | Performance: list pages usable without heavy JS | Partially implemented |
| X-05 | SEO: canonical URLs, noindex while private | Planned (Phase 2 / 9) |
| X-06 | Analytics events per [ANALYTICS_AND_METRICS.md](ANALYTICS_AND_METRICS.md) | Planned (Phase 9) |

## Out of scope (explicit)

- Used RV listings, auctions, finance calculators as product truth
- Tow vehicle database inside Polaris
- VanAssist provider CRUD inside Polaris admin
- Autonomous AI publishing of catalogue records
