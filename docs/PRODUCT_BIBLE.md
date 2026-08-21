# Assist Platform Enterprise Product Bible

## Document authority

This Product Bible defines what Assist Platform Enterprise is, who it serves and
how its brands create value. It is the product companion to
`ASSIST_PLATFORM_ENTERPRISE_SPECIFICATION.md`, which owns technical architecture.
Where an older brief conflicts with this document, the Charter, accepted ADRs and
the Enterprise Specification take precedence.

## Product thesis

Assist Platform Enterprise is one Australian multi-brand discovery, knowledge
and provider-membership platform. It owns the reusable operating system—identity,
business records, locations, search, reviews, memberships, content, media,
communications, analytics and administration. VanAssist, TowSmart, TrailerWise
and LocalTorque are focused public brands running on that platform.

The platform is designed to be operated and, if required, transferred as one
commercial asset. A brand is not a copied website or an independent database.
It is a governed combination of domain, theme, content, categories, modules,
permissions, launch state and analytics context.

## Customer promises

### Public users

- Find useful, relevant information or providers without creating an account.
- Understand why a result is shown and whether it is claimed, verified or paid.
- Use the essential journey on mobile, slow connections and without a map.
- Receive honest safety, availability, coverage and data-quality messaging.
- Move between related brands only when the recommendation is genuinely useful.

### Providers

- Maintain one canonical organisation and account across eligible brands.
- Claim and correct a discovered listing through a controlled verification flow.
- Understand membership benefits, status, performance and future billing before
  making a commitment.
- Receive useful enquiries and measurable exposure without hidden commissions.
- Control staff access, public information and communication preferences.

### Platform operators

- Sign in once and work in an All Brands or permission-scoped brand context.
- Manage brands without duplicating applications or silently leaking data.
- See coverage, claims, memberships, campaigns and operating health in one place.
- Launch changes through documented, testable and reversible release processes.
- Preserve enough documentation and provenance for a future operating team or
  purchaser to understand the asset.

## Brand portfolio

### VanAssist

**Purpose:** help caravan, camper and RV travellers find stays, facilities and
relevant assistance around Australia.

**Owns:** parks and stays, campgrounds, caravan parks, rest areas, dump points,
RV-oriented provider discovery, assistance requests and traveller guidance.

**Does not become:** a generic automotive directory, a trailer catalogue or a
towing-calculation product.

**Primary journeys:** find a stay; find nearby RV help; request assistance;
claim an RV-service listing; discover useful travel guidance.

### TowSmart

**Purpose:** help Australians understand whether and how a vehicle and trailer
combination can be towed safely and legally.

**Owns:** vehicle/trailer matching, ATM/GTM/GCM and towball-weight concepts,
calculators, saved combinations, checklists, compliance guidance, safety content
and towing education.

**Does not become:** a tow-truck or recovery marketplace. Relevant specialists
may be recommended, but towing intelligence remains the product centre.

**Primary journeys:** calculate a combination; understand limits and assumptions;
save and compare a setup; follow a safety checklist; locate a relevant specialist.

### TrailerWise

**Purpose:** help people buy, own, maintain, repair and understand trailers.

**Owns:** trailer manufacturers, dealers, repairers, parts, bearings, brakes,
suspension, fabrication, inspections, compliance and ownership knowledge.

**Does not become:** a campground or accommodation directory. Camper trailers
remain trailer products; places to stay remain VanAssist data.

**Primary journeys:** find a trailer business; find parts or repairs; understand
maintenance/compliance; research trailer types and ownership decisions.

### LocalTorque

**Purpose:** become the canonical Australian automotive workshop and specialist
directory used by the wider portfolio.

**Owns:** mechanics, mobile mechanics, auto electricians, diesel, tyres, brakes,
suspension, driveline, fabrication, inspections, body, glass, accessories, 4WD,
fleet, motorcycle, marine and agricultural automotive specialists. Categories
are data-driven and a business may hold several.

