# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

### Production migrate neighbour-graph memory (VAN-011)

- Avoid sorting the full national `town_neighbours` edge list during migrate (OOM at 128M on production).
- Raise CLI `memory_limit` to 512M in `scripts/migrate.php` and `scripts/rebuild-town-neighbours.php`.
- Add `scripts/seed-cms-content.php` CLI equivalent of Admin Populate Pages for post-release legal CMS publish.


### Legal PDF pack and published site policies (COM-005)

- Added administrator **Legal documents** workspace at `/admin/legal-documents`
  (`settings.manage`) with on-demand PDF downloads for Terms of Use, Privacy
  Policy, Provider Terms, DPA, OpCo/brand licence, and IP ownership assignment.
- Published multi-brand public CMS bodies (effective **18 September 2026**) via
  `database/seeds/legal_pages.php`; Maintenance “Populate Pages & Blocks” now
  merges those legal overrides. Formal solicitor review may refine wording later.
- Sources remain under `docs/legal/drafts/`; optional offline cache via
  `php scripts/generate-legal-pdfs.php`. Downloads are audited as
  `legal_document.download`.

### VanAssist national traveller-data source archive (DATA-012 / DATA-011A / VAN-001)

- Added Platform-side source vault under `data/sources/vanassist/` with machine
  registry (`registry/sources.json`), checksums, and human register
  `docs/data/VANASSIST_DATA_SOURCE_REGISTER.md`.
- Repeatable sync: `tools/vanassist_sources/sync_archive.py`.
- GREEN facility import via `GovernmentDatasetService`:
  `scripts/import-archived-green-facilities.php` and bulk Toilet Map helper
  `scripts/import-toilet-map-direct.php`.
- CSV/GeoJSON connectors support up to 50k rows with jurisdiction field
  fallbacks; bulk approve caches catalogue source keys.
- **Permission granted (2026-09-17)** for 15 industry association directories
  and regional tourism guides (BIG4, state caravan industry associations, CMCA,
  regional council guides). All sources now marked `PERMISSION_GRANTED` and
  importable pending PDF/web extraction. See
  `docs/data/VANASSIST_INDUSTRY_SOURCE_PERMISSIONS.md` and
  `docs/data/VANASSIST_DEDUPLICATION.md` for import guarantees.
- OSM remains YELLOW (special ODbL licence); National Formal Rest Areas remain
  UNKNOWN (not imported without licence clarification).

### CPAQ 2026 authorised directory import (DATA-001 / VAN-001)

- Added idempotent CPAQ 2026 import service and CLI
  (`App\Services\Cpaq2026ImportService`, `scripts/import-cpaq-2026.php`) for
  authorised Queensland parks and trade seed JSON under
  `database/seeds/cpaq-2026/`.
- Parks land in `caravan_parks` with `stay_facility_claims`; trade businesses in
  unclaimed providers with VanAssist listings and mapped services. Dry-run by
  default; `--apply` commits. See `docs/CPAQ_2026_IMPORT.md`.
- Batched apply helper (`scripts/cpaq-2026-batch-apply.php`), root-scoped host
  helper (`infrastructure/binarylane/ops/assist-cpaq-import.sh`), and GitHub
  Actions workflow `CPAQ 2026 production import` for safe production apply.

### Daily performance email delivery (DATA-004 / OPS-003)

- The daily VanAssist website performance report
  (`vanassist_daily_performance_email`, 06:15 Brisbane →
  `support@vanassist.com.au`) now also queues idempotently from the existing
  two-minute `process_email_queue` worker, so a missing host crontab line cannot
  silently strand the report after application code is current.
- Bootstrap installs `/etc/cron.d/assist-platform` and `/usr/local/sbin/assist-cron`
  on a fresh host. Existing hosts can install the same reviewed files as root;
  see `docs/OPERATIONS_RUNBOOK.md`.

### Provider discovery rescue (VAN-011 / DATA-013 / ADR 0042)

- Classic `/find` and Ask no longer stop at town-only (~20 km) for category
  searches: they expand 25 → 75 → 150 → 300 km until enough providers appear
  (explicit distance choices still win).
