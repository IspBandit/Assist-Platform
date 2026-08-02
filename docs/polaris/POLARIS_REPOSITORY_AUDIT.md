# Polaris — Repository Audit

- **Status:** Phase 0 complete
- **Date:** 2026-08-01
- **Backlog:** POL-001
- **Branch context:** Assist Platform Enterprise shared application

This audit precedes substantial Polaris implementation. Precedence follows
`docs/START_HERE.md`: executable code and migrations over historical docs.

## Executive summary

Polaris is **not present** in the repository (zero prior references). Assist
Platform already provides a typed multi-brand stack, shared auth/RBAC, admin
portal, provider claiming, soft-delete patterns, audit logging, TowSmart tow
vehicle catalogue and calculator, VanAssist provider directory, TrailerWise
service directory (plus secondary marketplace), LocalTorque private-brand
pattern, design system, feature flags, and an AI search abstraction (AI-1,
flagged off).

Polaris must be a **fifth brand** (tenant) on the shared application — not a
standalone app. The primary gap is a **canonical new-RV product catalogue** with
provenance, model-year versioning and guided matching. TowSmart JSON towables
and TrailerWise listings must **not** be treated as that catalogue.

---

## 1. Current architecture (verified)

| Area | Finding | Key paths |
| --- | --- | --- |
| Stack | Custom PHP MVC; Composer; PHP 8.1+ (prod/CI 8.3); MariaDB 11.4 | `composer.json`, `docs/CURRENT_ARCHITECTURE.md` |
| Brands | Immutable `Brand` VO; host/`ASSIST_BRAND` resolution | `app/Platform/Brand/*`, `config/brands.php` |
| Brands live | VanAssist, TowSmart, TrailerWise active; LocalTorque private | `config/brands.php` |
| Auth | Session auth; global RBAC; ownership checks in services | `app/Auth/Auth.php`, migrations `001` |
| Public UI | Server-rendered PHP views; brand-themed CSS vars | `app/Views/layouts/public.php`, `public/assets/css/app.css` |
| Admin | Shared `/admin`; nav gated by permission + `moduleEnabled()` | `routes/admin.php`, `app/Views/layouts/admin.php` |
| Migrations | Forward-only numbered SQL through `086` | `database/migrations/`, ADR 0002 |
| Deploy | Docker Compose, immutable releases, Quality Gate | `infrastructure/`, `docs/OPERATIONS_RUNBOOK.md` |

---

## 2. Capability matrix

### Exists and reusable

| Capability | Notes |
| --- | --- |
| Multi-brand registry | Add Polaris via `config/brands.php` + DB `brands` row (LocalTorque pattern) |
| Auth, roles, permissions | Shared `users` / RBAC; no separate Polaris auth |
| Admin portal shell | Extend nav with `rv_catalogue` module; do not fork admin |
| Provider claim / onboarding | Claim-first flows for manufacturers/dealers as organisations |
| Soft-delete columns | Model base supports `$softDeletes` |
| Audit log | `App\Services\AuditLog` |
| Feature flags | Env + DB `feature_flags` + brand modules |
| Media upload validation | `config/uploads.php`, `ImageProcessor` |
| First-party analytics | `App\Services\Analytics` (extend events) |
| Design system / public layout | Extend `PLATFORM_DESIGN_SYSTEM.md`; reuse tokens |
| TowSmart vehicle catalogue | Authoritative tow-vehicle source for compatibility |
| TowSmart calculator patterns | Guidance-only; reuse service boundary |
| VanAssist providers | Surface related services; do not duplicate |
| Shared Garage | Owner tow vehicles / RV assets (`CORE-009`) |
| Data Sources / review-first ingest | Pattern for draft imports (`DATA-006`) |
| AI orchestrator (AI-1) | Interpretation only; ADR 0021–0027 — not factual authority |
| CI / analyse / PHPUnit | Existing quality gate tooling |

### Exists but needs extension

| Capability | Extension required |
| --- | --- |
| Brand home routing | `HomeController` hardcodes brand IDs — add Polaris branch |
| Admin nav | Module-gated Polaris section |
| Brand Builder | Preview-only; Polaris registered manually like LocalTorque |
| Recycle Bin (`OPS-011`) | Soft-delete present; restore/purge UI incomplete — Polaris must follow shared lifecycle rules and not invent a parallel bin |
| TowSmart catalogue provenance | `TOW-001` incomplete; Polaris must not invent parallel vehicle DB |
| Reviews | Schema exists; product off by default |
| Billing | Disabled; manufacturer plans later |
| Search | MariaDB LIKE/geo; Polaris needs catalogue indexes + structured filters |
| UX tokens (`EXP-001`) | Semantic token migration incomplete — Polaris must use brand theme vars |
| Admin API (`CORE-011`) | Design/work in progress on some branches; Polaris catalogue can start without it; RIC sync later |
| AI budgets / cache | AI-2 planned; Polaris NL matching must fail closed without paid AI |

