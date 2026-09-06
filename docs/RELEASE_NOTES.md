# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

### Sale-readiness three-brand boundary

- Define the active Assist Platform product and acquisition boundary as
  **VanAssist, TowSmart and TrailerWise only**.
- Retire Polaris from active runtime brand resolution and add a forward
  retirement migration that disables its database brand row, removes active
  domains and prevents its listings from being exposed as a current product.
- Keep the existing LocalTorque retirement migration and transferred canonical
  provider coverage intact; LocalTorque remains excluded from the active runtime
  and sale package.
- Preserve historical migrations, ADRs, deployment records and audit evidence for
  both retired experiments where they are required for upgrade integrity and
  technical due diligence. Historical records do not make either retired brand a
  current product or sale dependency.
- Remove retired-brand delegation from active homepage behaviour and narrow
  active-brand tests and platform documentation to the three public products.
- Add a formal sale-readiness gate covering product acceptance, reliability,
  security/privacy, data provenance, buyer-grade operating records,
  transferability and acquisition data-room evidence.

### Shared provider-match quality and service mode

- Add one shared mobile-versus-workshop filter to the public provider directory
  used by VanAssist, TowSmart and TrailerWise, including an explicit fallback
  that widens only that filter when local coverage is thin.
- Rank direct or verified category matches ahead of paid featured placement and
  label those stronger matches on result cards without overstating imported or
  heuristic category assignments.
- Record the selected service mode in existing privacy-safe directory demand
  analytics so coverage work can distinguish mobile and workshop shortages.

### Canonical public brand schema logos

- Publish each public brand's canonical `mark.svg` alias in Organization
  structured data instead of the retired `symbol-v2.svg` filename.
- Keep the rendered artwork unchanged while aligning SEO/bot metadata with the
  protected public-identity release gate.

### CQDiggings validation-map release correction

- Refresh the reviewed CQDiggings overlay from source PR #71 and commit
  `1172690e6f50fea5b1e303dfad1ff6d73f8c8311`.
- Load the 15 published Clermont desktop validation points in both Research Map
  and Field Map, preserving the legal gate and explicit planning-only warning.
- Add local regression checks matching the protected production verification so
  a missing map integration fails before release.

### Product-brand completion programme (CORE-003 / CORE-012 / EXP-004 / EXP-005 / TOW-002 / TRL-001)

- Add owner-and-brand-scoped TowSmart edit/recalculate, three-way comparison
  and print/PDF report journeys without weakening the guidance/not-certification
  boundary.
- Add deterministic TowSmart and TrailerWise Ask intent matrices with visible
  routing provenance, safe clarification and no unrelated-provider fallback.
  The shared paid-AI path remains gated and is not required for these routes.
- Add direct TrailerWise repair, mobile, parts, inspection/certifier,
  manufacturer/dealer and fabrication/engineering journeys. Keep the
  marketplace secondary and give its empty state a service-directory route.
- Bind provider claim tokens to their issuing brand and filter Admin API invite
  review by selected brand. Expire ambiguous unused legacy links, retain their
  audit rows and require correctly scoped replacement invitations.
- Record a rendered 360 px production baseline for home, provider directory,
  TowSmart calculator and TrailerWise marketplace: one H1/main landmark,
  labelled controls, no horizontal overflow and no sub-24 px interactive
  targets on the audited pages. Manual screen-reader sign-off remains a release
  gate.

### Product-brand homepage discovery (EXP-005 / TRL-001)

- Add one shared service/location finder to the TowSmart and TrailerWise
  homepages using the existing brand-scoped directory, curated categories,
  town suggestions and device-location service.
- Keep trust wording visible and preserve the boundary that neither brand owns
  VanAssist stays or assistance requests.

### TowSmart saved combination management (TOW-002)

- Add authenticated, owner-and-brand-scoped saved combination detail reports.
- Allow users to remove their own saved snapshots through a CSRF-protected
  account action without exposing whether another user's record exists.
- Keep reports private from indexing and repeat the guidance/not-certification
  boundary.
- Publish factual Organisation and WebSite structured data from the shared
  trusted Brand configuration on every public brand homepage.

### TowSmart and TrailerWise directory parity (EXP-005 / TRL-001)

- Route TowSmart and TrailerWise `/find` requests through the shared,
  brand-scoped provider directory instead of the VanAssist-only results journey.
- Accept curated brand category keys without weakening server-side brand scope.
- Include public provider profiles and trust/legal pages in both product-brand
  sitemaps, and correct configured privacy and terms paths.
- Record remaining launch gaps in
  `TOWSMART_TRAILERWISE_PARITY_AUDIT_2026-08-26.md`.

### CQDiggings overlay mount isolation

- Mount the reviewed CQDiggings release overlay at its own read-only path and
  route its static files through Caddy.
- Avoid nested file mounts beneath the separate read-only CQDiggings release,
  which prevented deployment when an overlay introduced a new filename.

### Retire LocalTorque and transfer provider coverage (LOC-001 / DATA-001 / OPS-001)

- Removed the LocalTorque brand registry entry, public routes, views, imagery,
  social configuration and production journey checks.
- Renamed the authoritative corpus and importer as the VanAssist provider pack.
  Legitimate canonical provider records are retained; LocalTorque listings and
  domains are disabled by a forward migration.
- Moved shared automotive discovery and regulatory pathways to VanAssist-owned
  configuration and removed the old import task and setting names.
- Hardened production releases by removing only containers carrying the exact
  Assist Platform Caddy Compose labels before replacement and rollback.

### CQDiggings Clermont gold investigation release (INF-001 / OPS-001 / OPS-002)

- Packaged the exact reviewed CQDiggings commit
  `d3f4f5ea76c00ecea5ce6159abe1fa79e8ece3a0` as a read-only production
  overlay, including the complete twenty-pass investigation dossier, 8,666
  Queensland gold occurrence records, 757 historical gold-source features, 433
  historical alluvial-evidence features, 193 production and assay records, 41
  occurrence-to-report matches, 24 target and exclusion polygons, 150 drainage
  features, 15 field-validation points and both map integrations.
- The overlay uses the existing protected GitHub production environment,
  checksummed immutable Assist release and root-owned release command. It does
  not alter CQDiggings analytics, moderation records, uploaded images or other
  shared runtime data.
- Rollback restores the preceding Assist release and Compose file, removing the
  overlay mounts while retaining the prior CQDiggings base release.


### VanAssist provider taxonomy bridge
