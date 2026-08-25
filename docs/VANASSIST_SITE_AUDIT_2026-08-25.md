# VanAssist site audit — 25 August 2026

Backlog ownership: VAN-011, VAN-012, DATA-004, EXP-005, OPS-012.

## Decision

Ask VanAssist should be the preferred homepage starting point while direct structured search remains immediately available.

Reason: production verification already records public Ask, traveller-facility results, Google Routes road-distance integration, provider search, stays and the direct service journeys as live. The old phone layout intentionally placed Ask after four quick actions and the structured form. That ordering no longer matches the product capability or the simplest path for users who do not know the platform taxonomy.

The Ask-first change does not remove or hide structured search. Service category, town/suburb/postcode, current-location controls, stays, trusted services, fuel/essentials and browse-all paths remain available.

## Verified working from current production/repository evidence

- VanAssist is live on the shared Assist Platform Enterprise application.
- Ask VanAssist is enabled in production and `/ask` returns results.
- Traveller facilities are available to Ask when the production facility flag is enabled.
- Google Routes is configured for road-distance output.
- Structured provider search remains live and is a release criterion that must stay unchanged.
- VanAssist has 8,457 community-sourced stays across Australia, including 853 identified free stays.
- The shared production directory contains 7,304 providers, with brand-specific VanAssist visibility controlled by platform listings/categories.
- Provider-name lookup, regional fallback, typo recovery, location parsing, road-distance enforcement and a 1,000+ Ask regression corpus are implemented.
- Dump point, potable-water and rest-area structured selections already route into Ask when that dataset owns the result rather than incorrectly searching providers.
- Search, Ask and stay outcomes are now measured in first-party Website Insights and the daily performance report.

## Working but still operationally conditional

- VanAssist remains in initial-launch posture rather than formally signed-off commercial launch.
- The production Quality Gate is not a full PASS yet.
- Independent automated off-site backup and a current isolated restore rehearsal remain launch blockers.
- Owner acceptance of critical journeys/content remains required.
- Paid AI and Google Places remain off by policy; Ask must continue to work deterministically without depending on paid AI.

## Research/data acquired but not fully proven published

The Assist RIC facility catalogue packs, including original, third-wave and gap-fill packs, were submitted to the production Admin API. Repository production-state documentation explicitly says admin-side row counts and any unpublished import candidates still require owner verification in MariaDB.

This is the clearest current researched-but-not-proven-published gap. The site must not claim that every researched facility record is live until the production candidate/review counts are checked. The relevant audit should distinguish:

1. catalogue/source discovered;
2. acquired by RIC;
3. submitted to Admin API;
4. accepted as candidate;
5. approved/published;
6. searchable by structured search where applicable;
7. reachable through Ask;
8. rendered to the user.

## UX gaps found

### 1. Ask was visually secondary on the homepage

The Ask partial used the divider copy `or ask in plain language` and was included after the structured category/location form.

### 2. Phone layout explicitly pushed Ask below the direct journeys

The mobile CSS gives quick actions order 2, structured search order 3 and Ask order 4. The Playwright acceptance test also asserts that Ask follows the quick actions. This was an intentional earlier optimisation but is now inconsistent with an Ask-first product direction.

### 3. Homepage copy did not teach users what Ask can cover

The old prompt example was mainly a repair scenario and the helper only said `Providers, stays and traveller facilities only`. Users were not being shown that ordinary requests such as dump points, drinking water, pet-friendly stays and mobile mechanical help can start in Ask.

## Implemented changes in this branch

- Moved the Ask partial ahead of the structured search form in the homepage source hierarchy.
- Changed the Ask divider from secondary `or ask in plain language` wording to `Start here`.
- Added concise copy explaining that Ask looks across providers, stays and traveller facilities.
- Added representative examples: dump point, pet-friendly stay, mobile mechanic and drinking water.
- Renamed the structured search heading to `Browse directly`.
- Changed the quick-action navigation label to `Browse VanAssist directly`.
- Preserved service category, location, current-location, stays, service and assistance paths.
- Added unit coverage that fails if Ask is moved back below the structured search in source order or if the direct browse paths disappear.
- Overrode the existing phone order so Ask appears before quick actions and structured search without deleting those routes.

## Follow-up evidence required before production release

- Run the focused unit suite including `VanAssistAskFirstTest` and existing VanAssist UX/search tests.
- Run the VanAssist Playwright desktop and mobile homepage acceptance and update the prior expectation that Ask follows quick actions.
- Render the homepage at representative phone and desktop widths and confirm Ask, quick actions and structured search remain usable without horizontal overflow.
- Re-run representative Ask questions for provider, stay and facility intents with explicit place, device location and no-location clarification cases.
- Verify production facility counts and unpublished candidate counts in admin/MariaDB so researched-but-unpublished data can be quantified rather than inferred.
- Record the change in release notes and the customer finding-nearby-help guide before merge.

## Representative Ask acceptance set

- `Find a caravan repairer near Emerald`
- `Where is the nearest dump point?`
- `Find drinking water near Rockhampton`
- `Somewhere pet friendly to stay near Bundaberg`
- `I need a mobile mechanic near me`
- `Find a caravan park near Griffiths Creek`
- `I need an auto electrician within 50 km of Gladstone`
- `Where can I stop overnight near Roma?`

Each case must preserve explicit-place precedence over device GPS, label distance method honestly, avoid unrelated provider fallback for facility/stay-only intents, and record zero-result/rescued-search outcomes correctly.
