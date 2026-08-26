# Assist Platform Enterprise release notes

This is the chronological release index. Detailed historical deployment records
may remain as dated files and are linked here rather than copied.

## Unreleased

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

- Every LocalTorque category shared with VanAssist now links to the canonical
  VanAssist service taxonomy used by Ask and structured search. Previously only
  fuel and EV records crossed this bridge, leaving valid caravan repairers,
  mechanics and auto electricians invisible to category retrieval.
- The LocalTorque import fingerprint now includes the compatibility version so
  production safely reprocesses existing records when mappings change.
- Production journey checks accept a clearly labelled straight-line distance
  when Google Routes cannot calculate a road route for a valid provider; the
  protected Google credential is still verified independently before release.

- Ask VanAssist now retries the same specialist service within 150 km when its
  automatic 25 km search has no match. User-entered distance limits remain
  fixed, and unrelated provider categories are never used to fill an empty
  result list.
- Ask result pages now use a shorter mobile-first flow with the edit form
  collapsed, repeated explanations removed, and a clearer no-match action.

### VanAssist category-search precision (VAN-011 / DATA-004)

- Provider searches no longer fill an exact category miss with an unfiltered
  regional provider pool. A search for caravan repairs therefore cannot show
  nearby fuel stations or unrelated workshops merely because they are local.
- General caravan repairs no longer widens to generic mobile mechanics. When no
  supported repair listing matches, VanAssist reports the coverage gap instead
  of presenting unrelated businesses as possible help.

### CQDiggings runtime mount release fix (OPS-002)

- Caddy now reads CQDiggings' approved runtime JSON and image directories from
  a dedicated read-only runtime root instead of trying to create nested mount
  points below the site's read-only release tree. This removes the container
  startup failure while retaining the same narrow public paths.
- Approved public JSON and copied public images now receive read-only public
  file and traversal permissions during release. Private moderation reports and
  original uploads retain their existing private directory permissions.

### VanAssist growth and trust operating loop (DATA-004 / DATA-014 / VAN-002 / EXP-004 / INF-004)

- Added one VanAssist admin dashboard for facility publication coverage,
  prioritised search failures, provider claim/verification state and
  evidence-backed regional SEO candidates.
- Provider value reporting now includes appearance-to-profile and
  profile-to-contact rates while retaining the existing warning that actions
  are not completed jobs.
- Town pages can be made indexable only through an audited permission-controlled
  review after local evidence and reviewed SEO copy pass explicit thresholds.
- Added repeatable desktop/mobile accessibility and performance budgets for the
  core public journeys. The checks are an engineering baseline, not a claim of
  WCAG certification.

### CQDiggings moderated detector-setting storage (INF-001 / OPS-006)

- Added persistent, writable storage mounts for private detector-setting reports, private uploaded photos, the approved public settings index and approved public photo copies.
- Caddy serves only the approved index and approved photo directory. Direct web access to pending report records and pending photo uploads is explicitly blocked.
- The CQDiggings release prepares the shared paths and ownership before the containers are recreated. Rollback removes the four mounts and restores the preceding runtime files without altering retained reports.

### Production runtime and launch evidence follow-up (OPS-002 / DATA-001)

- Fixed the published-provider coordinate launch query so its distance
  expression executes in MariaDB and reports zero conflicts instead of
  swallowing a syntax error as unavailable evidence.
- Immutable releases now refresh the root-owned Compose, PHP, Caddy and
  operations runtime files from the reviewed release artefact before rebuilding
  containers. The previous runtime is retained during deployment and restored
  automatically if release validation fails.

### Ask-first VanAssist homepage (VAN-011 / EXP-005)

- Ask VanAssist is now the preferred homepage starting point when the existing
  feature flag is enabled. It appears before the structured category/location
  form on desktop and phone and uses plain-language examples covering providers,
  stays and traveller facilities.
- The four direct traveller shortcuts and the structured category/location
  search remain available under a clear direct-browse hierarchy; no provider,
  stay, facility, assistance or `/find` route is removed.
- Phone acceptance now explicitly verifies that Ask appears before the direct
  shortcut grid and structured browse form, preventing the previous mobile-only
  ordering from returning unnoticed.
- Added a dated site-audit record and matching customer guidance. No migration,
  paid-AI enablement, billing change or production-data rewrite is included.

### Production release guard reliability (OPS-002 / DATA-001)

- The strict provider-service audit now accepts any supported category matched
  by a compound business name. Legitimate records such as tyre-and-petroleum or
  parts-and-tyres businesses no longer cause a false rollback, while unrelated
  category assignments still fail the release. The controlled LocalTorque seed
  also removes unsupported, unverified shared-service links when the provider
  has no publishable source evidence.
- Production release now verifies that the installed root-owned release command
  exactly matches the reviewed GitHub script before any artefact is uploaded,
  exposing server command drift before it can create a partial deployment.
- The post-release road-distance check now uses a stable provider with precise
  coordinates. It no longer expects road distance from Brisbane results that
  are intentionally labelled as town-centre estimates.

### Production zero-result search fixes (VAN-011 / DATA-004)

- Structured selections for caravan parks and camps now open the stay directory;
  dump points, potable water and rest areas open Ask when it is enabled, so the
  request reaches the platform dataset that actually owns those records instead
  of searching provider listings only. Typed locations and device coordinates
  are preserved.
- Provider-only searches with no exact result widen once to relevant repair,
  mechanical or roadside categories, then to clearly labelled providers serving
  the resolved region. Standalone facility, stay and travel-retail searches are
  excluded from this fallback so unrelated repair businesses are not shown.
- Ask now records successful related/regional fallback reasons. Structured Find
  records exact result count separately from displayed alternatives, and Website
  Insights reports exact coverage misses and rescued searches independently from
  searches that still ended with no usable result.

### Ask VanAssist outcome explanations

- Added a separately feature-gated, deterministic explanation layer around the
  existing Ask pipeline. It states the interpreted need, location and radius,
  labels the distance method, explains result fit from recorded evidence and
  recommends a context-specific safest next action.
- Search intent, adapters, radius filtering, result ranking and paid-AI
  behaviour are unchanged. Disabling `assist_ai_outcomes` immediately restores
  the previous Ask results presentation.

### VanAssist observability closure (VAN-012 / DATA-004)

- The daily first-party performance email now compares visits, page views,
  provider searches and contact actions with the prior day instead of reporting
  an isolated daily total.
- Ask VanAssist searches and zero-result searches are reported separately from
  structured provider searches. Structured stay searches and their zero-result
  rate are now recorded behind the existing `demand_analytics` flag and included
  in the same report. Tracking failures remain unable to affect search results.
