# Project changelog

## Purpose

Direct readers to the canonical root changelog for detailed repository history while keeping deployment status in release records.

## Intended users

Developers and operators tracing when repository capabilities or fixes were documented.

## Permissions

None on this page. Changelog history does not grant runtime, deployment or production-data access.

## Fields

The canonical `CHANGELOG.md` supplies its existing dated/versioned headings and change descriptions. This guide adds no second changelog schema.

## Actions

Search the root changelog for a feature or fix, follow referenced code or documentation, and cross-check release status before stating that the change is live.

## Workflows

Record repository-level change history in `CHANGELOG.md`. Record deployment evidence in `docs/RELEASE_NOTES.md` or its linked dated record. Keep the two connected without duplicating long histories into this guide.

## Examples

A changelog entry can show when a capability entered the repository. The corresponding release record is still required to prove when and whether that commit reached production.

## Common mistakes

- Treating changelog prose as stronger than current code or tests.
- Treating a repository entry as deployment evidence.
- Maintaining a second conflicting change history in user-guide content.

## Related pages

See **Current release state** and **Repository workflow**.

## FAQ

**Why is the whole changelog not copied here?** The registry points to the canonical root file so detailed history has one owner.

**Where do known deployment issues belong?** In the dated release record or current release notes, with rollback information.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
