# 0012 — Brand-scoped, consent-aware email delivery

## Status

Accepted — 2026-07-28

## Decision

Transactional and marketing email share the durable platform queue but are
classified separately. Broadcasts own an immutable brand, resolve only that
brand's profiles and active provider listings, and restore their brand context
when processed by scheduled work. Marketing sends must pass the central
suppression check and include a signed unsubscribe link. Hard-bounce, complaint
and administrator suppressions can block all delivery; a voluntary opt-out
blocks marketing only.

Dedicated Microsoft 365 mailboxes are preferred per brand. The operations
mailbox remains a visible failure-continuity fallback, not proof that dedicated
sender acceptance passed.

## Consequences

- Cross-brand campaign leakage is prevented at the query and queue boundaries.
- A provider or customer can stop marketing without losing service-critical
  account messages.
- Suppression data is durable operational safety data and is retained across
  application rollbacks.
- Bulk promotional provider outreach remains disabled until explicit consent
  acquisition and production throughput acceptance are evidenced.
