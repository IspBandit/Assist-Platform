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