### Missing for Polaris

| Capability | Phase target |
| --- | --- |
| Product definition & docs suite | Phase 0 — this folder |
| Polaris brand registration | Phase 1 |
| RV manufacturer / model / year / variant schema | Phase 1–2 |
| Governed specification catalogue + hybrid values | Phase 1–2 |
| Source provenance for RV claims | Phase 2 / 6 |
| Public homepage, browse, detail, find shell | Phase 1 |
| Guided matching + scoring engine | Phase 3 |
| Comparison sets | Phase 5 |
| Data acquisition / extraction jobs | Phase 6 |
| Manufacturer & dealer portals | Phase 7 |
| VanAssist provider surfacing on model pages | Phase 8 |
| SEO sitemaps for models | Phase 2 / 9 |
| Polaris analytics event catalogue | Phase 9 (spec now) |
| Production domain / DNS | Owner decision — remain `private` until confirmed |

### Technical debt that could block Polaris

1. **Brand-ID conditionals** in `HomeController` and parts of admin — new brand requires explicit wiring until policy-based routing exists.
2. **ADR index vs file collision history** around 0015–0017 (Admin API vs other topics) — Polaris ADRs live under `docs/polaris/DECISIONS/` and platform ADR **0028**.
3. **TowSmart JSON provenance gaps** — compatibility claims must state data confidence.
4. **Recycle Bin not fully productised** — soft-delete only until `OPS-011`.
5. **Parallel WIP** (AI, Admin API) — avoid colliding migrations; use next free number (`087+`).

### Documentation vs implementation disagreements

| Document claim | Reality |
| --- | --- |
| Four brands only | Correct today; Polaris will be fifth |
| `PRODUCTION_CURRENT_STATE` migration depth | Branch ahead (through ~086) |
| Recycle Bin “ready” in backlog | Soft-delete yes; bin UI/API not complete |
| AI-0 “do not start AI-1” | AI-1 code present, flagged off — treat as optional for Polaris NL |

### Production risks

- Enabling Polaris on a public domain before Quality Gate and catalogue readiness.
- Migrating fabricated seed data into production.
- Treating TowSmart/Polaris weights as legal certification.
- Accidental public indexing of private brand hosts.

### Security risks

- Catalogue imports are untrusted input (prompt injection, SSRF on fetch).
- Manufacturer claim authority must reuse verified claim patterns.
- Object-level auth for manufacturer team edits.
- No secrets in seeds or docs.

### Data-quality risks

- Fabricated demo fixtures mistaken for real models.
- Duplicate manufacturers without claim-first UX.
- Missing specs treated as favourable in matching (must apply uncertainty penalty).
- Stale RRP shown as current.

---

## 3. Recommended implementation sequence

| Phase | Outcome |
| --- | --- |
| **0** | Audit, docs, ADRs, backlog IDs, roadmap — **this document** |
| **1** | Brand + homepage + basic entities + browse/detail/find shell + admin nav + seeds + tests |
| **2** | Full catalogue UX, filters, provenance display |
| **3** | Guided matching + scoring |
| **4** | TowSmart compatibility service boundary |
| **5** | Comparison |
| **6** | Import / AI extraction (draft-first) |
| **7** | Manufacturer portal |
| **8** | VanAssist provider surfacing |
| **9** | Hardening, SEO, analytics, release criteria |

Non-goals for Phases 0–1: used marketplace, dealer stock inventory, paid AI, production DNS, permanent delete UI beyond soft-delete restore.

---

## 4. Explicit reuse boundaries

| Domain | Authority |
| --- | --- |
| Tow vehicles, GVM/GCM/tow ratings, calculators | **TowSmart** |
| Repairers, warranty agents, RV service providers | **VanAssist** |
| Trailer service businesses / secondary listings | **TrailerWise** (do not copy into Polaris catalogue) |
| New RV manufacturers, models, specs, floorplans | **Polaris** (new) |
| Shared auth, admin, media, audit, flags | **Platform** |

---

## 5. Audit conclusion

The platform can host Polaris as a private fifth brand with a coherent Phase 1
vertical slice without duplicating TowSmart or VanAssist data. The largest build
is the RV catalogue and decision UX; the largest risk is treating incomplete
tow/catalogue data as certain. Proceed with documentation, ADR 0031, brand
registration, migration `087`, and a production-quality homepage under feature
module `rv_catalogue`.
