# Polaris — Administration

- **Status:** Scaffolded
- **Date:** 2026-08-01
- **Backlog:** POL-002, POL-003, POL-007

Polaris administration lives in the **shared** `/admin` portal — not a separate app.

---

## Module gating

| Control | Mechanism |
| --- | --- |
| Module | `rv_catalogue` in brand modules / feature flags |
| Permission prefix | `polaris.*` (e.g. `polaris.manufacturers.view`) |
| Brand context | Admin brand switcher includes Polaris when enabled |

---

## Navigation structure

```
Admin → Polaris
├── Dashboard
├── Manufacturers
├── Model families
├── Variants
├── Spec definitions
├── Spec values (bulk tools later)
├── Floorplans
├── Sources
├── Import drafts (Phase 6)
├── Duplicate review (Phase 6)
└── Settings (flags)
```

**Status:** Top-level nav and CRUD shells Scaffolded.

---

## Dashboard widgets (Planned enrichment)

- Published variant count
- Draft import queue depth
- Missing critical specs count
- Recent audit entries

---

## CRUD conventions

- Follow platform admin patterns: list → edit → save → flash message
- Soft delete only; restore via shared recycle bin when OPS-011 complete
- All mutations logged via `App\Services\AuditLog`
- CSRF tokens on forms (Existing platform middleware)

---

## Roles (initial)

| Role | Access |
| --- | --- |
| Platform admin | Full Polaris admin |
| Catalogue editor | CRUD without purge |
| Manufacturer portal user | Portal only (Phase 7) |
| Read-only analyst | View + export (future) |

Exact permission keys defined in migration seeds — align with RBAC table.

---

## Import review (Phase 6)

Admin queue shows:

- Draft payload vs source snippet
- AI extraction confidence (if used)
- Duplicate suggestions
- Actions: Approve, Reject, Merge, Request manufacturer claim

No one-click publish without source attachment.

---

## Spec definition governance

Administrators manage `polaris_spec_definitions`:

- Adding keys requires engineering review for match engine impact
- Deprecation: mark inactive; do not hard-delete values in use

---

## Feature flags (admin UI)

Toggle via existing platform feature flag UI:

- `polaris_public_catalogue` (staging previews)
- `polaris_ask` / AI import flags

---

## Implementation status

| Item | Status |
| --- | --- |
| Admin nav entry | Scaffolded |
| Manufacturer CRUD | Scaffolded |
| Model/variant CRUD | Scaffolded |
| Spec definition CRUD | Scaffolded |
| Import review queue | Planned |
| Recycle bin integration | Partially implemented (platform) |

---

## Related documents

- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
- [SECURITY_AND_PRIVACY.md](SECURITY_AND_PRIVACY.md)
- [INFORMATION_ARCHITECTURE.md](INFORMATION_ARCHITECTURE.md)
