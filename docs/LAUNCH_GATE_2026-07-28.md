# VanAssist launch evidence gate — 28 July 2026

**Backlog owners:** VAN-002, DATA-001, DATA-007, COM-001, COM-002, OPS-002, OPS-004

## Purpose

VanAssist launch readiness is evaluated through four evidence groups in the
administrator Platform Control Centre. A missing query, stale status file or
unavailable external service is a failure, never an inferred pass.

## Data trust

- Imported/unclaimed listings remain visibly labelled and are not represented
  as business-verified.
- Source records marked for review cannot be the sole evidence behind a public
  searchable listing.
- Precise provider coordinates are compared with the declared town. A mismatch
  over 150 km is corrected to a nearby known Australian town when possible.
  Records that cannot be reconciled within 150 km are quarantined.
- The strict production data-quality audit blocks a release if an unresolved
  coordinate/town conflict remains publicly visible.
- Conservative chain-name rules remove unsupported imported service claims.
  Claimed providers and verified category assignments are never changed.

## Search reliability

- Free text accepts town-only, postcode, abbreviated state and full state-name
  forms, including `Emerald QLD`, `Emerald, QLD`, `Brisbane Queensland` and
  `4720`.
- Representative searches are executed against the production town database in
  the launch gate.
- Radius labels remain explicitly described as straight-line estimates. The
  Directions action opens the current road route in the user's map provider.

## Compliant outreach

- Microsoft Graph certificate health must be healthy.
- The three dedicated application-path mailbox probes must have `sent` status.
- Failed queue rows are visible as a warning.
- Provider marketing eligibility requires opt-in, consent date, approved consent
  basis and retained evidence. A public email address alone is excluded.
- Campaign delivery remains review-first: internal test, 25-recipient pilot,
  then explicitly approved 50/day and 100/day stages with suppression and
  signed unsubscribe enforcement.

## Operational proof

- Migration history must be clean and the immutable release identifier present.
- A successful scheduled database backup must be recorded.
- The encrypted off-site backup job writes
  `storage/ops/offsite-backup.status.json`; evidence expires after 36 hours.
- The independent restore rehearsal writes
  `storage/ops/offsite-restore-drill.status.json`; evidence expires after eight
  days.
- Missing S3-compatible/restic credentials therefore remain a visible launch
  failure until the owner-controlled backup repository is configured and both
  jobs pass.

## Quality gate

- **Architecture:** pass — shared services and canonical providers remain the
  source of truth; no parallel search, mail or provider system was introduced.
- **UX:** pass for the candidate — common Australian location formats work and
  the admin receives plain-language, current evidence for every launch pillar.
- **Engineering:** pass for local validation — static analysis, syntax, unit
  tests and shell syntax checks pass. Disposable-database CI and production data
  audit remain required on the exact release head.
- **Business:** conditional — the application can support a controlled beta.
  Broad outreach and indexed launch remain blocked whenever the live gate shows
  a failure, including absent off-site backup/restore evidence.

## Rollback

The application release can roll back through the existing immutable symlink.
Importer corrections apply only to unclaimed providers. Quarantined source
records and suppression evidence must remain; rollback must not re-expose them
or resume marketing.
