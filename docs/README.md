# Assist Platform Enterprise documentation

This directory is the version-controlled project memory for the single Assist
Platform Enterprise workstream. VanAssist, TowSmart, TrailerWise and LocalTorque
are brands within this product; they are not parallel projects.

## Authoritative documents

| Concern | Source of truth |
| --- | --- |
| Product purpose and brand boundaries | `PRODUCT_BIBLE.md` |
| Platform architecture | `ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md` |
| Product principles | `PLATFORM_CHARTER.md` |
| Technical target | `TARGET_ARCHITECTURE.md` |
| UX and shared components | `PLATFORM_DESIGN_SYSTEM.md`, `UX_COMPONENT_INVENTORY.md` |
| Delivery priorities | `PRODUCT_BACKLOG.md`, `ROADMAP.md` |
| Material decisions | `ARCHITECTURE_DECISION_RECORDS.md`, `DECISIONS/` |
| Coding and contribution rules | `CODING_STANDARDS.md`, `../CONTRIBUTING.md` |
| APIs | `API.md` |
| Operations and recovery | `OPERATIONS_MANUAL.md`, `OPERATIONS_RUNBOOK.md` |
| Developer onboarding | `DEVELOPER_GUIDE.md`, `LOCAL_DEVELOPMENT.md` |
| Release history | `RELEASE_NOTES.md`, dated deployment/release records |
| Verified production state | `PRODUCTION_CURRENT_STATE.md` |

Start with `START_HERE.md`. Historical audit and implementation documents are
evidence, not competing product direction. When documents conflict, use the
precedence rules in `START_HERE.md` and reconcile the stale document.

## One-workstream rule

Every change must have one owning backlog ID in one of seven streams:

1. Platform
2. Experience
3. Brands
4. Data
5. Infrastructure
6. Operations
7. Commercial

Cross-stream dependencies are links, not duplicate tasks. New specifications,
design systems, admin applications, deployment paths or databases may not be
created outside this structure.
