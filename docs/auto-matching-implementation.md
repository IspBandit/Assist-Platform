# Auto-Matching & Dispatch

Automates the "request for assistance → provider" pipeline so the platform can
run unattended: approved requests are scored, the best providers are invited,
contact details are released when a provider bites, and silent invites are
escalated — all with no manual dispatch.

Everything is gated by the **`auto_matching`** feature flag. While it is off the
platform behaves exactly as before (manual admin matching console). Turning it
off at any time instantly reverts to manual operation; the schema changes are
additive and reversible.

## How it works

```
request submitted ─▶ (verify/moderate) ─▶ admin approves ─▶ status: open
        │                                                        │
        │                          auto_matching ON              ▼
        │                                   AutoMatchService::process()
        │                                   • score active providers
        │                                   • invite top N (caps + opt-out)
        │                                   • status → matching
        │                                   • no good match → fallback_admin (emails you)
        ▼                                                        │
provider gets "request invitation" email ◀───────────────────────┘
        │
        ▼
provider marks "interested"
        │
        ▼  AutoMatchService::releaseContactOnInterest()
   customer contact released to provider (consent + cap)
        │
   (silent? CronRunner::provider_followups → escalate() invites next batch)
```

### Trigger points
- **On approval** — `Admin\RequestsController::changeStatus` calls
  `AutoMatchService::process()` when a request is approved to `open`.
- **Cron safety-net** — `update_match_suggestions` runs
  `AutoMatchService::runBatch()` over any `open` request still in
  `auto_match_state = 'pending'` (covers retries / requests opened another way).
- **Escalation** — `provider_followups` runs `AutoMatchService::escalate()` to
  re-invite the next batch when invited providers stay silent past the
  urgency-based window.
- **Contact release** — `Provider\RequestController::respond` calls
  `releaseContactOnInterest()` when a provider marks *interested*.

## Scoring (`MatchingService`)
Transparent, additive scoring; each contribution is recorded in `match_reasons`:
- Primary category **+50**, related categories up to **+20**
- Same town **+30**, services town **+28**, region **+18**, state **+8**
- Distance: ≤50 km **+15**, ≤120 km **+10**, ≤250 km **+4**; within stated travel
  range **+6** (uses `towns.latitude/longitude` + `providers.max_travel_km`)
- Mobile match **+10**, workshop **+5**; verified **+10**, insured **+5**,
  featured **+5**

Auto-invite eligibility additionally requires: score ≥ `auto_invite_min_score`,
provider **not** opted out, provider **available** for the request's
`travel_deadline`, and not already matched.

## Configuration

`config/matching.php` (each value also overridable via `.env`, and the numeric
ones live via `site_settings` key `match_<name>`):

| Key | Default | Meaning |
|---|---|---|
| `auto_invite_max_per_request` | 5 | Providers invited on the first pass |
| `auto_invite_min_score` | 45 | Minimum score to auto-invite |
| `auto_invite_provider_daily_cap` | 8 | Max auto-invites per provider per day |
| `contact_release_max_providers` | 2 | Providers who can get contact before lock |
| `auto_release_requires_consent` | true | Require customer share consent to release |
| `escalation_hours` | urgent 3 / high 8 / medium 24 / low 48 | Silence before escalation |
| `escalation_batch` | 3 | Providers invited per escalation pass |
| `max_total_auto_invites` | 12 | Cap across all passes before handing to admin |
| `cron_batch` | 25 | Requests processed per cron run |

## Privacy & safety guardrails
- Only **post-moderation `open`** requests are ever auto-matched (spam gate intact).
- Auto-invite is **capped** (top N + per-provider daily cap) and skips opted-out
  providers — providers don't get flooded.
- Customer contact auto-releases **only** when the customer ticked "share my
  contact" (`consent_share`) — unless `auto_release_requires_consent` is set false
  — and only to **invited** providers, up to the release cap, after which the
  request `locks`.
- A request with no suitable provider becomes `fallback_admin` and emails the
  admin (`admin_request_no_match`) — it is never silently dropped.
- Every automated action is written to `auto_match_log` and the audit log.

> Tip: to maximise automatic contact release, make the "share my contact with
> matched providers" checkbox default-checked (or required) on the request form,
> or set `MATCH_RELEASE_REQUIRES_CONSENT=false` if your privacy policy already
> covers sharing contact with matched providers.

## Rollout
1. Apply migration `015_auto_matching.sql` and re-seed (adds the flag, off).
2. Confirm the matching cron entries exist (`update_match_suggestions`,
   `provider_followups`) — they are already scheduled.
3. Optional: tune `config/matching.php` / `.env` / `site_settings`.
4. Enable the **`auto_matching`** flag in admin → Feature flags. Backlogged `open`
   requests will be auto-matched on the next `update_match_suggestions` run.

## Rollback
- **Pause:** turn the `auto_matching` flag **off** → fully manual again.
- **Remove schema (optional):** drop the added columns and table:
  ```sql
  ALTER TABLE service_request_matches
    DROP COLUMN auto_invited, DROP COLUMN invited_at, DROP COLUMN match_reasons,
    DROP COLUMN released_at, DROP COLUMN release_reason;
  ALTER TABLE service_requests
    DROP COLUMN auto_match_state, DROP COLUMN auto_matched_at, DROP COLUMN interested_count;
  ALTER TABLE providers
    DROP COLUMN auto_invite_opt_out, DROP COLUMN notify_channel;
  DROP TABLE auto_match_log;
  ```

## Files
- `database/migrations/015_auto_matching.sql`
- `config/matching.php`
- `app/Services/AutoMatchService.php`
- `app/Services/MatchingService.php` (scoring upgrade)
- `app/Services/CronRunner.php` (`update_match_suggestions`, `provider_followups`)
- `app/Controllers/Admin/RequestsController.php` (approval hook)
- `app/Controllers/Provider/RequestController.php` (auto-release on interest)
- `database/seeds/data.php` (flag), `database/seeds/email_templates.php` (admin alert)
- `app/Views/admin/matching/*` (visibility badges + match reasons)
