# Demand Analytics — implementation notes

Living architecture/decision record for the **VanAssist User Needs, Provider
Usage and Demand Analytics** capability: a measurable demand-to-outcome funnel
layered on top of the existing platform. Updated as each stage lands.

> Scope: record *what* assistance users need, *where* and *how urgently*;
> distinguish a provider **impression** from a **profile view** from a
> **contact action** from a **confirmed provider use**; measure repeat usage,
> coverage gaps and conversion — without breaking the live site and without
> over-collecting personal data.

---

## 1. Architecture discovered (what already exists)

- **Stack:** PHP 8.1+, bespoke lightweight MVC, MySQL via PDO wrapper
  (`App\Core\Database`). Deploy target: GoDaddy cPanel subdomain, doc root
  `public/`. No external framework — reuse the existing patterns.
- **Routing:** `routes/{web,auth,install,admin,account,provider,park}.php`.
  Public routes carry `headers,csrf`; admin routes add `auth,role,permission`.
- **Views:** PHP templates via `App\Core\View`. **CSP forbids inline JS** — no
  `onclick`/`onsubmit`; behaviour goes in `public/assets/js/app.js`.
- **Auth/RBAC:** `App\Auth\Auth`, `can('permission.slug')`; permissions seeded
  from `database/seeds/data.php` (`permissions` + `role_permissions`).
  Super-administrator bypasses all checks.
- **Migrations:** plain numbered `.sql` in `database/migrations`, applied in
  filename order, tracked in `migrations`; run via `php scripts/migrate.php`
  (or the installer). Statements split on `;`; full-line `--` comments stripped.
- **Seeding:** idempotent `App\Services\Seeder` (`INSERT IGNORE`); a "Sync from
  seed" admin action re-runs seeders on live data.
- **Cron:** `App\Services\CronRunner` runs named tasks with a file lock and
  records outcomes in `scheduled_tasks`. cPanel cron calls `scripts/cron.php`.
- **Feature flags:** `App\Services\FeatureFlag` (DB-backed, request-cached).
- **Analytics today:** `App\Services\Analytics` records first-party, cookieless
  public **page views** into `page_views` (off unless `analytics_enabled`).
- **CSV export:** `App\Services\CsvExport`. **Audit:** `App\Services\AuditLog`.

### Existing demand model (reused, not duplicated)

The funnel is built **on top of** these existing tables:

| Funnel concept                | Existing home |
|-------------------------------|---------------|
| Customer need (location, vehicle, category, fault, urgency, consent, preferences) | `service_requests` (+ `service_request_categories`, `service_request_images`) |
| Provider match + match score + lifecycle status | `service_request_matches` (`suggested→invited→interested→declined→more_info→offered→accepted→unsuitable→reported→withdrawn`) |
| Status changes | `service_request_status_history` |
| In-app messaging | `service_request_messages` |
| Providers, services, service areas, verification | `providers`, `provider_services`, `provider_service_areas`, `provider_verifications` |
| Page views | `page_views` |

---

## 2. What this capability adds (the measurement layer)

The genuinely new layer captures the stages the existing tables do **not**
distinguish: anonymous identity, granular funnel events, search sessions and
impressions, attributable contact clicks, and confirmed outcomes with a
**confidence level** so "providers used" never means "raw clicks".

### Migration `014_demand_analytics.sql` (additive, reversible)

All tables are **new** — no existing table is altered or dropped.

