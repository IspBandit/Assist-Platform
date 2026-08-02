# Polaris — Data Acquisition

- **Status:** Partially implemented (CSV/JSON/XLSX + brochure text; Phase 6)
- **Date:** 2026-08-02
- **Backlog:** POL-006
- **ADR:** [0010-source-provenance.md](DECISIONS/0010-source-provenance.md),
  [0012-duplicate-merging.md](DECISIONS/0012-duplicate-merging.md)

---

## Purpose

Populate the Polaris new-RV catalogue from **governed sources** with full
provenance. Acquisition never bypasses human review for initial publish.

---

## Source types

| Type | Description | Trust default |
| --- | --- | --- |
| Manufacturer website | Public spec pages | imported → verified after claim |
| Brochure PDF | Official literature | imported |
| Manufacturer portal | Direct submission | verified |
| Admin manual | Staff entry with citation | verified |
| AI extraction | Draft field suggestions | inferred (never auto-publish) |

---

## Acquisition channels

### 1. Manual admin entry (Phase 1–2)

**Status:** Scaffolded

Administrators create manufacturers, models and spec values in `/admin/polaris`.
Each value requires `source_id` or explicit “unknown” with reason.

### 2. Structured import (Phase 6)

**Status:** Planned

- CSV template aligned to `polaris_spec_definitions` keys
- Validation against definitions and enums
- Import → `polaris_import_drafts` → reviewer publish

### 3. Web fetch jobs (Phase 6)

**Status:** Planned

- Allowlisted domains per manufacturer
- SSRF protections: no private IP, no file://, timeout limits
- Snapshot stored for audit; content hash deduplication

### 4. AI-assisted extraction (Phase 6, optional)

**Status:** Planned

- Uses shared Assist AI Orchestrator (ADR 0008)
- Output: draft JSON mapped to spec definitions
- Mandatory human review per ADR 0019 / 0026
- Feature-flag and budget gated (ADR 0022)

---

## Pipeline stages

```
Source → Extract/Parse → Draft → Validate → Review → Publish → Monitor
```

| Stage | Owner | Output |
| --- | --- | --- |
| Extract | Job or admin | Raw + normalized draft |
| Validate | Schema validator | Errors/warnings list |
| Review | Administrator | Approve/reject/merge |
| Publish | Service layer | Live variant + provenance |
| Monitor | Scheduled job | Stale source detection |

---

## Duplicate handling

Before publish:

1. Fuzzy match manufacturer name (AI suggestion allowed)
2. Model family match on manufacturer + slug/name
3. Merge UI records alias slugs; audit trail required

See ADR 0012.

---

## Data quality rules

| Rule | Enforcement |
| --- | --- |
| Required match specs | Warn on publish if missing ATM/length/category |
| Price staleness | Flag if `effective_from` > 12 months |
| Unit normalisation | Store metric; display metric (AU market) |
| Contradictory values | Block publish; show conflict summary |
| Untrusted HTML | Strip on ingest; no script in descriptions |

---

## Seed and demo data

Phase 1 seeds may include **fabricated demo manufacturers** for UI development.
Seeds must:

- Use obvious demo names or `is_demo` flag where supported
- Never ship to production without review
- Not copy real brochure text without licence

**Status:** Scaffolded — treat as non-authoritative.

---

## Security

- Imports are untrusted input (prompt injection in PDF text, malicious URLs)
- Fetch jobs run with restricted egress
- No credentials stored in source records
- Manufacturer claim required before portal self-service publish

See [SECURITY_AND_PRIVACY.md](SECURITY_AND_PRIVACY.md).

---

## Operational metrics

| Metric | Use |
| --- | --- |
| Draft queue age | Staffing |
| Publish rejection rate | Template/schema quality |
| Stale price count | Re-fetch priority |
| Source fetch failures | Allowlist / blocking |

---

## Implementation status

| Item | Status |
| --- | --- |
| Admin manual CRUD | Scaffolded |
| CSV / JSON / XLSX import | Implemented (draft-first) |
| Brochure / PDF text-layer extract | Implemented behind `polaris_brochure_extract` (off) |
| Paid AI extraction drafts | Flag `polaris_ai_import` present; orchestrator wiring Planned |
| Web fetch jobs | Planned |
| Model↔source provenance links | Implemented (`099`) |
| Stale source monitor | Planned |
| Manufacturer self-upload | Implemented (media pending review) |

---

## Related documents

- [DATA_ARCHITECTURE.md](DATA_ARCHITECTURE.md)
- [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md)
- [ADMINISTRATION.md](ADMINISTRATION.md)
- [ACCESSIBILITY_QA.md](ACCESSIBILITY_QA.md)
