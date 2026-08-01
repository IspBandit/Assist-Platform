# Assist AI workstream — where we are

This is the single status page for the original Assist AI Orchestrator prompt.

## Phases (from the original brief)

| Phase | Meaning | Status |
| --- | --- | --- |
| **AI-0** | Design/audit only | **Done and approved** (`docs/PHASE_AI0_DESIGN.md`) |
| **AI-1** | Deterministic orchestrator (no paid AI) | **Done on this branch** — ready to commit/merge |
| AI-2 | Cache + budget foundation | Next |
| AI-3 | OpenAI intent interpreter | Later |
| AI-4 | Knowledge gaps | Later |
| AI-5 | Dataset routing | Later |
| AI-6 | Traveller facilities | After DATA-012 |
| AI-7 | Hardening | Later |

## What AI-1 includes

- Shared service: `App\Platform\AiSearch`
- Ask VanAssist: `GET /ask` (VanAssist only)
- Feature flag: `assist_ai_search` (**off** by default)
- Migration: `085_assist_ai_search.sql`
- Keyword intent → provider/stay search
- Structured `/find` unchanged
- Admin API Phase 1 untouched
- No OpenAI, no paid APIs, no traveller-facility table

## How to enable Ask VanAssist locally

1. Apply migration 085  
2. Turn on feature flag `assist_ai_search`  

## Branch

`feature/core-012-ai-1-deterministic` (from `main`)
