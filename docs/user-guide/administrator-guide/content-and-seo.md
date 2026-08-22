# Content, email templates and SEO

## Purpose

Maintain the current global CMS and email-template records, plus SEO settings that are mostly global with brand-keyed search-verification tokens.

## Intended users

Editors, marketing users and administrators authorised for content, email or SEO work.

## Permissions

Pages, blocks and FAQs require `content.manage`; email templates and delivery tests require `email.manage`; SEO settings require `seo.manage`. The current brand provides the content and sender context.

## Fields

- **Pages:** list rows show Title (and System badge), Slug, Published and Indexable. The form contains Title, Slug, Body HTML, Published, SEO title, Canonical URL, SEO description, noindex, Open Graph title/image URL/description and Structured data JSON-LD.
- **Homepage blocks:** list rows show Order, Title, Subtitle and Active. The form contains Title, Subtitle, Body, Button label, Button URL, Sort order and Active.
- **FAQs:** list rows show Category, Question, Order and Active. The form contains Category, Sort order, Question, Answer and Active; visible categories are General, For caravan owners, For providers and For caravan parks.
- **Email delivery/templates:** transport status, Waiting/Failed/Sent counts and recent failures with To, Subject, Error and When. Delivery/template test forms ask for a recipient. Templates show Name, Key, Subject and Enabled; edit fields are Name, Subject, HTML body, optional plain-text body and Enabled, with sample-data Preview.
- **SEO:** Site name, Default meta description, Default social share image absolute URL, Google Search Console token, Bing Webmaster token and Allow indexing; current Launch mode is displayed. Most values are global while verification tokens are brand-keyed.

## Actions

- **Pages:** **New page**, **Edit**, **Delete** for non-system pages, **Create page** / **Save page**, and **Back**.
- **Homepage blocks:** **New block**, **Edit**, **Delete**, **Create block** / **Save block**, and **Back**.
- **FAQs:** **New FAQ**, **Edit**, **Delete**, **Create FAQ** / **Save FAQ**, and **Back**.
- **Email delivery/templates:** open **Settings**, **Send delivery test now**, **Send queued emails now**, **View email log**, **Edit**, **Preview**, **Send test email now**, **Save template** and **Back**.
- **SEO:** **Save settings**, and open the generated **sitemap.xml** and **robots.txt** links.

## Workflows

Before editing, recognise that CMS and email-template changes affect their global records despite the workspace header. Preview the rendered result, then publish only when accurate. For email, preserve required placeholders and send a test before relying on delivery. Keep indexing off for private or unready launches.

## Examples

An editor updates a global FAQ and checks every affected public rendering. A mail administrator changes a global template, previews it in the intended brand context and sends a test to an internal address before campaign use.

## Common mistakes

- Editing a global CMS record without checking every affected brand rendering.
- Assuming the workspace isolates CMS or email-template records.
- Removing a required email placeholder.
- Turning on indexing before launch acceptance.
- Treating a successful SMTP connection test as proof that every production email workflow has passed.

## Related pages

See **Growth and campaigns** for campaign staging and **Overview and brand workspaces** for scope.

## FAQ

**Does saving SEO settings launch a private brand?** No. Launch state, domain and operational acceptance remain separate. Most SEO values are currently global; only search-verification tokens are brand-keyed.

**Are email template tests a bulk send?** No. The controller exposes explicit test operations; bulk campaigns use the notification staging workflow.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
