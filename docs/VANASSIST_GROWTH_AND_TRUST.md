# VanAssist growth and trust operating loop

**Backlog:** DATA-004, DATA-014, VAN-001, VAN-002, EXP-004, EXP-005 and INF-004.

The VanAssist **Admin → Insights → Growth & trust** dashboard joins six existing
platform capabilities into one operator workflow. It does not create a second
analytics store or publish unreviewed research.

## Facility publication

The dashboard distinguishes active reviewed/verified facilities, facilities
with usable coordinates, independently verified rows, pending import candidates
and enabled datasets. Breakdowns by facility type and state make acquired data
different from published/searchable coverage.

## Search-success loop

Open and researching knowledge gaps are ranked by the existing priority score,
zero-result frequency and recency. Operators research the most valuable gap,
publish evidence through the existing facility/provider/stay workflows and mark
the knowledge gap resolved. Clicks and contacts are usefulness signals, never
proof that a service was completed.

## Provider trust and value

The dashboard reports active, claimed and evidence-verified providers plus
pending claims and claims awaiting more evidence. The provider-interest report
adds appearance-to-profile and profile-to-contact rates without exposing visitor
identity or presenting those actions as completed jobs.

## Accessibility and performance baseline

`tests/Acceptance/vanassist-quality-baseline.spec.js` checks the homepage, Find,
stays and provider directory at desktop and phone widths. It requires a single
main landmark and H1, labelled form controls, image alternatives, no horizontal
overflow, DOM readiness below four seconds and transferred resources below 3 MB.
The browser run is evidence for the tested environment, not a WCAG certification.

## Evidence-backed regional SEO

Noindex town pages are ranked using live VanAssist providers, published
facilities, active stays and recent searches. Publication remains a manual,
permission-controlled action. A town needs reviewed public copy, SEO title and
description plus at least three live local records. Publication is audited and
adds the town to the existing sitemap; thin pages remain noindex.

## Rollback

The dashboard is read-only except town publication. Reverting a published town
sets its existing `noindex` field back to 1 through the normal town editor. No
new tables, flags, background jobs or external credentials are introduced.
