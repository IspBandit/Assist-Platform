# Providers and directory

## Purpose

Maintain the current global canonical provider, category and location records, plus brand-mapped Data Sources imports. The provider controller itself is not filtered by the selected workspace.

## Intended users

Administrators responsible for provider quality, directory coverage, claims, evidence or import review.

## Permissions

Provider viewing and editing requires `providers.manage`; status approval requires `providers.approve`; document and licence decisions require `documents.verify`. Categories and locations require their matching `*.manage` permissions. Data Sources additionally requires a platform-level administrator role and the specific `data_sources.*` permission.

## Fields

- **Providers list:** Search (business, email, contact or phone), Base town, Service type, State, Status, Listing source, Verified only and Featured only filters. Rows show Business, Base town, Status, Flags and Plan.
- **Provider form:** Business name, Contact name, ABN, Website, Private email, Private phone, Public email, Public phone, Base town, Region, Service model, Max travel (km), Description, SEO title, SEO description, Show public phone, Show public email, documented promotional-email consent, Consent basis, Consent evidence and Founding provider.
- **Provider detail:** claim Email; structured inbound claim/correction requests with authority evidence and review status; status-workflow controls; evidence-backed provider verification basis and notes; existing and addable Services; Service-area Type, Town, Region, Radius km and Label; verification documents showing Type, File and Status with Verification status and Notes; licences showing Type, Number, Expiry and Status with Verification status; and Internal note body. Duplicate review shows Listing A and Listing B.
- **Service categories:** list columns are Name, Parent, Order and Status. The form contains Name, Parent category, Slug, Icon key, Sort order, Active, Short description, Public description, Customer guidance, Typical issues, SEO title and SEO description.
- **Locations:** states show State, Abbr, Regions, Towns and Status; state fields are Name, Abbreviation, Country, Slug, Active, SEO title and SEO description. Regions have a State filter and show Region, State, Towns, Featured and Status; fields are Name, State, Slug, Active, Featured, Public content, SEO title and SEO description. Towns have State and Region filters and show Town, Region, State, Postcode and Flags; fields are Name, Primary postcode, State, Region, Slug, Latitude, Longitude, Active, Launch town, Featured, noindex, Public content, SEO title and SEO description.
- **Import review:** status tabs plus Search, State, Route hub, Suggested service, Evidence, Possible duplicate, Contact availability and Status filters. Candidate rows show Business, Route, Suggested service, Evidence, Duplicate and Review; review fields are Confirmed service category, Independent evidence URL, Review notes, Retention confirmation and Merge target provider ID. The page also exposes a national Discovery file upload and controlled bulk-decision/confirmation fields.
- **Trailer listings:** Status filter; rows show Listing, Business, Type, Status and Moderate, with the same status choices in the moderation select.

## Actions

- **Providers:** **Possible duplicates**, **New provider**, **Filter**, **Reset**, consent-gated **Send claim invites (25 per batch)** / **Continue bulk invites**, **Manage**, pagination, **Save provider**, **Cancel**, **Edit details**, **View public profile**, **Send claim invite**, approve/reject/request evidence on inbound claim requests, record provider verification evidence, add/remove services and areas, download/review documents, save licence review, and **Add note**. Approving claim authority queues a secure account link; it does not itself verify the provider. Each duplicate pair currently exposes **Review A** only; there is no merge action on that page.
- **Service categories:** **New category**, **Edit**, the rendered active-state toggle, **Save category** and **Cancel**.
- **Locations:** **Regions**, **Towns**, **New state**, **Sync from seed**, **Edit**, **New region**, **New town**, **Filter**, pagination, each form's **Save** action, and **Cancel**.
- **Import review:** **Data sources**, **Continue screening now**, **Stage for safe review**, **Run server processor now**, status tabs, **Apply filters**, **Reset**, controlled continuation/process-all/auto-resolve actions, **Apply to selected**, and per-candidate **Confirm evidence for bulk approval**, **Approve new listing**, **Merge**, **Hold** / **Return to pending**, or **Reject**, plus pagination. The server processor resolves VanAssist through the canonical brand registry and continues independently of the browser; an error banner means the pass stopped safely and no remaining candidate was silently approved.
- **Trailer listings:** **Filter** and **Update** the selected moderation status. This view's module-based sidebar visibility is not equivalent to controller brand enforcement.

## Workflows

Search the global provider table before creating a provider. Review business identity, explicit brand listings and provenance, then add services and areas. For an inbound claim, inspect the recorded role/evidence, request more information where needed, and approve only enough authority to issue the secure claim link. After the account claim is completed, provider verification remains a separate evidence-backed decision; setting it updates the canonical identity and its active brand listings together. For imports, confirm the brand mapping, configure or run a connector, review staged candidates, resolve duplicates, and promote only eligible reviewed records.

## Examples

To invite a business to claim its listing, open the global provider record, confirm its explicit brand listings and public email, then use the claim-invite action. The controller records the action and does not grant ownership merely because an email exists.

Soft-deleted providers, stays and traveller facilities appear under **Directory → Recycle bin** for restore. Admin API service accounts are managed under **Administration → API service accounts** (create, rotate, disable); secrets are shown once and must be stored in an OS vault, never in application SQLite.

Assist RIC facility lists, details and writes use the selected workspace scope.
Shared traveller facilities remain visible, but changing an ID cannot expose or
modify a facility assigned to another brand. Facility-contribution moderation
is limited to workspaces where the stays module is enabled.

## Common mistakes

- Creating a duplicate instead of reviewing existing matches.
- Assuming the Providers, Categories or Locations pages are filtered by workspace.
- Treating imported data as public before review and activation.
- Approving a document without the `documents.verify` permission.
- Assuming a visible status or document-review control guarantees permission; the detail view renders some controls whose controller actions can still return 403.
- Recording marketing consent without dated evidence and a supported basis.
- Treating an approved claim as provider verification, or verifying an unclaimed/inactive provider.

## Related pages

See **Insights and data operations** for coverage opportunities and Provider Guide **Profile, services and evidence** for the provider-side workflow.

## FAQ

**Does a global provider record automatically appear in every brand?** No. The admin record is global, but public brand listings and relevance are explicit.

**Can a brand administrator run Data Sources?** Not unless the controller's platform-admin role check and required permission both pass.

**Are Trailer listings server-filtered by the selected brand?** The current controller checks `providers.manage` but does not enforce brand or module scope. The sidebar module gate is navigation only, so treat this as a known limitation rather than proof of isolation.

## Version introduced

Current repository baseline.

## Last updated

2026-08-24.

## Owner

Assist Platform product and engineering.
