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

## Consequences

- Provider seed/import data cannot silently become a marketing audience.
- Launch is slower, allowing delivery, complaints, bounces, replies and opt-outs
  to be reviewed before volume increases.
- Campaigns above 100 recipients require repeated daily admin actions.
- Existing scheduled broadcasts return to draft for review.
- These controls support compliance operations but do not replace legal advice
  or the operator's obligation to retain evidence and use relevant content.

## Alternatives rejected

- Immediate full-audience queueing: too easy to make a large, irreversible error.
- Throttling only in the mail worker: does not create review gates and mixes
  transactional email with campaign policy.
- Treating public or active-provider email as inferred consent: the address alone
  does not demonstrate that the particular message is relevant or expected.