- Acquisition, device, search and contact figures now exclude recognised bots,
  synthetic checks and same-brand navigations that did not retain the first-party
  session cookie. Existing suspect sessions are filtered without deleting raw
  evidence, and Ask rows retain an explicit exclusion marker for diagnosis.
- Website Insights and the daily email distinguish new, previously seen and
  multi-day visitors so retention is measured directly rather than inferred from
  page views.

### Provider claim and verification closure (VAN-002)

- Public claim/correction submissions now create a structured request against
  the exact provider record and require a business email plus authority evidence.
  Administrators can request more evidence, reject the request or approve it and
  queue a secure, transactional claim link without treating the request as
  promotional consent.
- Claim approval, account control and verification remain separate. Provider
  verification now requires a recorded basis and evidence notes, cannot be set
  on an unclaimed or inactive record, and updates the canonical provider and its
  active brand listings together. No existing provider is auto-verified.

### Formal launch-gate and backup evidence (OPS-002 / DATA-001)

- The provider-coordinate launch check now measures the coordinates actually
  published on the provider against its displayed town. It no longer depends
  on optional JSON source fields, so unavailable or differently shaped source
  payloads cannot turn a clean public-directory audit into unknown evidence.
- The production application image now includes the MariaDB dump client used by
  the scheduled local backup. Backup creation refuses to report success for an
  empty file, writes and verifies a SHA-256 sidecar, and preserves the source
  SQL if compression fails. The launch gate requires the recorded file and
  checksum to exist and be no more than 36 hours old; a task left running for
  more than an hour is identified explicitly.
- Independent encrypted off-site backup and a recent isolated restore rehearsal
  remain hard failures when their signed status evidence is absent or stale.

### VanAssist reliability closure (VAN-011 / DATA-004 / EXP-005)

- Website Insights now excludes assets, manifests, service workers, API and
  health requests from both new and historical public-page reports. Authorised
  release/performance checks carry a synthetic marker and no longer distort
  visits, searches or conversion figures.
- The production release seeds and then verifies at least 1,000 active Ask
  question variations. Release acceptance also checks Griffiths Creek,
  provider-name lookup and Google road-distance output.
- Ask recognises `within 50 km of {place}` facility wording without treating
  campground words as another requested stay type, and exact provider-name
  searches no longer fail solely because GPS was unavailable.
- Provider road distances are shown only for exact provider points. A
  town-centre fallback remains visible but is labelled as an estimate without a
  false numeric provider distance or avoidable Google route lookup.
- VanAssist phone layouts place the four direct journeys before the larger
  search form, shorten search-page artwork and reduce the footer to essential
  links. Footer heading order and low-contrast fine print were corrected.
- Replaced the oversized 218 KB PNG header wordmark with a 20 KB WebP and
  reduced the mobile hero from 164 KB to 110 KB without changing its crop.

### VanAssist daily website performance email (VAN-012)

- Added a VanAssist-only previous-day website performance email to
  `support@vanassist.com.au`, using the existing first-party analytics and
  Microsoft 365 queue rather than a second analytics store or direct PHP mail.
- The plain-English report covers approximate visits, pages opened, pages per
  visit, popular pages, sources, devices, provider searches, no-result searches,
  search success, service/location interest, provider profiles, contact actions
  and providers attracting interest. Low or zero activity is called out rather
  than dressed up as a healthy result.
- Each Brisbane calendar date has an idempotent queue key, so cron retries do
  not send duplicate reports. Staff, known bots and raw visitor identities stay
  outside the report.
- Migration `132_register_vanassist_daily_performance_email.sql` registers the
  monitored task. BinaryLane schedules it at 06:15 after the existing daily
  aggregation, with normal queue delivery following within two minutes.

### Brand-safe public discovery measurement (DATA-004)

- Public pages load a brand's optional GA4 measurement ID only when it is a
  valid `G-` identifier; empty or invalid settings remain disabled.
- Search, result, provider, stay, phone, website, navigation and claim journeys
  emit a shared non-personal event vocabulary alongside the existing
  first-party demand records. No names, email addresses, phone numbers, search
  text or precise locations are sent by this browser bridge.
- Existing per-brand Google and Bing verification settings and automatic
  canonical URLs remain the sole webmaster configuration path.

### VanAssist public search link compatibility

- Restored compatibility for public search links that use `text` instead of
  the current `location` or `q` fields.
- Public location lookups now accept the descriptive `latitude` and
  `longitude` names as aliases for `lat` and `lng`. Current links remain
  unchanged; the aliases prevent older bookmarks and integrations from losing
  their search text or device location.

### VanAssist Ask question library and cache safety (VAN-011)

- Added a migration-backed, idempotent library of 1,550 versioned common Ask
  question variations. Exact matches resolve through deterministic intents
  before the expiring intent cache or paid AI, with aggregate hit counts only.
- Corrected intent-cache keys so wording such as `near me` remains distinct
  from a locationless query, preventing device-GPS behaviour from being reused
  for the wrong search. The default internal interpretation TTL is now 30 days.
- Admin API/RIC health reports the active Ask library count and the Google
  Routes cache policy. Google distance/duration results remain non-persistent;
  duplicate destinations are suppressed within each request.
- Migration `131_ask_question_library.sql` creates the library; the normal
  release now runs the idempotent `--ask-library` seed and verifies the live
  active count. Rollback uses the prior immutable release; the table is
  additive and can remain without affecting the prior runtime.

### Ask VanAssist everyday phrasing batch (VAN-011)

- Expanded intent rules (v8) for common traveller wording: *anywhere to stay*,
  *camping near*, *caravan repairs*, *roadside help*, *grey/black water disposal*,
  *water fill*, *petrol station*, *where to weigh*, *motorhome/rv/4wd service*,
  *find a …* / *where do I find …*, and bare *help near {town}* when a place is
  present. Vague *help please* without location still asks for clarification.
- Removed bare *tow* from towing rules to avoid false matches on tow-vehicle
  servicing queries.
- Location suffix cleanup now uses trailing state tokens only in the fallback
  path as well (Mount Isa and similar names stay intact).

### Ask VanAssist regression corpus (VAN-011)

- Added a committed 1,000-question deterministic corpus at
  `tests/fixtures/ask-question-corpus.json` (40 phrasing templates × 25 regional
  hubs). Regenerate with `php tools/generate-ask-question-corpus.php`; CI can
  verify drift with `--check`.
- Added `AskQuestionCorpusTest` so every corpus entry must route without unknown
  intent before release.

### Ask VanAssist regional provider fallback (VAN-011)

- When a servicing or mobile-mechanic category search returns no rows, Ask now
  widens once to general caravan repairs, auto electrical and diesel mechanics
  at 50 km instead of stopping at an empty specialist-only result.