**Does not become:** a duplicate roadside, parks, trailer or towing-knowledge
product. It supplies relevant business records to those contexts.

**Primary journeys:** search by service and location; use Near Me; compare claimed
and verified listings; claim a listing; manage business locations and categories.

LocalTorque remains private until its domain, DNS, sender identity, legal pages
and launch quality gate are complete. No public availability claim may be made
before those prerequisites are verified.

## Shared product model

### Canonical business, contextual listing

A business exists once. Locations, contacts and ownership belong to the canonical
record. Brand listings provide contextual description, categories, visibility,
membership presentation and lead settings. Cross-brand visibility is explicit
and relevance-based; it is never created by blind copying.

### Membership lifecycle

The agreed membership language is used across the portfolio:

- **Launch Access:** temporary full-value access while marketplace value is built.
- **Free Listing:** permanent useful presence after launch access ends.
- **Founding Verified:** protected early-provider offer at $10/month or $100/year.
- **Verified Provider:** core membership at $15/month or $150/year.
- **Featured Provider:** increased, clearly labelled visibility at $29/month or
  $290/year.

Entitlements—not scattered plan-name checks—govern capabilities. Providers who
do not choose a paid membership move safely to Free Listing; they are not billed
or deleted. Live charging remains disabled until gateway, GST, legal wording,
webhooks, invoices, cancellation and failed-payment handling pass acceptance.

### Cross-brand commercial rule

One membership account can cover eligible brand listings. Eligibility depends on
approved categories and relevance, not payment alone. Sponsored or featured
placement is always labelled and cannot override safety, location or service
relevance.

## Experience and voice

The existing UX redesign is the official starting point. Shared interaction
patterns remain familiar across brands; themes, imagery, terminology and domain
tools express each brand.

- VanAssist is calm, useful, regional and reassuring.
- TowSmart is precise, educational and explicit about assumptions and limits.
- TrailerWise is practical, capable and ownership-focused.
- LocalTorque is direct, credible and trade-aware.

Copy avoids fake urgency, unsupported leadership claims, invented counts,
guaranteed leads and implied verification. Safety information distinguishes
education from legal or engineering advice and identifies jurisdiction/freshness.

## Core journeys

1. Public discovery without forced registration.
2. Search by intent and location with list-first fallback.
3. Provider/business profile evaluation and contact.
4. Existing listing claim, proof, review and ownership transfer.
5. Provider onboarding, relevant brand selection and profile completion.
6. Membership understanding, transition and self-service management.
7. Review submission, response, reporting and moderation.
8. One administrator session with All Brands and scoped brand switching.
9. Brand configuration, private preview, quality gate and controlled launch.
10. Campaign creation, consent-aware segmentation, test send and delivery audit.

## Success measures

- useful-search and zero-result rates by category/location;
- listing claim and verification conversion;
- provider profile completeness and data freshness;
- profile-to-contact and enquiry conversion;
- membership conversion, annual mix, churn and recurring revenue;
- relevant cross-brand recommendation engagement;
- campaign delivery, bounce, unsubscribe and claim conversion;
- critical journey accessibility, performance and error rate;
- coverage depth on major travel corridors and population centres;
- restore, release and operational-health evidence.

No estimated job value is reported as confirmed provider revenue.

## Product governance

- The Platform Charter decides principles.
- The Enterprise Specification decides architecture.
- The Design System decides shared experience patterns.
- Accepted ADRs record material decisions and trade-offs.
- The Product Backlog records outcomes, dependencies and evidence.
- The Platform Quality Gate decides production eligibility.

New proposals must name an owning workstream, intended measurable value, affected
brands, data/privacy implications and quality-gate evidence. Production defects
may interrupt sequencing; unplanned feature work may not.

## Launch definition

A brand is launch-ready only when its domain and canonical routing, legal pages,
sender identity, data-quality report, critical journeys, SEO controls, analytics,
monitoring, backup/rollback and Platform Quality Gate are complete. A populated
homepage or a successful build alone is not launch readiness.

