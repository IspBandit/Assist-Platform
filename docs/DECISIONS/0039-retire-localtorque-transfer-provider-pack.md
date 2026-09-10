# ADR 0039: Retire LocalTorque and transfer provider coverage

**Status:** Accepted
**Backlog:** LOC-001, DATA-001, OPS-001

## Context

LocalTorque was no longer an authorised product, but its private brand runtime,
assets, scheduled import and provider-pack naming remained in the production
repository. VanAssist still needs the legitimate, evidence-backed provider
records in that corpus.

## Decision

Remove the LocalTorque runtime and public product surface. Disable its domains
and listings with a forward migration. Preserve canonical provider identities,
source evidence and compatible VanAssist services, but move the corpus,
importer, progress settings and scheduled task under VanAssist ownership.

Historical migrations and dated decision records remain immutable evidence and
do not make LocalTorque an active runtime dependency.

## Consequences

- LocalTorque cannot resolve as an application brand or publish public assets.
- Existing canonical providers are not deleted or assigned new IDs.
- The renamed provider pack reprocesses compatible VanAssist services safely.
- Rollback restores application code, while the forward migration leaves the
  retired brand disabled and its listings non-public.