### Ask VanAssist location parsing (VAN-011)

- State suffix cleanup no longer truncates town names such as Mount Isa when
  removing a trailing `, SA` / ` NSW` style suffix.

### Ask VanAssist stay phrasing (VAN-011)

- Ask now recognises *where to stay*, *accommodation* and *overnight …* wording
  for stay searches. Previously these returned unknown intent even when the town
  was parsed correctly (for example *where to stay in Coober Pedy*).

### Ask VanAssist vehicle service phrasing (VAN-011)

- Ask now recognises everyday tow-vehicle and caravan service wording such as
  *service my car*, *car service*, *logbook service* and *service my caravan*.
  These route to general servicing, mechanical repairs, mobile mechanics and
  general caravan repairs instead of returning an unknown-intent empty result.

### VanAssist homepage launch note styling (VAN-002 / EXP-001)

- Restyled the admin-configurable free-launch message on the VanAssist homepage
  from a teal alert banner into muted inline copy below the hero, matching the
  simplified search note typography.

### VanAssist mobile cache hotfix (VAN-002 / EXP-001)

- Bumped the PWA service-worker static cache and added a client-side cleanup so
  phones that still hold pre-simplification homepage HTML no longer show the
  retired **Service providers by location** grid after the next visit or refresh.

### VanAssist homepage discovery simplification (VAN-002 / EXP-001)

- Removed the **Service providers by location** block from the VanAssist
  homepage. Discovery now starts from the hero search and four intent shortcuts
  instead of a default launch-town provider grid. The `/locations/nearby-providers`
  JSON endpoint remains available for future reuse but is no longer wired to the
  homepage.
- Simplified the VanAssist homepage to a single hero: trimmed duplicate copy,
  removed below-the-fold stays, category, assistance and provider panels, and
  moved advanced search and assistance links into one muted note under the
  primary search form.

### TowSmart and TrailerWise service discovery enrichment (EXP-001 / EXP-005)

- `/services` on TowSmart and TrailerWise now lists each brand's
  `brand_provider_categories` entries instead of VanAssist caravan categories.
  Category detail pages link into the shared provider directory with location
  search and honest empty-state copy when coverage is still growing.
- TowSmart homepage adds an **After the check** section with linked specialist
  category tiles and a quick path to the towing specialist directory.
- TrailerWise homepage removes future-tense placeholder copy, links category
  tiles to live service pages and surfaces marketplace, providers and categories
  as quick paths.
- Brand navigation and sitemaps now include calculator tools, tow guide,
  checklist, provider directory, service categories and TrailerWise marketplace
  URLs where applicable.
- TowSmart and TrailerWise public `/services`, homepage tiles, provider filters
  and sitemaps now show curated brand categories only. LocalTorque taxonomy
  import rows remain for classification but are hidden from customer journeys.
  Migration `130_restore_curated_brand_directory_categories` restores curated
  copy if taxonomy imports overwrote shared keys.

### TowSmart and TrailerWise shell parity (EXP-001 / EXP-005)

- TowSmart and TrailerWise now match VanAssist's shared public shell polish:
  footer-action CTA bar, brand-specific footer link columns, primary header CTA
  button and save-to-phone install control with per-brand manifest metadata.
- PWA manifests are generated per brand from `AssetController`; LocalTorque and
  private brands remain excluded.
- Non-VanAssist apple-touch-icon links use each brand favicon so production smoke
  checks do not flag retired symbol assets in public HTML.

### Documentation and VanAssist navigation (Aug 2026 production alignment)

- Reconciled programme status and production-current-state docs with live
  verification (release `6a3f09d`, Admin API + Ask + traveller facilities on
  VanAssist). See assist-ric `docs/RIC_FACILITY_CATALOGUE_STATUS.md` for RIC upload posture.
- VanAssist header, footer and category-search pages now link to **Ask VanAssist**
  when `assist_ai_search` is enabled (previously only homepage/search panel).

- Ask accepts an active VanAssist provider's business name directly and still
  applies the question's place or the device GPS/radius boundary.
- A provider business name without a place is no longer misread as the place;
  Ask requests device GPS before searching, while an explicit place still wins.
- Ask now defaults to current device GPS when no place is included in the
  question. A place written in the question always takes priority.
- The protected release workflow now installs the already validated Google
  Routes key through a one-time, release-bound HTTPS hand-off before live Ask
  checks. The package contains only a nonce hash; the key is encrypted
  immediately, never printed, and the database rejects replay.

### VanAssist Ask and road-distance integrity (VAN-011 / CORE-012, ADR 0036)

- Ask category detection now excludes the explicit `near/in/around/at` location
  clause, so a landmark containing “camping ground” does not turn a dump-point
  request into a national mixed stay search.
- Added conservative typo recovery for towns and public stay landmarks, including
  the reported “Grffiths camping ground, Queensland” query. An unresolved or
  missing location now fails closed before adapters run; typed locations always
  override hidden device coordinates.
- Google Routes now batches and deduplicates provider, fuel, stay and facility
  destinations. Returned road kilometres and estimated drive times replace the
  preliminary straight-line value, and the selected radius is enforced again
  using road distance. Successful routed lists omit unroutable/overflow cards.
- Added `GOOGLE_ROUTES_API_KEY` and `GOOGLE_ROUTES_MAX_DESTINATIONS` environment
  settings. No migration is required and Google route results are not stored.
- Rollback is the previous immutable release or removal of the Routes key; the
  UI then returns to explicitly labelled straight-line estimates.
- Approved, source-ranked facility evidence attached to a public stay now feeds
  Ask facility results without duplicating or weakening the canonical evidence
  model. This generically closes the Griffiths Creek dump-point/water gap.
- Ask, structured provider/category search and stays now show 20 results first
  and offer an explicit expansion to 40. The same window bounds route-matrix
  destinations and prevents oversized mobile lists during routing outages.
- Routes credentials resolve from root environment or encrypted connector
  storage. Admin API/RIC health reports configuration and source without
  exposing the secret. Production release validation probes the protected key
  before upload and then checks Griffiths plus a real road-distance result.
- RIC overview data now reports provider contact/exact-location completeness and
  stay facility/freshness coverage instead of implying that record count equals
  useful data. No migration or public record rewrite is required.

### VanAssist runtime logic integrity (VAN-001 / CORE-011)

- Provider maps and radius searches now use provider coordinates only when both
  latitude and longitude are present. A partial provider point falls back to a
  provenance-trusted town-centre pair instead of combining mismatched axes and
  producing an inaccurate distance.
- RIC traveller-facility detail and mutation queries now enforce the same
  selected-brand-or-shared scope as the facility list. Facility-contribution
  review endpoints are available only in a workspace whose stays module is
  enabled.
