# Reports, feature flags and system logs

## Purpose

Document three existing global operational surfaces that have routes and controllers even though they are not currently linked in the main sidebar.

## Intended users

Administrators with reporting or feature-control responsibility, and super administrators diagnosing system logging.

## Permissions

Reports requires `reports.view`. Feature Flags requires `feature_flags.manage`. System Logs uses a controller-level super-administrator check rather than `logs.view`. Route-group access or a copied URL does not bypass these checks.

## Fields

- **Reports:** Request funnel shows Stage and Requests; Email queue shows its status counts; Demand by town shows Town, Region, Total, Open and Completed; Demand by category shows Category, Total, Open and Completed; Providers shows Status, Total and Verified; Service runs shows Status, Total and Booked; Traffic — top pages (30 days) shows Route and Views. The controller also prepares the global park summary even though the current view does not render a separate park table.
- **Feature flags:** the table shows Enabled, Flag and Description for every database-backed flag, and separately displays the billing environment state.
- **System logs:** channel tabs come from the logger's available channels. **Show last** offers the visible bounded line-count choices; the controller clamps any value to 50–2,000. Diagnostics show the selected channel's content and file/database storage state.

## Actions

- **Reports:** the visible **CSV** links export Request funnel, Demand by town, Demand by category, Providers and Service runs; **Export requests CSV** exports request rows. Email queue and top-page traffic are display-only on this page.
- **Feature flags:** toggle the Enabled checkboxes and choose **Save flags**.
- **System logs:** **Refresh**, **Check / repair logging**, **Write test entry**, choose a channel, change **Show last**, **Apply**, or use the destructive **Clear this log** action for the selected sanitised channel.

## Workflows

Open these pages by their direct routes only when authorised; absence from the sidebar does not mean the capability is absent. Treat report data as global. Before changing a flag, understand every consumer and its fail-closed default. Before clearing a log, preserve incident evidence. Log repair can use the database fallback when file storage is unavailable.

## Examples

An authorised operator opens `/admin/reports` and exports the supported requests report. A super administrator opens `/admin/logs`, selects the application channel and writes a test entry before considering repair; clearing is a separate destructive action.

## Common mistakes

- Assuming reports are filtered by the workspace shown in the header.
- Assuming a feature flag enables an external dependency such as the billing environment switch.
- Clearing a log before preserving incident evidence.
- Expecting a sidebar link for every implemented route.

## Related pages

See **Users, settings and operations** and **Current release state**.

## FAQ

**Why can I open Reports but not Logs?** They use different gates: `reports.view` versus super-administrator.

**Does enabling a database flag enable billing?** No. The controller explicitly reports the separate billing environment configuration.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
