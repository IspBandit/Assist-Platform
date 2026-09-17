#!/usr/bin/env bash
# Root-owned production helper for authorised CPAQ 2026 import.
# Install once as root (see docs/CPAQ_2026_IMPORT.md), then invoke via:
#   sudo -n /usr/local/sbin/assist-platform-cpaq-import [--batch-size=25]
set -euo pipefail

batch_size=25
for arg in "$@"; do
  case "$arg" in
    --batch-size=*)
      batch_size="${arg#--batch-size=}"
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      exit 1
      ;;
  esac
done

if [[ ! "$batch_size" =~ ^[1-9][0-9]*$ ]]; then
  echo "Invalid --batch-size: $batch_size" >&2
  exit 1
fi

cd /opt/assist-platform

batch_helper="/opt/assist-platform/current/scripts/cpaq-2026-batch-apply.php"
single_helper="/opt/assist-platform/current/scripts/import-cpaq-2026.php"

if [[ -f "$batch_helper" ]]; then
  docker compose cp "$batch_helper" app:/tmp/cpaq-2026-batch-apply.php
  docker compose exec -T app php /tmp/cpaq-2026-batch-apply.php --batch-size="$batch_size"
elif [[ -f "$single_helper" ]]; then
  echo "Batch helper not in current release; falling back to scripts/import-cpaq-2026.php --apply" >&2
  docker compose exec -T app php scripts/import-cpaq-2026.php --apply
else
  echo "CPAQ import scripts missing from /opt/assist-platform/current (is the importer release live?)" >&2
  exit 1
fi
