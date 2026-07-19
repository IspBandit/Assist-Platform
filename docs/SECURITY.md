# Assist Platform Security

## Status

This is the current security record for Assist Platform Core. It supersedes
unqualified control claims in the legacy root `SECURITY.md`. See
`PLATFORM_AUDIT.md` and `PRODUCTION_READINESS.md` for the full audit and release
gates.

Security readiness is incomplete. No document in this repository is a
penetration-test result or legal/compliance certification.

## Protected assets

- Global user identity, credentials, sessions, and consent.
- Customer contact, location, request, and service outcome data.
- Provider business, licence, insurance, verification, and private documents.
- Brand-scoped content, analytics, billing, subscriptions, and administration.
- Email, database, storage, payment, and deployment secrets.

## Trust boundaries

- Public browser to Apache/PHP.
- Authenticated customer/provider/park/admin sessions.
- Brand and provider/organisation scope boundaries.
- Application to MySQL/MariaDB.
- Application to SMTP and payment webhooks.
- Public web root versus private storage.
- CLI/cron/deployment operators.
- Imported provider/location datasets.

## Implemented controls

- `PASSWORD_DEFAULT` hashing and password verification/rehash.
- Session ID rotation at authentication transitions.
- Secure, HttpOnly, SameSite session-cookie configuration.
- CSRF middleware on normal state-changing routes.
- Native prepared PDO statements across reviewed application queries.
- Escaping helpers for ordinary HTML output.
- Role/permission middleware and controller ownership checks.
- MIME/size validation, randomized names, private storage, and image re-encoding
  for customer request images.
- HMAC/timestamp validation for Stripe webhook signatures.
- Installer process locking and checked atomic final lock creation.
- Middleware resolution fails closed.
- SMTP fallback verifies TLS peers/hostnames and rejects header-control
  characters.
- Customer outcome writes require the provider to be matched to the request.
- Migration locking, checksums, and dirty/failed status.
- Persistent HMAC-keyed account/IP rate limits for login, password reset,
  registration, assistance requests, provider interest, and park applications.
- Pre-decode image dimension/pixel limits and public-upload script denial.
- Same-origin referrer returns and safe redirect scheme handling.
- Password resets revoke existing sessions through per-user auth versions and
  invalidate all older unused reset tokens.
- Security headers wrap the complete request lifecycle, including errors and
  health responses.
- Database-held SMTP passwords use authenticated AES-256-GCM encryption under
  an APP_KEY-derived key; a validation/migration CLI detects legacy plaintext.
- Authenticated responses are marked private/no-store. Forwarded client IP and
  HTTPS headers are accepted only from explicitly configured exact proxy IPs.

## Open release blockers

- Encrypted offsite backups and tested restores.
- Secure artefact deployment that detects deleted/changed files.
- Admin MFA/step-up authentication.
- Integration tests for IDOR, CSRF, installer, uploads, and brand isolation.

## Multi-brand authorization model

Global credentials remain in `users`. Scope is represented separately:

- platform roles in existing `user_roles`;
- brand participation in `user_brand_profiles`;
- brand roles in `user_brand_roles`;
- provider access in `provider_memberships`;
- canonical providers in `providers`;
- public brand participation in `provider_brand_listings`.

Every private brand-aware query must include brand scope unless the operation is
explicitly platform-wide. UI visibility is never authorization.

Required denial tests:

- user from brand A cannot access private resource from brand B;
- provider member cannot access another provider;
- brand admin cannot perform platform-admin actions;
- customer cannot submit an outcome/review for an unmatched provider;
- aliases and numeric IDs do not bypass ownership;
- disabled modules do not expose legacy VanAssist routes.

## Session and cross-domain identity

Host-only secure sessions are the supported default. Raw session cookies must
not be shared across unrelated brand domains. SameSite, CSRF, and cookie-domain
settings must be validated per deployment.

Future seamless cross-domain sign-in requires a central authorization endpoint,
short-lived one-time codes, PKCE/state where applicable, and exact redirect
allowlists. It is not currently implemented.

## Input and output

- Validate route/query/body/file/webhook data server-side.
- Escape output for its HTML, attribute, URL, JavaScript, email-header, or CSV
  context.
- Trusted-admin HTML is still executable content and requires strong admin
  security; future sanitization should use an allowlist parser.
- CSV exports must neutralize spreadsheet formula prefixes.
- External URLs require allowed schemes and safe redirect handling.

## Uploads

- Private documents remain outside `public/`.
- Public upload directories require server rules that disable script execution.
- Image resource limits must consider dimensions and decoded memory, not only
  compressed byte size.
- A malware-scanning adapter is required before accepting broader document
  types at scale.

## Secrets

- Never commit `.env`, deployment credentials, API keys, private keys, database
  dumps, or production logs.
- Production secrets use host secret storage/environment configuration.
- Rotate secrets after personnel, vendor, or incident changes.
- Logs and error responses must redact credentials, tokens, payment data, and
  unnecessary personal data.
- Composer lock and CI dependency audit are required for releases.

## Logging and audit

Security-relevant records should include request ID, brand, actor, action,
resource, outcome, timestamp, and safe network/user-agent context. Do not log
passwords, raw reset/session tokens, SMTP passwords, full payment data, private
documents, or sensitive request descriptions.

Current audit logs are useful but are not tamper-evident and may fail silently.
That remains an open production-readiness item.

## Incident response

1. Put affected brand(s) or the platform into fail-closed maintenance mode.
2. Preserve logs and evidence; record times and release/database versions.
3. Revoke sessions/tokens and rotate affected secrets.
4. Stop queues/webhooks/imports if they amplify the incident.
5. Identify affected brands, users, providers, records, and time range.
6. Restore only from a verified clean backup when integrity is compromised.
7. Validate authorization, migration state, queues, and user journeys before
   reopening.
8. Follow the organization’s legal/privacy notification process.

## Reporting

Security reports should go to the configured platform security contact through a
private channel. A public vulnerability-reporting address and response policy
must be established before broad launch.