- When fewer than 3 category matches remain for a resolved town, Ask and `/find`
  fill from immediate neighbouring towns (`town_neighbours`) for the **same**
  categories, with an honest nearby-towns note — not an unfiltered regional pool.
- `town_neighbours` is rebuilt from measurable town coordinates (same state,
  within `geo.neighbour_max_km`, capped by `geo.neighbour_limit`) via
  `TownNeighbourGraphBuilder` on migrate and
  `php scripts/rebuild-town-neighbours.php` (`--dry-run` / `--force`). Migration
  `140` clears a stale graph fingerprint so the next release rebuilds the
  empty production graph (including Charters Towers local localities).
- Feature flag `provider_places_rescue` (default **off**) enables demand-driven
  Google Places rescue on zero/weak provider searches: labelled public-source
  results in the journey, budget-gated via Data Sources, and optional
  auto-create of **unclaimed** listings with Place ID provenance.
- Migration `138` promotes Charters Towers QLD town-centre coordinates to
  authoritative and backfills unclaimed providers that had a town link but no
  measurable point (so Ask radius search can see Dealz on Deane / Rural
  Mechanical and peers).
- Migration `139` activates Charters Towers unclaimed listings that were stuck
  as pending/draft after import (Ask requires `providers.status='active'`).
- Provider pack refresh no longer demotes already-active measurable unclaimed
  listings back to pending (that was undoing migration 139 on release).
- `scripts/provision-google-places.php` loads `GOOGLE_PLACES_API_KEY` into the
  encrypted connector vault and can enable `provider_places_rescue`.
- Root helper `assist-platform-places-rescue` and workflow **Places rescue
  production bootstrap** for authorised hub applies.
- National hub bootstrap script:
  `scripts/places-rescue-bootstrap.php` / `docs/PLACES_RESCUE_BOOTSTRAP.md`.
- Production enablement requires Places API key, connector budget, Quality Gate
  and turning the flag on — code alone does not authorise live Places spend.

### Production IndexNow notify hardening (OPS-001)

- Production release IndexNow notify now fetches the key and sitemap with the
  same smoke User-Agent used by journey checks (so bot controls do not 403 the
  step), submits the sitemap in batches of at most 5,000 URLs (IndexNow limit
  is 10,000), and continues on IndexNow failure so a healthy deploy is not
  marked red by best-effort SEO notify.

### Browser visual UX audit fixes (UX-001)

- VanAssist mobile home keeps the Ask search card intact instead of flattening it
  with `display:contents`, which had leaked “Prefer category and town search?”
  under the header and broke the closed structured-search disclosure.
- Ask homepage placeholder shortened so it is not clipped; mobile duplicate H1
  replaced with a decorative title while the real H1 stays available to AT.
- Location “Locating…” controls recover after a watchdog timeout when geolocation
  stalls; homepage structured search no longer auto-locates while collapsed.
- TowSmart calculator keeps custom-entry fields collapsed until a catalogue pick
  or “not listed” action.
- Results map summary only opens for named results and follows Ask facility cards.

### Flagship deterministic Ask and three-brand correctness

- Promoted Ask VanAssist to the primary homepage search candidate while keeping
  category/town search as an accessible fallback.
- Preserved state qualifiers, honoured explicit radii, added safe unique typo
  correction and duplicate-town clarification, and restricted GPS searches to
  Australian bounds.
- Enforced VanAssist brand identity through the orchestrator and brand-scoped
  reviewed traveller facilities; unsafe source URL schemes are discarded.
- Added emergency guidance for fire, gas, brake, medical and roadside danger.
- Added national question/location matrices, cross-browser acceptance coverage
  and server-side module denial for requests, service runs and parks on brands
  where those modules are disabled.
- Paid AI and pending dataset answers remain disabled for the initial launch.
  Deployment of the flagship ordering and wrong-host corrections requires the
  exact candidate's full Quality Gate and controlled release evidence.
### Shared public-edge release guard (OPS-001)

- Add a generic, root-owned guard for releases sharing the production Caddy
  edge. It checks every host in the host-owned registry for valid HTTPS and its
  expected product identity.
