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

The private key must exist only in server-side private storage with restrictive
permissions. It must not enter Git, documentation, logs, screenshots or chat.
The queue chooses the sender from its immutable `brand_id`: VanAssist uses
`support@vanassist.com.au`, TowSmart uses `support@towsmart.com.au`, and
TrailerWise uses `support@trailerwise.com.au`. Microsoft 365 must have sending
from aliases enabled. Test all three identities externally before enabling the
queue worker.

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
