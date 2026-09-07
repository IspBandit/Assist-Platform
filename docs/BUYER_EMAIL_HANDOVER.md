# Buyer email handover

Assist Platform does not host email inboxes. The buyer supplies a mailbox
provider and buyer-owned credentials, then chooses either Microsoft 365 Graph or
standard authenticated SMTP for outbound transactional email.

## Choose the transport

### Microsoft 365 Graph

Use this when the buyer operates the included brand mailboxes in Microsoft 365.
Set `MAIL_DRIVER=graph` and configure the tenant ID, application ID, certificate
and private-key paths, sending mailbox and optional per-brand mailboxes described
in `.env.example`. The buyer must create or recreate the Entra application,
Exchange application RBAC scope and certificate under its own tenant. Private-key
material stays outside Git with restrictive server permissions.

### Domain email or another SMTP provider

Use this for a registrar-hosted mailbox, Google Workspace, Microsoft 365 SMTP,
Amazon SES, Mailgun, SendGrid or another provider offering authenticated SMTP.
Set:

```dotenv
MAIL_DRIVER=smtp
MAIL_HOST=smtp.provider.example
MAIL_PORT=587
MAIL_USERNAME=provider-login-or-mailbox@example.com
MAIL_PASSWORD=buyer-owned-secret
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=operations@example.com
MAIL_FROM_NAME=Assist Platform
TOWSMART_MAIL_FROM_ADDRESS=support@towsmart.example
TRAILERWISE_MAIL_FROM_ADDRESS=support@trailerwise.example
```

The SMTP username and visible From address may differ when the provider permits
it. The buyer must verify every From address or domain with the selected provider.
Database-held SMTP passwords are encrypted under `APP_KEY`; keep that key in the
buyer-controlled secret store and follow `APP_KEY_ROTATION.md` when rotating it.

## Domain and mailbox work outside the application

For each transferred or replacement sending domain, the buyer must:

1. create the required mailboxes or verified sender identities;
2. publish the provider's MX records when inbound mail is required;
3. publish one authoritative SPF record that includes the chosen sender;
4. publish and enable the provider's DKIM records;
5. publish a DMARC policy and reporting addresses appropriate to the buyer;
6. confirm Reply-To inbox ownership and forwarding; and
7. retain screenshots or exports of the final DNS and provider configuration.

Do not copy the seller's passwords, certificate private keys or provider tokens
into the repository or transaction documents.

## Verification before enabling customer mail

1. Keep scheduled/bulk outbound work disabled while changing transport.
2. Update the environment and restart the application/worker using the normal
   release procedure.
3. Open **Admin → Email templates** and send a delivery test for VanAssist,
   TowSmart and TrailerWise to buyer-controlled external inboxes.
4. Confirm the visible From name/address, Reply-To, SPF, DKIM and DMARC results in
   the received message headers.
5. Send a controlled failure to a buyer-owned invalid address and confirm the
   queue's retry/failure handling without contacting customers.
6. Record the provider, account owner, tested release SHA, test time and result in
   the restricted handover record.
7. Enable normal queue processing only after all three active brands pass.

## Rollback

If delivery fails, disable outbound queue processing, restore the previous
buyer-controlled transport configuration, restart the worker and repeat the
delivery test. DNS changes may take time to propagate; do not send customer mail
until authentication results are stable.

## Handover acceptance

Email handover is complete when the buyer can rotate the credentials without the
seller, send and receive tests for all three active brands, see successful domain
authentication, observe a controlled failure and identify the provider billing
and renewal owner.