| Table | Purpose |
|-------|---------|
| `tracking_sessions` | First-party anonymous identity (random token, cookie `va_sid`), linked to user/customer on sign-in. No IP stored; UA kept only as a salted hash. |
| `analytics_events` | Granular validated funnel events (no FKs by design, like `page_views`, for fast inserts). `metadata` JSON for small non-sensitive context; `is_excluded` flag. |
| `provider_searches` | One row per meaningful provider search (location, category, urgency, service type, radius, result counts, nearby-fallback / radius-expanded / led-to-request flags). |
| `provider_search_results` | Provider **impressions** per search — rank, match score, distance, sponsored/verified/available, service model. Deduped by `(search_id, provider_id)`. |
| `provider_contact_actions` | Attributable contact clicks: phone/email/website/directions/message/quote/assistance/booking. |
| `service_outcomes` | **Record of truth** for provider usage: one row per `(request, provider)`, status + `confidence` (`inferred→contact_only→customer_reported→provider_reported→both_confirmed→admin_verified`), confirm flags, repeat flag, satisfaction, value band, timestamps. |
| `outcome_confirmations` | Audit trail of every customer/provider/admin confirmation. |
| `demand_gap_feedback` | Structured "why no suitable provider" reasons + optional comment. |
| `customer_followups` | Cron-driven follow-up queue (email default; SMS only if a real provider is configured). |
| `provider_daily_metrics` | Daily per-provider rollups (dashboards never scan raw events). |
| `demand_daily_metrics` | Daily location×category demand rollups. |
| `admin_reporting_snapshots` | Pre-computed heavy dashboard payloads. |

Indexes cover provider, request, user, session, category, location, event type,
event timestamp, status, confidence, completion date, provider+date,
location+category, and search+provider, per the spec.

---

## 3. Event vocabulary (validated)

`App\Services\Demand\ActivityTracker::EVENTS` is the single source of truth.
Unknown names are rejected. Current set:

```
location_prompt_displayed, location_permission_granted, location_permission_denied,
location_manually_selected, location_changed,
category_viewed, category_selected, subcategory_selected,
need_form_started, need_form_step_completed, need_form_abandoned, need_submitted,
provider_search_completed, provider_impression, provider_profile_viewed,
no_provider_found, search_radius_expanded, nearby_provider_selected,
provider_phone_clicked, provider_email_clicked, provider_website_clicked,
provider_directions_clicked, provider_message_started, provider_request_sent,
provider_saved, provider_unsaved,
provider_responded, quote_received, provider_selected,
job_booked, job_completed, job_cancelled, outcome_unknown,
review_requested, review_submitted, demand_gap_reported
```

Each event may carry only non-sensitive structured context (session, user,
request, provider, category, location, search, match, outcome ids; previous
stage; route; small metadata). **Sensitive free text** (descriptions, contact
details) is never copied into events — it already lives in `service_requests`.

---

## 4. Metric definitions (section 23 — used everywhere, shown in admin help)

- **Provider impression** — listing displayed in a valid customer search
  (`provider_search_results`).
- **Profile view** — user deliberately opened the provider profile
  (`provider_profile_viewed`).
- **Contact action** — phone/email/website/directions/message/request-help
  (`provider_contact_actions`).
- **Provider response** — provider responded to a request within VanAssist.
- **Provider selection** — customer identified the provider they intend to use.
- **Booking** — work scheduled (customer or provider confirmed).
- **Confirmed provider use** — customer, provider or both confirmed engagement
  (`service_outcomes.confidence` ≥ `customer_reported`).
- **Completed job** — work reported completed.
- **Mutually confirmed completed job** — both parties (or an admin) confirmed.
- **Repeat customer** — same customer has >1 confirmed use of the same provider
  across separate needs.

The primary "providers used" metric defaults to **confirmed/strongly evidenced**
usage (`service_outcomes`), never raw clicks.

---

## 5. Privacy, consent & retention (section 20)

- First-party only. No third-party analytics; no exact GPS sent anywhere.
- `tracking_sessions` stores a random non-personal token; **no IP**, UA only as
  a salted hash for bot/dedupe heuristics. Cookie `va_sid`, `SameSite=Lax`,
  `HttpOnly`, `Secure` on HTTPS, 180-day expiry.
- Admin/staff and bot traffic are excluded from customer-facing metrics
  (`ActivityTracker::excluded()`), satisfying section 19.
- Retention (cron `analytics_retention`) purges raw `analytics_events` after
  `analytics_retention_event_days` (default 365) and unlinked anonymous
  `tracking_sessions` after `analytics_retention_session_days` (default 540).
  Aggregated daily metrics are kept long-term.
- Aggregated reporting never exposes individual customer identity.

---

## 6. Cron entries (cPanel)

New `scheduled_tasks` (seeded), dispatched by `scripts/cron.php <task>`:

```
# Daily 01:15 — roll up yesterday's analytics into daily metric tables
15 1 * * * /usr/local/bin/php /home/USER/vanassist/scripts/cron.php aggregate_daily_metrics

# Hourly — send due customer outcome follow-ups (delivery lands in a later stage)
0 * * * * /usr/local/bin/php /home/USER/vanassist/scripts/cron.php customer_followups

# Weekly Sun 02:30 — purge raw analytics past the retention window
30 2 * * 0 /usr/local/bin/php /home/USER/vanassist/scripts/cron.php analytics_retention
```

All three no-op safely while the `demand_analytics` flag is off.

---

## 7. Feature flag & rollout

- Flag `demand_analytics` (seeded **off**). `ActivityTracker` and all tracking
  are no-ops until it is enabled in **Admin → Feature flags**, so the migration
  can ship to production with zero behaviour change.
- Permissions `demand.view` and `demand.export` (granted to administrator +
  super-administrator) gate the new admin dashboards/exports (added in a later
  stage).

---

## 8. Rollback procedure

Because 014 is additive, rollback is non-destructive to existing data:

1. Turn the `demand_analytics` flag **off** (instantly disables all tracking).
2. Remove the three cron entries.
3. (Optional, full removal) drop the new tables and de-register the migration:

```sql
DROP TABLE IF EXISTS admin_reporting_snapshots, demand_daily_metrics,
  provider_daily_metrics, customer_followups, demand_gap_feedback,
  outcome_confirmations, service_outcomes, provider_contact_actions,
  provider_search_results, provider_searches, analytics_events, tracking_sessions;
DELETE FROM migrations WHERE migration = '014_demand_analytics.sql';
```

No existing provider/customer/request/analytics data is touched.

---

## 9. Deployment (live site)

1. **Back up** the production database (Admin → Backups, or cPanel export).
2. Deploy code (`scripts/deploy.ps1`).
3. Apply the migration: `php scripts/migrate.php` (cPanel cron/SSH) — adds the
   new tables only.
4. Re-run seeds / "Sync from seed" to register the new permissions, feature
   flag and scheduled tasks (idempotent).
5. Add the cron entries from section 6.
6. Leave `demand_analytics` **off** until the tracking + dashboard stages are
   verified, then enable it to begin recording.

Test the migration against a copy of the database first.

---

## 10. Staged delivery status

| Stage | Scope | Status |
|-------|-------|--------|
| 1 | Migration 014, `TrackingSession` + `ActivityTracker`, feature flag, permissions, cron handlers, this doc | **Done** |
| 2 | Wire search-session + impression tracking + `need_submitted`/profile-view events into the public flow (behind flag) | **Done** |
| 3 | Contact-action attribution redirect endpoints (`/go/{action}/{slug}`) with dedupe; profile buttons routed through them | **Done** |
| 4 | Customer + provider outcome confirmation, confidence levels, repeat-provider, demand-gap feedback, reviews + saved-providers (migration 019) | **Done** |
| 5 | Provider analytics dashboard (own data only; estimated vs confirmed; FY filters) | **Done** |
| 6 | Admin demand/usage/funnel/coverage-gap dashboards + demand map (server SVG) + CSV exports | **Done** |
| 7 | Follow-up email automation (`FollowupService` + tokenised landing), daily aggregation, retention, consent copy | **Done** |
| 8 | Automated + manual tests, deployment/rollback, changelog, summary | **Done** |

### New in stages 2–8

- **Migration `019_reviews_saved_providers.sql`** — `provider_reviews` (one moderated
  review per outcome) and `saved_providers`. Additive/reversible.
- **Services:** `Demand\DemandRecorder` (searches, impressions, contact actions,
  demand-gap), `Demand\OutcomeService` (service_outcomes record-of-truth,
  confidence ladder, repeat detection, reviews, saves), `Demand\ReportingService`
  (provider + platform reporting, AU FY ranges, low-sample suppression),
  `Demand\FollowupService` (auto-enqueue + send + tokenised response).
- **Customer:** `/account/requests/{ref}/outcome` ("Did you use a provider?"),
  saved providers (`/account/saved`, save button on profiles), login-free
  follow-up landing `/followup/{token}`, demand-gap form on no-result searches.