- No migration or environment change is required. Rollback is the prior
  application release; no provider or facility data is rewritten.

### Remaining Queensland provider discovery scope (DATA-006)

- Added a separate, review-only Google Places discovery scope for 63 regional
  Queensland hubs outside the existing south-east and central Queensland run.
  Coordinates come from the committed national town seed, town/query overlap is
  rejected, and the supplied request budget remains a hard stop.
- This change only produces local import candidates for independent evidence
  review. It does not write providers to production or publish Places content.

### GitHub Actions Node.js 24 maintenance (OPS-006)

- Updated the official checkout and dependency-cache actions used by CI,
  staging and production releases to their current Node.js 24-native versions.
  This removes the runner's Node.js 20 deprecation fallback without changing
  application code, deployment permissions or release approval gates.

### VanAssist compact phone homepage launcher (VAN-001)

- Reduced phone-only hero copy, field spacing and action padding while keeping
  the desktop homepage unchanged and the primary controls touch-friendly.
- Moved the four direct traveller journeys ahead of the optional Ask VanAssist
  field on phones, so services, stays, fuel and the full directory are available
  in the first screen instead of after another search block.
- Added 390 x 844 acceptance coverage for all four quick actions, source order,
  horizontal containment and preservation of the main nearby-search action.

### VanAssist provider import worker reliability (DATA-006)

- Corrected the server queue worker's VanAssist brand lookup to use the
  canonical typed brand registry. The manual and scheduled worker
  no longer stop with an unknown `slug` column before screening candidates.
- No migration, environment change or data rewrite is required. Rollback is
  the prior application release; queued candidates remain review-first.

### VanAssist facility accuracy, moderation and lean mobile stays (VAN-001 / DATA-014)

- Enforced the selected straight-line radius at the query boundary and again
  after Ask VanAssist aggregates every provider, stay, facility and staged
  result. Boundary comparisons use unrounded kilometres, so an out-of-radius
  result cannot be admitted by display rounding.
- Provider results now distinguish a provider point from a town-centre
  estimate. Places to stay continue to use their own coordinates and describe
  the radius as straight-line rather than current road distance.
- Added forward migration `130_merge_residual_duplicate_stays.sql` for exact-
  name geospatial duplicates missed by incomplete coordinates or inconsistent
  imported state assignments, plus repeated source identities. The trusted
  survivor retains source aliases, linked records, facility evidence and any
  missing location fields before the absorbed row is soft-deleted.
- Corrected phone overflow on the VanAssist home search and facility-suggestion
  journey, collapsed secondary phone copy, and kept search/form actions inside
  the 390 px viewport. Desktop layout remains unchanged.
- Replaced the conflicting generic provider-card grid on phone stay results
  with a stay-specific compact row: name, distance/location and leading
  facility facts stay lean beside fixed Details and Directions actions.
- Facility-filtered stay search now resolves a wider in-radius candidate pool
  before applying the public result limit, avoiding valid facility matches
  being hidden behind unrelated stays.
- Added structured stay-facility evidence with status, conditions, source authority, confidence and verification timestamps. Specific official facts take precedence over broad summaries without deleting conflicting evidence.
- Added migration `128_stay_facility_enrichment_and_contributions.sql`, including the generic Griffiths Creek regression seed for dump point, untreated water and confirmed no-toilets facts when the canonical stay exists.
- Travellers can suggest one or more facility changes from a stay page. Suggestions remain pending, are rate-limited and never alter public facts directly; matching pending suggestions retain independent confirmations.
- Added the **Facility contributions** admin queue with approve, approve-with-edit, partial approval, reject and duplicate decisions, plus immutable moderation and audit history.
- Added human-gated Admin API reads and moderation actions under `/facility-contributions` using existing facility scopes.
- Stay search can filter by structured facility facts. Ask VanAssist recognises dump station, cassette disposal, black-water and portable-toilet disposal wording plus potable, untreated and treat-before-drinking water wording.
- Phone stay results use compact cards, two-line facility summaries and two primary actions while desktop layout and rich detail pages remain intact.
- Phone stay search keeps location visible and collapses stay type, cost, radius and facility controls behind one accessible **Filters** button.

### Assist RIC third-wave / gap-fill dataset keys

- Migrations `125` and `126` insert `government_datasets` rows for third-wave and
  gap-fill Assist RIC Ready packs so production `/facility-imports` accepts those
  keys (auto-publish still applies via ADR 0034).

### Assist RIC facility auto-publish (ADR 0034)

- Owner decision: Assist RIC government facility packs
  (`connector_key=assist_ric_package`) auto-publish to `traveller_facilities`
  after `POST /api/v1/admin/facility-imports`.
- Added `POST /api/v1/admin/facility-imports/publish-pending` so Assist RIC can
  drain the pre-existing pending queue without human Approve clicks.
- Other government connectors, drafts, provider imports and AI paths stay
  review-first.

### Assist RIC missing ready-pack dataset keys

- Migration `127_ric_missing_ready_facility_import_datasets.sql` **inserts**
  `nsw_rest_areas`, `nsw_ev_charging_locations`, `sa_rest_areas_state_maintained`,
  `wa_major_rest_areas`, `nsw_boat_ramps`, and `gold_coast_caravan_parks`.
  Migration 124 only tried to enable those keys if they already existed, so live
  `POST /facility-imports` returned "Unknown government dataset_key". Apply
  before retrying those packs from Assist RIC.

### Assist RIC facility package upload

- Added `POST /api/v1/admin/facility-imports` (`imports:write`, `Idempotency-Key`)
  so Assist RIC can push facility packages (auto-publish per ADR 0034).
- Migration `124_ric_ready_facility_import_datasets.sql` registers missing RIC
  ready dataset keys and enables known pack rows for staging.

### All-brand RIC workspace identity

- Extended `GET /api/v1/admin/capabilities` with the resolved host brand key,
  name, status, URL and enabled modules.
- Capability resource modes now mark providers and stays unavailable for brands
  that do not support those modules, allowing RIC to hide unsupported sections.
- Added contract coverage for VanAssist, TowSmart, TrailerWise, LocalTorque and
  Polaris. No production flags or data were changed.

### RIC everyday management — overview Admin API

- Added `GET /api/v1/admin/overview` and `GET /api/v1/admin/website-insights`
  for Assist RIC operational dashboards (analytics:read).
- Overview reuses `WebsiteInsightsService` so genuine visitors exclude bot/unknown
  page views; queue and AI sections are scope-gated.
- Expanded Assist RIC service-account scopes with `corrections:read`,
  `duplicates:read` and `ai:read` (still no production-danger scopes).
