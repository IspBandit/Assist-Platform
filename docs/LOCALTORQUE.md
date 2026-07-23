# LocalTorque

LocalTorque is the automotive business-directory brand within the Assist Platform. It reuses the canonical provider, location, account, claim, verification, review, membership, advertising and administration foundations. It does not duplicate provider businesses that already participate in VanAssist, TowSmart or TrailerWise.

## Current implementation

- Brand ID `4`, key `localtorque`, status `private`.
- Development hostname `localtorque.test`; no production domain is assumed.
- Forty data-driven automotive categories seeded by migration `041_localtorque_foundation.sql`.
- Brand-scoped directory filtering and business profiles.
- Canonical LocalTorque routes `/business/{slug}` and `/category/{slug}`.
- Dedicated LocalBusiness structured data, canonical URLs and sitemap entries.
- Automatic, restartable classification of relevant canonical providers into LocalTorque, with unverified heuristic assignments requiring business or administrator review.
- Shared provider claim, verification, membership and administration workflows.

## Data integrity and verification

The presence of a public business record is not proof of current trading status, ownership, accreditation, roadworthy authority or service availability. Imported and heuristically classified listings remain unverified until reviewed. National-chain locations must be stored as separate provider locations or branch records with source provenance; a brand name alone must never be presented as proof that a branch exists in a particular town.

## Search and future scope

The directory supports business, category and town filtering and uses the national town type-ahead formatted as `Town / State`. GPS-nearby and map endpoints are shared platform capabilities. Open-now, review ranking, richer branch modelling, route-aware suggestions and AI search remain staged work and must not be presented as live until implemented and tested.

## Launch blockers

LocalTorque must remain private until the owner supplies and verifies its production domain, support/sender address, Cloudflare DNS, transactional-mail configuration, legal links and launch acceptance. Production DNS must never point `localtorque.test` at the live server.
