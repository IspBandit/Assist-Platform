# Backup and restore

BinaryLane snapshots are useful infrastructure protection but are not the only
required application backup.

## Required backup set

- Encrypted MariaDB logical backup with SHA-256 manifest.
- Public media and private provider/request documents, preserving permissions.
- Root-owned environment/configuration secrets in a separate protected store.
- Release SHA and migration inventory.
- At least one independent off-server copy with documented retention.

Never commit backups or copy production personal data into an unprotected local
development environment.

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