- Add snapshot and verification modes that stop a release if another product's
  vhost or the registry changes, plus candidate validation that rejects a Caddy
  configuration dropping any registered hostname.
- Add a narrow bootstrap installer that changes no product vhost and fails
  closed unless all registered sites are healthy. No migrations, application
  behaviour, brand configuration, or environment variables change.
- Rollback removes only
  `/opt/shared-public-edge/bin/check-shared-public-edge`; existing routes and
  product data remain untouched.

### Sale-candidate evidence reconciliation (OPS-005 / COM-005)

- Reconcile the buyer-facing production baseline to deployed release
  `74b18116f19f0a5ba1b8a651cdf9cf4ad4b74843` and production run `34077589608`.
- Close the release-identity evidence row after all three public `/readyz`
  endpoints returned that exact release.
- Record the transaction boundary that independent off-site backup storage is not
  configured or included and must be supplied by the customer/buyer after transfer.
- Add a buyer email handover procedure for choosing Microsoft 365 Graph or any
  compatible authenticated SMTP provider, configuring domain authentication and
  proving delivery without seller-owned credentials.
- Put public directory trust copy on a stable dark surface so its legibility no
  longer depends on the underlying hero image. No migrations or environment
  changes are introduced.

### Three-brand UX and operational closeout (EXP-005 / OPS-012)

- Remove the redundant VanAssist `Start here` divider while keeping Ask VanAssist
  as the preferred homepage entry and preserving direct structured search.
- Keep TowSmart calculator and guidance as the primary product journey and move
  specialist discovery into a supporting position rather than treating the
  provider directory as the product centre.
- Strengthen TowSmart and TrailerWise deterministic Ask routing and location
  handoff without requiring paid AI or substituting unrelated businesses.
- Make typed town/suburb/postcode input authoritative over stale device GPS in the
  shared provider directory, then rank measurable provider locations by road
  distance when Google Routes is available, with honest fallback labelling.
- Add live three-brand acceptance for product-brand homepage location behaviour
  and extend the protected production release smoke gate across health/readiness,
  key public journeys and brand-scoped discovery.
- Align the Product Bible with the authoritative three-brand acquisition boundary.
  No migrations or new environment variables are introduced.

### Public provider crawl access (OPS-012)

- Add exact CQDiggings legacy navigation redirects to the shared Caddy edge:
  `/occurrences/site-index.html` and `/occurrences/glossary.html` return 301 to
  their root-level pages. Apache `.htaccess` rules do not run on this server.
  Exercise the real Caddy engine in CI, including unchanged missing/private URLs.
- Restrict private-area robots rules to exact routes, query strings and
  descendants. The former `/provider` prefix also blocked public `/providers`
  listings and `/provider-terms`.
- Preserve authentication and the indexing-off switch. Shared controller
  behaviour applies to VanAssist, TowSmart and TrailerWise without brand forks.
- Exclude towns marked noindex from VanAssist's sitemap even when marked
  featured or launch towns. Preserve their deliberate page-level noindex setting.
- Exclude `/go/` contact tracking actions from crawling while retaining their
  normal phone, email, website and directions behaviour.
- Show the stored state on stay pages even without a town, and include location
  in default page titles/descriptions to distinguish same-name campsites.
  Existing custom SEO text, identifiers and canonical URLs are preserved.
- Add behavioural regression tests for public pages, private routes and launch
  settings and stay location fallbacks. No migrations or environment changes.
- Release through the reviewed immutable release process. Rollback uses the
  preceding release and restores the old crawl restriction. Search Console
  recovery requires Google's subsequent recrawl; deployment is not proof of indexing.

### Generic shared public edge extension (OPS-001)

- Keep the existing Assist Caddy service as the sole public listener on host
  ports 80 and 443 while allowing reviewed host-only vhost drop-ins for separate
  products sharing the VPS temporarily.
- Mount `/opt/shared-public-edge/sites` read-only into Caddy and attach Caddy to
  the named `shared-public-edge` Docker network.
- Preserve the three-brand Assist application and acquisition boundary: separate
  products remain independently deployed, do not become Assist brands and own
  their own vhost, application, credentials and data.
