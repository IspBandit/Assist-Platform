# Rendered acceptance — 22 July 2026

This record documents browser-level acceptance testing performed against the
live VanAssist, TowSmart and TrailerWise production domains. No credentials or
private customer data are recorded here.

## Public user coverage

- 72 desktop and mobile page renders across the three brands.
- Covered home, discovery, provider directory, provider registration, account,
  password reset, contact, about and brand-specific public tools.
- Zero 4xx/5xx responses on applicable routes.
- Zero broken images.
- Zero horizontal-overflow defects at the tested desktop and mobile sizes.
- Zero browser console or failed-request errors.
- Zero brand support-email mismatches.
- All three mobile menus opened and exposed usable navigation.
- TowSmart's live weight calculator accepted a representative combination and
  rendered its result.
- Provider-interest forms submitted successfully on all three brands; the three
  temporary submissions and queued test emails were removed afterwards.

## Provider coverage

A temporary draft provider and provider user were created for acceptance only,
given participation in all three brands, then deleted after testing.

- 70 applicable authenticated provider page renders across desktop and mobile.
- Covered dashboard, profile, services, service areas, documents, licences,
  availability, incoming requests, analytics and service-run management.
- TrailerWise also covered its brand-specific trailer-listing management pages.
- Zero HTTP failures on applicable routes.
- Zero unexpected login redirects or cross-brand access leaks.
- Zero broken images, browser errors or horizontal-overflow defects.
- The service-area coverage selector was exercised after its strict Content
  Security Policy correction; town, region, state and radius controls now switch
  through the trusted external JavaScript bundle.
- Dashboard and profile screenshots were visually reviewed for all three brands
  at desktop and mobile sizes.

## Code and deployment verification

- PHPUnit: 75 tests, 203 assertions; seven environment-dependent tests skipped
  and one pre-existing output-buffer test reported risky.
- PHPStan: no errors with a 512 MB analysis limit.
- Composer manifest strict validation: valid.
- GitHub pull requests 10 and 11 passed CI and were merged to `main`.
- Production release: `edbb26e`.
- A verified database backup was taken immediately before each deployment.
- Containers were recreated successfully, migrations were current and the
  application container reported healthy.

## Scope boundary

This is rendered and functional acceptance, not a claim that the platform has
completed every commercial launch dependency. Microsoft Graph transactional
delivery, independent off-server backup storage, credential rotation, provider
business verification and owner approval for search indexing remain governed by
the production-readiness and current-state documents.
