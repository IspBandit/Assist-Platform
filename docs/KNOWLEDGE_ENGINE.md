# Knowledge engine

**Status:** design (Phase AI-0).  
**Backlog:** DATA-013.  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md) §13–§15.  
**Related:** [`KNOWLEDGE_GAPS.md`](KNOWLEDGE_GAPS.md),
[`DATA_TRUST_AND_PROVENANCE.md`](DATA_TRUST_AND_PROVENANCE.md).

## Goal

Every natural-language search should improve the platform through one or more of:

- Demand recorded  
- Synonym / taxonomy mismatch learned  
- Locality or category gap identified  
- Dataset gap identified  
- Trusted external result staged  
- Confidence/duplicate signals updated  
- Useful interaction recorded  
- Interpretation cached  

## Flow

Adequate local results → return + log.  
Inadequate → approved external query → attributed results → stage candidate →
duplicate check → confidence → admin/trusted-source policy → publish via
approved workflow → future searches hit local record.

Never blindly store every result. Never treat AI as a live data source.
