# Assist Platform known issues and deferred work

This document is a buyer-facing disclosure register for the active three-brand
Assist Platform sale package. It is intentionally conservative: a feature is
not described as complete unless current repository or production evidence
supports that claim.

## Acquisition boundary

Active products are **VanAssist, TowSmart and TrailerWise only**. LocalTorque and
Polaris are retired/excluded. Historical code or database records that remain
for upgrade/audit integrity are not active product promises.

## Production and reliability

### 7 September evidence reconciliation: current blockers

See [the dated review](SALE_REVIEW_2026-09-06.md) for the state observed before
the final 6 September corrective release.

The service-worker form-reload defect recorded in that review is **no longer an
undeployed blocker**. Production release
`e2269bf9d072e5877c32129034029dd43e27f3da` completed successfully in GitHub
Actions run `34068185858`. The exact release passed the form-preservation
regression test, immutable archive/checksum checks, production backup, container
health, Google Routes provisioning and protected public smoke checks across all
three active brands.

The remaining material blockers are:

- the merged TowSmart/TrailerWise homepage location fix on main and open PR #251
  are not part of the verified production release yet;

- no independent encrypted off-site backup account/configuration has yet been
  evidenced;
- the isolated 232-table database restore passed, but full application/media/
  configuration recovery and rollback rehearsal remain open;
- no enabled MFA enrolments were recorded in the 6 September sale snapshot;
- public-key root SSH access was available at that review and requires an explicit
  transfer/security decision;
- external uptime/error and scheduled-task failure alert receipt has not yet been
  retained as buyer-grade evidence;
- ten registered tasks had never run in the dated review; their expected schedule
  and execution evidence remain to be reconciled;
- authenticated provider/admin/TowSmart owner-isolation journeys remain to be
  completed on the selected sale baseline;
- provider/data licence metadata remains materially incomplete, including 10,979
  non-deleted provider records without a recorded licence in the acquisition
  provenance review;
- invoice-backed operating costs, account/domain transfer evidence, IP/asset
  assignments and privacy approval remain outstanding.

### Formal sale/quality gate not yet complete

The platform has substantial production verification and a successful current
application deployment, but the final sale-ready and full Platform Quality Gate
sign-off is not yet recorded. The authoritative remaining work is tracked in
`../SALE_READINESS.md` and GitHub issue COM-005.

### Automated independent off-site recovery evidence

The deployment workflow creates and verifies a local production database backup,
and a checksum-verified isolated 232-table restore has passed. The sale candidate
still requires:

- automated encrypted independent off-site backup;
- a current full application/database/media/config restore rehearsal from the
  transferable recovery set;
- immutable release rollback evidence; and
- retained evidence showing a buyer/operator can repeat the process.

This is a transfer/reliability gate, not a speculative feature.

### Monitoring and scheduled-task alert delivery

Application health checks, container monitoring and scheduled jobs exist. The
successful production release records the provider-import queue as a root-owned
five-minute cron task. Buyer-grade sign-off still requires external uptime/error
alert delivery and explicit task/cron failure alerts to an owner-controlled
destination, plus proof that the expected production schedule is complete.

## Product limitations

### VanAssist provider verification is progressive

Imported provider data is not automatically treated as business-verified.
Evidence/verification status must remain visible and claims must not imply that
all directory businesses have confirmed their listing.

### Provider claim acceptance requires final sale-candidate verification

The claim/approval path exists, but before sale sign-off the complete listing
claim → evidence review → approval → account access journey must be re-run and
retained as acceptance evidence, including second-account/brand isolation.

### TowSmart is guidance, not certification

TowSmart calculations and reference data are designed to assist towing decisions
and must continue to be presented as guidance. They do not replace manufacturer,
engineering, registration-authority or legal requirements. The current release
audited 199 vehicle references and 3,769 towable references; source URLs and
commercial reuse/update responsibility remain incomplete and must be resolved or
explicitly restricted in the data-room provenance register.

### TrailerWise marketplace is secondary

TrailerWise is positioned service-first: repairers, parts, engineering,
inspection/compliance and related trailer services are the primary proposition.
The sales/hire marketplace is secondary and must not be represented to a buyer
as the sole or primary business model.

### Paid AI and some paid external connectors are optional/gated

Paid AI and optional paid place/search connectors may remain disabled by policy.
Their absence is not a blocker to the core provider/search products, but any
buyer enabling them must supply its own credentials, accept provider terms and
understand the operating-cost implications.

### Production charging should not be assumed

Do not value or describe the platform as an established paid SaaS unless the
transaction data room contains current production billing evidence. Existing
billing/entitlement foundations are not, by themselves, proof of recurring
revenue or production charging. Production billing activation is not required to
sell the current software/data asset.

## Test and accessibility position

The repository includes unit/integration/static-analysis/CI coverage. The public
three-brand browser suite passed 12/12 isolated desktop/mobile cases before the
service-worker correction was deployed, and the corrective production release
subsequently passed protected public smoke checks.

Sale sign-off still requires the remaining authenticated journeys and a browser
pass with live service workers enabled. Do not claim complete E2E coverage or
WCAG 2.2 AA compliance unless evidence closes those gates.

Required remaining acceptance includes:

- VanAssist claim/approval/account isolation and selected GPS/no-result cases;
- TowSmart saved-combination save/reload/edit/owner-isolation and provider handoff;
- administrator/RBAC/create-revoke-admin journeys;
- desktop/mobile checks for those authenticated paths; and
- keyboard/focus/contrast evidence for critical public/admin paths or explicit
  disclosure of unresolved accessibility exceptions.

## Privacy and data governance

A field-level engineering inventory exists in `PRIVACY_REGISTER.md`, but seller
approval and transaction evidence are still required for retention,
export/deletion/anonymisation, subprocessors/data regions, backup deletion effects
and legal/finance exceptions. Reachable privacy/terms pages are not by themselves
legal approval.

## Retired-product residue

LocalTorque and Polaris are disabled/retired and excluded from the active sale
package. Historical migrations/ADRs/audit records may remain where deletion would
damage upgrade or due-diligence integrity. Any newly discovered active route,
navigation, admin entry, brand configuration or runtime dependency that re-enables
a retired product is a defect.

## Commercial evidence

The software/data asset can be transferred independently of proven revenue, but
buyers must not be given unsupported growth, user, revenue, conversion or
provider-verification claims. Before marketing the business, complete the
restricted transaction records for current production metrics, invoices,
renewals and account transfer mechanics and clearly separate:

- measured values;
- derived calculations; and
- projections/opportunity assumptions.

## Deferred by design

The following are not sale-readiness blockers unless a real production defect or
buyer requirement makes them necessary:

- shared cross-domain login;
- major visual redesigns or design-system refactors;
- new brands or a broader Brand Builder;
- additional marketplaces or ownership-content expansion;
- broad AI expansion;
- bulk marketing/founding-membership campaign tooling;
- production billing activation; and
- infrastructure scaling without measured load justification.
