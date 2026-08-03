# AI cost controls

**Status:** implemented foundation (Phase AI-2). Paid interpreter deferred to AI-3.  
**ADR:** 0022 (accepted).  
**Related:** OPS-010, DATA-006 connector budgets, Assist RIC budget ADRs.

## Why

VanAssist has no revenue. Cost blowout is unacceptable.

## Controls (live in `ai_settings`)

- Global AI enabled/disabled (default **off**)
- Per-provider OpenAI enabled/disabled (default **off**)
- Model allowlist (empty = no paid calls)
- Daily/monthly request caps
- Daily/monthly currency budgets
- Soft warning + hard stop
- Max prompt length, output tokens, retries, timeout
- Intent cache TTL
- Cache-first and deterministic-rule-first processing
- Zero caps ⇒ paid attempts blocked (no silent spend)
- No automatic model upgrade
- No paid fallback when AI disabled or exhausted
- API keys **never** stored in MariaDB

Admin UI: `/admin/ai-search`.

## When budget exhausted / AI disabled

Structured search, automatic-location search, keyword intent, cached
interpretations, and local DB search continue. Graceful user fallback.
No unexpected paid request. Usage events record `budget_state`.