- **Provider:** job-status control on each request, `/provider/analytics`.
- **Admin:** `/admin/demand` (overview), `/demand/providers`, `/demand/funnel`,
  `/demand/coverage`, `/demand/map`, `/demand/export` (CSV, gated by `demand.export`).
- **Cron:** `customer_followups` now delivers via `FollowupService`;
  `aggregate_daily_metrics` and `analytics_retention` already populate/purge.

---

## 11. Contact attribution design (stage 3)

Profile phone/email/website/directions links point at `GET /go/{action}/{slug}`,
which records a `provider_contact_actions` row (deduped per session within 30s)
and then 302-redirects to the real `tel:` / `mailto:` / website / Google Maps
target. Recording is best-effort and wrapped in try/catch — **a tracking failure
never blocks the redirect** (sections 7 & 25). The phone number/email also remain
visible as plain text on the page, so the user can always reach the provider even
if the handler misbehaves.

## 12. Manual test plan

With `demand_analytics` **on** (Admin → Feature flags) and signed out:
1. Search `/find?location=Gladstone` → a `provider_searches` row + one
   `provider_search_results` row per shown provider; refresh → no duplicate
   impressions (unique key). Open a profile → one `provider_profile_viewed`
   event; click Call/Email/Website/Directions → one `provider_contact_actions`
   row each; rapidly re-click → deduped.
2. No-result search → "Help us improve coverage" form posts a
   `demand_gap_feedback` row.
3. Submit a request → `need_submitted` event + `service_requests` row.
4. As the matched provider: set job status → `service_outcomes` row
   (`provider_confirmed`, confidence `provider_reported`). As the customer:
   `/account/requests/{ref}/outcome` confirm completion + review → same outcome
   becomes `both_confirmed`; a `provider_reviews` row appears (pending).
5. Save a provider from its profile → `saved_providers` row → shows in
   `/account/saved`; remove it → row deleted.
6. Provider dashboard `/provider/analytics` shows own figures only; another
   provider login never sees these. Admin `/admin/demand*` pages render; CSV
   export downloads and is audit-logged; a non-`demand.export` admin is blocked.
7. Run `php scripts/cron.php customer_followups` (flag on) → due follow-ups send
   a tokenised `outcome_followup` email; `/followup/{token}` records an outcome
   without login and is single-use.

Security: providers can't open another provider's `/provider/requests/{id}` or
analytics (ownership resolved from the signed-in user); admin demand pages require
`demand.view`; exports require `demand.export`; all POST forms carry CSRF; the
`/go` and `/followup` GET endpoints take no destructive input.

## 13. Privacy & consent copy (mark for legal review)

Add to the privacy policy (admin-editable CMS page) — **review with a lawyer**:

> *We record activity on VanAssist to match you with suitable providers,
> understand where help is needed, improve provider coverage, measure whether
> connections are successful, and follow up on outcomes. We use a first-party,
> randomly generated session identifier (no cross-site advertising) and do not
> send your exact location to third-party analytics. We protect your identity,
> contact details, exact address, vehicle registration, request description and
> photos, and only show aggregated, de-identified figures in reporting. You can
> request access, correction or deletion of your data and opt out of follow-up
> messages at any time.*

## 14. Deployment (live site) — stages 2–8

1. **Back up** the production database (Admin → Backups).
2. Deploy code (`scripts/deploy.ps1`).
3. Apply migrations: `php scripts/migrate.php` (adds `019` only; `014` if not yet
   applied). Or Admin → Maintenance → "Apply pending migrations".
4. Re-run seeds / "Sync from seed" to register the `outcome_followup` email
   template and the new analytics settings (`followup_delay_days`,
   `analytics_retention_event_days`, `analytics_retention_session_days`).
5. Confirm cron entries (section 6) exist; the hourly `customer_followups` now
   sends mail when the flag is on.
6. Toggle `demand_analytics` **on** when ready to begin recording.

**Rollback:** flag off (instantly disables tracking, dashboards no-op cleanly),
optionally drop `019` tables and the `014` tables (section 8). No existing data
is altered by either migration.