- No migrations or new application environment variables. Validate Caddy and
  Compose configuration plus all existing public domains after release. Rollback
  removes the drop-in/network extension and restores the preceding edge runtime.

### Sale evidence and form-preserving service worker (OPS-005 / COM-005)

- Stop service-worker activation from reloading open pages and discarding forms.
- Add desktop/mobile three-brand acceptance and a worker activation regression test.
- Separate called release CI concurrency from standalone CI; remove obsolete
  CQDiggings investigation assertions from the Assist release workflow.
- Record current production SHA, isolated database restore, aggregate analytics,
  source/licence gaps and privacy inventory in the acquisition data room.
- No migrations or new environment requirements. Normal reviewed release only;
  rollback to the preceding release reintroduces the worker reload defect.
  Independent backup, full transfer rehearsal and seller legal/account evidence
  remain open; these changes do not certify the platform sale-ready.

### Three-brand runtime cleanup and acquisition evidence (OPS-005 / COM-005)

- Removed retired Polaris routes, administrator navigation, search delegation,
  registry configuration, upload path and unused public styles.
- Aligned the Enterprise specification, charter and backlog with VanAssist,
  TowSmart and TrailerWise; historical migrations and audit records are retained.
- Added acquisition evidence requirements, operating-cost schedule and transfer
  rehearsal steps. Unverified production controls remain open sale gates.
- No new migrations or environment variables. Deploy through the existing
  immutable release process; rollback uses the preceding release. A code rollback
  may restore retired surfaces and must not be described as sale-ready.

### Sale-readiness three-brand boundary

- Define the active Assist Platform product and acquisition boundary as
  **VanAssist, TowSmart and TrailerWise only**.
- Retire Polaris from active runtime brand resolution and add a forward
  retirement migration that disables its database brand row, removes active
  domains and prevents its listings from being exposed as a current product.
- Keep the existing LocalTorque retirement migration and transferred canonical
  provider coverage intact; LocalTorque remains excluded from the active runtime
  and sale package.
- Preserve historical migrations, ADRs, deployment records and audit evidence for
  both retired experiments where they are required for upgrade integrity and
  technical due diligence. Historical records do not make either retired brand a
  current product or sale dependency.
- Remove retired-brand delegation from active homepage behaviour and narrow
  active-brand tests and platform documentation to the three public products.
- Add a formal sale-readiness gate covering product acceptance, reliability,
  security/privacy, data provenance, buyer-grade operating records,
  transferability and acquisition data-room evidence.

### Shared provider-match quality and service mode

- Add one shared mobile-versus-workshop filter to the public provider directory
  used by VanAssist, TowSmart and TrailerWise, including an explicit fallback
  that widens only that filter when local coverage is thin.
- Rank direct or verified category matches ahead of paid featured placement and
  label those stronger matches on result cards without overstating imported or
  heuristic category assignments.
- Record the selected service mode in existing privacy-safe directory demand
  analytics so coverage work can distinguish mobile and workshop shortages.

### Canonical public brand schema logos

- Publish each public brand's canonical `mark.svg` alias in Organization
  structured data instead of the retired `symbol-v2.svg` filename.
- Keep the rendered artwork unchanged while aligning SEO/bot metadata with the
  protected public-identity release gate.

### CQDiggings validation-map release correction

- Refresh the reviewed CQDiggings overlay from source PR #71 and commit
  `1172690e6f50fea5b1e303dfad1ff6d73f8c8311`.
- Load the 15 published Clermont desktop validation points in both Research Map
  and Field Map, preserving the legal gate and explicit planning-only warning.
- Add local regression checks matching the protected production verification so
  a missing map integration fails before release.

### Product-brand completion programme (CORE-003 / CORE-012 / EXP-004 / EXP-005 / TOW-002 / TRL-001)

- Add owner-and-brand-scoped TowSmart edit/recalculate, three-way comparison
  and print/PDF report journeys without weakening the guidance/not-certification
  boundary.
- Add deterministic TowSmart and TrailerWise Ask intent matrices with visible
  routing provenance, safe clarification and no unrelated-provider fallback.
  The shared paid-AI path remains gated and is not required for these routes.
