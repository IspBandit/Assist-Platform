# Shared public edge release safety (OPS-001)

## Incident and boundary

On 7 September 2026 a shared-runtime refresh removed a separately hosted
product's inline vhost. Assist checks passed while that product returned 525.
The product backend remained healthy. Restoring its product-owned vhost under
`/opt/shared-public-edge/sites` and reloading Caddy restored public HTTPS.

Never append separate-product routes to Assist's replaceable runtime Caddyfile.
Keep them in the mounted, persistent `sites/*.caddy` directory. This is generic
host infrastructure and does not expand the three-brand Assist application.

## Required host provisioning

Root must install the exact reviewed
`infrastructure/binarylane/ops/check-shared-public-edge.sh` as executable
`/opt/shared-public-edge/bin/check-shared-public-edge`. The release command
checks its bytes against the incoming release, like the existing root-owned
release-command hash gate. Keep both commands tied to the approved commit.

Create root-owned `/opt/shared-public-edge/required-sites.txt`, mode 0644. Each
non-comment line is `https://host/path|expected body text`. Register every live
apex and www hostname and suitable health endpoints; choose product-specific
body text from verified public responses. Do not store secrets in this file.
The registry and all product-owned vhosts must be backed up with host config.

An absent/empty registry fails closed. Confirm the independent product's web
service is attached to `shared-public-edge` under its stable DNS alias. Never
point a public route at a changing container IP or disable origin TLS checks.

## Release protocol

All release tools that can reload/recreate Caddy acquire an exclusive nonblocking
flock on `/opt/shared-public-edge/release.lock`, for the whole activation and
verification window. Another release must stop rather than overlap.

1. Create a private temporary snapshot directory and run `guard snapshot DIR`.
2. Run `guard candidate CADDYFILE` before activation. The real Caddy adapter
   must retain every hostname in the registry, including external products.
3. Activate only the approved product release; never overwrite external vhosts.
4. Run `guard verify DIR` before accepting the release. This checks registry
   and vhost checksums plus HTTPS 200 and product identity for every entry.
5. On failure restore the previous runtime/application and recheck all sites.
   Keep backup evidence if rollback verification fails. Do not certify success.

The guard uses bash, curl, GNU coreutils, Python 3 and the existing Caddy container.
Its CI fixture tests reject missing hosts, vhost changes, registry changes,
empty registries and wrong-product responses. Production remains subject to
real network, certificate and end-to-end checks; tests alone are not deployment.
