# Installing VanAssist on GoDaddy / cPanel hosting

This guide deploys VanAssist to a standard Linux cPanel account
(Apache + PHP 8.1+ + MySQL/MariaDB). No SSH is required for normal operation.

Throughout, replace `CPANEL_USERNAME` with your cPanel account username and
`vanassist.com.au` with your production domain.

---

## 1. Create the subdomain

1. cPanel → **Domains** (or **Subdomains**).
2. Create `vanassist.com.au`.
3. Set the **Document Root** to:
   ```
   /home/CPANEL_USERNAME/vanassist/public
   ```
   The application files live in `/home/CPANEL_USERNAME/vanassist`, and only the
   `public` sub-folder is web accessible.

## 2. Create the MySQL database

1. cPanel → **MySQL Databases**.
2. Create a database, e.g. `cpaneluser_vanassist`.
3. Create a database user with a strong password.
4. **Add the user to the database** and grant **ALL PRIVILEGES**.
5. Note the database name, user and password (used in the installer).

## 3. Upload the files

Using **File Manager** or **FTP**, upload the project so that the structure is:

```
/home/CPANEL_USERNAME/vanassist/        ← project root (app/, config/, public/, ...)
/home/CPANEL_USERNAME/vanassist/public  ← document root from step 1
```

Do **not** upload a `.env` file with real secrets; the installer creates it.

## 4. Set PHP version & extensions

1. cPanel → **Select PHP Version** → choose **8.1 or newer**.
2. Enable extensions: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, and
   **`gd`** (for image processing in later phases).

## 5. Set folder permissions

Ensure these are writable by PHP (usually `0755` dirs / `0644` files is fine on
cPanel; if the installer reports "not writable", set the folder to `0775`):

```
storage/ and all subfolders (cache, logs, sessions, private/*, backups)
public/uploads-public
the project root (so the installer can write .env once)
```

## 6. (Optional) Composer dependencies

Email sending uses PHPMailer. If your host provides Composer (Terminal or SSH):

```bash
cd /home/CPANEL_USERNAME/vanassist
composer install --no-dev --optimize-autoloader
```

If you cannot run Composer, the site still runs; outbound email stays queued
until PHPMailer is available. You can upload a `vendor/` folder built elsewhere.

## 7. Run the installer

1. Visit `https://vanassist.com.au/` — you'll be redirected to `/install`.
2. **Step 1** confirms PHP version, extensions and folder permissions.
3. **Step 2** collects database, site, email (SMTP) and super-administrator
   details. On submit it tests the DB connection, writes `.env`, runs
   migrations, seeds Queensland data + service categories, optionally inserts
   demo records, and creates your super administrator.
4. **Step 3** confirms success and locks the installer
   (`storage/installed.lock`).

> The installer cannot run again while the lock file exists.

## 8. Enable SSL & force HTTPS

1. cPanel → **SSL/TLS Status** → run **AutoSSL** for the subdomain.
2. The bundled `public/.htaccess` already forces HTTPS. If you upload before SSL
   is active, comment out the HTTPS redirect block until the certificate issues,
   then restore it.

## 9. Configure cron jobs

cPanel → **Cron Jobs**. Use the full path to the PHP CLI binary (cPanel shows it,
often `/usr/local/bin/php` or an `ea-php81` path). Each task self-locks.

