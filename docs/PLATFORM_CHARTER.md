# Assist Platform Charter

This charter applies to product, UX, engineering, data, operations and commercial
work across Assist Platform Enterprise and every brand.

## User Experience First

Deliver the simplest trustworthy path to the user's goal. Preserve the current
UX redesign and evolve it into shared, tested patterns. Mobile, accessibility,
weak connections, clear language, recovery from errors and honest empty states
are core product requirements.

**Evidence required:** user journey, responsive states, accessibility checks and
rendered acceptance for material UI changes.

## Platform First

Consider every new capability as a shared platform service before implementing
it for one brand. Share identity, memberships, reviews, search, maps, media,
email, analytics and administration where behaviour is genuinely common.
Brand-specific domain logic remains explicit.

**Evidence required:** documented ownership boundary and confirmation that the
change does not duplicate an existing platform capability.

## Brand Integrity

Each brand must remain immediately understandable and focused:

- VanAssist: stays, travel support and caravan/RV services.
- TowSmart: towing intelligence, matching, weights, safety and guidance.
- TrailerWise: trailer businesses, services, parts and ownership.
- LocalTorque: automotive workshops and specialists.

Cross-brand recommendations must be relevant, transparent and non-confusing.

**Evidence required:** named source/destination brand, relevance rule and public
copy review.

## Commercial Value

Work must materially improve user value, provider value, operating efficiency,
recurring revenue potential, defensibility or sale readiness. Trust and safety
must not be traded for short-term monetisation.

**Evidence required:** measurable intended outcome and analytics/event plan when
appropriate.

## Maintainability

Prefer configuration over duplicated customisation, explicit domain models over
vague abstractions and small forward migrations over rewrites. Enforce scope and
ownership on the server. Record significant decisions and preserve operational
documentation so a future team or buyer can run the platform.

**Evidence required:** tests, documentation, migration/rollback notes and an ADR
when the decision is architecturally material.

## Conflict rule

When principles conflict, user safety, privacy and data integrity take priority.
The trade-off must be written in the relevant ADR or pull request.
