# ADR 0035: Stay facility evidence and moderated community contributions

Status: proposed  
Date: 2026-08-10  
Backlog: VAN-001, DATA-014

## Context

Boolean columns on `caravan_parks` cannot represent untreated water, conditional access, conflicting sources, recency or provenance. Broad source summaries such as “no facilities” can also contradict specific official facility facts. Travellers need richer facts, while community corrections must not publish without review.

## Decision

Keep stays in `caravan_parks` and standalone amenities in `traveller_facilities`. Store stay-level facility claims as separate, source-attributed evidence. Resolve public values per facility using specificity first, then source authority, confidence and recency. Preserve conflicts and stale claims rather than deleting them.

Community suggestions create pending contributions and immutable items. Duplicate suggestions become independent confirmations. Only a human administrator can approve, edit-and-approve, partially approve, reject or mark them duplicate. Approval creates a `user_approved` facility claim linked to the original item; all moderation is audited.

## Consequences

- Existing boolean stay columns remain backward-compatible fallbacks.
- Mobile results show only a short resolved summary; detail pages expose conditions, source and verification date.
- The Admin API and browser admin share the same moderation service.
- Migration 128 is additive. Rollback leaves the new tables unused rather than destructively reversing approved evidence.
