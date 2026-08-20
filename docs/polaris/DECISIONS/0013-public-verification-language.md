# ADR 0013: Public verification language

- **Status:** accepted
- **Date:** 2026-08-01
- **Owners:** Assist Platform Architecture
- **Backlog item:** POL-001, POL-004, POL-005
- **Affected brands/modules:** polaris public UX, towsmart integration

## Context

Tow compatibility and RV specifications influence safety and purchasing. Overstated
confidence (“certified to tow”, “verified legal”) creates liability and erodes trust
when data is incomplete or guidance-only.

## Decision

Public copy and UI components use **graded, honest language**:

| Context | Allowed | Prohibited |
| --- | --- | --- |
| Tow match | “Guidance”, “within stated limits”, “check with TowSmart” | “Legal to tow”, “certified”, “guaranteed safe” |
| Specs | “Manufacturer states”, “Not provided”, source chip | Implied verification without source |
| Find results | “Strong fit”, “Possible trade-offs” | “Perfect match”, “AI recommends you buy” |
| Price | “Indicative RRP as at {date}” | “Current drive-away price” without source |
| Provenance | verified / imported / unknown labels | Blank or hidden gaps |

Mandatory disclaimer component on tow-match and compatibility blocks linking to
TowSmart assumptions.

Platform design system rule applies: no fake urgency, reviews or endorsements.

## Alternatives considered

- Strong marketing language for conversion: rejected (trust, legal).
- Legal review per page: impractical; template language instead.
- Hide low-confidence data entirely: rejected (uncertainty penalty preferred).

## Consequences

- UX writers and engineers share phrase library in UX_SPECIFICATION.md.
- QA checklist includes forbidden terms scan.
- TowSmart and Polaris both describe guidance not certification.

## Quality Gate impact

- Architecture: N/A.
- UX: slightly longer disclaimers.
- Engineering: shared disclaimer partial/component.
- Business: reduced misrepresentation risk.

## Validation and rollback

Validate: content review and automated grep for prohibited terms in views. Rollback:
copy changes only.
