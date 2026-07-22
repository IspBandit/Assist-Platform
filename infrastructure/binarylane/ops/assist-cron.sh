#!/usr/bin/env bash
set -euo pipefail

task="${1:?task key required}"
cd /opt/assist-platform
docker compose exec -T app php cron/run.php "$task"
