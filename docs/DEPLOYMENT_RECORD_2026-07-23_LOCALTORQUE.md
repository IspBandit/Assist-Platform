# Deployment record: LocalTorque foundation

Date: 23 July 2026 (Australia/Brisbane)  
Reviewed PR: `#26`  
Merged and deployed commit: `434ede3`  
Previous release: `d04bf41`  
Artefact SHA-256: `3008d2057001760dc8495b30011bafb2bf4a8be2ff950048dc1f540aa64efbb0`

## Commands and actions performed

Sensitive authentication arguments are intentionally redacted. No password is stored in this record.

```text
git status --short
git branch --show-current
composer validate --strict
php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress
vendor/bin/phpunit
composer audit
node --check public/assets/js/app.js
php -l <each changed PHP file>
git diff --check
git add <LocalTorque implementation and documentation files>
git commit -m "feat: add LocalTorque automotive directory foundation"
git push -u origin agent/localtorque-foundation
gh pr create --draft --base main --head agent/localtorque-foundation ...
gh pr checks 26 --watch --interval 10
git commit -m "test: respect configured TowSmart domain"
git push
gh pr ready 26
gh pr merge 26 --merge --delete-branch
git checkout main
git pull --ff-only

# Production preflight and backup over SSH
readlink -f /opt/assist-platform/current
docker compose ps --format json
curl -H "Host: vanassist.com.au" http://127.0.0.1/readyz
bash infrastructure/binarylane/ops/assist-backup-now.sh

# Exact-commit production artefact
git archive 434ede3
composer install --no-dev --classmap-authoritative --no-interaction
tar -czf 434ede3.tar.gz <release-content>
sha256sum 434ede3.tar.gz
pscp 434ede3.tar.gz 434ede3.tar.gz.sha256 <production incoming directory>

# Controlled server release
sha256sum /opt/assist-platform/incoming/434ede3.tar.gz
mkdir -p /opt/assist-platform/releases/434ede3
tar -xzf /opt/assist-platform/incoming/434ede3.tar.gz -C /opt/assist-platform/releases/434ede3
chown -R root:root /opt/assist-platform/releases/434ede3
find /opt/assist-platform/releases/434ede3 -type d -exec chmod 755 {} +
find /opt/assist-platform/releases/434ede3 -type f -exec chmod 644 {} +
sed -i "s/^APP_RELEASE=.*/APP_RELEASE=434ede3/" /opt/assist-platform/config/app.env
ln -sfn releases/434ede3 /opt/assist-platform/current
docker compose up -d --force-recreate app caddy
docker compose exec -T app php scripts/migrate.php
docker compose exec -T app php scripts/seed.php
docker compose exec -T app php scripts/classify-brand-providers.php
docker compose ps

# Verification
curl <public home, health, readiness, directory, contact, robots and sitemap routes>
curl https://vanassist.com.au/install
docker compose exec -T mariadb mariadb <verification queries>
docker compose logs --since 10m app caddy
cgi-fcgi <private LocalTorque home, directory, categories, sitemap and robots checks>
```

## Results

- GitHub clean MySQL CI passed after correcting a test to respect CI's configured `towsmart.test` domain.
- Pre-deployment database backup verified successfully.
- Migration `041_localtorque_foundation.sql` applied with no dirty migrations.
- All three public brands returned 200 on affected smoke tests and reported release `434ede3` from `/readyz`.
- `/install` returned 403.
- LocalTorque remained private and passed direct FastCGI rendering checks.
- Provider enrichment scanned 7,304 canonical providers and produced 6,760 LocalTorque listings with 26,677 unverified category assignments.
- No fatal, uncaught, exception or 5xx application/Caddy log pattern was found after release.

## Rollback

Migration 041 is additive. If an application rollback is required, point `/opt/assist-platform/current` back to `/opt/assist-platform/releases/d04bf41`, restore `APP_RELEASE=d04bf41`, recreate the app/Caddy containers and repeat all public health checks. Do not delete LocalTorque database records during an emergency application rollback.