- Add direct TrailerWise repair, mobile, parts, inspection/certifier,
  manufacturer/dealer and fabrication/engineering journeys. Keep the
  marketplace secondary and give its empty state a service-directory route.
- Bind provider claim tokens to their issuing brand and filter Admin API invite
  review by selected brand. Expire ambiguous unused legacy links, retain their
  audit rows and require correctly scoped replacement invitations.
- Record a rendered 360 px production baseline for home, provider directory,
  TowSmart calculator and TrailerWise marketplace: one H1/main landmark,
  labelled controls, no horizontal overflow and no sub-24 px interactive
  targets on the audited pages. Manual screen-reader sign-off remains a release
  gate.

### Product-brand homepage discovery (EXP-005 / TRL-001)

- Add one shared service/location finder to the TowSmart and TrailerWise
  homepages using the existing brand-scoped directory, curated categories,
  town suggestions and device-location service.
- Keep automatic nearest-town resolution on both product-brand homepages, but
  only fill the location field. Do not submit `/providers` until the user
  deliberately presses the directory search button.
- Add regression coverage for both shared homepage location controls. No
  migrations or environment changes; rollback to the previous behaviour would
  reintroduce unwanted automatic provider-directory navigation.
- Keep trust wording visible and preserve the boundary that neither brand owns
  VanAssist stays or assistance requests.

### TowSmart saved combination management (TOW-002)

- Add authenticated, owner-and-brand-scoped saved combination detail reports.
- Allow users to remove their own saved snapshots through a CSRF-protected
  account action without exposing whether another user's record exists.
- Keep reports private from indexing and repeat the guidance/not-certification
  boundary.
- Publish factual Organisation and WebSite structured data from the shared
  trusted Brand configuration on every public brand homepage.

### TowSmart and TrailerWise directory parity (EXP-005 / TRL-001)

- Route TowSmart and TrailerWise `/find` requests through the shared,
  brand-scoped provider directory instead of the VanAssist-only results journey.
- Accept curated brand category keys without weakening server-side brand scope.
- Include public provider profiles and trust/legal pages in both product-brand
  sitemaps, and correct configured privacy and terms paths.
- Record remaining launch gaps in
  `TOWSMART_TRAILERWISE_PARITY_AUDIT_2026-08-26.md`.

### CQDiggings overlay mount isolation

- Mount the reviewed CQDiggings release overlay at its own read-only path and
  route its static files through Caddy.
- Avoid nested file mounts beneath the separate read-only CQDiggings release,
  which prevented deployment when an overlay introduced a new filename.

### Retire LocalTorque and transfer provider coverage (LOC-001 / DATA-001 / OPS-001)

- Removed the LocalTorque brand registry entry, public routes, views, imagery,
  social configuration and production journey checks.
- Renamed the authoritative corpus and importer as the VanAssist provider pack.
  Legitimate canonical provider records are retained; LocalTorque listings and
  domains are disabled by a forward migration.
- Moved shared automotive discovery and regulatory pathways to VanAssist-owned
  configuration and removed the old import task and setting names.
- Hardened production releases by removing only containers carrying the exact
  Assist Platform Caddy Compose labels before replacement and rollback.

### CQDiggings Clermont gold investigation release (INF-001 / OPS-001 / OPS-002)

- Packaged the exact reviewed CQDiggings commit
  `d3f4f5ea76c00ecea5ce6159abe1fa79e8ece3a0` as a read-only production
  overlay, including the complete twenty-pass investigation dossier, 8,666
  Queensland gold occurrence records, 757 historical gold-source features, 433
  historical alluvial-evidence features, 193 production and assay records, 41
  occurrence-to-report matches, 24 target and exclusion polygons, 150 drainage
  features, 15 field-validation points and both map integrations.
- The overlay uses the existing protected GitHub production environment,
  checksummed immutable Assist release and root-owned release command. It does
  not alter CQDiggings analytics, moderation records, uploaded images or other
  shared runtime data.
- Rollback restores the preceding Assist release and Compose file, removing the
  overlay mounts while retaining the prior CQDiggings base release.


### VanAssist provider taxonomy bridge
