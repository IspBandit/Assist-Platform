# TowWise value and monetisation model

## Product rule

The core towing assessment remains free. Commercial placement must never alter
a calculation, warning, completeness score or organic recommendation.

## Retention value

- Local-browser saved scenario with no account requirement.
- Load planner for common vehicle and caravan payload.
- Component-aware headroom checks and printable results.
- Educational explanations attached to the assessment.
- Future account features: named combinations, weighbridge history, reminders,
  comparison reports and shared household access.

## Commercial sequence

1. Build repeat usage and trusted organic traffic with the free checker.
2. Offer clearly labelled contextual placements after the result.
3. Prioritise mobile weighing, suspension, towbar/coupling, brake, tyre,
   insurance and relevant dealer categories.
4. Add qualified provider enquiries and appointment requests.
5. Add optional consumer convenience features only after free utility is strong.

## Campaign model

Migration `036_contextual_advertising.sql` adds reusable brand-scoped
advertisers, campaigns, approved creatives and impression/click events.
Campaigns target a placement and a context such as `mobile_weighing`,
`suspension`, `towing_equipment` or `general`. Only active campaigns with an
approved creative and active advertiser may render. Destination URLs must use
HTTPS and outbound clicks are attributed through a validated redirect.

`ENABLE_TOWWISE_ADVERTISING` remains false by default. Do not enable it until
campaign administration, creative moderation, advertiser terms and privacy
notices have received production acceptance.
