# Knowledge gaps

**Status:** design (Phase AI-0).  
**Backlog:** DATA-013.  
**Admin API:** `GET /api/v1/admin/search-gaps` is already in the Phase 1
inventory as planned — do not change Phase 1 locked scope; implement behind
that contract when authorised.

## Model (proposed)

Grouped gap rows (not one row per search). Fields include: original and
normalised query, intent, brand, location/radius, taxonomy keys, local and
external result counts, quality, AI/external flags, first/last seen, search
count, approximate unique users, CTR/contact, resolution status, priority,
assigned dataset/research job, resolution date.

## Priority

Frequency, urgency, zero-result rate, traveller safety relevance, remoteness,
provider contact demand, trusted dataset availability.

## RIC hand-off

Platform aggregates → Admin API search-gaps (when live) → RIC research tasks →
drafts/imports → Platform review. See Phase AI-0 §15 and Assist RIC sibling
docs (do not duplicate RIC ownership here).
