# Assist RIC everyday-management staging rehearsal — 2026-08-04

## Release under test

- Platform commit: `3c7ce0e4d40b4d4d80acc47725ece4fad7979b4d`
- Protected staging workflow: <https://github.com/IspBandit/Assist-Platform/actions/runs/30886474880>
- Environment: isolated, password-protected staging deployment and separate MariaDB
- Production was not deployed or modified during this rehearsal.

## Safety posture

- `ADMIN_API_ENABLED=true` in staging only.
- `ADMIN_API_RESTRICTED=true`.
- `ADMIN_API_MFA_REQUIRED=false` pending human TOTP rehearsal.
- Ask VanAssist, dataset routing, traveller facilities and paid AI remained off.
- A fresh least-privilege RIC service account was created. Its secret was stored in
  Windows Credential Manager and was not committed or included in this evidence.
- Granted service scopes include the Increment G–I additions: `flags:read`,
  `import_candidates:read`, `ops:read`, `categories:read` and `locations:read`.

## Results

The server-side Admin API probe passed:

- `/health`
- `/capabilities`
- `/auth/token`
- `/auth/me`

The actual Assist RIC client then authenticated and passed all rehearsed reads:

- overview and website insights;
- providers, stays, traveller facilities, categories, states and towns;
- claims and corrections;
- facility and provider import candidates;
- RIC import jobs;
- failed emails and failed scheduled tasks;
- feature flags, AI usage summary and search gaps.

One provider package was submitted with `validate_only=true`:

- Import job: `fd8ca1ae-2e74-402c-ac8e-179683b68e9f`
- Item count: 1
- Final status: `validated`
- No staging or publication was requested.

RIC local validation at the exact client main line plus the shutdown cleanup fix:

- 276 tests passed;
- 4 focused main-window GUI tests passed;
- Ruff passed;
- strict MyPy passed;
- offscreen application smoke passed without leaving an HTTP client session open.

## Remaining gates

- RIC currently has one live Admin API base URL. It has not yet demonstrated an
  operator workspace switch across VanAssist, TowSmart, TrailerWise, LocalTorque
  and Polaris. This is a genuine all-brand management gap and must not be marked
  complete until each brand context is selected and isolation-tested.
- Human TOTP enrolment/enforcement rehearsal remains.
- Production `ADMIN_API_ENABLED` remains owner-gated.
- Ask VanAssist, traveller facilities, dataset routing and paid AI production flags
  remain separately owner-gated.

## Verdict

The completed everyday-management endpoints and the RIC client pass the VanAssist
staging rehearsal. Production Admin API enablement is not authorised by this result,
and all-brand RIC management is not yet accepted.