- OpenAPI, LIVE_API and RIC contract tests updated. No production flags changed.

### RIC Directory Management Admin API contract

- Clarified Assist RIC Directory Management uses existing Admin API directory
  and review-queue reads (`/providers`, `/stays`, `/facilities`, `/claims`,
  `/corrections`) with cursor pagination. No new endpoints. Category/location
  taxonomy remains website admin only. Claim/correction mutations stay human-
  session scoped.

### RIC Data Review Admin API contract

- Documented Assist RIC Data Review against existing `/drafts`, `/duplicates`
  and `/recycle-bin` Admin API reads (cursor pagination; OpenAPI list filters
  clarified). No new endpoints for import-candidate or stale/missing queues.
- Added `recycle_bin:restore` to default Assist RIC service scopes so recycle
  bin listing works; purge remains human-only and never in `RIC_SERVICE`.

### RIC Ask Insights Admin API contract

- Documented Assist RIC Ask Insights against `GET /ai/usage/requests`, usage
  summary/costs/cache and dual-source `GET /search-gaps`.
- Knowledge-gap SearchGap `meta` now includes click/contact/`ai_used` counts and
  taxonomy keys (additive). OpenAPI documents the AI usage request feed.

### RIC Operations Admin API contract

- Documented Assist RIC Operations as read-only Admin API visibility:
  `/health`, `/version`, `/capabilities`, dataset `/sync-history`,
  `/sync-conflicts`, `/imports/{id}`, AI usage rollups and `/audit`.
- Explicitly deferred feature-flag reads, import list index, failed-job queues
  and AI budget caps (remain website admin). No production toggles from RIC.

### RIC Operations Admin API gaps (Increment G)

- Added `GET /api/v1/admin/imports` import-job index (`imports:read`, cursor +
  optional status; sparse when jobs table missing).
- Nested read-only `budget` snapshot on `GET /ai/usage/summary` (`ai:read`).
- Added `GET /api/v1/admin/feature-flags` (`flags:read`) catalogue; no Admin API
  write/toggle paths. Scope included in `RIC_SERVICE`.
- OpenAPI, LIVE_API, contract and unit coverage updated. No production flags
  changed.

### Facility import-candidate review (Increment H.1)

- Added human-only Admin API mutations for facility import candidates:
  `POST /facility-import-candidates/{id}/approve` and
  `POST /facility-import-candidates/{id}/reject`
  (`import_candidates:review` in `ALL` and `NEVER_SERVICE`; not in
  `RIC_SERVICE`; requires `admin_api_human`).
- Optional JSON `{ "reason": "..." }` maps to
  `GovernmentDatasetService::reviewCandidate` notes.
- RIC Ops/Data Review service accounts remain read-only on import candidates;
  website admin review remains available.

### Provider import-candidate review (Increment H.2)

- Added human-only Admin API mutations for provider import candidates:
  `POST /provider-import-candidates/{id}/approve` and
  `POST /provider-import-candidates/{id}/reject`
  (same `import_candidates:review` + `admin_api_human`).
- Approve requires `retention_confirmed` and independent `evidence_url`
  (optional `category_id` / notes); delegates to `DataSourceService::review`
  and confirms evidence first when needed. Creates an unclaimed provider on
  success. Reject accepts pending or held candidates.
- Merge/hold/confirm workflow aids remain website admin only.
- Capabilities mark `provider_import_candidates` as `read_write`. Detail
  payloads may include `evidence_url` and `review_notes`.

### Facility import-candidate bulk review (Increment H.3)

- Added human-only Admin API batch mutations:
  `POST /facility-import-candidates/bulk-approve` and
  `POST /facility-import-candidates/bulk-reject`
  (same `import_candidates:review` + `admin_api_human`).
- Body `{ "ids": [1,2], "reason": "..." }` — optional shared notes; batch size
  capped by `admin_api.max_batch_size`. Returns per-id success/failure results
  without aborting the whole batch on a single conflict.

### Provider import-candidate merge (Increment H.4)

- Added human-only Admin API merge for provider import candidates:
  `POST /provider-import-candidates/{id}/merge`
  (same `import_candidates:review` + `admin_api_human`).
- Requires `retention_confirmed` and independent `evidence_url`; optional
  `provider_id` (defaults to candidate `duplicate_provider_id`), `category_id`,
  and notes. Delegates to `DataSourceService::review` manual merge (attach to
  unclaimed target). Exact-identity gates apply. Hold/confirm/auto-link stay
  website admin.

### RIC ops + taxonomy reads (Increment I)

- Added read-only `GET /ops/failed-emails` and
  `GET /ops/failed-scheduled-tasks` (`ops:read` in `RIC_SERVICE`). Email
  bodies are never returned.
- Added `GET /categories` (`categories:read`) for brand
  `brand_provider_categories`, and
  `GET /locations/states|regions|towns` (`locations:read`) for picker taxonomy.
- Staging enablement checklist documents new RIC scopes. No production flags.
  Stale/missing quality queues remain deferred (product criteria).

### Import-candidate review queues (Increment H)

- Added read-only Admin API queues for facility and provider import candidates:
  `GET /facility-import-candidates`, `GET /facility-import-candidates/{id}`,
  `GET /provider-import-candidates`, `GET /provider-import-candidates/{id}`
  (`import_candidates:read` in `ALL` and `RIC_SERVICE`).
- These are separate from `GET /imports` (RIC package jobs / `api_import_jobs`).
- Cursor pagination; default `status=pending`; provider supports `q`/`state`.
- List payloads omit `raw_json`; detail may include a sanitised `raw` summary.
- No approve/reject endpoints this increment (PHP admin retains review writes).

- Ask VanAssist recognises plain-language accommodation requests such as
  “somewhere to stay free near Emerald” and searches both free-camp records
  and stays explicitly marked free.
- Added a regression matrix of realistic traveller questions and expanded
  everyday synonyms across provider faults, stay types and traveller facilities.

### Numbered maps across search results

- Added the existing accessible map/list experience to Ask VanAssist, filtered
  directories, stays, service categories, towns and regions.
- Providers, stays and traveller facilities with reliable coordinates receive
  matching numbered pins and list references. Unmappable records remain usable
  in the list and are never assigned invented coordinates.

### Cleaner, more visual VanAssist journeys

- Made the four homepage capability items direct links and removed the duplicate
  shortcut and statistics sections beneath the hero.
- Added distinct, relevant photo treatments to the main services, search, Ask,
  stays, provider-directory, provider-profile and how-it-works page headings.
- Put the Ask VanAssist plain-language field directly on the homepage when Ask
  is enabled, while retaining the structured category and location search.
