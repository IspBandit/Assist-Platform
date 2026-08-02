# Polaris — Security and Privacy

- **Status:** Partially implemented (inherits platform controls)
- **Date:** 2026-08-01
- **Backlog:** POL-001, POL-009

Polaris inherits Assist Platform security baseline. This document covers
Polaris-specific risks and controls.

---

## Authentication and authorisation

| Control | Status |
| --- | --- |
| Shared session auth | Existing |
| RBAC permissions on admin/portal | Existing pattern |
| Object-level manufacturer scope | Planned (Phase 7) |
| Server-side ownership checks | Required on all mutating routes |
| No auth bypass via hidden fields | Existing platform policy |

---

## Brand isolation

- Catalogue queries scoped to Polaris brand context
- Admin mutations validate brand module enabled
- No cross-brand catalogue leakage in APIs

---

## Input validation

| Vector | Mitigation |
| --- | --- |
| SQL injection | Parameterised queries / query builder |
| XSS in descriptions | Escape on output; HTML allowlist if rich text added |
| File uploads | Platform MIME/size limits; ImageProcessor |
| SSRF on import fetch | Allowlist domains; block private IPs |
| Prompt injection in PDFs | Treat as untrusted; human review before publish |

---

## AI-specific

- AI flags off by default
- Budget enforcement fail-closed (ADR 0022)
- No PII in AI logs; query hashing only
- AI cannot write production catalogue rows (ADR 0026)

---

## Privacy

| Data | Handling |
| --- | --- |
| Account PII | Platform privacy policy |
| Find answers | Stored per user if logged in; session-only if anonymous (Planned) |
| Analytics | First-party events; no third-party ad pixels at launch |
| Manufacturer contact | Business contact only |

Australian Privacy Act alignment via platform privacy programme — no separate
Polaris policy required unless legal advises otherwise.

---

## Private brand protection

Until production release:

- `X-Robots-Tag: noindex` on all public pages
- No sitemap submission
- robots.txt disallow (see SEO doc)
- Warn in UI on non-prod hosts

---

## Secrets

- No API keys in seeds, docs or views
- TowSmart/VanAssist integration uses server-side config only
- `.env` never committed

---

## Audit and incident response

- Catalogue mutations → `AuditLog`
- Security incidents follow `docs/OPERATIONS_RUNBOOK.md`
- Import job failures logged with correlation id

---

## Threat summary (from audit)

| Risk | Severity | Mitigation status |
| --- | --- | --- |
| Untrusted import content | High | Draft-first (Planned) |
| Manufacturer claim fraud | Medium | Existing claim verification |
| Spec tampering | Medium | Audit + review |
| Accidental public indexing | Medium | noindex while private |
| Tow guidance misinterpreted | Medium | ADR 0013 language |

---

## Related documents

- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
- [AI_ARCHITECTURE.md](AI_ARCHITECTURE.md)
- [docs/AI_SECURITY.md](../AI_SECURITY.md)
