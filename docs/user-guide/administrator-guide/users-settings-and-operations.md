# Users, settings and operations

## Purpose

Manage global users and assigned roles, inspect the global audit trail, change mostly global settings, and use tightly restricted backup or maintenance tools.

## Intended users

Administrators responsible for access or configuration, and super administrators responsible for recovery operations.

## Permissions

User management requires `users.manage`; export additionally requires `users.export`. Audit requires `audit.view`; settings require `settings.manage`. Backups and maintenance require a super administrator in the controller, regardless of whether a link is visible.

## Fields

- **Users list:** Status, Role and Search (name, email or phone) filters; rows show Name, Email, Roles, Status and Last login.
- **User form:** Name, Email, Phone, Status, Marketing opt-in, the visible role checkboxes, Internal notes and, for a new user, the option to send a password reset. User detail shows phone, marketing opt-in, email verification, last login, created date, roles, linked accounts, internal notes, consent history (Type, Granted, Document version, When) and recent login activity (When, Result, IP, Device).
- **Audit log:** Action and Search (object/value) filters; rows show When, User, Action, Object and IP.
- **Settings:** General fields are Site name, Tagline and Free launch message. Contact/business fields are Public contact email, Public contact phone, Legal name, Business structure, ABN, Facebook URL and Business address. Email delivery fields are SMTP host, Username, Port, Encryption, Password, From address and From name. Launch/availability fields are Launch mode, Maintenance mode and Maintenance message. Analytics has the first-party page-view toggle. Production readiness is displayed separately from the Demo data section.
- **Backups:** rows show File, Size and Created.
- **Maintenance:** Town coverage shows State, Towns, Local 0, Local 1–2, Local 3+, Serving and No serving. The page also shows database, town, major-city, website-page, email-template, claim-invite-template and provider-refresh status; provider refresh exposes a **Scan OpenStreetMap** checkbox.

## Actions

- **Users:** **Filter**, **Export CSV**, **New user**, **Manage**, pagination, **Edit**, **Save changes** or **Create user**, **Suspend**, **Reactivate**, **Send password reset** and **Delete user** where the controller permits them.
- **Audit log:** **Filter**, **Export CSV**, **Previous** and **Next**.
- **Settings:** **Save settings** and the separate destructive **Remove all demo data** action.
- **Backups:** **Generate backup now**, **Download** and **Delete**. A successful
  local backup includes a SHA-256 manifest. Launch evidence requires a verified
  archive no more than 36 hours old; local storage alone is not an independent
  off-site backup or a restore rehearsal.
- **Maintenance:** **Apply database updates**, **Import all Australian towns**, **Promote major cities in search**, **Populate Pages & Blocks**, **Populate missing email templates**, **Sync claim invite from seed**, **Refresh providers (auto)** and **Run national import / backfill matches**. Repeated recovery buttons invoke the same named operations; they do not create additional capabilities.

## Workflows

Grant the least role and permissions needed. These records are not filtered by workspace. A non-super-admin cannot grant or alter the super-administrator role, manage a super administrator, or use super-admin-only operations. Before maintenance, confirm a backup and recovery path, run one bounded operation, and review its audit and result output.

## Examples

An administrator creates a support user with only the required role. A super administrator generates a backup before a controlled maintenance task, downloads and verifies it through the documented operations process, then records the result.

## Common mistakes

- Granting broad roles to solve one missing permission.
- Assuming Users, Audit or Settings is isolated to the selected workspace.
- Attempting to change your own status or delete your own account.
- Treating a generated backup as restorable without verification.
- Running migration or import maintenance without the release and rollback procedure.

## Related pages

See **Overview and brand workspaces**, **Commercial and finance**, and **Current release state**.

## FAQ

**Can an administrator grant super-administrator?** Only an existing super administrator can grant that role or alter another super administrator.

**Why are Backups and Maintenance missing?** Those modules require a super administrator, not merely an administrative route-group role.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
