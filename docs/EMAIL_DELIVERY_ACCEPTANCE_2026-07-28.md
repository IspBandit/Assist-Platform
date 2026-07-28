# Email delivery acceptance — 2026-07-28

## Scope

Transactional sender identity and consent-aware campaign delivery for the three
currently provisioned public mailboxes:

- VanAssist Support — `support@vanassist.com.au`
- TowSmart Support — `support@towsmart.com.au`
- TrailerWise Support — `support@trailerwise.com.au`

LocalTorque remains private and has no production sender until its domain and
mailbox prerequisites are owner-complete.

## External mailbox evidence

Three unique messages were sent through Microsoft 365 from the shared mailboxes
to the operations mailbox and then found in the recipient inbox. The received
sender identities were VanAssist Support, TowSmart Support and TrailerWise
Support at their respective domains. Each mailbox also retained its message in
Sent Items. This proves current Exchange delegation, delivery and display-name
configuration independently of the application.

## Application acceptance

Migration 057 queues one idempotent application-path probe per public brand.
After the release worker processes those messages, acceptance requires all
three subjects to arrive in the operations mailbox with the matching dedicated
sender address and display name. A message arriving from the operations
fallback proves delivery continuity but does not satisfy dedicated-sender
acceptance.

Shared templates now resolve `{{brand_name}}` at queue time. Migration 058 adds:

- a central marketing/all-mail suppression register;
- signed public marketing-unsubscribe links;
- queue-time suppression enforcement;
- explicit transactional versus marketing classification;
- brand ownership for broadcasts and brand-scoped audience queries;
- suppressed-recipient audit status;
- bounded worker batches and the existing retry/backoff/lease controls.

Hard bounces and complaints can be recorded with `scope=all`; voluntary opt-out
uses `scope=marketing`, so essential account and service messages remain
available.

Provider invitations and listing-claim outreach are classified as marketing.
They fail closed unless documented consent is recorded, include the operator
identity and contact details, and provide the same no-login unsubscribe action
in HTML and plain text. A public email address alone is not recorded as consent.
This follows current ACMA guidance: consent must be provable, the sender and
contact details must be accurate, and unsubscribe must remain functional and be
honoured within five working days:
https://www.acma.gov.au/avoid-sending-spam

## Quality gate

- **Architecture — pass:** one shared queue and suppression service; immutable
  brand attribution; forward-only migrations 057–058; no duplicate mail system.
- **UX — pass:** brand-correct copy and sender identity; a plain-language,
  signed unsubscribe action in HTML and text; invalid links fail without change.
- **Engineering — pending final CI/deploy evidence:** unit/static checks pass;
  disposable-database migration/integration checks and production probes are
  required on the release head. The release workflow also blocks completion if
  representative VanAssist user entry, provider search, assistance, location,
  authentication, TowSmart or TrailerWise journeys do not return successfully.
- **Business — conditional pass:** three provisioned brands may send after the
  production probes pass. LocalTorque and bulk promotional campaigns remain
  disabled until their external sender and explicit provider-marketing consent
  prerequisites are complete.

## Rollback

Application rollback may point the release symlink to the prior immutable
release. Migrations are additive and remain in place. Suppression records must
not be deleted during rollback because doing so could resume unwanted mail.
