#!/usr/bin/env bash
set -Eeuo pipefail
root=/opt/assist-platform
env_file="$root/config/backup.env"
status_dir="$root/shared/storage/ops"
status_file="$status_dir/offsite-backup.status.json"
metadata_dir="$root/backups/metadata"
mkdir -p "$status_dir"
record_failure(){ printf '{"status":"failed","completed_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$status_file"; }
trap record_failure ERR
[[ -r "$env_file" ]] || { record_failure; echo "Missing $env_file" >&2; exit 2; }
set -a; source "$env_file"; set +a
: "${RESTIC_REPOSITORY:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}" "${RESTIC_PASSWORD:?}"
"$root/runtime/ops/assist-backup-now.sh"
cd "$root"
release_path="$(readlink -f current)"
release="${release_path##*/}"
[[ "$release_path" == "$root/releases/$release" && "$release" =~ ^[a-f0-9]{40}$ && -d "$release_path" ]] || {
  echo 'Current application is not an immutable full-SHA release.' >&2
  exit 3
}
[[ -s config/app.env && -s config/infra.env ]] || {
  echo 'Protected application or infrastructure configuration is missing.' >&2
  exit 4
}
mkdir -p "$metadata_dir"
printf '{"schema_version":1,"created_at":"%s","release":"%s"}\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$release" > "$metadata_dir/latest.json"
chmod 0600 "$metadata_dir/latest.json"
if ! restic snapshots >/dev/null 2>&1; then restic init; fi
restic backup \
  backups/database backups/metadata \
  shared/storage/private shared/uploads-public \
  config/app.env config/infra.env "releases/$release" \
  --tag assist-production --tag "release-$release"
restic forget --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
restic check --read-data-subset=5%
printf '{"status":"success","completed_at":"%s","release":"%s"}\n' \
  "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$release" > "$status_file"
trap - ERR
echo "Encrypted off-site application, database, media and configuration backup completed for $release."
