# Current Status

Last aligned with production verification: 24 August 2026 (Australia/Brisbane).
Sale-readiness work commenced 6 September 2026.

## Active product boundary

Assist Platform Enterprise is being prepared for sale as one three-brand business:

- VanAssist
- TowSmart
- TrailerWise

LocalTorque and Polaris are retired/excluded. Their historical migrations and audit
records may remain for database-upgrade and due-diligence integrity, but they are
not part of the active runtime or acquisition scope.

## Completed
- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source /search-gaps
- CORE-012 Assist AI Orchestrator (AI-1–AI-7); **Ask + traveller facilities enabled on production VanAssist**; Quality Gate **CONDITIONAL PASS**
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync (code + production Admin API)
- **DATA-011A National Dataset Catalogue**
- DATA-012 Dataset Engine; DATA-013 Knowledge gaps; DATA-002 duplicates
- Queensland essential facility coverage
- RIC facility catalogue and management foundations
- TowSmart calculator/catalogue foundations and saved combinations
- TrailerWise service-first directory and secondary marketplace foundations
- LocalTorque retirement migration and provider-pack transfer
- Sale-readiness product boundary defined as VanAssist + TowSmart + TrailerWise only
- Polaris retirement migration added for forward upgrades

## Sale-readiness work in progress
- Remove stale active-product references to retired LocalTorque and Polaris surfaces.
- Complete three-brand documentation and operator handover material.
- Complete VAN-002 provider-claim production acceptance.
- Verify VAN-012 analytics accuracy and buyer-grade KPI reporting.
- Confirm production provider/facility counts and import backlog.
- Complete automated independent encrypted off-site backup and current restore rehearsal.
- Complete credential rotation and owner/admin journey sign-off.
- Complete three-site desktop/mobile acceptance for buyer-visible journeys.
- Prepare asset register, data provenance register, recurring-cost register and buyer data room.

## Remaining formal sale gates

The platform must not be described as sale-ready until the following are evidenced:

1. VanAssist, TowSmart and TrailerWise critical public and authenticated journeys pass.
2. Retired brands cannot resolve as active runtime brands.
3. Scheduled off-site backup and current restore rehearsal pass.
4. Production credentials requiring rotation are rotated through owner-controlled consoles.
5. Security/privacy gaps required by `docs/SALE_READINESS.md` are resolved or explicitly disclosed.
6. Data provenance and commercial reuse rights are documented for transferred datasets.
7. Operating costs, domains, third-party accounts and transfer steps are inventoried.
8. Buyer/operator documentation is sufficient to run the platform without founder-only knowledge.
9. Exact sale candidate commit passes CI and the Platform Quality Gate evidence pack is updated.

## Current task

1. Three-brand boundary cleanup — **in progress**
2. Three-platform buyer-visible UX and workflow audit — **next**
3. Reliability/security/privacy close-out — **next**
4. Transfer pack and acquisition data room — **next**
5. Final sale-candidate acceptance and release evidence — **final gate**

## Overall posture

The software is substantially built and production-backed, but it is now in a
**sale-readiness release cycle**, not normal feature development. The priority is
transferability, reliability, clean product boundaries, provable operations and
buyer confidence rather than adding new product scope.
