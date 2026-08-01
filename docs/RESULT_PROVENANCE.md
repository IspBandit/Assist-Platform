# Result provenance

**Status:** design (Phase AI-0).  
**ADR:** 0025 (proposed).  
**Related:** [`DATA_TRUST_AND_PROVENANCE.md`](DATA_TRUST_AND_PROVENANCE.md),
DATA-001, DATA-014.

## Every displayed result should retain

- Canonical / local / external status  
- Source and source record ID  
- Verification status and last checked  
- Distance and confidence  
- Attribution requirement  
- Whether temporary or pending review  

## Presentation rule

Do not present a general web-found or live external result as equivalent to a
provider-confirmed canonical listing. Label clearly.

## Staging

Only identifiable source + acceptable trust policy → draft candidate →
duplicate check → human or documented trusted_automatic policy → publish.
