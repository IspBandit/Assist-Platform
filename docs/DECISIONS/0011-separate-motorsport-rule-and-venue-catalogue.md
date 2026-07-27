# ADR 0011: Separate motorsport rule and venue catalogue

- **Status:** accepted
- **Date:** 2026-07-27
- **Owners:** Data and LocalTorque
- **Backlog item:** DATA-010, LOC-004
- **Affected brands/modules:** LocalTorque public rules, venues, calendars and provider discovery

## Context

Competition eligibility is not road legality. Australian motorsport rules can be issued by several sanctioning bodies and layered through national manuals, discipline or class technical specifications, state or series regulations, event supplementary regulations and venue instructions. Rally and off-road events can use approved routes rather than permanent circuits. Combining this material with government road-registration documents would misstate authority and make completeness impossible to assess.

## Decision

LocalTorque has a separate motorsport catalogue with explicit discipline families and named disciplines. It records the publishing authority, rule layers, jurisdictions, version and official source. A separate venue register records permanent, temporary, route-based and club-network locations, the venue website where available, and an official venue, club or governing-body calendar source.

The initial boundary is Australian land-based car, kart and motorcycle sport represented by Motorsport Australia, AASA, ANDRA, Speedway Australia, Karting Australia and Motorcycling Australia. The public experience explains that current event supplementary regulations, bulletins and officials’ directions may add to or override general material. Motorsport records never imply road-registration approval.

## Alternatives considered

- Add sanctioning-body documents to `regulatory_documents`: rejected because its authority levels and public claims are government road-law oriented.
- Publish editorial category pages with manually embedded links: rejected because categories, jurisdiction gaps and stale sources could not be tested reliably.
- Copy event dates into permanent content: rejected because late event and venue changes would create avoidable stale-date risk.

## Consequences

The schema is additive and LocalTorque-only. More than 50 disciplines remain explicit and testable. Venues can be discovered by family and state, while calendars remain source-owned. The motorsport source monitor applies the road-rule library's fail-closed review principles to rulebook, venue and calendar URLs. Commercial provider handoffs remain separated from official rules and calendars.

## Quality Gate impact

- **Architecture — pass:** road-law and competition authority domains remain separate, migrations are additive, and the route is enforced as LocalTorque-only.
- **UX — pass:** nine collapsed family choices progressively disclose every named discipline; official rules, venues and source-owned calendars precede one provider action. Desktop and phone-width renders retain contrast, tap targets and single-column fallbacks.
- **Engineering — pass:** strict Composer validation and static analysis pass; 140 unit tests (26,332 assertions) and 19 disposable-database integration tests (127 assertions) pass; all 445 PHP files pass syntax validation; migrations are idempotent and every family maps to an official source and venue/location source.
- **Business — pass:** the journey ends in measurable relevant-provider discovery, while paid placement remains clearly labelled and cannot alter authority results or organic provider ranking. Providers use the shared four-brand workspace rather than a separate motorsport portal.

## Validation and rollback

The release candidate was migrated twice on a disposable MySQL database, source and venue coverage was verified, and desktop plus phone-width views were rendered. Application rollback removes the route and UI while leaving additive catalogue tables intact; no destructive rollback is required.
