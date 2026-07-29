# ADR 0017: Review-first organisation PR outreach

- **Status:** accepted
- **Date:** 2026-07-30
- **Backlog:** COM-002, COM-003
- **Scope:** shared administration and VanAssist outreach

## Context

VanAssist can be useful to caravan, motorhome and 4WD clubs, industry bodies,
manufacturers, dealer and rental networks, park groups, tourism organisations
and specialist publications. Those organisations are not providers or customers,
and a published email address is not blanket permission for a bulk campaign.
Mixing them into either existing audience would hide the source, role and reason
for contact and make an accidental irrelevant blast too easy.

## Decision

The platform has a separate PR & Outreach Hub and organisation audience.
Research imports are review-only. Every target retains its official source URL,
source-check date, publication context, published role, relevance rationale and
safety flags. A target becomes eligible only after a human review records an
accepted basis and evidence, the source is no more than 180 days old, the address
is not personal or ambiguous, no no-unsolicited warning is present and global
suppression does not apply.

Campaigns select one organisation type and use role-matched copy. Club member
resources, industry/data collaboration, fleet/dealer owner support, tourism
resources and editorial pitches are separate messages. Delivery reuses the
internal test, maximum-25 pilot and reviewed 50/day and 100/day stages. Automatic
continuation is not available. Each delivery records the organisation contact
and evidence used. A message identifies the sender, includes contact details and
an unsubscribe route, makes no implied endorsement and never requests member or
customer lists.

## Consequences

- Organisation research can be broad without becoming an automatic mailing list.
- Peak bodies and federations can be approached before their member clubs.
- Explicit warnings, personal-looking contacts and stale sources fail closed.
- One campaign cannot silently mix clubs, media, manufacturers and tourism bodies.
- The engineering controls support careful operation but are not legal advice;
  the sender remains responsible for relevance, evidence and current law.

## Rollback

Disable the PR & Outreach Hub routes and stop new organisation campaigns. Cancel
pending organisation campaign queue rows and retain contacts, review history,
delivery evidence, bounces, complaints and suppressions. Migration 084 is
additive and should remain in place during application rollback.
