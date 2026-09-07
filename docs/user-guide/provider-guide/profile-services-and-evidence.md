# Profile, services and evidence

## Purpose

Maintain the provider record you are authorised to operate, including public profile details, services, service areas, availability, documents and licences.

## Intended users

Authenticated provider users and administrators using the provider portal for an authorised provider.

## Permissions

The provider route group requires authentication plus a provider or administrator role. Controllers also enforce provider ownership. Documents remain private and downloads use the same ownership boundary.

An unclaimed public profile offers **Request to claim or correct this listing**. The form identifies the exact listing and requires a business email plus an explanation of the claimant's role and authority. Submitting records a reviewable claim request; it does not prove ownership or grant access. An administrator can request evidence, reject the request, or approve authority and send a secure claim link. Onboarding contact permission is limited to the request. Promotional provider email uses a separate, optional checkbox that is unticked by default.

New businesses registering via **For providers** must search existing listings first (“Is this your business?”) and claim a match when appropriate. Creating a new listing requires an explicit confirmation that none of the matches apply; likely duplicates are held for administrator review and are not auto-published.

## Fields

The profile form supplies the supported business and contact fields. Services reference existing service categories; areas reference existing locations. Documents and licences use the type, expiry and evidence fields shown by their forms.

## Actions

Update the profile, add or remove a service, add or remove a service area, upload or delete a document, download an owned document, save or delete a licence, and manage availability.

Provider navigation is grouped as **Overview**, **Your listing**, **Trust**, **Work** and **Growth**. Phones use one compact provider-menu disclosure so the workspace does not require scrolling through a long horizontal menu.

## Workflows

Complete the profile first, then add only services the business actually performs and areas it genuinely serves. Upload evidence under **Documents** and record licences under **Licences**. Claim approval, account control and administrative verification are separate steps; a successful claim does not create a public verification badge.

The secure claim link is single-use and brand-bound. Final acceptance locks and
revalidates the unused link and unclaimed listing in one database transaction;
replay, concurrent use, a different provider ID or another brand fails without
transferring ownership or granting the provider role.

## Examples

A mobile repair provider can add the relevant service category and towns it serves, then upload current evidence for administrator review.

## Common mistakes

- Adding unrelated categories to gain visibility.
- Treating an uploaded document as verified before review.
- Entering public contact details that the business does not monitor.
- Assuming a claim/correction request immediately transfers control of a listing.
- Assuming onboarding contact permission also consents to promotional email.

## Related pages

See **Requests, runs and growth** for operational work and the Administrator Guide's **Providers and directory** page for review controls.

## FAQ

**Does uploading evidence create a public endorsement?** No. Upload and verification are separate, and verification does not guarantee workmanship or suitability.

**Can I edit another provider by changing an ID?** No. Ownership is enforced by the controller.

Public profile visits and contact actions are attributed to the originating
search when that context is available. This gives providers clearer performance
evidence without exposing a visitor's precise device location or changing
listing permissions.

## Version introduced

Current repository baseline.

## Last updated

2026-09-07.

## Owner

Assist Platform product and engineering.
## VanAssist provider-pack records

The retired LocalTorque brand no longer publishes provider profiles. Legitimate
source evidence and canonical provider identities from its former corpus remain
available through VanAssist where the provider has a relevant VanAssist service.
Existing provider IDs, claims and evidence are preserved; only the retired
brand listing is suspended.
