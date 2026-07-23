# Authoritative handoff reconciliation

The owner brief in `Assist_Platform_Work_Handoff.md` is the product and launch brief. Where it assumed PostgreSQL/Redis, the verified repository and production runtime remain authoritative: this application uses PHP, MariaDB, Docker and Caddy. Replacing that working runtime is not required for the requested product outcomes.

## Implemented and verified in the repository

- VanAssist, TowSmart and TrailerWise multi-domain runtime and brand-aware email attribution.
- National town autocomplete displayed as `Town / State`.
- Shared providers, brand listings, claims, verification, reviews, memberships, advertising, analytics and administration foundations.
- TowSmart vehicle/towable catalogue, calculator and saved combinations.
- VanAssist stays/park-partner foundation and curated park dataset.
- Social Media Studio with downloadable brand assets.
- Billing schema, plans, Stripe webhook verification and feature gates, with live charging disabled.
- LocalTorque private automotive-directory foundation.

## External or owner-controlled launch work

- Microsoft Entra application/certificate consent and Graph sending verification.
- S3-compatible off-server application-backup credentials and a restore test against that target.
- Production domain, DNS, mailbox and legal acceptance for LocalTorque.
- Stripe production products/prices and explicit owner approval before enabling billing.
- Final owner acceptance of public and provider/admin workflows.
- Rotation of any passwords or secrets previously exposed outside the server.

## Data-sourcing rule

Australia-wide enrichment is ongoing operational work, not a one-time software completion claim. New businesses, branches, parks, vehicles and towables must have lawful provenance, import reports, duplicate detection and verification status. Scraped or proprietary datasets must not be imported solely because they are available on disk.
