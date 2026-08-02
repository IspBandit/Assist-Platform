# Phase AI-0 — Owner decision brief

**Status:** AI-0 **approved** 2026-08-01. AI-1 authorised (deterministic only).  
**Gate document:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md)

## What was approved

1. A **shared** Assist AI Orchestrator (not VanAssist-only, not a chatbot).
2. Natural-language **Ask VanAssist** **alongside** existing `/find` and `/stays`
   structured search (unchanged for users who prefer it).
3. AI as **intent interpretation only** — never factual authority, never direct
   publish.
4. Phased delivery AI-1 → AI-7, with AI off by default and hard cost stops.
5. Admin API Phase 1 scope **unchanged**; AI only documents dependencies on
   planned `/search-gaps`, drafts and imports.
6. Traveller facilities remain a **future** entity (ADR 0032 / 0027);
   no facility migration in early AI phases.

## Decisions (AI-0)

| # | Decision | Outcome |
| --- | --- | --- |
| 1 | Accept AI-0; authorise **AI-1 only** | Approved |
| 2–14 | Defaults in prior brief | Accepted with AI-0 package; revisit budgets/model at AI-2/AI-3 |

## After approval

Implement **AI-1** only: shared orchestrator skeleton, validation, keyword
intent engine, taxonomy mapping, search logging, provider/stay adapters,
feature flag **off by default**.

## Sign-off

| Role | Name | Date | Decision |
| --- | --- | --- | --- |
| Business owner | (chat) | 2026-08-01 | Approve AI-0 |
| Architecture | | 2026-08-01 | ADRs 0021–030 accepted |
| Engineering | | 2026-08-01 | Proceed AI-1 |
