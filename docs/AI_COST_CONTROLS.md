# AI cost controls

**Status:** design (Phase AI-0).  
**ADR:** 0022 (proposed).  
**Related:** OPS-010, DATA-006 connector budgets, Assist RIC budget ADRs.

## Why

VanAssist has no revenue. Cost blowout is unacceptable.

## Controls

- Global AI enabled/disabled (default **off**)
- Per-provider enabled/disabled
- Model allowlist
- Daily/monthly request caps
- Daily/monthly currency budgets
- Soft warning + hard stop
- Max prompt length, output tokens, batch size, retries, timeout
- Cache-first and deterministic-rule-first processing
- Cost estimation before batch jobs; confirmation above threshold
- Automatic stop at hard limit
- No automatic model upgrade
- No paid fallback when AI disabled or exhausted

## When budget exhausted

Structured search, automatic-location search, keyword intent, cached
interpretations, local DB and imported dataset search continue. Graceful
user fallback. No unexpected paid request. Audit `ai.budget_blocked`.
