# Growth and campaigns

## Purpose

Manage global provider-prospect outreach and **Promotions** alongside brand-context Social Studio assets and brand-recorded staged email campaigns, while preserving consent and suppression controls.

## Intended users

Marketing and growth administrators with the matching permission for each tool.

## Permissions

Provider outreach requires `prospects.manage`. Social Studio requires `content.manage`. Provider email campaigns require `notifications.send`. These permissions do not override global email suppression or campaign-stage rules.

## Fields

- **Provider outreach:** Outreach status and Search filters; rows show Business, Contact, Town, Status and Follow up. The form contains Business name, Contact name, Email, Phone, Website, Base town, Region, Source, Outreach status, Next follow-up date, Services observed, Notes, Consent documented, Consent basis and Consent evidence. Detail shows invitation Email, Sent, Expires and Status, plus contact-log Type and Note.
- **Social Studio:** Campaign name; Purpose (Brand launch, Provider recruitment, Customer service discovery, Education and safety, Community engagement); Design system (Premium editorial, Field guide/educational, Provider spotlight, Launch impact); and Platform/format (Instagram post 1080×1080, story 1080×1920 or profile 1080×1080; Facebook post 1200×630, cover 1640×624 or profile 1080×1080). Library cards show preview, platform, template, dimensions, status, optional campaign name, headline, post copy and any Facebook publish ID/error.
- **Provider email campaigns list:** queue counts for Pending, Sent and Failed; rows show Title/author, Type/audience, Status/stage, Queued/sent, Live audience and When. Live audience shows with email, eligible, held, suppressed and removed.
- **Campaign compose:** Campaign type, optional Service-family starter, Title/subject, HTML message, Audience, and conditional Town, Region or Service category. Visible types distinguish consented provider marketing, fixed factual listing accuracy and general/customer marketing. Audience choices are everyone opted in, consented providers, providers by category, customers with open requests, by town, by region and by category.
- **Campaign detail/recipient review:** status, delivery stage, type, audience, sent/estimated recipient count, message, tests and recipient Email/Status. Provider review has Search, with email/eligible/held/removed/suppressed summary, Provider, Review status, evidence/consent and Campaign action. Inclusion asks for Basis, Date and Evidence; removal can include a Reason.

## Actions

- **Provider outreach:** **Filter**, **Export CSV**, **New prospect**, import a **CSV file**, **Open**, pagination, **Save prospect**, **Edit**, **Send invitation**, and **Log contact**.
- **Social Studio:** **Generate premium asset**, open preview, **Download PNG**, **Approve**, **Archive**, and **Publish to Facebook** only when approval and connection checks allow it.
- **Campaign list/compose:** **New broadcast**, **View**, **Apply starter**, **Preview recipients**, **Save staged campaign** and **Back**.
- **Campaign detail:** queue internal test, pilot (maximum 25), 50/day or 100/day when stage rules allow; switch qualifying factual automatic continuation on/off; cancel pending email; and remove, restore, or **Record consent and add** a recipient. Suppressed addresses cannot be restored.

## Workflows

For prospects, remember that changing workspace does not filter the list. For marketing campaigns, record valid consent before inclusion, check the campaign's live audience, send an internal test, then progress through the enforced pilot and daily stages. Factual directory-accuracy notices use server-fixed content and separate suppression scope. Automatic continuation is unavailable to marketing and remains an explicit reviewed option for qualifying factual campaigns only.

## Examples

A provider marketing campaign includes only selected-brand providers with documented consent and no marketing/all suppression. A factual listing notice uses public source evidence and cannot be edited into promotional copy.

## Common mistakes

- Treating a business email as marketing consent.
- Assuming Provider outreach prospects are workspace-filtered.
- Assuming “queued” means “sent”.
- Publishing a social asset before its approved state.
- Enabling factual continuation and then changing the message into marketing copy; the server locks the factual content.

## Related pages

See **Content, email templates and SEO** for reusable content and **Insights and data operations** for measured demand and campaign review.

## FAQ

**Can suppression be overridden from a campaign?** No. Global suppression wins at audience resolution, queueing and delivery.

**Can provider marketing send automatically?** No. Automatic continuation is restricted to reviewed factual directory notices.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