| Schedule | Command |
|----------|---------|
| Every 5 min | `php /home/CPANEL_USERNAME/vanassist/cron/run.php process_email_queue` |
| Every 15 min | `php /home/CPANEL_USERNAME/vanassist/cron/run.php process_notifications` |
| Hourly | `php /home/CPANEL_USERNAME/vanassist/cron/run.php update_match_suggestions` |
| Hourly | `php /home/CPANEL_USERNAME/vanassist/cron/run.php expire_sessions` |
| Hourly | `php /home/CPANEL_USERNAME/vanassist/cron/run.php update_run_capacity` |
| Daily 02:00 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php send_run_reminders` |
| Daily 02:10 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php provider_followups` |
| Daily 02:20 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php document_expiry` |
| Daily 02:30 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php expire_requests` |
| Daily 02:40 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php update_town_demand` |
| Daily 03:00 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php database_backup` |
| Daily 03:30 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php clean_temp` |
| Weekly Mon 04:20 | `php /home/CPANEL_USERNAME/vanassist/cron/run.php clean_logs` |
| Every 15 min | `php /home/CPANEL_USERNAME/vanassist/cron/run.php import_osm` |
| Every 15 min | `php /home/CPANEL_USERNAME/vanassist/cron/run.php import_locality` |

`import_osm` / `import_locality` chew through deployed seed JSON in short batches and
**no-op when the current file was already fully imported**. After you deploy a new
`businesses_osm.json`, cron finishes the DB load without browser clicks. Status
appears on the admin dashboard (scheduled tasks).

Cron run status appears on the admin dashboard.

## 10. Configure transactional email

1. Create a mailbox or use your SMTP relay credentials.
2. In the installer (or later via `.env`), set `MAIL_HOST`, `MAIL_PORT`,
   `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`,
   `MAIL_FROM_NAME`.
3. Confirm the `process_email_queue` cron is running.

## 11. Configure backups

The `database_backup` cron writes compressed dumps to `storage/backups` with
retention controlled by `BACKUP_RETENTION_*`. Periodically download a copy via
File Manager, and keep cPanel's own account backups enabled.

## 12. Test the install

- **Uploads** (later phases): submit a request with images.
- **Email**: trigger a password reset; confirm it sends within ~5 minutes.
- **Admin**: sign in at `/login`, confirm `/admin` loads.

## 13. Harden & launch

- Confirm `https://vanassist.com.au/.env` returns **403/404** (never the
  file contents).
- Confirm `https://vanassist.com.au/storage/logs/app.log` is **not**
  accessible.
- Change the super-administrator password if anyone else saw the install.
- Set the **launch mode** in admin settings (private → provider-onboarding →
  local-pilot → public).
- Remove demo data from admin when ready for real content.

## 13a. Applying updates after a deploy (no SSH needed)

When you deploy code that includes new database migrations (e.g. unclaimed
listings, possible-match support), apply them from the browser:

1. Sign in as the super administrator.
2. **Admin → System → Maintenance** (`/admin/maintenance`).
3. Click **Apply database updates** (runs any pending migrations).
4. If you also need to (re)load the researched business listings, click
   **Run national import / backfill matches**.

Both actions are idempotent and audit-logged. The equivalent CLI commands (if you
do have SSH/Terminal) are:

```bash
php scripts/migrate.php
php scripts/seed.php --providers   # towns + national + OSM + locality + feature cities
# or individually:
php scripts/seed.php --towns
php scripts/seed.php --national
php scripts/seed.php --osm
php scripts/seed.php --locality
```

Without SSH, use **Admin → Maintenance → Import all provider data (auto)** — the
browser continues batches until finished. With the `import_osm` cron enabled,
deploying a new OSM seed is enough; cron loads it into the database.

## 14. Redeploying updates (automated FTP)

After the first install, code updates are pushed with a one-command script
(`scripts/deploy.ps1`, Windows PowerShell + WinSCP). It uploads only files
tracked by git and **never deletes** remote files, so your server-side `.env`,
`storage/installed.lock`, uploads, and database are untouched.

One-time setup:

1. Install [WinSCP](https://winscp.net) (the script auto-detects `WinSCP.com`).
2. Copy `scripts/deploy.env.example` to `scripts/deploy.env` and fill in the FTP
   host/user/password (this file is gitignored — never commit it).

Each deploy:

```powershell
git add -A; git commit -m "your change"     # the script deploys the last commit
pwsh ./scripts/deploy.ps1                    # fast, uploads changed files
pwsh ./scripts/deploy.ps1 -Full              # force a complete re-upload
pwsh ./scripts/deploy.ps1 -DryRun            # preview without connecting
```

The subdomain serves the app through the project-root `.htaccess`, which routes
all requests into `public/`. This works whether the cPanel document root is the
project root (`…/vanassist`) or `…/vanassist/public`.

---

## Migrating later (dedicated domain / new server / VPS)

1. Put the site in maintenance mode (admin settings).
2. Back up the database (`database_backup` cron or phpMyAdmin export) and the
   `storage/private` + `public/uploads-public` folders.
3. On the new host, repeat steps 1–7, but instead of seeding fresh, **import the
   database dump** and copy the uploads/private folders across.
4. Update `APP_URL` in `.env` to the new domain. No code changes are required to
   change domain or move between cPanel servers or a VPS.
