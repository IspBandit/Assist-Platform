# Production readiness audit — 24 July 2026

## Decision

- **Full indexed/commercial production launch: FAIL.** Do not enable public
  indexing or paid memberships yet.
- **Existing provider-onboarding environment: CONDITIONAL PASS.** It may remain
  online while the external-service and operational conditions below are
  completed.
- **LocalTorque: PRIVATE ONLY.** Its application paths passed candidate testing,
  but it has no approved public production domain, sender identity or launch
  acceptance.

This decision follows the Architecture, UX, Engineering and Business checks in
`PLATFORM_QUALITY_GATE.md`. A successful build or HTTP response is not treated as
commercial launch approval.

## Candidate evidence

### Architecture — pass for the candidate

- The current branch includes the Enterprise brand registry, shared identity,
  canonical provider model, unified administration, brand switcher, Platform
  Control Centre and private Brand Builder foundation.
- VanAssist, TowSmart, TrailerWise and LocalTorque resolve as distinct brands
  without separate applications or duplicate administrator identities.
- The secure admin handoff issued and consumed a one-time token and established
  the destination-brand session without another login.
- Database migrations `001` through `042` applied successfully to a new isolated
  database and remained idempotent when applied a second time.
- Provider-brand backfill integrity was 359/359 in the isolated candidate data.

### UX — conditional pass

- Thirty browser checks passed at 360 px and 1280 px across representative pages
  for all four brands and the unified administration experience.
- Checks covered HTTP success, brand titles, browser errors, failed requests,
  horizontal overflow, broken images and the main page landmark.
- A mobile Control Centre overflow found during the audit was corrected by
  allowing shared grid children to shrink around wide data tables.
- Cross-brand logos in the Control Centre now use same-origin platform assets,
  preserving the strict image Content Security Policy.
- This is automated representative coverage, not a complete manual WCAG 2.2 AA,
  keyboard-only or screen-reader acceptance pass.

### Engineering — pass for the candidate

- Composer validation passed.
- Platform requirements passed.
- PHPStan passed with the project configuration.
- PHPUnit passed: 94 tests, 25,754 assertions, with nine database integration
  tests skipped in the dependency-only local run.
- The nine database integration tests then passed separately against the
  isolated MySQL database with 38 assertions.
- Composer reported no known dependency security advisories.
- GitHub's candidate checks passed unit, integration, migration, seed, encrypted
  secret validation, backfill and production-build jobs before the latest main
  branch merge. The final pushed commit must pass the same checks before release.

### Live environment — operational but behind the candidate

- `vanassist.com.au`, `towsmart.com.au` and `trailerwise.com.au` returned 200 for
  `/healthz` and `/readyz`.
- `www` hosts redirected permanently to their canonical apex hosts.
- `/install` returned 403 and administrator routes required authentication.
- HSTS, CSP, frame protection, MIME sniffing protection, referrer and permissions
  policies were present. Login cookies were Secure, HttpOnly and SameSite=Lax.
- Live release identifier `c841ded` is older than this Enterprise candidate and
  must not be described as containing the candidate until a controlled release
  is completed.
- All three live robots files currently disallow indexing, as required for the
  provider-onboarding posture.

## Blocking conditions for full launch

1. **Transactional email is not production-tested.** No authenticated Microsoft
   Graph/SMTP credential set was available in this checkout. MX, SPF and DMARC
   records exist for the three public brands, but DKIM still requires provider-
   side confirmation. Run a real delivery, queue, retry, bounce and unsubscribe
   test before inviting providers.
2. **GitHub cannot currently perform a controlled production release.** The
   repository has no deployment secrets and no protected production environment.
   Configure the documented VPS host, deploy user, SSH key and known-host values,
   then require production approval.
3. **Automated off-site backups are not active.** A prior manual restore drill is
   documented, but scheduled independent object-storage replication requires its
   endpoint and credentials plus a fresh restore test.
4. **Paid billing is disabled and unverified.** Stripe production credentials,
   signed webhook validation, GST invoice acceptance and failure/recovery tests
   are required before any membership can charge money.
5. **Security ownership work remains.** Install owner-controlled SSH keys,
   disable password/root access as appropriate, rotate previously exposed
   temporary credentials and change any exposed administrator password.
6. **Business and legal acceptance remains.** The owner must accept provider and
   park data quality, critical journeys, terms, privacy wording, disclaimers and
   brand content before indexing.
7. **LocalTorque remains private.** Add and validate its purchased domain, DNS,
   canonical URLs, legal links, sender identity and launch content before making
   it public.

## Required release sequence

1. Push this candidate and require all GitHub checks to pass.
2. Configure protected GitHub staging and production environments and secrets.
3. Configure and test transactional email, including DKIM and suppression paths.
4. Configure scheduled off-site backup replication and complete a restore drill.
5. Deploy to staging; repeat browser, accessibility, email and critical-journey
   acceptance with production-like configuration.
6. Complete owner/security/legal acceptance.
7. Release to production with a database backup, migration check, health probes
   and documented rollback point.
8. Keep billing and indexing disabled until their separate gates pass.

## Audit scope limitation

No irreversible production action was taken. No live database migration, email,
billing transaction, DNS change or deployment was attempted because the required
credentials and approvals were unavailable. Those items are recorded as blockers,
not inferred passes.
