# Products and feature status

Status terms: **live** means verified on production; **implemented** means code
exists and is tested but may not be publicly launched; **partial** means an MVP
or foundation exists; **planned** means it must not be advertised as available.

## VanAssist

Purpose: help caravan, camper and motorhome owners find appropriate providers
and request assistance, especially across regional Australia.

Implemented/live foundations include provider/location search, provider pages,
service categories, assistance requests, matching, customer/provider/admin
accounts, provider onboarding/claims, service runs, caravan-park partnerships,
reviews foundations, CMS, demand analytics and provider promotion foundations.

Billing is dormant and must not be described as live charging. Provider coverage,
content quality, transactional email and public-launch acceptance remain business
and operational work.

## TowSmart

Purpose: help Australian users understand a loaded tow combination before travel.
It is educational guidance, not certification, engineering or legal advice.

Implemented/live MVP:

- eight manual inputs in kilograms;
- five limit checks: vehicle GVM, vehicle GCM, braked towing capacity, trailer
  ATM and maximum towball download;
- calculated vehicle loaded mass, trailer GTM, combination mass and remaining
  vehicle/trailer capacity;
- within/near/exceeds status and safety disclaimer;
- authenticated saved result snapshots, isolated to TowSmart.

Partial/planned:

- vehicle and towable-asset tables exist, but profile CRUD is not connected;
- saved combinations cannot yet be edited, deleted, reopened or compared;
- axle-group checks, towball percentage guidance, detailed payload composition,
  manufacturer-data sourcing, trip checklists, reports and education library are
  not complete;
- formulas require domain-expert review before stronger safety claims.

## TrailerWise

Intended core purpose: a trailer-industry service and business platform—not a
classified-advertising site. The primary experience must help users find and
evaluate:

- trailer manufacturers and dealers;
- repairers and mobile repair services;
- parts and accessories suppliers;
- inspectors, certifiers and compliance-related services;
- specialists by trailer type, location and service area;
- useful ownership, maintenance, registration and compliance information.

The current implementation drifted from this intent: its homepage and marketplace
are centred on `trailer_listings` for new, used and hire trailers. That sales/hire
capability is **partial and secondary**. It must not be treated as TrailerWise's
primary product. Business/service discovery, category/location search, business
profiles and appropriate content are the next product correction. Sales listings
should remain disabled or secondary until the owner explicitly approves them.

## Shared platform

Shared capabilities include identity, provider entities, locations, email queue,
notifications, CMS, analytics, admin, audit records, billing foundations,
security middleware, brand resolution, deployment and monitoring. Shared code
does not imply that private data or unsuitable features are automatically visible
across brands.

