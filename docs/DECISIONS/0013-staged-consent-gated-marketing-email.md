# ADR 0013: staged, consent-gated marketing email

**Status:** accepted
**Backlog:** CORE-005
**Scope:** shared email and administration platform; all brands

## Context

The broadcast service supported suppression and unsubscribe, but a single admin
action could queue an entire audience. Provider selection also relied on a
marketing checkbox without independently requiring the dated consent basis and
evidence fields. That was not an acceptable launch control for provider outreach.

## Decision

Marketing broadcasts fail closed. A provider is eligible only when the active
brand listing, opt-in, consent date, approved consent basis and non-empty evidence
are all present. A public listing or published business email is not consent.

Every campaign uses these irreversible progression gates:

1. internal test;
2. pilot capped at 25 eligible recipients;
3. explicit review before a rolling cap of 50 queued recipients per 24 hours;
4. explicit second review before a rolling cap of 100 per 24 hours.

The application does not automatically promote a campaign. Each batch is an
audited admin action. Cancellation also cancels its still-pending queue entries.
Suppression and a signed unsubscribe link remain mandatory at queue time.

Provider campaigns may be segmented with the dedicated `provider_category`
audience. Unlike the general category audience, it never includes customers.
The administrator sees the number of active providers with email, the subset
with complete consent evidence and the remainder held for review. A lightweight
service-family image and human category-specific copy can be prepared in a
draft, but neither changes recipient eligibility.

The campaign detail screen exposes every matching active provider with a usable
email as a review pool, grouped as eligible, held, campaign-removed or globally
suppressed. Administrators may remove an eligible provider from one campaign,
restore an eligible provider, or record the actual consent basis, date and
evidence before adding a held provider. These actions are audited. A campaign
exclusion is applied again at queue time; a global opt-out, complaint or bounce
cannot be overridden by the campaign screen.

## Consequences

- Provider seed/import data cannot silently become a marketing audience.
- Launch is slower, allowing delivery, complaints, bounces, replies and opt-outs
  to be reviewed before volume increases.
- Campaigns above 100 recipients require repeated daily admin actions.
- Existing scheduled broadcasts return to draft for review.
- An empty eligible count no longer hides providers that need consent review.
- Recorded consent updates the canonical provider, so its evidence must genuinely
  apply to future relevant provider communications, not merely one draft.
- When an administrator opens VanAssist email campaigns, the platform
  idempotently prepares a review-only draft for each active provider service
  category that has at least one listed provider email. No draft is queued.
- These controls support compliance operations but do not replace legal advice
  or the operator's obligation to retain evidence and use relevant content.

## Alternatives rejected

- Immediate full-audience queueing: too easy to make a large, irreversible error.
- Throttling only in the mail worker: does not create review gates and mixes
  transactional email with campaign policy.
- Treating public or active-provider email as inferred consent: the address alone
  does not demonstrate that the particular message is relevant or expected.
