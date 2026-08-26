# ADR 0038: CQDiggings reviewed release overlay

- Status: accepted
- Date: 26 August 2026
- Backlog: INF-001, OPS-001, OPS-002

## Context

CQDiggings remains a separate repository and keeps its own retained release at
`/opt/cqdiggings/current`. Its public Caddy and PHP containers are nevertheless
owned by the Assist Platform production Compose project. The current execution
environment cannot use the former workstation-only root SSH key, while the
reviewed GitHub production environment already has the restricted deploy
identity, pinned host key, checksum checks, backup, health checks and rollback.

CQDiggings commits `a9c67df33461251efff5e8901ef3ca77a88f58fe` and
`d3f4f5ea76c00ecea5ce6159abe1fa79e8ece3a0` add a Clermont gold investigation
and map layers. Leaving those reviewed commits on GitHub while production serves
the earlier files creates a visible release drift.

## Decision

The exact CQDiggings files changed by the two reviewed commits, plus the
supporting occurrence, historical-evidence, production, report-link and research
catalogue datasets from the same target commit, are copied into
`infrastructure/cqdiggings-overlay` with their source commit and SHA-256 values.
The complete twenty-pass investigation record is retained in both platform
documentation and the public overlay with the same SHA-256. Production Compose
mounts each public file read-only over the matching path in both the Caddy and
CQDiggings PHP containers.

The base CQDiggings release, shared analytics, moderation records, uploads and
approved public runtime data remain in their existing locations. The overlay is
part of the checksummed Assist Platform release artefact and is applied only by
the existing protected production workflow and root-owned release command.

On 26 August 2026, CQDiggings PR #71 corrected the two reviewed map integrations
to load the already-published field-validation dataset. The overlay was refreshed
from source commit `1172690e6f50fea5b1e303dfad1ff6d73f8c8311`; its markers retain
the legal gate and state that they are planning aids, not dig-here coordinates
or access guarantees.

This is a bounded cross-repository release bridge, not a transfer of CQDiggings
product ownership into Assist Platform. Future CQDiggings releases should either
replace this overlay with a dedicated restricted release command or refresh the
reviewed overlay from an exact CQDiggings commit.

## Alternatives considered

### Reuse the former root SSH deployment

Rejected. Production root SSH and password authentication are disabled, and the
workstation-only key is not available to GitHub or this execution environment.

### Add an unguarded GitHub SSH workflow to CQDiggings

Rejected. That repository does not hold the established production environment
or restricted deploy contract. Duplicating secrets and allowing arbitrary
remote commands would weaken the existing boundary.

### Copy files directly into the live CQDiggings release

Rejected. It would mutate an immutable release in place and make rollback and
checksum evidence unreliable.

## Consequences

- The Clermont investigation can be released through the established protected
  GitHub path without disclosing or duplicating production credentials.
- The release remains checksummed, reversible and tied to reviewed commits.
- Compose contains explicit file mounts for the bounded change set. This is
  verbose, but visible and fail-closed if a packaged file is absent.
- A later full CQDiggings release must remove or deliberately refresh the
  overlay so stale files do not mask newer base-release content.

## Validation and rollback

CI verifies the source commit marker, required file set, 8,666 Queensland gold
occurrence records, historical-evidence and research-register counts, the three
Clermont GeoJSON layers, the twenty-pass dossier, service-worker version and two
read-only mounts per public file. Production verification checks the same
records plus both map integrations. Rollback restores the preceding Assist
Platform release, which restores the prior Compose file and removes the overlay
mounts.
