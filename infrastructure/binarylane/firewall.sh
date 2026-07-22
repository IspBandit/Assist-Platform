#!/usr/bin/env bash
set -Eeuo pipefail

ufw default deny incoming
ufw default allow outgoing
ufw limit 22/tcp comment 'Rate-limited SSH'
ufw allow 80/tcp comment 'HTTP'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 443/udp comment 'HTTP/3'
ufw --force enable
ufw status verbose
