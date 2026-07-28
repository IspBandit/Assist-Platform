# Staged provider email operations

The production admin uses the configured email transport (Microsoft 365 through
Microsoft Graph in production, or SMTP in environments explicitly configured for
SMTP). The Email templates screen's **Delivery test** processes the exact test
message it creates and reports that message's transport result, so an older queue
item cannot produce a false success or failure.

Migration `072_prepare_vanassist_provider_campaign.sql` creates the initial
VanAssist provider-review campaign as a draft only. It does not add recipients or
send email. An operator must still complete the internal test, 25-recipient pilot,
50-per-day and 100-per-day stages. Audience selection remains fail-closed unless a
provider has recorded consent source and evidence.

**Backlog:** CORE-005
**Status:** implemented; no provider launch campaign has been authorised or sent

## Purpose

Assist Platform provider outreach is deliberately review-first. Public directory
data and published business email addresses are not automatically eligible for
marketing. The operator remains responsible for the audience, consent evidence,
message relevance and legal review.

The operational reference is the Australian Communications and Media Authority's
[Avoid sending spam](https://www.acma.gov.au/avoid-sending-spam) guidance and the
[Spam Act 2003](https://www.legislation.gov.au/C2004A01214/latest/text). This
document is an engineering control record, not legal advice.

## Eligibility gate

A provider is included only when all of these are true:

- active provider and active listing for the selected brand;
- a valid email address;
- `marketing_opt_in=1`;
- a recorded consent date;
- an approved basis: written, phone, web or specifically documented
  role-relevant inferred consent;
- non-empty evidence explaining who, when, how and where proof is retained;
- no marketing opt-out, complaint, hard bounce or all-mail suppression.

Imported or scraped addresses are not opted in. Do not send an email merely to
ask for consent. Never invent consent evidence.

## Delivery sequence

1. Select one brand and the narrowest relevant audience.
2. Start from the matching service-family copy, then verify every claim.
3. Preview the consent-eligible count and save the campaign.
4. Queue an internal test. Check From name/address, subject, links, mobile layout,
   legal sender/contact, reply handling and unsubscribe.
5. Queue the maximum 25-recipient pilot.
6. Pause and review deliveries, bounces, complaints, opt-outs and replies.
7. Explicitly approve 50/day. The application enforces a rolling 24-hour cap.
8. Only after another clean review, explicitly approve 100/day.
9. Repeat daily batches manually. The application never auto-promotes a stage.

Cancellation stops pending entries linked to that campaign. A message already
being processed may complete, so complaints or legal issues also require pausing
the email worker and applying suppression immediately.

## Copy policy

Use tailored service-family messages for workshop/mobile repair, electrical and
diagnostics, tyres/brakes/suspension, caravan/RV, trailer/towing, fuel/charging,
inspection/compliance and stays. Dry humour is permitted when it is restrained,
relevant and does not trivialise safety or misrepresent the provider. Do not claim
that the provider is verified, partnered, receiving leads or offering services
unless the record supports the statement.

Every commercial email must retain accurate sender identity and contact details,
the recorded reason for receipt and the signed one-click unsubscribe link.
Suppression is immediate in the application; operational requests must be handled
within the statutory period.

## Quality Gate record

- **Architecture — pass:** shared service, brand-attributed queues, forward
  migration, audited stages and ADR 0013.
- **UX — pass:** no bulk-send control; a readable staged checklist, explicit
  recipient count and service-family starters in the existing admin design.
- **Engineering — pass:** fail-closed consent query, deduplication, suppression,
  rolling hard caps, linked cancellation, queue leases and automated tests.
- **Business — conditional pass:** supports cautious provider acquisition while
  protecting sender reputation. The first live pilot remains a separate business
  decision requiring verified consent records and monitored results.

The same release also makes the authoritative provider-pack activation
fingerprint part of the normal migration command. This closes a deployment gap
where an older root-owned release wrapper could apply schema changes without
running the newer pack seed command. Current fingerprints remain a no-op.

## Rollback

Application rollback is safe before a batch is queued. Migration 067 is additive
and should remain in place. If a campaign causes concern, cancel it, stop the email
worker, suppress affected addresses as required, investigate, and do not resume by
bypassing the stage controls.