- Filtered obvious automated traffic from website insights and rejected common
  abusive scraping tools without blocking recognised search crawlers.
- Phone-save guidance now distinguishes iPhone/iPad, Android and desktop and
  shows only the relevant brief instructions.

### Traveller-facility staging reliability

- Moved immutable acceptance fixtures outside runtime-mounted storage so
  staging releases retain their toilet, dump-point and water test data.
- Acceptance evidence now falls back to writable storage on read-only releases,
  and Linux no longer receives the Windows `NUL` redirect warning.
- Ask VanAssist traveller facilities now use compact mobile-friendly rows.

### Cleaner public provider pages

- Removed decorative business-name initials and the repeated provider name from
  provider-profile breadcrumbs.
- Corrected workspace-help routing so public `/providers/...` pages no longer
  show the private account/provider “Open page guide” strip.

### Provider map-reference icons

- Replaced `Map pin N` text in provider rows with a compact coloured numbered
  pin matching the corresponding marker on the results map.
- Removed the unexplained business-name letter tiles from provider rows.

### Compact provider lists

- Provider collections now use concise rows across public search, directory,
  town, region, service and saved-provider pages.
- Mobile rows prioritise the provider name and location, retain 44-pixel action
  targets, and move full descriptions and details to the provider profile.

### VanAssist map/list matching

- Located provider cards now show `Map pin N`, matching the numbered pin on the
  results map so travellers can identify the corresponding business quickly.
- Corrected the map-marker styling so those numbers are visible inside the pins.

### Isolated staging deployment path

- Added a manually approved GitHub workflow for immutable releases to the
  separate staging runtime and database on the existing server.
- Production deployment, data and feature flags are unchanged.

### POL-008 dealer enquiry handoff

- Model pages show linked published dealers with email/website CTAs via
  `/dealers/{id}/enquire` (tracks `dealer_enquiry_click`, then redirects).
  No platform-sent enquiry email. Demo contacts use `example.invalid` (`120`).

### POL-002 demo catalogue volume

- Migration `119` adds Demo Alpine Family plus six more demo models/variants
  (`is_demo` only). Not national production catalogue data.

### POL-007 manufacturer portal data quality

- Claimed manufacturers see a completeness checklist on
  `/portal/manufacturer/data-quality` (missing ATM, length, berths, price
  guidance, descriptions). Guidance only — not a Quality Gate pass.

### POL-007 manufacturer portal analytics

- Claimed manufacturers see 7/30/90-day detail views and saves for their models
  on `/portal/manufacturer/analytics` (first-party events only). Find impressions
  and dealer enquiry clicks remain planned.

### POL-003 Find preference hydration

- Signed-in users opening Find without preference query fields use saved
  travel preferences; explicit query/POST values still win. Stage list marks
  `aria-current="step"`.

### POL-002 accessibility markup polish

- Compare/model table captions; compare **Differs** text markers (not colour alone);
  empty-state `role="status"` on browse/saved/find/account surfaces; year selector
  focus-visible + labelledby. Evidence remains **CONDITIONAL** — no WCAG PASS /
  no CI axe gate yet.

### POL-002 model year selector

- Model detail resolves published years and filters variants by `?year=YYYY`
  (default = current, else newest). Invalid years fall back with a notice.
  Canonical URL stays without year. Demo migration `118` adds a 2025 Southern
  Cross variant for the selector.

### POL-005 account comparison history

- Signed-in users see shareable comparisons they created on `/account/comparisons`
  (brand-scoped; guest shares remain unlisted). Alert delivery still scaffolded.
- Saved browse searches: capture current `/rvs` filters via `POST /saved/searches`,
  reopen from `/saved` and `/account/alerts`. Email notifications remain off.

### CORE-011 + CORE-012 unification

- Merged Admin API Phase 1 / Option B A–L with Assist AI / Polaris onto one tree.
- AI/Polaris migrations renumbered to `101`–`116`; AI ADRs to `0021`–`0032`.
- Wired dual-source Option B into inventoried `GET /api/v1/admin/search-gaps`
  (`meta.source=dual`). No second API path. Production flags remain off.
- Wired `POST /api/v1/admin/datasets/{id}/sync` to real government dataset fetch
  (review-first facility candidates; optional fixture mode).
- **DATA-011A** National Dataset Catalogue on `government_datasets` (`117` +
  ADR 0033): RIC acquires; Platform catalogue SoR; no direct production publish.

### Government datasets and traveller facilities (DATA-012 / AI-6)

- Added the government dataset catalogue under **Admin → Data sources → Government datasets**, with CKAN / ArcGIS / CSV / GeoJSON connectors, demo fixtures, and curated National Public Toilet Map rows (disabled until an administrator enables Fetch).
- Facility import is review-first: candidates are approved into `traveller_facilities` only — never into caravan parks.
- Ask VanAssist can show a separate **Traveller facilities** section when the `assist_ai_traveller_facilities` flag is on; the flag stays off by default until facilities are populated and Quality Gate evidence is recorded.
- Administrators manage catalogue rows (add/edit), import fixtures or Fetch enabled sources, and bulk-approve or reject facility candidates.
- CLI bootstrap: `php scripts/import-demo-traveller-facilities.php --approve`.
- Knowledge gaps export SearchGap-shaped JSON for RIC (`/admin/ai-search/gaps/export?format=json`).
- Dual-source SearchGap merge helper (`SearchGapDualSource`) and merge plan (`docs/SEARCH_GAP_DUAL_SOURCE.md`) for CORE-011 `GET /api/v1/admin/search-gaps` — no second API; production Ask remains off.
- Quality Gate evidence: **CONDITIONAL PASS** only — `docs/AI_QUALITY_GATE_EVIDENCE.md`. Production Ask remains off until a full Platform Quality Gate pass.
### Option B programme Increments A–L (functional management coverage)

- Programme tracker: `docs/OPTION_B_MANAGEMENT_PROGRAMME.md`.
- Conditional Quality Gate:
  `docs/evidence/option-b-programme-2026-08-02/PLATFORM_QUALITY_GATE.md`.
- Assist RIC Option B sync console shipped on sibling branch
  `feature/option-b-ric-management` (pull providers/stays, conflicts, gap→research,
  dataset catalogue, budget guardrails).
- Production `ADMIN_API_ENABLED` remains off until staging rehearsal is recorded.

### Option B Increment H — claim-first onboarding + PHP admin polish (VAN-010 / OPS-010)

- Added claim-first provider onboarding on `/for-providers/register`: search-before-create,
  “Is this your business?” match step, explicit none-of-these confirmation, second
  duplicate check on submit with pending hold (no publication) and internal note /
  `listing_corrections` row when tables exist. Controlled by `CLAIM_FIRST_ONBOARDING`
  (default true).
