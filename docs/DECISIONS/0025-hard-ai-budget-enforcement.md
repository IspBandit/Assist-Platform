# ADR 0028: Hard AI budget enforcement

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Platform Engineering / Business Owner
- **Backlog item:** CORE-012, OPS-010
- **Affected brands/modules:** Assist AI Orchestrator, admin settings

## Context

VanAssist has no revenue. Soft quotas that can be exceeded are insufficient.

## Decision

Enforce global/provider enable flags, model allowlist, daily/monthly request and
currency caps, soft warnings, hard stops, token/timeout/retry limits,
cache/rules-first processing, no automatic model upgrade, and no paid fallback
when disabled or exhausted. AI is off until configured. Exhaustion must leave
structured search and local/imported search operational.

## Alternatives considered

- Best-effort metering only: rejected.
- Unlimited AI with alerts: rejected.

## Consequences

Requires usage tables and admin visibility (AI-2). Aligns with DATA-006 and
RIC budget culture.

## Quality Gate impact

- Business: spend bounded.
- Engineering: hard-stop tests mandatory.
- Ops: runbook for disable/rotate.

## Validation and rollback

Flip global disable; rotate keys; structured search unaffected.
