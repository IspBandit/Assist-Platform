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

- Graph accepted direct sends from `support@vanassist.com.au`,
  `support@towsmart.com.au` and `support@trailerwise.com.au` through
  `operations@vanassist.com.au`.
- All three direct messages arrived with the correct brand identity.
- The production queue processed three isolated acceptance messages: three
  sent, zero failed, one attempt each.
- All three queued messages arrived in the operations mailbox with the correct
  brand sender; receipt was also confirmed by the owner.
- MX, SPF, DMARC and both Microsoft DKIM selector records resolve for all three
  public domains.

This accepts Microsoft Graph transport and queued delivery. External-recipient
bounce ingestion, suppression and consent-aware bulk campaign acceptance remain
separate COM-001/COM-002 work and must not be inferred from successful transport.
