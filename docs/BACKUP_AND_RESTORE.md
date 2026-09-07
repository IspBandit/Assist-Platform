# Backup and restore

BinaryLane snapshots are useful infrastructure protection but are not the only
required application backup.

## Required backup set

- Encrypted MariaDB logical backup with SHA-256 manifest.
- Public media and private provider/request documents, preserving permissions.
- Root-owned environment/configuration secrets in a separate protected store.
- Release SHA and migration inventory.
- At least one independent off-server copy with documented retention.

The administrator **Generate backup now** action and the `database_backup`
scheduled task create a local logical archive plus a `.sha256` manifest. The
formal launch gate accepts that local evidence only when the task succeeded in
the last 36 hours, the named archive and manifest still exist, and the checksum
verifies. This local archive is a release safeguard; it does not replace the
independent encrypted copy or restore rehearsal below.

Never commit backups or copy production personal data into an unprotected local
development environment.

## Independent off-site automation

The BinaryLane runtime includes `assist-offsite-backup.sh` and
`assist-offsite-restore-drill.sh`. They use restic with a private
S3-compatible bucket. Each snapshot contains the checksum-protected database
backups, private media, public media, protected application and infrastructure
configuration, the complete matching immutable application release and a
machine-readable release identity. Retention and repository integrity are
checked automatically.

The weekly drill restores that whole set into a private temporary directory,
verifies the release identity and required application/configuration/media
paths, and imports the newest database into a disposable MariaDB 11.4 container
without touching production. Its status file records the restored release and
table count. It proves the encrypted recovery set is internally complete; final
sale acceptance still requires starting the restored application with outbound
mail/payments/scheduled actions disabled and exercising authenticated journeys.

Copy `infrastructure/binarylane/ops/backup.env.example` to
`/opt/assist-platform/config/backup.env`, set mode `0600`, and supply the
independent bucket endpoint, access key, secret and a unique restic password.
Until those credentials exist, onsite backups do not satisfy the independent
off-server launch control.

## Restore rehearsal

1. Provision an isolated non-public environment.
2. Verify archive checksums before extraction.
3. Restore database and media with least-privilege credentials.
4. Deploy the matching release and configuration.
5. Verify migration state, row-count/integrity checks, ownership, private media,
   logins, brand isolation and critical journeys.
6. Record recovery point, elapsed recovery time and discrepancies.
7. Destroy or sanitise the rehearsal environment when complete.

Perform a rehearsal after material schema/storage changes and on a regular
schedule. A backup is not considered recoverable until restoration is proven.
