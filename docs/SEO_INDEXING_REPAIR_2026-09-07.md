# Indexing repair evidence (OPS-012)

Public checks on 7 September 2026 found 17,758 URLs in VanAssist's sitemap.
The live robots prefix `/provider` blocked 7,196 `/providers` URLs and the
separate `/provider-terms` page. Restrict private rules to the exact route,
its query strings and its descendants. The private-launch block remains.

All 338 sitemap entries outside individual provider/park profiles were checked
for HTTP status, canonical URL and robots metadata. Nine town pages returned
200 with matching canonicals but explicitly declared noindex:

- canberra
- dubbo
- alice-springs
- katherine
- mount-gambier
- port-augusta
- mildura
- bunbury
- darwin

The sitemap query wrongly let launch/featured flags override noindex. Match
the existing page controller by requiring active status and noindex=0. Preserve
the exclusion on the page itself. A database-backed test covers all 16
combinations of active, noindex, launch and featured flags in an isolated
in-memory database. Behavioural robots tests cover public/private URL boundaries
and both indexing-off configurations.

Sample provider and park profiles returned 200 with matching canonicals and
indexable metadata; the complete 17,419 individual profile URLs were not crawled.
HTTP and www homepage redirect chains on both sites resolved successfully.
The email alerts do not identify the individual redirect-error or
Google-selected-canonical URLs. No speculative redirects or canonical changes
are justified by these checks.

No migrations, credentials, environment or rendered UI changes. Shared robots
behaviour also benefits TowSmart and TrailerWise. Architecture: existing shared
controller and brand URL helper. UX: no visual or interaction changes. Business:
restore crawler access to public directory content without publishing unreviewed
towns. Engineering and release gate require CI results on the exact candidate.

Deploy through the reviewed immutable production workflow. Rollback uses the
preceding release, which restores the crawl/sitemap defects. After release,
check the generated robots and sitemap, private-route authentication, health
and readiness on all affected brands. Google's recrawl/validation remains
outstanding until verified in Search Console.
