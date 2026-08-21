# Account and My Garage

## Purpose

Use the account area to manage your own saved vehicles, trailers, caravans or motorhomes, their private documents, and compliance preferences. Records are shared through the account only where an explicit workflow permits it.

## Intended users

Signed-in customers managing assets they own.

## Permissions

Authentication is required. Garage and compliance controllers enforce the signed-in user's ownership; knowing another numeric record ID does not grant access.

## Fields

Garage records include an asset type, nickname and type-specific details. Document uploads belong to one owned asset. Compliance records capture the selected vehicle, jurisdiction or job context and optional alert preferences supported by the form.

## Actions

Open an asset, update it, remove it, upload or remove a document, download an owned document, save a compliance outcome, and subscribe or unsubscribe from supported compliance alerts.

## Workflows

Open **Account → My Garage**, create an asset, then open it to update details or attach documents. Use **Compliance** to save a guided outcome. Cross-brand handoff is a separate consented action and does not copy unrestricted private fields.

## Examples

Create a caravan record named “Touring van”, then attach its registration document. The document remains behind the account ownership check.

## Common mistakes

- Treating guidance as certification or legal advice.
- Uploading a document to the wrong asset.
- Assuming another brand can see private asset fields without an explicit handoff.

## Related pages

See **Requests and saved providers** for assistance activity. TowSmart also exposes saved towing combinations when that brand module is enabled.

## FAQ

**Can another customer open my document URL?** No. The download action checks ownership.

**Does removing an asset remove it for every user?** The action applies to the signed-in owner's record.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
