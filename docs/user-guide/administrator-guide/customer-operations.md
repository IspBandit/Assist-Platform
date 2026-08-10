# Customer operations

## Purpose

Operate the current global **Customers**, **Service requests**, **Matching**, **Service runs** and caravan-oriented **Places to stay / Caravan parks** records. Brand modules control navigation visibility, but these controllers do not make the underlying datasets workspace-scoped.

## Intended users

Support and operations administrators with the relevant module permissions.

## Permissions

Customers require `customers.manage`; requests require `requests.manage`; matching requires `requests.match`; service runs require `runs.manage`; places to stay require `parks.manage`. The sidebar also checks whether the active brand enables each module.

## Fields

- **Customers:** Home town and Search (name, email or phone) filters; rows show Name, Email, Home town, Preferred contact and Requests. Detail shows email, phone, linked user account, Saved locations, Alerts (category plus town/region and active state) and request history (Reference, Title, Urgency, Status, Created). Editable fields are Preferred contact, Home town and Notes.
- **Service requests:** Status and Search (reference, title or email) filters; rows show Reference, Summary, Town, Category, Urgency and Status, with spam/safety chips where applicable. Detail shows reference/time/status/spam/safety, customer name/email/phone/preferred contact/consent, location and vehicle, service and fault, photos, status history and internal notes. Status changes expose a new status and optional note.
- **Matching:** queue rows show Reference, Summary, Town, Category, Urgency, Status and Matches, with Needs you, Auto or Locked state and interested count. Detail shows current-match Provider, Score, Status, Contact and Update; suggestions show Provider, Score and Why plus Verified, Unclaimed, Manual-contact-only or Already matched states.
- **Service runs:** Status and Search (title/provider) filters; rows show Run, Provider, Start, Places, Public and Status. The form contains Provider, Region, Title, Start, End, Register-by, Capacity, Minimum, Travel note, Description, Mobile-only, Public and Featured. Detail shows status/history, Services, Stops (Town and Arrival), Registrations, linked requests and matched candidates.
- **Places to stay:** Status and Search (name/email) filters; rows show Park, Town, Public page and Status. Detail shows claims (claimant/contact/role/evidence/status), contact/managers, documents, status and service-day requests. The form contains Name, Address, Town, Sites, Phone, Email, Website, Booking URL, Stay type, Cost, Verification, Listing plan, Featured/sponsored and Public-enabled fields. VanAssist discovery review additionally exposes upload, job/status filters, Search, Stay type, Duplicate, Evidence URL, Notes, Retention confirmation and Merge provider ID.

## Actions

- **Customers:** **Filter**, **Export CSV**, **View**, pagination, **Back**, open **User account**, and save the displayed customer-edit fields.
- **Service requests:** **Open**, **Match providers** where `requests.match` permits, **Approve**, **Reject**, **Mark spam** / **Clear spam**, save a status with optional note, **Add note**, and download a displayed photo.
- **Matching:** **Match**, open **Full request**, **Add**, **Add & invite**, update match status, and **Release contact** when offered.
- **Service runs:** **New run**, **Open**, pagination, save/edit the run, set status with a note, add/remove services and stops, update registration status, unlink requests, and open matched candidates.
- **Places to stay:** **Open**, **Review discovered stays** only when its VanAssist/platform-role conditions pass, **View public page**, **Edit**, approve/reject a claim, set status, download a document and update a service-day request. Discovery review offers upload/stage/process controls and per-candidate **Create private draft**, **Link existing**, **Hold** / **Restore**, and **Reject**.
- **Facility contributions:** review current resolved facts beside each user suggestion. Approve, edit-and-approve, partially approve, reject or mark duplicate. Approval publishes a source-linked claim through the shared service; rejection does not change public facility data.

## Workflows

Open the request before changing its status. Use Matching to add a relevant provider and follow the match state before releasing customer contact. For stays, verify the record and evidence before approving status or a claim; stay discovery import is limited by its explicit VanAssist platform-admin check.

## Examples

A support user reviews a request, records a note, then an authorised matcher selects a relevant provider. Contact release is a separate action and is audit logged.

## Common mistakes

- Releasing customer contact before the supported matching state permits it.
- Assuming the workspace label filters Customers, Requests, Matching, Runs or Parks.
- Sharing private request images outside the authorised workflow.
- Assuming a disabled module should be reachable because its route exists.
- Treating stay discovery candidates as approved listings.

## Related pages

See Customer Guide **Requests and saved providers**, Administrator Guide **Providers and directory**, and **Overview and brand workspaces**.

## FAQ

**Why is Places to stay absent?** The active brand must enable the parks module; VanAssist owns the stays dataset.

**Can request access be gained by changing an ID?** Controllers retrieve and operate only within their authorised workflow; IDs are not permission tokens.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
