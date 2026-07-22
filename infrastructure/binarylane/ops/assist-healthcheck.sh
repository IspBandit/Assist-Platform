#!/usr/bin/env bash
set -euo pipefail

cd /opt/assist-platform
timestamp="$(date --iso-8601=seconds)"
failed=0
for service in app mariadb caddy; do
  container_id="$(docker compose ps -q "$service")"
  if [[ -z "$container_id" ]]; then
    logger -t assist-health "${timestamp} ${service} container is missing"
    failed=1
    continue
  fi
  state="$(docker inspect --format '{{.State.Status}}' "$container_id")"
  health="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$container_id")"
  if [[ "$state" != "running" || "$health" == "unhealthy" ]]; then
    logger -t assist-health "${timestamp} ${service} state=${state} health=${health}; restarting"
    docker compose restart "$service"
    failed=1
  fi
done
if (( failed == 0 )); then
  logger -t assist-health "${timestamp} all services healthy"
fi
exit "$failed"
