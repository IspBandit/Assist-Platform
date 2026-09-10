# Indexing repair evidence (OPS-012)

## CQDiggings shared-edge correction

CQDiggings release `1876d5bcb467e2e999c8efb7779a20f75431b46f` is deployed.
Live checks confirmed 340 sitemap URLs without query variants and passing
desktop/mobile map checks. The two legacy navigation aliases still returned
404, including a cache-busting request. Their Apache `.htaccess` rules were
ineffective because production serves CQDiggings through Caddy and PHP-FPM.

Add exact 301 rules in the existing `cqdiggings_site` Caddy snippet, before file
handling, for `/occurrences/site-index.html` and `/occurrences/glossary.html`.
Do not redirect other occurrence paths or private files. CI starts an isolated
Caddy container using the actual production snippet and checks redirects,
destinations, query variants and unchanged 404 behaviour. No production data,
migrations or environment variables change. The correction is not live until
the reviewed Assist immutable release deploys its shared-edge configuration.

## VanAssist evidence

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
Search Console was then inspected directly (report last updated 4 September).
All 145 redirect-error examples are `/go/phone/` actions. All six 404 examples
are `/go/phone/` or `/go/directions/` actions. Exclude the `/go/` action namespace
from crawling; preserve user contact behaviour and existing 404s for unavailable
providers. These are not missing content pages.

The five Google-selected-canonical examples are Mill Creek, Policemans Point,
Little Beach, Heartbreak Hill and Northbrook Mountain campsite `-2` pages.
Google's inspected Mill Creek decision chose the unsuffixed Tasmania page for
the NSW record. Read-only checks of the ten stored records confirm three pairs
are in different states (Tasmania and NSW). Their state was omitted from the
public query and hidden unless a town existed. Join the stored state and show
it in the existing location line and default title/description. Preserve custom
SEO text and self-canonicals. This improves identity signals; it does not prove
Google has changed its selection.

Do not merge these records based on names. Northbrook's two OSM source records
have different coordinates; Heartbreak Hill lacks coordinates/address and needs
identity review. No speculative record merges, redirects or data changes.

No migrations, credentials or environment changes. Shared robots
behaviour also benefits TowSmart and TrailerWise. Architecture: existing shared
controller and brand URL helper. UX: existing stay location line now includes
stored state; desktop/mobile rendering requires validation. Business:
restore crawler access to public directory content without publishing unreviewed
towns. Engineering and release gate require CI results on the exact candidate.

Deploy through the reviewed immutable production workflow. Rollback uses the
preceding release, which restores the crawl/sitemap defects. After release,
check the generated robots and sitemap, private-route authentication, health
and readiness on all affected brands. Google's recrawl/validation remains
outstanding until verified in Search Console.
