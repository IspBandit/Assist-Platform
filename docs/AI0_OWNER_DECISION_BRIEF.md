# Phase AI-0 — Owner decision brief

**Status:** AI-0 approved; **AI-1 deterministic foundation implemented**.  
**Gate document:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md)  
**Owner approval:** recorded 2026-08-01 (“approve AI-0, start AI-1”).

## AI-1 delivered

- `App\Platform\AiSearch` orchestrator (rules only; no paid AI)
- `GET /ask` Ask VanAssist (404 unless `assist_ai_search` enabled)
- Migration `085_assist_ai_search.sql` + seed flag default **false**
- Provider + stay adapters; facility/dataset stubs
- Unit tests under `tests/Unit/AiSearch`

## Remaining decisions for later phases

| # | Decision | Suggested default |
| --- | --- | --- |
| 3–5 | AI budgets and OpenAI model (AI-2/AI-3) | Conservative AUD caps; lowest-cost schema model |
| 6 | Secret storage for API key | Env / vault |
| 8–10 | Paid connectors, GPS retention, public JSON API | Off / round / defer |

## Sign-off

| Role | Name | Date | Decision |
| --- | --- | --- | --- |
| Business owner | (chat approval) | 2026-08-01 | Approve AI-0; start AI-1 |
| Architecture | | 2026-08-01 | ADRs 0018–0027 accepted |
| Engineering | | 2026-08-01 | AI-1 implemented on feature branch |

## What you are approving

1. A **shared** Assist AI Orchestrator (not VanAssist-only, not a chatbot).
2. Natural-language **Ask VanAssist** **alongside** existing `/find` and `/stays`
   structured search (unchanged for users who prefer it).
3. AI as **intent interpretation only** — never factual authority, never direct
   publish.
4. Phased delivery AI-1 → AI-7, with AI off by default and hard cost stops.
5. Admin API Phase 1 scope **unchanged**; AI only documents dependencies on
   planned `/search-gaps`, drafts and imports.
6. Traveller facilities remain a **future** entity (ADR 0016 / proposed 0027);
   no facility migration in early AI phases.

## Decisions required (yes / amend / defer)

| # | Decision | Suggested default |
| --- | --- | --- |
| 1 | Accept AI-0 package; authorise **AI-1 only** (deterministic, no paid AI) | Accept |
| 2 | Ask VanAssist copy/placement on VanAssist home + find | Alongside `/find`, not replacing |
| 3 | Initial AI daily/monthly AUD hard caps (AI-2/AI-3) | Conservative (e.g. low tens AUD/month) until measured |
| 4 | Initial daily/monthly AI request caps | Soft warn + hard stop; owner-set numbers |
| 5 | First OpenAI model snapshot (AI-3 only, after fresh pricing check) | Lowest-cost mini/nano with strict JSON schema |
| 6 | Secret storage for `OPENAI_API_KEY` | Env / approved secret manager; never DB plaintext |
| 7 | Reaffirm: no `trusted_automatic` without written dataset decision | Yes |
| 8 | Paid Places/connectors remain off by default for NL fallback | Yes |
| 9 | Precise GPS retention for NL analytics | Round/derive for long-term; short raw retention |
| 10 | Public `POST /api/v1/search/assist` | Defer; web form first |
| 11 | Backlog: CORE-012 discovery; VAN-011 discovery | Yes |
| 12 | AI-6 / DATA-012 after Admin API Phase 1 progress, not inside it | Yes |
| 13 | RIC remains sole research client for gap fulfilment (ADR 0017) | Yes |
| 14 | Accept proposed ADRs 0018–0027 (or mark amended) | Accept as proposed → accepted after review |

## Explicit non-approvals in AI-0

- No production PHP orchestrator code yet  
- No migrations applied  
- No OpenAI key provisioning or paid calls  
- No traveller_facilities table  
- No change to locked Admin API Phase 1 endpoint inventory  

## After approval

Implement **AI-1** only: shared orchestrator skeleton, validation, keyword
intent engine, taxonomy mapping, search logging, provider/stay adapters,
feature flag **off by default**. Report files, tests, docs, cost/security
impact, rollback, next increment.

## Sign-off

| Role | Name | Date | Decision |
| --- | --- | --- | --- |
| Business owner | | | Approve / Amend / Reject |
| Architecture | | | |
| Engineering | | | |
