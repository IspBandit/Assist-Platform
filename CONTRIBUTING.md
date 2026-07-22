# Contributing

Start with `docs/START_HERE.md` and obey `AGENTS.md`.

## Branches and commits

- Branch from current `main` using `feature/<name>`, `fix/<name>` or `docs/<name>`.
- Keep commits focused and use conventional prefixes such as `feat:`, `fix:`,
  `test:`, `docs:`, `security:`, `ci:` and `chore:`.
- Do not commit directly to `main`, rewrite shared history or force-push.

## Pull requests

Describe the affected brands, user-visible behaviour, tests, migrations,
environment changes, security/privacy implications, deployment and rollback.
CI must pass and unresolved review comments must be addressed before merge.

## Validation baseline

```bash
composer install
composer validate --strict
composer check-platform-reqs
composer analyse
./vendor/bin/phpunit
composer audit
```

For schema changes, test a fresh migration, a repeated no-op migration and the
database integration suite. For public changes, smoke-test every affected brand,
canonical URL, robots/sitemap behaviour and relevant mobile journey.

## Definition of done

Code, tests, documentation and rollback guidance agree; existing VanAssist
behaviour remains compatible; no private cross-brand or cross-owner data is
exposed; and every failed or skipped validation is reported.

