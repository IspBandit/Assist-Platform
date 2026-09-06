# Current Status

Last reconciled with production release evidence: 7 September 2026
(Australia/Brisbane).
Sale-readiness work commenced 6 September 2026.

## Active product boundary

Assist Platform Enterprise is being prepared for sale as one three-brand business:

- VanAssist
- TowSmart
- TrailerWise

LocalTorque and Polaris are retired/excluded. Their historical migrations and audit
records may remain for database-upgrade and due-diligence integrity, but they are
not part of the active runtime or acquisition scope.

## Current verified production baseline

- Production release `4d5a4c957df1e556dc0c26f5345880aaad13277b`
  deployed successfully through GitHub Actions run `34030818368` on 6 September.
- Reusable validation, immutable build/checksum, production backup, release,
  container health, Google Routes provisioning and protected public smoke checks
  all passed for that release.
- The service-worker form-reload defect is corrected and deployed, with a CI
  regression test protecting open forms from forced navigation.
- Public release smoke passed VanAssist, TowSmart and TrailerWise routes and
  designated VanAssist Ask/distance/provider-name checks.
- Later main commit `5618605ac82cbca6a83343c61c336ffa3634b857`
  (#248) passed CI but is unrelated shared-host edge plumbing and is not required
  for the current Assist application sale baseline.

## Completed

- CORE-001 Brand registry; CORE-004 Entitlements; CORE-009/010 Garage handoffs
- CORE-011 Admin API Phase 1 + Option B A–L on unified tree + dual-source `/search-gaps`
- CORE-012 Assist AI Orchestrator (AI-1–AI-7); Ask + traveller facilities enabled
  on production VanAssist
- DATA-006 Connectors; DATA-008/009 Regulatory; DATA-011 RIC live sync
- DATA-011A National Dataset Catalogue
- DATA-012 Dataset Engine; DATA-013 Knowledge gaps; DATA-002 duplicate handling
- Queensland essential facility coverage
- RIC facility catalogue and management foundations
- TowSmart calculator/catalogue foundations and saved-combination foundations
- TrailerWise service-first directory and secondary marketplace foundations
- Sale-readiness boundary cleanup: VanAssist + TowSmart + TrailerWise only
- LocalTorque and Polaris runtime/product retirement
- Three-brand domain/proxy/canonical/robots/sitemap parity for active sale brands
- Buyer-facing acquisition data-room index and working registers
- Current acquisition asset, provenance, dependency, privacy, analytics,
  known-issues, operating-cost and transfer-rehearsal records created
- Checksum-verified isolated database restore: 232 tables in 44 seconds
- Public three-brand browser acceptance subset: 12/12 isolated desktop/mobile
  tests passed
- Production deployment of the form-preserving service-worker correction and
  three-brand protected public smoke verification

## Sale-readiness work still in progress

These are now the material close-out items rather than a general product backlog:

1. **Authenticated acceptance** — complete VanAssist provider claim/review/
   approval/account isolation, administrator/RBAC journeys and TowSmart saved
   combination save/reload/edit/owner-isolation on the selected sale baseline.
2. **Mobile/accessibility finish** — repeat browser acceptance with live service
   workers enabled, complete authenticated/admin mobile journeys and record basic
   keyboard/focus/contrast evidence or explicit exceptions.
3. **Independent recovery** — configure encrypted off-site backup storage,
   create a fresh off-site backup, perform full application/database/media/config
   restore and rehearse rollback.
4. **Monitoring** — prove external uptime/error and scheduled-task failure alerts
   reach an owner-controlled destination.
5. **Security ownership** — enrol administrator MFA, rotate credentials through
   owner-controlled consoles and review/close the privileged/root SSH position.
6. **Data/IP transferability** — reconcile unresolved provider/stay/TowSmart
   source rights, quarantine/exclude unknown rights and document code/logo/image
   ownership or assignments.
7. **Commercial/account records** — fill invoice-backed hosting/domain/mail/API/
   monitoring/backup costs, owners, renewal dates and transfer methods.
8. **Privacy approval** — approve retention, export/deletion/anonymisation and
   subprocessor positions against actual production behaviour.
9. **Assist RIC disposition** — explicitly include, license or exclude the sibling
   RIC application and document any operational dependency.
10. **Transfer rehearsal** — prove clean buyer-style build/restore/deploy/admin
    operation on an isolated or buyer-controlled target.
11. **Final candidate evidence** — bind all accepted/disclosed gates to one
    immutable sale-candidate tag and archive checksum.

## Formal sale gates

The platform must not be described as fully sale-ready until the following are
evidenced or explicitly accepted/disclosed for the transaction:

1. VanAssist, TowSmart and TrailerWise critical authenticated/public journeys pass.
2. Independent off-site backup, full restore and rollback evidence is current.
3. Required credentials/MFA/security ownership actions are completed.
4. Security/privacy positions match actual production behaviour.
5. Data provenance and commercial reuse rights are documented for transferred
   datasets; unknown rights are quarantined or excluded.
6. Operating costs, domains, third-party accounts and transfer steps are inventoried.
7. Buyer/operator documentation and rehearsal prove no founder-only operational
   dependency.
8. The exact approved sale candidate has passing CI/release evidence and an
   immutable tag/checksum.

## Non-blockers for the sale candidate

Do not reopen sale readiness for new brands, major UI redesigns, broad AI
expansion, speculative capacity work, optional marketing campaign tooling,
production billing activation, extra marketplaces or other feature growth unless
a real production defect or buyer requirement makes the work material.

## Overall posture

The software is substantially built, production-backed and now has a verified
current application release with buyer-facing acquisition records. The remaining
work is predominantly authenticated acceptance, independent recovery, security
ownership, provenance/legal evidence, operating-account evidence and transfer
rehearsal — not new product development.
