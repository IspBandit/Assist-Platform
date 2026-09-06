# Three-brand transactional mail delivery evidence

Workstream: COM-001 / COM-005.
Scope: VanAssist, TowSmart and TrailerWise.

This record contains only redacted acceptance metadata. The original recipient
messages remain in the seller-controlled Microsoft 365 mailbox and should be
retained in the restricted transaction evidence set.

## Recipient-side delivery proof

Controlled Microsoft 365 shared-mailbox delivery tests were received in the
seller-controlled `operations@vanassist.com.au` mailbox on 28 July 2026 from all
three configured public support identities:

| Brand | Sender identity | Sent/received evidence |
| --- | --- | --- |
| VanAssist | `support@vanassist.com.au` / VanAssist Support | Controlled delivery proof received at 05:33:20 UTC |
| TowSmart | `support@towsmart.com.au` / TowSmart Support | Controlled delivery proof received at 05:33:20 UTC |
| TrailerWise | `support@trailerwise.com.au` / TrailerWise Support | Controlled delivery proof received at 05:33:22 UTC |

Each controlled message identified the expected brand sender mailbox and the
seller-controlled recipient. Additional July acceptance messages exercised the
Microsoft Graph queue path and sender identities.

A separate VanAssist delivery test was also received with text explicitly stating
that receipt proves the website email transport is working.

## Current production-side evidence

The 6 September production review recorded Microsoft Graph sender health as
healthy and all three application-path brand mailbox probes as sent successfully.
This is newer application-side evidence; the July messages above are the retained
recipient-side proof.

## Queue/failure implementation evidence

`app/Services/Mailer.php` implements:

- configured Graph/SMTP transport validation;
- leased queue processing to avoid concurrent duplicate handling;
- durable sent logging;
- retry attempts with exponential backoff;
- terminal failed state after maximum attempts;
- redacted error logging;
- suppression handling before delivery; and
- notification/outreach status updates for sent, suppressed and failed outcomes.

This implementation plus real recipient-side delivery is strong evidence that the
core transactional transport works. It does **not** prove a current controlled
post-delivery NDR/bounce callback path from an external recipient domain.

## Remaining COM-001 evidence

To fully close the original sender-reputation/transport issue without overstating
capability:

1. retain a controlled failure/NDR acceptance showing the operator can see a
   deliberately failed destination or equivalent transport failure; and
2. record the Microsoft tenant/subscription ownership, cost allocation, sender
   DNS and buyer transfer/recreate procedure in the restricted transaction data
   room.

The account/cost/transfer item is primarily COM-005 transfer evidence. Core
three-brand sender identity and mailbox receipt are already proven.
