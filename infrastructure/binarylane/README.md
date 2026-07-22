# BinaryLane production runtime

This directory contains the reproducible Ubuntu 24.04 production runtime for
the three-domain Assist Platform.

## Topology

- Caddy terminates HTTPS for VanAssist, TowSmart, and TrailerWise.
- One PHP-FPM application resolves the brand from the trusted request host.
- MariaDB is reachable only on the internal Docker network.
- Runtime storage and public uploads survive immutable release switches.
- Cron locks live in shared writable storage, never inside immutable releases.
- Cloudflare proxies public DNS to the VPS.

## Safe deployment

1. Review the files and create a BinaryLane snapshot.
2. Run `bootstrap.sh` as root.
3. Create `/opt/assist-platform/config/app.env` and `infra.env` with mode `0600`.
4. Upload a checksum-verified immutable release and point `current` at it.
5. Run `docker compose config -q` before starting containers.
6. Apply migrations once, seed reference data, and create the administrator locally.
7. Install monitoring and scheduled jobs from `ops/`.
8. Verify `/healthz`, `/readyz`, brand pages, and installer denial for every domain.

The public installer is deliberately denied by Caddy. Initial production setup
must be completed through a controlled server-side operation.
