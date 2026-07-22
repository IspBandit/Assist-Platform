# Production release record — 22 July 2026

This is the sanitised operator record for the Assist Platform production update.
Passwords, private keys, application secrets and database credentials are
intentionally excluded.

## Release scope

- Deployed the merged Assist Platform release and applied all pending migrations.
- Ran the Australia-wide provider classifier against the production dataset.
- Set brand-specific public and sender addresses for VanAssist, TowSmart and
  TrailerWise while leaving Microsoft Graph delivery disabled.
- Deployed the Alpine font-discovery correction from GitHub pull request 7.
- Generated 90 private Social Studio draft assets.
- Created and independently restored a fresh production database dump.

## Sanitised command record

The following commands or equivalent controlled wrappers were run from
`/opt/assist-platform`. Values containing credentials were supplied through
protected environment files and are omitted here.

```sh
docker compose build app
docker compose run --rm --no-deps -v RELEASE:/var/www/html:ro app php scripts/migrate.php
docker compose run --rm --no-deps -v RELEASE:/var/www/html:ro app php scripts/classify-brand-providers.php
ln -sfn releases/RELEASE current.next
mv -Tf current.next current
docker compose up -d --force-recreate app caddy
docker compose exec -T app php scripts/migrate.php
docker compose exec -T app php scripts/classify-brand-providers.php
runtime/ops/assist-backup-now.sh
gzip -t backups/database/BACKUP.sql.gz
sha256sum -c backups/database/BACKUP.sql.gz.sha256
docker run --rm mariadb:11.4
```

Production-only configuration was updated through a root-owned temporary script,
with a timestamped copy of `config/app.env` created before the change. Temporary
scripts were removed from the server after use.

## Verification evidence

- Current release symlink: `/opt/assist-platform/releases/2f7ef9f`.
- Application container: healthy.
- Three brand health endpoints: HTTP 200.
- Three brand sitemaps and robots files: HTTP 200 with correct content types.
- Contact pages: correct brand-specific support addresses.
- Database migrations: up to date.
- Social assets: 30 VanAssist, 30 TowSmart and 30 TrailerWise.
- Latest restore rehearsal: 135 tables and 1,399 provider rows restored.
- GitHub CI: passed for pull requests 6 and 7.

## Deliberately not activated

- Microsoft Graph transactional delivery, pending owner completion of Entra and
  certificate/mailbox configuration.
- Encrypted off-server restic upload, pending an owner-controlled S3-compatible
  bucket and credentials.
- Search indexing, pending final owner acceptance and launch approval.
