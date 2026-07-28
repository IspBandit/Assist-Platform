#!/usr/bin/env bash
set -Eeuo pipefail
root=/opt/assist-platform
env_file="$root/config/backup.env"
status_dir="$root/shared/storage/ops"
status_file="$status_dir/offsite-backup.status.json"
mkdir -p "$status_dir"
record_failure(){ printf '{"status":"failed","completed_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$status_file"; }
trap record_failure ERR
[[ -r "$env_file" ]] || { record_failure; echo "Missing $env_file" >&2; exit 2; }
set -a; source "$env_file"; set +a
: "${RESTIC_REPOSITORY:?}" "${AWS_ACCESS_KEY_ID:?}" "${AWS_SECRET_ACCESS_KEY:?}" "${RESTIC_PASSWORD:?}"
"$root/runtime/ops/assist-backup-now.sh"
cd "$root"
if ! restic snapshots >/dev/null 2>&1; then restic init; fi
restic backup backups/database shared/storage/private shared/uploads-public config/app.env --tag assist-production
restic forget --keep-daily 14 --keep-weekly 8 --keep-monthly 12 --prune
restic check --read-data-subset=5%
printf '{"status":"success","completed_at":"%s"}\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" > "$status_file"
trap - ERR
echo "Encrypted off-site backup and repository integrity check completed."