- Added PHP admin pages: **Administration → API service accounts** (list/create/rotate/disable
  via `AdminApiServiceAccountService`) and **Directory → Recycle bin** (list/restore via
  `AdminApiRecycleBinService`).
- PHPUnit coverage for duplicate scoring/hold decision logic (`ClaimFirstOnboardingTest`).

### Admin API Option B Increments B–G (CORE-011 / VAN-002 / DATA-002 / DATA-012 / DATA-013)

- Added Admin API resources for claims, listing corrections, duplicate review/merge
  (with `dry_run`), government datasets, AI usage summaries, search analytics,
  sync conflicts, traveller facilities (`/facilities`, ADR 0019) and import
  publish/cancel/retry lifecycle actions.
- Extended scopes: `claims:*`, `corrections:*`, `duplicates:read`, `datasets:*`,
  `facilities:*`, `ai:read`; `duplicates:merge` and `drafts:approve` remain
  human-only (`NEVER_SERVICE`). RIC least-privilege adds `claims:read`,
  `datasets:read`, `facilities:read`.
- Forward migrations `088`–`092` create listing corrections, duplicate decisions,
  dataset/facility tables, AI usage reporting tables and sync conflict queue.
- Contract tests and OpenAPI paths updated; apply migrations before enabling new
  routes in staging.

### Admin API Option B closeout (CORE-011 / OPS-010 / DATA-011 / OPS-011)

- Recorded conditional Platform Quality Gate evidence for Admin API + Assist RIC
  client foundation (`docs/evidence/admin-api-2026-08-02/`). Production
  `ADMIN_API_ENABLED` / MFA flags remain off until staging rehearsal is appended.
- Synced Phase 1 design, LIVE_API, OPERATIONS_RUNBOOK and backlog statuses:
  CORE-011 / OPS-011 / DATA-011 client work done; OPS-010 production enablement
  still gated.

### Admin API staging enablement checklist

- Documented the staging-only sequence to migrate Admin API tables, enroll TOTP,
  create a least-privilege RIC service account, and rehearse import submit before
  any production `ADMIN_API_ENABLED` change (`docs/LIVE_API.md`).
- Added CLI helpers `scripts/admin-api-create-ric-service-account.php` and
  `scripts/admin-api-probe.php` for safe staging bootstrap and health checks.

### Admin API TOTP MFA (OPS-010)

- Replaced the MFA verify scaffold with RFC 6238 TOTP validation (pure PHP).
- Added enrollment endpoints `POST /auth/mfa/enroll/begin` and
  `POST /auth/mfa/enroll/confirm` for authenticated humans.
- When `ADMIN_API_MFA_REQUIRED=true`, password login returns a short-lived
  `mfa_token` (`mfa:verify` scope); `POST /auth/mfa/verify` exchanges a valid
  authenticator code for a full session. Enroll MFA before enabling the flag.
- `ADMIN_API_MFA_REQUIRED` remains **false** by default.

### Admin API Phase 1 foundation (CORE-011)

- Added the versioned `/api/v1/admin` surface for Assist RIC and other management
  clients: health/capabilities, human and service-account auth, providers/stays
  read and write, recycle bin, drafts/imports, audit and search-gaps.
- Locked the boundary so external tools must not open production MariaDB
  (ADRs 0018–0020). Stays remain `caravan_parks`; traveller facilities stay out
  of Phase 1. Assist RIC is the initial local management client.
- Migrations `085`–`087` create Admin API credentials, MFA tables and
  draft/import job storage. The API remains disabled by default
  (`ADMIN_API_ENABLED=false`) until staging rehearsal and an updated Quality
  Gate allow production flags.
- TOTP MFA enrollment/verify shipped in a follow-up (OPS-010); enforcement flag
  stays false by default.

### National coverage map

- Replaced the floating heat points with a recognisable Australia map including mainland and Tasmania outlines, state/territory boundaries and labels, an opportunity-score legend, and keyboard-focusable town/category points.

### Free Growth Hub

- Added direct, prominently labelled email campaign actions for caravan/RV clubs, club federations, 4WD/touring groups, publications, tourism organisations, industry bodies, manufacturers, dealers, rental fleets and caravan park networks. Each action shows the reviewed eligible count and opens a correctly targeted, prefilled campaign instead of making administrators reconstruct the audience and message manually.
- Added the real next sending action directly to every existing campaign row, including a pre-addressed internal preview and one-click staged batch controls, while retaining the enforced recipient caps and evidence checks.
- Consolidated provider factual notices, reviewed organisation outreach, Social Studio, Facebook/community sharing, partner referrals, search indexing and Website Insights into one dashboard workflow.
- Added ready-to-copy community, Messenger, club-newsletter and provider/park share messages with distinct tracked links and mobile native sharing.
- Added first-party UTM source capture without collecting visitor IP addresses; Website Insights renders tracked sources in plain English.
- Added live channel status for prepared factual campaigns, reviewed organisation contacts, Facebook connection, indexing and approved social assets.
- Kept Facebook-group posting manual so administrators can follow each group’s rules, and retained consent, suppression, sender-identification and unsubscribe boundaries for electronic messages.

### Location-first behaviour across every discovery journey

- Extended optional current-location resolution beyond the homepage to Fuel, EV charging, Places to Stay, the service directory, every service-category page, provider browsing, result refinements and assistance requests.
- Nearby shortcut links reuse a recent device location and carry coordinates into the destination search instead of opening an unfiltered national page.
- Preserved the rule that a typed town, suburb or postcode immediately overrides stored or device coordinates.
- Kept the phone's coordinates as the distance origin for service-category and Places to Stay searches rather than silently replacing them with a town-centre estimate.

### Clear email campaign sending path

- Added a direct **Email campaigns** item to the admin Growth navigation and a prominent dashboard shortcut.
- Reworked the campaign list around a visible four-step path: open, review recipients, email a preview to the administrator, and start staged delivery.
- Added a plain-English next action and **Open & send** control to each active campaign.
- Added an in-campaign **Next step** prompt and direct jump to the delivery controls.
- Renamed the internal test action to **Email preview to me** so it is clear that this is a one-time preflight check, not the provider campaign.

### Website interaction recording repaired

