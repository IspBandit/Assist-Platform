#!/usr/bin/env bash
# Root-owned production helper for ADR 0042 Places rescue bootstrap (VAN-011).
# Install once as root (see docs/PLACES_RESCUE_BOOTSTRAP.md), then invoke via:
#   sudo -n /usr/local/sbin/assist-platform-places-rescue --town="Charters Towers" --category=refrigeration
set -euo pipefail

town="Charters Towers"
category="refrigeration"
enable_flag=1
for arg in "$@"; do
  case "$arg" in
    --town=*)
      town="${arg#--town=}"
      ;;
    --category=*)
      category="${arg#--category=}"
      ;;
    --skip-enable-flag)
      enable_flag=0
      ;;
    *)
      echo "Unknown argument: $arg" >&2
      exit 1
      ;;
  esac
done

cd /opt/assist-platform

provision="/opt/assist-platform/current/scripts/provision-google-places.php"
bootstrap="/opt/assist-platform/current/scripts/places-rescue-bootstrap.php"

if [[ ! -f "$provision" || ! -f "$bootstrap" ]]; then
  echo "Places scripts missing from /opt/assist-platform/current (is the Charters coverage release live?)" >&2
  exit 1
fi

if [[ "$enable_flag" -eq 1 ]]; then
  docker compose exec -T app php scripts/provision-google-places.php --enable-rescue
fi

docker compose exec -T app php scripts/places-rescue-bootstrap.php --apply --town="$town" --category="$category"
