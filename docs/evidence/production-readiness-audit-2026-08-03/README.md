# Assist Platform production-readiness audit — 2026-08-03

**Scope:** Assist Platform branch `feature/completion-unify-core-011-012` and
Assist RIC branch `feature/data-011a-catalogue-acquisition`.

## Evidence-based status

| Area | Result | Evidence / action |
| --- | --- | --- |
| Platform implementation | PASS locally | 937 tests, 79,460 assertions; PHPStan clean |
| Platform dependencies | PASS | `composer validate --strict`; `composer audit` reports no advisories |
| RIC implementation | PASS locally | 149 unit, 41 integration and 30 GUI tests pass when run in bounded groups |
| RIC static checks | PASS after repair | Ruff clean; MyPy clean across 150 source files |
| RIC dependencies | PASS | `pip-audit` reports no known vulnerabilities; local package is not on PyPI |
| Migration rehearsal | BLOCKED locally | Local database contains Polaris schema/brand without matching migration 103 history. Do not use it as clean migration evidence; rehearse on a disposable production-shaped staging restore |
| Admin API rehearsal | BLOCKED on staging | Requires staging URL, credentials, migrations, restricted enablement and RIC validate-only submission |
| VAN-002 claims E2E | BLOCKED on staging/owner | Requires a human claim, evidence review and approve/reject cycle |
| Production flags | CORRECTLY OFF | Ask, traveller facilities, datasets, paid AI and Admin API remain gated |
| Polaris launch | BLOCKED by owner/business | Domain, real non-demo catalogue and public-launch Quality Gate |
| LocalTorque / billing | BLOCKED by owner/business | Domain/legal and gateway/tax decisions respectively |

## Complete and not to be rebuilt

- CORE-011 Admin API and Option B API/RIC architecture.
- DATA-011 RIC sync client and DATA-011A catalogue acquisition.
- CORE-012 / DATA-012 / DATA-013 AI, facilities and knowledge-gap code behind flags.
- Claim-first, duplicate review, recycle lifecycle and audited draft/import paths.
- Existing PHP admin and PySide6 RIC responsibilities; no separate Tauri stack.

## Staging rehearsal — OPS-010 / DATA-011

1. Restore a recent production-shaped backup into isolated staging.
2. Deploy the exact release-candidate SHA and verify checksums.
3. Apply migrations through the current head; require zero dirty migrations.
4. Set `ADMIN_API_ENABLED=true`, `ADMIN_API_RESTRICTED=true`, and restrict the
   allowed admin IDs. Keep production unchanged.
5. Enrol human TOTP, create the least-privilege RIC service account and store
   its one-time secret in the OS credential vault.
6. Run `scripts/admin-api-probe.php` against staging.
7. From RIC, test connection, pull canonical providers/stays/search gaps, and
   submit one approved package in **Validate only** mode.
8. Confirm no canonical production-like data changed; record request IDs and
   per-record validation results.
9. Exercise API disable, credential revocation and RIC sync-disable rollback.
10. Append results to the Option B Quality Gate evidence pack.

## VAN-002 staging acceptance

1. Search for an existing provider during onboarding and select **Claim this listing**.
2. Verify a likely duplicate cannot silently create a second public listing.
3. Complete provider verification and inspect the claim in PHP admin.
4. Exercise request-evidence, rejection and approval paths with test claims.
5. Confirm canonical ID, aliases, analytics, provenance and audit events survive.
6. Record screenshots, IDs, tester and result; remove or archive staging test data.

## Release package and order

1. Owner signs all four Platform Quality Gate pillars for the exact SHA.
2. Verify encrypted database/media backup, checksum and tested restore evidence.
3. Build an immutable release and retain the preceding release.
4. Deploy code with all new production flags off.
5. Apply forward migrations once, then verify migration history is clean.
6. Rebuild production dependencies/config and run health checks.
7. Smoke-test homepage, `/find`, provider detail/contact, login, admin, claims,
   stays, API-disabled posture and analytics.
8. Monitor application, Caddy, PHP and database logs plus latency/error rate.
9. Roll back the release pointer on regression; leave additive migrations in
   place unless the documented restore threshold is met.

## Owner approvals still required

- Staging access and approval to perform the rehearsal.
- Human VAN-002 acceptance sign-off.
- Production Admin API enablement after evidence review.
- Any Ask/facilities/dataset/paid-AI production flag change.
- Polaris and LocalTorque domains/legal decisions and billing gateway/tax choice.

**Deployment readiness:** code-ready for staging rehearsal; **not approved for
production enablement** until staging evidence, owner acceptance and the full
Platform Quality Gate are complete.
