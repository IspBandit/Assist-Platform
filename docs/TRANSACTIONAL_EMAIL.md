# Transactional email

The queue supports SMTP and Microsoft 365 Graph transports. Production launch
uses `MAIL_DRIVER=graph` after the Microsoft Entra application, Exchange
Application RBAC mailbox scope and certificate credential have been configured.

Required production settings:

- `MICROSOFT_GRAPH_TENANT_ID`
- `MICROSOFT_GRAPH_CLIENT_ID`
- `MICROSOFT_GRAPH_CERTIFICATE_PATH`
- `MICROSOFT_GRAPH_PRIVATE_KEY_PATH`
- `MICROSOFT_GRAPH_PRIVATE_KEY_PASSWORD` when the key is encrypted
- `MICROSOFT_GRAPH_SENDING_MAILBOX=operations@vanassist.com.au`

Optional dedicated mailbox settings become active only after the corresponding
Exchange shared mailbox and application scope have passed external acceptance:

- `MICROSOFT_GRAPH_VANASSIST_MAILBOX=support@vanassist.com.au`
- `MICROSOFT_GRAPH_TOWSMART_MAILBOX=support@towsmart.com.au`
- `MICROSOFT_GRAPH_TRAILERWISE_MAILBOX=support@trailerwise.com.au`
- `MICROSOFT_GRAPH_LOCALTORQUE_MAILBOX=` remains empty while the brand is private

When a brand setting is blank, the worker continues using
`MICROSOFT_GRAPH_SENDING_MAILBOX`; this prevents a partially provisioned mailbox
from interrupting transactional delivery. Activate and externally test one
brand at a time. A dedicated mailbox supplies the visible Exchange display name
and address, while the queue's immutable `brand_id` selects it server-side.

On 27 July 2026 the owner supplied Microsoft 365 Admin evidence that the three
public support addresses exist as shared mailboxes. Those addresses are now the
safe configuration defaults. A dedicated mailbox request rejected explicitly
with Graph HTTP 403 or 404 is retried once through the proven operations mailbox;
timeouts and server errors are not retried through another sender because the
first request may have been accepted. Migration 049 supplies one controlled
acceptance message per public brand.

The private key must exist only in server-side private storage with restrictive
permissions. It must not enter Git, documentation, logs, screenshots or chat.
The queue chooses the sender address from its immutable `brand_id`: VanAssist uses
`support@vanassist.com.au`, TowSmart uses `support@towsmart.com.au`, and
TrailerWise uses `support@trailerwise.com.au`. Until each address is provisioned
as a real Exchange Online shared mailbox, the transport sends through the
configured `MICROSOFT_GRAPH_SENDING_MAILBOX` and keeps the relevant brand
support address as Reply-To. This preserves delivery without treating an alias
as a Graph mailbox. Test all three identities externally before enabling bulk
queue work.

## Production acceptance — 24 July 2026

Microsoft Graph certificate authentication and the production queue are active.
The certificate fingerprint ends in `5C:41:48:B5` and expires on 23 July 2028.
The Platform Control Centre reports certificate presence, SHA-256 fingerprint,
expiry and remaining days without exposing paths or private-key material.

Acceptance evidence:

- Graph accepted direct delivery requests containing the three configured
  support addresses through `operations@vanassist.com.au`.
- The production queue processed three isolated acceptance messages: three
  sent, zero failed, one attempt each.
- All three queued messages arrived and receipt was confirmed by the owner, but
  the visible sender for every message was `operations@vanassist.com.au`.
- MX, SPF, DMARC and both Microsoft DKIM selector records resolve for all three
  public domains.

This accepts Microsoft Graph authentication, transport and queued delivery, but
not brand-attributed sending. App-only Graph sending uses the mailbox targeted
by `/users/{mailbox}/sendMail`; message-body `from` values did not produce the
required visible aliases. Configure dedicated/shared brand mailboxes and target
the correct mailbox per immutable `brand_id` before full acceptance. External
bounce ingestion, suppression and consent-aware bulk campaign acceptance also
remain separate COM-001/COM-002 work.

## Production incident recovery — 26 July 2026

The brand-mailbox endpoint change caused transactional failures because the
support addresses had not been proven as Exchange mailbox objects. The recovery
routes delivery through the already accepted operations mailbox, preserves
brand Reply-To addresses, teaches the admin queue controls to recognise Graph
without an SMTP host, and re-queues only rows whose recorded failure came from
Microsoft Graph. Visible From attribution remains a named COM-001 limitation;
transactional delivery takes priority until dedicated shared mailboxes pass
external acceptance.

The follow-up recovery makes that fallback explicit in the Graph payload: the
real `operations@vanassist.com.au` mailbox is both the endpoint and message From
identity, while the immutable brand sender remains Reply-To. Migration 047
retries only Graph-rejected rows and queues one idempotent delivery probe to the
owner-controlled operations mailbox. The probe is acceptance evidence, not a
customer campaign.

Migration 048 closes the remaining evidence gap by queuing three separate,
idempotent probes through the normal worker: one row each for VanAssist,
TowSmart and TrailerWise. Every probe targets the owner-controlled operations
mailbox, retains its own immutable `brand_id`, and therefore exercises the same
brand sender-name and Reply-To resolution used by transactional customer mail.
