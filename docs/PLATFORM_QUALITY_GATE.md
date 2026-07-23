# Platform Quality Gate

Every production candidate requires evidence in four areas. Approval is recorded
in the pull request and release record. One person may fulfil multiple roles in
the current team, but each perspective must be evaluated explicitly.

## Architecture approval

- Fits the Enterprise specification and accepted ADRs.
- Shared versus brand-specific ownership is explicit.
- Brand, provider, organisation and user scope are server-enforced.
- Migrations are forward-only, validated and documented.
- URLs, compatibility, dependencies and rollback are addressed.
- No duplicate admin, identity or platform service is introduced.

## UX approval

- Uses the current official design-system patterns and brand tokens.
- Desktop and mobile rendered states are reviewed.
- Keyboard, focus, semantics, contrast and error handling are acceptable.
- Copy preserves the correct brand purpose and avoids unsupported claims.
- Loading, empty, failure and disabled states are present.
- Social assets are individual production exports, not mock-up boards.

## Engineering approval

- Relevant unit, integration and end-to-end tests pass.
- Static analysis, Composer validation and production dependency build pass.
- Security, privacy, performance and observability impacts are addressed.
- Secrets are absent; environment changes are documented.
- Health checks, backup, migration and rollback steps are executable.
- Skipped or unavailable checks are recorded as risks, not passes.

## Business approval

- The backlog outcome and measurable value are stated.
- Membership, advertising or sponsored behaviour is transparent.
- Legal, tax, sender-domain or external-service prerequisites are satisfied or
  the feature remains disabled.
- Analytics needed to assess the outcome are defined.
- Brand integrity and future sale/transfer readiness are preserved.

## Gate result

The result is one of:

- **Pass:** all required evidence exists; production release may proceed through
  the operations runbook.
- **Conditional pass:** only non-production or private/disabled deployment is
  permitted, with named conditions and expiry.
- **Fail:** production release is prohibited.

No code merge, successful build or administrator request alone constitutes a
production pass.

## Pull request evidence template

```text
Backlog item:
Architecture: PASS / CONDITIONAL / FAIL — evidence
UX: PASS / CONDITIONAL / FAIL — evidence
Engineering: PASS / CONDITIONAL / FAIL — evidence
Business: PASS / CONDITIONAL / FAIL — evidence
Overall gate:
Approver/date:
Release and rollback notes:
```
