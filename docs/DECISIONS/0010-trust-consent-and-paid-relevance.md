# ADR 0010: Separate authority, owner consent, provider evidence and paid relevance

- **Status:** accepted
- **Date:** 2026-07-27
- **Owners:** Assist Platform Enterprise
- **Backlog items:** CORE-010, EXP-006, EXP-007, DATA-009, COM-006, COM-007
- **Affected brands/modules:** all brands, accounts, Garage, rules, providers, campaigns, admin, scheduled work

## Context

The platform becomes more useful when an official requirement leads to a saved
owner action and an appropriate local specialist. That usefulness creates four
different kinds of trust: government authority, private owner data, provider
claims and paid promotion. Combining those states would make an advertisement
look official, a provider claim look government-approved or a cross-brand
handoff disclose more than the owner intended.

## Decision

Official sources remain first and fail closed when their monitored content
changes. Guided journeys organise those sources and state their limitations;
they do not calculate legal approval. Owners explicitly choose exact-scope email
alerts and separately consent to a handoff containing only jurisdiction,
vehicle class, document kind and intention. Garage notes and documents are not
included.

Provider capabilities remain pending until a reviewer verifies the linked
private evidence. Public labels show scope and validity and expressly disclaim
government endorsement. Expired or withdrawn rows do not display.

Paid campaigns remain after authority results, visibly sponsored and separately
measured. Provider self-service creates only a pending campaign. Activation
requires destination, targeting, daily/total budget and CPC review. Paid metrics
never enter official-source order or organic provider ranking. Contact
attribution is first-party, campaign/provider scoped, expires after 24 hours and
is consumed once.

## Alternatives rejected

- personalised alerts without explicit scope consent;
- copying private Garage records between brand databases;
- allowing provider-entered qualifications to display immediately;
- placing advertisements within official result cards;
- recording paid clicks as organic engagement or allowing budget to alter rank;
- silently updating a legal summary when a source fingerprint changes.

## Quality Gate result

- **Architecture — pass:** shared platform records retain explicit owner,
  source/destination brand and review boundaries; migration 052 is additive.
- **UX — pass:** mobile-first guided check, consent controls, compliance centre,
  provider workspace and reviewer dashboard use live labels and native controls.
- **Engineering — pass:** fresh migrations 001–052 pass; changed-source alerts,
  campaign isolation, owner privacy, static analysis and unit/integration suites
  are automated.
- **Business — pass:** verified capability and sponsored campaign products are
  monetisable without selling private owner data or weakening authority trust.
  Automated card charging remains outside this decision and stays blocked by
  COM-004.

## Operations and rollback

Run `regulatory_alerts` after the source monitor and before the email queue.
Review changed sources in **Admin → Trust, rules & growth**. Application rollback
removes routes and scheduled invocation while retaining consent, review and
metric records. Migration 052 is not reversed in place. Pausing all campaigns
stops paid delivery independently. Disabling alert subscriptions stops new mail
without deleting the consent audit.
