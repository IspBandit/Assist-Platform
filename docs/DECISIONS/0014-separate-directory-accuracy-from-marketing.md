# ADR 0014: Separate factual directory accuracy notices from marketing

- **Status:** accepted
- **Date:** 2026-07-29
- **Affected areas:** provider data, email campaigns, suppression, audit and admin UX

## Decision

The platform implements two distinct provider campaign types and does not allow
an administrator to blur the boundary between them.

`provider_marketing` is promotional email. A recipient is queueable only when a
dated consent basis and supporting evidence are stored. Public contact details,
an active listing or an ABN record are not treated as marketing consent.

`directory_accuracy` is a factual record notice. Its subject and message are
owned by server code and cannot be edited into promotional copy. Each recipient
must be an unclaimed listing with a recorded public source URL. The notice shows
the information currently held and asks the business to reply CONFIRM, CORRECT
or REMOVE. It contains no offer, pricing, benefit claim, claim-listing link or
commercial destination.

Both types use an internal test, a maximum 25-recipient pilot, reviewed daily
caps, recipient-level evidence, campaign removal and type-specific signed
preference links. Hard bounces, complaints and platform-wide suppression always
win.

## Reason

An editable “factual” email could acquire one promotional sentence or link and
become commercial in substance. The fail-closed template prevents that drift
and gives the operator an auditable basis for each individual delivery. The
design follows the factual-message distinction in the Australian Spam Act and
ACMA guidance, but it is an engineering control rather than legal advice.

- [Spam Act 2003, Schedule 1](https://www.legislation.gov.au/C2004A01214/latest/text)
- [ACMA: Avoid sending spam](https://www.acma.gov.au/avoid-sending-spam)

## Rollback

Disable new directory-accuracy campaigns and cancel their pending queue rows.
Retain recipient evidence and suppressions. Marketing remains independently
consent-gated.
