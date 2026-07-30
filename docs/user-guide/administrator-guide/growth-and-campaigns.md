# Growth and campaigns

## Purpose

Manage provider prospects, evidence-backed organisation PR targets and brand-recorded staged email campaigns alongside Social Studio assets, while preserving consent, relevance and suppression controls.

## Intended users

Marketing and growth administrators with the matching permission for each tool.

## Permissions

Provider outreach requires `prospects.manage`. Social Studio requires `content.manage`. The PR & Outreach Hub and email campaigns require `notifications.send`. These permissions do not override global email suppression, organisation evidence requirements or campaign-stage rules.

## Fields

- **Provider outreach:** Outreach status and Search filters; rows show Business, Contact, Town, Status and Follow up. The form contains Business name, Contact name, Email, Phone, Website, Base town, Region, Source, Outreach status, Next follow-up date, Services observed, Notes, Consent documented, Consent basis and Consent evidence. Detail shows invitation Email, Sent, Expires and Status, plus contact-log Type and Note.
- **Social Studio:** Campaign name; Purpose (Brand launch, Provider recruitment, Customer service discovery, Education and safety, Community engagement); Design system (Premium editorial, Field guide/educational, Provider spotlight, Launch impact); and Platform/format (Instagram post 1080×1080, story 1080×1920 or profile 1080×1080; Facebook post 1200×630, cover 1640×624 or profile 1080×1080). Library cards show preview, platform, template, dimensions, status, optional campaign name, headline, post copy and any Facebook publish ID/error.
- **Growth & outreach:** headline measures show organisation targets needing review, send-eligible targets, follow-ups due, messages accepted by the configured mail transport and positive outcomes. The target register preserves review basis/evidence and reviewer date. Recent outreach history is append-only across queued, sent, failed, suppressed and human outcome events.
- **Provider email campaigns list:** queue counts for Pending, Sent and Failed; prominent Factual listing checks and Provider marketing summaries show how many addresses can currently be contacted under each boundary. Rows show Title/author, factual or marketing Type, canonical brand service category, Status/stage, Queue state, Live audience and When. A completed campaign means its current reviewed audience has been queued; transport acceptance and failures are reported separately.
- **Campaign compose:** Campaign type, optional Service-family starter, Title/subject, HTML message, Audience, and conditional Town, Region or Service category. Visible types distinguish consented provider marketing, fixed factual listing accuracy and general/customer marketing. Audience choices are everyone opted in, consented providers, providers by category, customers with open requests, by town, by region and by category.
- **Campaign detail/recipient review:** status, delivery stage, type, audience, sent/estimated recipient count, message, tests and recipient Email/Status. Provider review has Search, with email/eligible/held/removed/suppressed summary, Provider, Review status, evidence/consent and Campaign action. Inclusion asks for Basis, Date and Evidence; removal can include a Reason.
- **PR & Outreach Hub:** headline counts for researched, review-required, eligible, held/do-not-contact and contacted organisations; filters for organisation, role, email, status, target type and state; official website and source links; source-check date, published role, publication context, relevance rationale, safety flags and review evidence. Target types include clubs and federations, industry bodies, manufacturers, dealer and rental networks, park groups, publications, tourism bodies and 4WD/touring associations.

## Actions

- **Provider outreach:** **Filter**, **Export CSV**, **New prospect**, import a **CSV file**, **Open**, pagination, **Save prospect**, **Edit**, **Send invitation**, and **Log contact**.
- **Social Studio:** **Generate premium asset**, open preview, **Download PNG**, **Approve**, **Archive**, and **Publish to Facebook** only when approval and connection checks allow it.
- **Campaign list/compose:** open **Growth → Email campaigns** directly. The campaign list presents the four-step sending path and labels the next required action for every campaign. Use **Create email campaign**, **Open & send**, **Apply starter**, **Preview recipients**, **Save staged campaign** and **Back**.
- **Campaign delivery:** review the live recipients, use **Email preview to me** once to check the sender, wording, graphics and links, then start the 25-recipient pilot. After reviewing delivery and responses, continue at 50 per day and then 100 per day. Eligible factual listing checks can use automatic continuation only after those reviews; marketing remains consent-gated.
- **Campaign detail:** queue internal test, pilot (maximum 25), 50/day or 100/day when stage rules allow; switch qualifying factual automatic continuation on/off; cancel pending email; and remove, restore, or **Record consent and add** a recipient. Suppressed addresses cannot be restored.
- **PR & Outreach Hub:** download the evidence CSV template, import research for review, filter targets, open official sources, mark research/held/eligible/do-not-contact and record the exact eligibility basis and evidence. Record sent, replied, interested, shared, declined, bounced or opted-out outcomes and an optional next follow-up. Opt-out immediately adds marketing suppression. **Plan organisation campaign** opens a separate organisation campaign; choose one target type and matching message starter.

## Workflows

For prospects, remember that changing workspace does not filter the list. Provider campaign drafts are prepared from the same brand-specific categories used by public search, including categories that currently have no sendable addresses. Organisation research imports never become eligible automatically. Check the official page, the published role, any inbox-purpose limitation and the relevance of the exact proposed message. Prefer peak bodies and federations before individual clubs. Do not import member or customer lists. For every campaign, send an internal test, then progress through the enforced pilot and daily stages. Automatic continuation is unavailable to organisation outreach and provider marketing.

## Examples

A provider marketing campaign includes only selected-brand providers with documented consent and no marketing/all suppression. A factual listing notice uses public source evidence and cannot be edited into promotional copy. A club campaign uses a reviewed secretary or general club role and member-resource wording; an editorial campaign uses only a published editorial route and a genuine story pitch.

## Common mistakes

- Treating a business email as marketing consent.
- Assuming Provider outreach prospects are workspace-filtered.
- Assuming “queued” means “sent”.
- Publishing a social asset before its approved state.
- Enabling factual continuation and then changing the message into marketing copy; the server locks the factual content.
- Treating an official-page email as automatic eligibility without reviewing its role and the proposed message.
- Mixing clubs, publications, manufacturers and tourism bodies in one generic campaign.
- Asking a federation, fleet or publication for member, customer or subscriber addresses.

## Related pages

See **Content, email templates and SEO** for reusable content and **Insights and data operations** for measured demand and campaign review.

## FAQ

**Can suppression be overridden from a campaign?** No. Global suppression wins at audience resolution, queueing and delivery.

**Can provider marketing send automatically?** No. Automatic continuation is restricted to reviewed factual directory notices.

**Can every published organisation email be contacted?** No. A current official source, appropriate role, direct message relevance, no contrary warning, human approval and no suppression are all required. Personal or ambiguous contacts stay held.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30 (PR & Outreach Hub and reviewed organisation campaigns).

## Owner

Assist Platform product and engineering.
