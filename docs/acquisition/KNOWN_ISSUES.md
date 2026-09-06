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

### Formal sale/quality gate not yet complete

The platform has substantial production verification, but the final sale-ready
and full Platform Quality Gate sign-off is not yet recorded. The authoritative
remaining work is tracked in `../SALE_READINESS.md`.

### Automated independent off-site recovery evidence

A historical manual off-server database restore has been recorded, but the sale
candidate still requires:

- automated encrypted independent off-site backup;
- a current isolated restore rehearsal from that automated backup; and
- retained evidence showing the buyer can repeat the process.

This is a transfer/reliability gate, not a speculative feature.

### Monitoring and scheduled-task alert delivery

Application health checks and scheduled jobs exist. Buyer-grade sign-off still
requires external uptime/error alert delivery and explicit task/cron failure
alerts to an owner-controlled destination.

## Product limitations

### VanAssist provider verification is progressive

Imported provider data is not automatically treated as business-verified.
Evidence/verification status must remain visible and claims must not imply that
all directory businesses have confirmed their listing.

### Provider claim acceptance requires final sale-candidate verification

The claim/approval path has been under production acceptance work. Before sale
sign-off, the complete listing claim → evidence review → approval → account
access journey must be re-run and retained as acceptance evidence.

### TowSmart is guidance, not certification

TowSmart calculations and reference data are designed to assist towing decisions
and must continue to be presented as guidance. They do not replace manufacturer,
engineering, registration-authority or legal requirements. Catalogue provenance
and update procedures must be included in the data-room provenance register.

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
revenue or production charging.

## Test and accessibility position

The repository includes unit/integration/static-analysis/CI coverage and prior
rendered acceptance work. Sale sign-off still requires a current three-brand
acceptance pass. Do not claim complete E2E coverage or WCAG 2.2 AA compliance
unless new evidence closes those gates.

Required current acceptance includes:

- VanAssist search, Ask, claim, stay and assistance journeys;
- TowSmart calculation, explanation, saved-combination and provider-handoff journeys;
- TrailerWise service discovery and secondary marketplace journeys;
- desktop and mobile layout/interaction checks;
- keyboard/accessibility checks for critical public and admin paths.

## Privacy and data governance

The application contains consent/privacy-related controls, but sale-ready due
diligence still requires a current field-level personal-data inventory,
retention schedule, subprocessor/data-region register and documented
export/deletion/anonymisation position including legal/finance exceptions.

## Retired-product residue

LocalTorque and Polaris must not be required for active operation. Historical
migrations/ADRs/audit records may remain where deletion would damage upgrade or
due-diligence integrity. Any remaining active routes, navigation, admin entries,
brand configuration, scheduled tasks or runtime dependencies for those retired
products are defects to be removed before sale-ready sign-off.

## Commercial evidence

The software/data asset can be transferred independently of proven revenue, but
buyers must not be given unsupported growth, user, revenue, conversion or
provider-verification claims. Before marketing the business, export current
production metrics and costs into the transaction data room and clearly separate:

- measured values;
- derived calculations; and
- projections/opportunity assumptions.

## Deferred by design

The following are not sale-readiness blockers unless a real production defect or
buyer requirement makes them necessary:

- shared cross-domain login;
- major visual redesigns;
- new brands;
- additional marketplaces;
- broad AI expansion;
- infrastructure scaling without measured load justification.
