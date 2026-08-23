# Insights and data operations

## Purpose

Review selected-brand website demand, **Demand reports**, **Coverage gaps** and Data Intelligence, global trust/growth records, and platform-owned imports whose mappings explicitly carry a brand.

## Intended users

Administrators responsible for reporting, data quality, regulatory review or connector ingestion.

## Permissions

Website insights requires `demand.view`, with CSV export separately requiring `demand.export`. Data Intelligence requires `data_intelligence.view` or `data_intelligence.manage` for task changes. Trust review uses `regulatory.manage` and/or `campaigns.manage`. Data Sources requires a platform administrator plus the matching `data_sources.*` permission.

## Fields

- **Website insights:** Period, From and To filters. Summary values are Visitors, Page views/pages per visitor, Provider searches/no-results, Provider profiles opened, Contact actions and Confirmed provider uses. **What the numbers say** shows search success, search-to-contact interest and the latest recorded page/funnel activity so an operator can confirm tracking is alive. Daily website pulse, Services wanted, Visitor actions, Provider interest, Most viewed pages, Visitor sources, Devices, demand locations and coverage gaps remain available. Provider interest shows Provider, Result appearances, Profile views and Contact actions; Funnel shows Stage, Count and percentage from previous.
- **Data Intelligence:** State and Category filters; summary shows Active providers, Verification coverage/verified count, Critical opportunities and Population-backed rows. The national heat map includes a recognisable Australia outline, state/territory boundaries and labels, plus keyboard-focusable town/category opportunity points and a score legend. Import quality shows Candidates, Awaiting review, Approved, Merged, Rejected and Possible duplicates. Opportunities show Priority, Location, Category, Providers, Verified %, Population, Per 10k, Score and Action; the action queue shows Priority, Title and Rationale.
- **Trust, rules & growth:** Public sources, Fail-closed review, Checks overdue, 7-day failures and Alert subscribers; source rows show status, Title, Authority and Last checked; capability evidence shows Provider, Capability, Jurisdiction, filename/status and Review note; campaign review shows Campaign, advertiser, Brand, daily/total budgets, target count, destination and Approved CPC; alert audit shows Created, Source, Recipient, Status and Reason.
- **Data Sources:** connector cards show status, usage/limit, cost, credential hint, New API key, Daily request limit, Daily AUD budget and Enable. Gap finder fields are Connector, mapped Category/query and Location. Schedule fields are Name, Connector, Mapping, Location, Frequency and Enabled. Mappings show Platform category, Connector query and Active; coverage shows Category, Active and Verified; jobs show Source, Query, Status and Found; schedules show name, frequency and enabled/paused. Import review shows Pending total, Ready for automatic processing, Need evidence or a decision, and recorded blocking reasons. Import-review fields are enumerated in **Providers and directory**.
- **Government datasets:** catalogue rows show Publisher, Title, Method, Trust, Enabled and Last import. Add/edit fields include dataset key, publisher, title, coverage, record types, licence, attribution, trust policy, fetch method, connector, landing URL, CKAN `package_api_url`/`resource_id`, field mappings, optional filter field/value and row limit. Facility import review shows name, facility type, dataset, locality/address/coordinates, duplicate hint, review notes, per-row Approve/Reject and bulk Approve/Reject selected.

## Actions

- **Website insights:** **Apply**, **Export summary**, open detailed provider reporting, **Inspect providers**, **Conversion funnel**, tracking settings/feature-flag links, provider links and available CSV exports. Coverage and map tables are display-only.
- **Data Intelligence:** **Apply**, **Send to import workflow**, **Continue import**, **Complete**, **Manage data sources** and **Open review queue**.
- **Trust, rules & growth:** **Check next 20 due sources**, **Confirm current**, **Retire**, **Verify** / **Reject** capability evidence, and **Activate** / **Reject** a campaign using Approved CPC.
- **Data Sources:** **Queensland coverage**, **Open review queue**, **Government datasets**, **Save secure settings**, **Find missing providers**, **Save schedule**, save each mapping and **Run server processor now**. The worker continues staged screening, safe duplicate links and evidence-confirmed publication after the browser closes. Import-review actions are enumerated in **Providers and directory**.
- **Government datasets:** **Add dataset**, **Edit**, enable toggle, **Import fixture** (demo rows) or **Fetch** (enabled live rows), then **Facility review** to approve into `traveller_facilities` or reject. Never publishes into caravan parks. Populate and approve before enabling the Ask traveller-facilities flag on Assist AI Search.

## Workflows

Start with a selected-brand insight, create a task where follow-up is justified, then hand an import opportunity to Data Sources. Treat Trust, rules & growth as global despite the workspace header. Keep connector credentials in the encrypted settings workflow. Review candidate provenance and duplicate signals before promotion.

For VanAssist, the same aggregate Website Insights data is emailed daily to
`support@vanassist.com.au` for the preceding Brisbane calendar day. The email is
an operational summary, not a replacement for the date filters and exports on
this page. Technical paths (including admin, API, health checks and static
assets) and explicitly identified synthetic monitoring are excluded from public
traffic totals. The email includes repeated no-result searches as an enrichment
queue; it never auto-publishes invented providers or facilities. A low/no-traffic
warning can indicate genuine low use or a tracking problem; check the latest
page-view time and scheduled-task state before drawing conclusions.

## Examples

A zero-result service/location pattern can become a Data Intelligence task and then a scoped connector run. Candidates with adequate independent evidence can be advanced by the server worker. Restricted-source rows remain temporary review records until a lawful independent source supports publication.

## Common mistakes

- Comparing brand totals without confirming the workspace and date range.
- Assuming Trust, rules & growth is workspace-filtered.
- Treating aggregate analytics as raw customer records.
- Running broad imports without category, location, limit and review planning.
- Assuming a successful source fetch makes its claims verified.
- Assuming every pending discovery row can lawfully become a permanent listing. The status panel distinguishes safe automatic work from records missing independent evidence or retention rights.

## Related pages

See **Providers and directory** for promotion into canonical records and **Growth and campaigns** for reviewed outreach.

## FAQ

**Are anonymous session IDs shown?** No. The administrator reporting service returns aggregate counts rather than raw anonymous identifiers.

**Can any administrator store a connector key?** No. Data Sources combines a specific permission with a platform-admin role gate.

## Version introduced

Current repository baseline.

## Last updated

2026-08-16 (VanAssist daily website performance email and operational interpretation).

## Owner

Assist Platform product and engineering.
