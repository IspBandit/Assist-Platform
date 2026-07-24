# Environment and integration configuration contract

This document owns the non-secret runtime configuration contract for Assist
Platform Enterprise. Real values remain in the root-owned production
configuration and GitHub protected environments; they are never committed.
`.env.example` is the complete copyable inventory.

## Startup validation

An installed application validates configuration before dispatching requests.
Invalid values fail closed with field names but never secret values. Production
additionally requires HTTPS, secure sessions, strict registered hosts, a release
identifier, debug mode off, a 32-character-or-longer application key and a real
mail transport. Live billing remains prohibited by the validator until COM-004
is separately accepted.

Supported controlled values:

| Area | Variables | Contract |
| --- | --- | --- |
| Runtime | `APP_ENV`, `APP_URL`, `APP_KEY`, `APP_RELEASE`, `LAUNCH_MODE` | Known environment and launch modes; production uses HTTPS and an immutable release SHA |
| Brand resolution | `ASSIST_DEFAULT_BRAND`, `ASSIST_BRAND`, `ASSIST_STRICT_BRAND_HOSTS`, `ASSIST_ALLOW_BRAND_QUERY`, brand domain/URL variables | Production uses strict registered hosts and never query fallback |
| Database | `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | Dedicated least-privilege account; port 1–65535; name and user required |
| Sessions/proxy | `SESSION_LIFETIME`, `SESSION_SECURE`, `TRUSTED_PROXIES` | Secure in production; proxies are exact IP addresses, not CIDRs or wildcards |
| Mail | `MAIL_DRIVER` plus SMTP or Microsoft Graph variables | `log` is non-production only; SMTP requires TLS/SSL host and sender; Graph requires tenant, client, certificate/key paths and mailbox |
| Abuse control | `TURNSTILE_ENABLED`, `TURNSTILE_SITE_KEY`, `TURNSTILE_SECRET_KEY` | Both keys required when enabled |
| Billing/tax | `ENABLE_*`, `BILLING_*`, `STRIPE_*`, `GST_*` | All charging flags remain off until COM-004; `free_listing` is the safe fallback plan |
| Uploads | `MAX_*`, `IMAGE_*`, `THUMBNAIL_WIDTH` | Limits are validated by upload services; production changes require capacity/security review |
| Backup/security | `BACKUP_RETENTION_*`, login/admin timeouts | Credentials for independent backups live only in the protected backup environment |

## Integration activation rule

Code and database tables do not prove an integration is operational. Before an
integration is enabled, record its owner, secret location, least-privilege
scope, sender/domain or endpoint verification, quota, health check, alert,
rotation date, rollback and acceptance evidence. Missing prerequisites leave
the related feature disabled.

## Secret rotation

1. Open a dated change record naming the secret, owner and affected services.
2. Create and verify backup/rollback evidence; use staging first.
3. Issue the replacement with least privilege. Do not revoke the current value yet.
4. Update the protected environment or root-owned configuration without logging the value.
5. Restart only affected services and run readiness plus integration-specific tests.
6. Revoke the old value, repeat health checks and record the new expiry/rotation date.
7. For `APP_KEY`, follow `APP_KEY_ROTATION.md`; encrypted database values must be
   re-encrypted before the old key is removed.

Never rotate a production secret by editing repository files, printing it in a
terminal transcript or copying it into a GitHub issue.

## Deployment and rollback

Configuration is validated before an immutable release becomes current. A
failed validation stops activation. Roll back configuration from the protected
previous version and restore the preceding release symlink; do not weaken the
validator to make a release pass.
