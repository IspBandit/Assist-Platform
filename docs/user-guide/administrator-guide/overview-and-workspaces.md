# Overview and brand workspaces

## Purpose

Use one administration identity to operate the selected brand or, for eligible platform roles, review the cross-brand control centre.

## Intended users

Moderators, administrators, platform administrators, brand administrators, editors, support, finance and marketing users whose assigned permissions allow specific modules.

## Permissions

The `/admin` route group requires authentication and an accepted administrative role. The dashboard then reveals only permission-allowed modules. The control centre requires a super administrator, administrator or platform-administrator role. Changing a hostname or request parameter does not grant a brand.

## Fields

- **Dashboard:** the workspace/launch alert shows the current brand, launch mode and maintenance state. Available stat cards can include New requests, Open requests, Pending providers, Pending documents, Active providers, Active runs, Runs forming, Confirmed runs, Customers, Caravan parks, Provider prospects, Failed emails, Active brand accounts, Campaign assets, Saved towing combinations, Trailer listings, Official rule documents and Motorsport venues. The 30-day Website activity card shows Visitors, Page views, Searches, Profiles opened and Contact actions. Recent activity shows Action, Object, User and When; Scheduled tasks shows Task, Status and Last run. Cards appear only when their data and permission checks allow them.
- **Launch readiness / control centre:** platform totals show Users, Canonical providers, Active memberships and queued/failed email. Launch-gate groups display a label and status. Microsoft Graph certificate details show Transport, Sending mailbox, Certificate expires, Days remaining and the rendered fingerprint/health warning. Each brand card shows name, configured status, primary domain, Listings, Categories and Social assets. Scheduled operations show Task, Status and Last run.
- **Workspace switcher:** lists only brands returned by `AdminBrandAccess`, with brand name, configured status and current-workspace state. Its form submits the target brand plus the constrained `/admin` return path.

## Actions

- **Dashboard:** open permitted stat-card destinations and **Open website insights**.
- **Launch readiness / control centre:** **Open Brand Builder**, **Open Data Intelligence**, **Manage Data Sources**, **Open [brand] dashboard**, and the private-blueprint validation link.
- **Admin header:** open context **Help**, open the workspace menu, select an available workspace (using the cross-domain handoff when required), **View site** where shown, or **Sign out**.

## Workflows

Confirm the workspace name in the header before acting. Use **All brands** for platform status, then switch into a brand for brand-context tools. Do not infer data isolation from the workspace label: several current legacy controllers, including Providers, Customers, Content, Users and Audit, query global tables. The server records workspace switches in the audit log.

## Examples

An administrator with access to VanAssist and TowSmart switches to TowSmart before using TowSmart's brand-keyed verification-token field. The account identity remains the same, while global SEO defaults remain global.

## Common mistakes

- Assuming the sidebar alone is an authorisation control.
- Editing records before confirming the selected workspace.
- Assuming every page is brand-filtered because the header shows a workspace.
- Treating LocalTorque's configured workspace as proof of public launch.

## Related pages

Continue with **Providers and directory**, **Insights and data operations**, or **Users, settings and operations** according to your permissions.

## FAQ

**Why is a navigation item missing?** The required permission, role or brand module is not available.

**Does switching workspace sign me into a different account?** No. It changes trusted brand context for the same administration identity.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
