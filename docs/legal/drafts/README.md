# Legal pack (Assist Platform Enterprise)

**Status:** Published for platform use from **18 September 2026**. Formal solicitor review may refine wording later. Not legal advice.  
**Operator:** Glen Condren (sole trader), ABN 76 553 821 887; Queensland; GST not registered (per owner-finance notes).  
**Active brands:** VanAssist, TowSmart, TrailerWise only. LocalTorque and Polaris are excluded.

## Admin downloads

Administrators with `settings.manage` can download PDFs from
**Admin → Legal documents** (`/admin/legal-documents`).

```bash
php scripts/generate-legal-pdfs.php
```

## Public site pages

Authoritative HTML for Privacy, Terms, Provider Terms and Disclaimer lives in
`database/seeds/legal_pages.php` and is merged by Seeder and by
Admin → Maintenance → Populate Pages & Blocks.

| Document | Public URL | Also in PDF pack |
| --- | --- | --- |
| Terms of use | `/terms-of-use` | Yes |
| Privacy policy | `/privacy-policy` | Yes |
| Provider terms | `/provider-terms` | Yes |
| Disclaimer | `/disclaimer` | (CMS only; TowSmart guidance covered in Terms/Disclaimer) |
| DPA | Not a public footer page | Yes |
| OpCo / brand licence | Data room | Yes |
| IP ownership schedule | Data room | Yes |

## Files in this folder

- `01-terms-of-use.md`
- `02-privacy-policy.md`
- `03-provider-terms.md`
- `04-dpa-data-processing-addendum.md`
- `05-opco-operating-entity-and-brand-licence.md`
- `06-ip-ownership-and-assignment-schedule.md`
