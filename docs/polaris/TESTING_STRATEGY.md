# Polaris — Testing Strategy

- **Status:** Partially implemented (Phase 1 unit tests scaffolded)
- **Date:** 2026-08-01
- **Backlog:** POL-001 through POL-009

Follows `docs/TESTING.md` platform requirements.

---

## Test layers

| Layer | Scope | Status |
| --- | --- | --- |
| Unit | Pure services: matching, compatibility, normalisers | Planned / partial |
| Integration | DB repositories, migrations, seed integrity | Scaffolded |
| Feature/HTTP | Public routes render; admin CRUD auth | Scaffolded |
| Contract | TowSmart/VanAssist read boundaries (mocked) | Planned |
| Visual/regression | Hero, model page responsive | Planned (Phase 9) |
| Accessibility | axe/manual WCAG checks | Planned |

---

## Phase 1 priorities (current)

| Area | Tests |
| --- | --- |
| Brand resolution | Polaris host resolves `Brand::polaris()` |
| Homepage | 200 response; hero CTA links |
| `/rvs` list | Filters apply; empty state |
| Model detail | 404 unknown slug; spec table renders unknowns |
| Admin CRUD | Permission denied without role; audit log written |
| Migrations | Forward migrate rollback not required; schema matches DATA_ARCHITECTURE |
| Seeds | Demo flag; no required real manufacturers |

Run: `vendor/bin/phpunit tests/Unit/Polaris/` (path TBD as code lands).

---

## Recommendation engine (Phase 3)

- Fixture variants with known specs
- Assert score bands and penalties for missing ATM
- Assert explain arrays non-empty when score > 0
- No database in unit tests

---

## TowSmart integration (Phase 4)

- Mock compatibility service responses
- Assert disclaimer component present
- Assert no INSERT into tow vehicle tables from Polaris tests

---

## Search (Phase 2+)

- SQL filter combinations return expected counts
- FULLTEXT injection attempts sanitised
- NL adapter: flag off → route absent

---

## Import pipeline (Phase 6)

- SSRF blocked URLs rejected
- AI draft creates import row only, not published variant
- Approve action creates spec values + source

---

## Security regression

- CSRF on admin forms
- Manufacturer A cannot edit manufacturer B (Phase 7)
- Private brand robots noindex header

---

## CI expectations

Same pipeline as platform:

- `composer validate --strict`
- `composer analyse` (PHPStan)
- PHPUnit Polaris suite
- PHP syntax lint on new files

Document skipped checks honestly if environment lacks extensions.

---

## Test data

- Use factories/fixtures — not production dumps
- Demo manufacturers clearly named
- TowSmart/VanAssist mocks use minimal stubs

---

## Quality Gate mapping

| Gate | Polaris evidence |
| --- | --- |
| Architecture | ADRs, boundary tests |
| UX | Responsive screenshots, a11y checklist |
| Engineering | CI green, coverage on matching/compatibility |
| Business | Release criteria checklist |

---

## Implementation status

| Item | Status |
| --- | --- |
| PHPUnit Polaris directory | Scaffolded |
| Migration smoke test | Planned |
| Matching engine unit tests | Planned |
| Playwright/visual | Not planned v1 |

---

## Related documents

- [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md)
- [RELEASE_CRITERIA.md](RELEASE_CRITERIA.md)
- [docs/TESTING.md](../TESTING.md)