- Made the permission-scoped administrator shell practical on portrait and landscape tablets: navigation now uses a touch-friendly side drawer up to 1100px, supports outside-tap/Escape dismissal and keyboard focus containment, compacts crowded header actions, keeps forms within the viewport and contains wide tables in their own scroll area.
- Corrected the first-party funnel-event insert so searches, provider profile opens, contact actions and confirmed outcomes retain the complete database parameter list instead of failing silently.
- Added regression coverage for the exact placeholder/value mismatch that caused the lost events.
- Made the VanAssist main search location-first on desktop and mobile. It resolves the nearest town without submitting, while any typed town, suburb or postcode immediately clears GPS coordinates and remains authoritative.
- Reordered the administrator dashboard around queues needing action and website demand. Inventory totals, audit detail and scheduled tasks remain available in compact disclosure panels instead of competing with launch work.
- Added data-freshness timestamps, search-success interpretation and a daily traffic pulse to Website Insights; provider reporting is now isolated to the active brand.
- Anchored town/suburb/postcode suggestions directly below their input at the same width, with compact touch-friendly rows, internal scrolling and clear keyboard focus on both hero and standard forms.

### Consolidated enterprise workstream

- Established Assist Platform Enterprise as the single product and governance
  direction for VanAssist, TowSmart, TrailerWise and LocalTorque.
- Consolidated product, architecture, UX, backlog, engineering and operational
  documentation into one version-controlled project memory.
- Continued the current UX redesign as the official Design System foundation.
- Organised delivery into Platform, Experience, Brands, Data, Infrastructure,
  Operations and Commercial streams.
- Continued unified admin, Data Sources and Data Intelligence work without
  creating a competing implementation.
- Completed CORE-004's shared membership catalogue and honest provider dashboard
  state. Paid selection and charging remain disabled pending COM-004 acceptance.
- Completed INF-003's fail-closed environment and integration configuration
  contract, corrected the free-plan example and documented secret rotation.
- Added OPS-006's repository-backed living documentation system with seven
  guide sections, global search and audience/brand/module/version filters,
  contextual dashboard Help links, mobile/accessibility treatment and a What's
  new view. Administrator/developer/API material remains behind the admin role
  gate; public help exposes only customer/provider and release information.
- Added CI and pull-request enforcement so changed public, customer, provider,
  administrator or API behaviour must update the matching registered guide and
  these release notes. No migration or environment change is required.
- Replaced browser-dependent provider import continuation with a locked server
  worker that resumes national screening, merges safe unclaimed duplicates,
  publishes only evidence-confirmed listings and reports processable versus
  review-required counts. Migration 083 registers the scheduled task.
- Corrected provider campaign scope to use the same canonical brand categories
  as the live directory. VanAssist now prepares factual and consent-gated
  marketing drafts for every active service category, validates sendable email
  addresses and exposes up to 250 current campaigns.
- Reworked VanAssist's phone first screen around immediate service/location
  search, current-location access and touch-safe traveller shortcuts over the
  approved navy travel treatment. Search results now show returned, located
  providers as numbered OpenStreetMap pins tied to the accessible result list;
  each pin opens the exact provider summary, list link and available directions
  action. Phones default to compact list rows with a List/Map control, preserve
  a prominent nearby Places to stay path, separate Featured and related-service
  results, and keep the list as the no-JavaScript/map-failure fallback.
- Added direct map manipulation after users select Map: touch drag, two-finger
  pinch zoom, mouse-wheel and keyboard pan/zoom, accessible zoom and fit-result
  controls, plus a collapsible and pointer/keyboard-movable provider summary.
  Supporting trust/distance copy now follows the results instead of interrupting
  the compact search and list/map task flow.
- Removed the legacy minimum card height from compact provider results and tightened spacing, avatars, badges and actions on desktop and mobile. Phone actions retain practical touch targets while each result consumes materially less vertical space.
- Added a compact listing-accuracy notice to the VanAssist homepage, results
  and Places to stay, with direct disclaimer and correction links. Public
  contact actions now use only explicitly public phone/email fields; an
  unclaimed listing can no longer expose private canonical contact data.
- Changed unclaimed-profile handoff into a listing-specific claim/correction
  request. Authority remains subject to review. Provider registration now
  separates request-related contact from optional, unticked promotional email
  consent, and the provider workspace uses grouped desktop navigation plus a
  compact phone menu.
- Replaced the provider-acquisition image containing invented-looking business
  signage and contact details with a neutral, unbranded caravan service scene.
- Corrected production and maintenance workflows to run the safeguarded
  provider worker non-interactively inside the existing application container,
  avoiding the remote password prompt that interrupted the previous release.
- Corrected the production release result so it no longer fails after a
  successful live deployment by attempting Docker Compose through the
  deliberately restricted deploy account. Provider imports continue through
  the root-owned five-minute cron task, with current counts in Provider data.
- Added a focused PR & Outreach Hub for clubs, peak bodies, manufacturers,
  dealer/rental networks, park groups, tourism organisations, touring bodies and
  publications. Imports remain research-only until a human verifies the current
  official source, published role, direct relevance and absence of contrary
  warnings. Organisation campaigns select one target type, use role-matched
  copy, retain recipient evidence, honour suppression and progress through the
  existing test, 25-recipient pilot and reviewed daily limits. Automatic sending
  and member/customer-list ingestion are not provided. The controlled release
  now consolidates growth tools behind one navigation entry, preserves review evidence and append-only queue/transport/outcome history, and no longer presents queue completion as proof of delivery.
  loader idempotently places the initial 63 official-source targets directly in
  the production register as research/held records; no manual upload and no
  automatic eligibility or delivery is required.

Production status: not released. A dated release entry requires a passed Platform
Quality Gate and verified deployment record.

### Ask VanAssist reliability candidate (2026-08-03)

- Expanded natural-language validation and routing from a partial allowlist to
  the complete seeded VanAssist service catalogue.
- Added common fault-language coverage across caravan systems, appliances,
  body repairs, roadside help, inspections, parts and travel essentials.
- Added a bounded general-repair fallback for unclear but clearly caravan/RV
  fault requests; unrelated general-AI questions remain rejected.
- Linked privacy-safe Ask questions, interpretations, result counts and returned
  answer summaries through the existing Admin API AI-usage feed for RIC review.
  Raw GPS and provider contact details are not retained, and review never
  changes live routing automatically.
- Added a clearly labelled related-provider fallback over a modestly wider area
  when an understood specialist request has no exact local listing.
- Prevented unresolved or conversationally suffixed town names from degrading
  into Australia-wide results. Proxy-aware rate limits now separate visitors,
  and blocked Ask requests receive a branded recovery page instead of raw text.

## Historical records

- `PRODUCTION_RELEASE_2026-07-22.md`
- `DEPLOYMENT_RECORD_2026-07-23_LOCALTORQUE.md`
- `PRODUCTION_READINESS_AUDIT_2026-07-24.md`

## Entry template

Each release records version/date, commit and checksum, user/provider/admin
changes, brands affected, migrations, environment changes, quality-gate evidence,
validation, deployment result, known issues and rollback target.
