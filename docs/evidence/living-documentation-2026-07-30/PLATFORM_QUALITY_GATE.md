# OPS-006 Platform Quality Gate

## Architecture — PASS

- Repository-backed Markdown and one PHP registry preserve version control and
  avoid a second CMS or database ownership model.
- Public and operational catalogues share services and views while retaining
  the existing admin role boundary.
- No migration, environment variable or external dependency is introduced.
- Route, permission and current global-versus-brand scope limitations are
  documented rather than hidden.

## UX — PASS

- Search supports query, audience, brand, module and version filters.
- Every admin page receives a route-resolved Help action from the shared layout;
  customer/provider/park layouts receive the matching contextual guide action.
- Playwright passed at 1440 × 900 and 390 × 844 with zero horizontal overflow,
  labelled filters, 44px mobile controls, visible keyboard focus and public
  audience separation.
- Final desktop and mobile screenshots are stored beside this record.

## Engineering — PASS

- Documentation registry validation passed with no missing metadata, required
  section, source file, related article or route errors.
- Focused documentation suite: 19 tests, 50 assertions.
- Full unit suite: 482 tests, 77,463 assertions.
- Full Playwright acceptance: 8 tests passed.
- Composer validation, PHPStan, PHP/JavaScript syntax, dependency audit,
  production dependency install and `git diff --check` passed locally.
- GitHub CI remains the authoritative MySQL 8 migration, seed and integration
  gate before landing.

## Business — PASS

- Customers, providers and administrators gain searchable help without paid
  infrastructure or a separate content system.
- Documentation ownership, version and update date are visible; release notes
  and CI enforcement reduce support cost and stale workflow guidance.
- Public users cannot retrieve administrator, developer or API-guide content.

## Release and rollback

Deploy through the existing production release workflow after protected-branch
landing. No schema or configuration action is required. Roll back to the prior
verified release commit through the same workflow; this removes the new routes,
layout entry points and assets as one code release. Repository documentation
remains available in Git history.
