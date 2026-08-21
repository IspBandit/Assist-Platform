# LocalTorque

LocalTorque is a first-class Assist Platform Enterprise brand. Its implementation
is tracked by LOC-001 through LOC-003 and DATA-008 in `PRODUCT_BACKLOG.md`. Build and test it
as production-capable while keeping public launch fail-closed until the external
prerequisites below are confirmed.

LocalTorque is the automotive business-directory brand within the Assist Platform. It reuses the canonical provider, location, account, claim, verification, review, membership, advertising and administration foundations. It does not duplicate provider businesses that already participate in VanAssist, TowSmart or TrailerWise.

## Current implementation

- Brand ID `4`, key `localtorque`, status `private`.
- Development hostname `localtorque.test`; no production domain is assumed.
- The original forty-category foundation plus the authoritative provider-pack taxonomy, routed per brand from `category.brands`.
- Brand-scoped directory filtering and business profiles.
- Canonical LocalTorque routes `/business/{slug}` and `/category/{slug}`.
- Dedicated LocalBusiness structured data, canonical URLs and sitemap entries.
- Automatic, restartable classification of relevant canonical providers into LocalTorque, with unverified heuristic assignments requiring business or administrator review.
- Shared provider claim, verification, membership and administration workflows.
- Authority-linked roadworthy, inspection and modification library at `/rules`, covering national material and every state and territory.
- Daily source fingerprinting with current/upcoming effective states and review-before-republish when official material changes.
- Clearly labelled, context-matched provider sponsorship after rule results; sponsorship never changes official sources or organic rankings.
- Resumable ingestion of the publishable LocalTorque MDM pack with record-level source, licence, confidence and review provenance.
- Fuel-station and EV-charging discovery shared with VanAssist only, using exact site coordinates where available.

## Data integrity and verification

The presence of a public business record is not proof of current trading status, ownership, accreditation, roadworthy authority or service availability. Imported and heuristically classified listings remain unverified until reviewed. National-chain locations must be stored as separate provider locations or branch records with source provenance; a brand name alone must never be presented as proof that a branch exists in a particular town.

## Search and future scope

The directory supports business, category and town filtering and uses the national town type-ahead formatted as `Town / State`. GPS-nearby and map endpoints are shared platform capabilities. Exact provider coordinates are preferred; results without exact coordinates use their resolved town location and must not imply site-level precision. Open-now, road-route distance, review ranking, richer branch modelling and AI search remain staged work and must not be presented as live until implemented and tested.

See `LOCALTORQUE_PROVIDER_PACK.md` for the import contract, fuel/EV UX, filters,
ranking, attribution requirements and known coverage gaps.

## Launch blockers

LocalTorque must remain private until the owner supplies and verifies its production domain, support/sender address, Cloudflare DNS, transactional-mail configuration, legal links and launch acceptance. Production DNS must never point `localtorque.test` at the live server.
