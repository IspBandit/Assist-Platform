# Intellectual property ownership and assignment schedule

**Effective 18 September 2026.** Published for data-room use; formal solicitor review may refine wording later. Not legal advice.  
**Purpose:** acquisition data room, contractor onboarding, and OpCo capitalisation.  
**Not a public website page** (public sites already include a short IP clause in Terms of use).

**Current legal owner (until assignment):** Glen Condren (sole trader), ABN 76 553 821 887.  
**Active brand assets in scope:** VanAssist, TowSmart, TrailerWise and the shared Assist Platform Enterprise software required to operate them.  
**Excluded from active product/sale scope:** LocalTorque and Polaris (historical/retired), except where technical history must remain in Git/migrations for integrity.

---

## 1. Why you need this

| Need | Why |
| --- | --- |
| Clear ownership | Buyers, investors and banks ask who owns the code, brands and data rights |
| Contractor hygiene | Anyone who wrote code/design without assignment can create title risk |
| Open-source compliance | Lockfile licences must be respected and disclosed |
| Third-party data | Government/industry datasets and APIs are usually licensed, not owned |
| Brand protection | Logos/names should be inventoried; consider trade mark filing |

Website Terms of use alone do **not** replace an IP assignment deed.

---

## 2. Ownership statement (template)

I, **Glen Condren**, sole trader under ABN **76 553 821 887**, state that, except for:

1. open-source components used under their licences;
2. third-party fonts, icons, photographs or libraries under their licences;
3. government and industry datasets/APIs used under their terms;
4. content submitted by users/providers (who retain ownership subject to the Platform licence); and
5. any item listed in Schedule C (exceptions),

I am the legal and beneficial owner of the **Assist Platform Enterprise** software and related materials required to operate **VanAssist**, **TowSmart** and **TrailerWise**, including:

- source code in the Assist Platform repository (and Assist RIC sibling repository to the extent included in a transaction);
- original documentation authored for the Platform;
- original brand design assets created for the three active brands;
- original editorial CMS content authored by or for the operator;
- domain name registrations for the three active public brands (subject to registrar records);
- configuration templates and deployment tooling in-repo (excluding secrets).

Signed: ________________  Date: ________

---

## 3. Founder / contractor IP assignment deed (short form)

**Assignor:** [name]  
**Assignee:** Glen Condren / [future OpCo Pty Ltd]  
**Works:** all present and future copyright and related rights in code, documentation, designs, content and inventions created for Assist Platform Enterprise / VanAssist / TowSmart / TrailerWise.

**Operative wording (solicitor to finalise):**

> Assignor hereby assigns to Assignee all right, title and interest in the Works worldwide, including copyright and the right to sue for past infringements. Assignor waives enforceable moral rights to the extent permitted by Australian law so Assignee can adapt and commercialise the Works. Assignor will execute further documents reasonably requested to perfect title. This assignment is irrevocable and royalty-free. Governing law: Queensland, Australia.

Include: consideration ($1 or employment/contract fees), date, signatures, schedule of repos/paths if needed.

---

## 4. Asset classes register

### Schedule A — owned / intended-owned Platform assets

| Asset class | Examples | Status to confirm |
| --- | --- | --- |
| Application source | PHP app, migrations, tests, admin API | Confirm sole authorship + contractor assignments |
| Sibling RIC tooling | `assist-ric` acquisition/sync tooling if in sale | Separate repo ownership statement |
| Brand marks | Wordmarks/logos for VanAssist, TowSmart, TrailerWise | Confirm designer assignment; consider TM filing |
| Domains | vanassist.com.au, towsmart.com.au, trailerwise.com.au | Registrar owner must match seller |
| Original photography / marketing | Hero images, brand imagery | Licence or assignment on file |
| Original CMS copy | About/legal/help authored in-house | Operator-owned |
| TowSmart catalogue curation | Compiled vehicle/trailer data structures | Provenance vs ownership (see Schedule B) |

### Schedule B — licensed / third-party (not “owned”)

| Asset class | Examples | Action |
| --- | --- | --- |
| Open-source PHP/JS deps | Composer / npm lockfiles | Export licence report for data room |
| Maps / Routes APIs | Google Routes / Maps Platform | Customer/buyer must accept Google terms; keys not transferable as secrets dump |
| Email platform | Microsoft 365 / Graph | Account transfer, not IP ownership |
| Hosting/CDN | BinaryLane, Cloudflare | Service contracts |
| Government geo/facility datasets | Rest areas, toilets, boat ramps, parks data | Keep attribution + licence; no claim of ownership |
| Industry PDFs / directories | BIG4, CPAQ, regional guides used as import sources | Confirm licence to extract/publish; do not transfer unlawful copies |
| User/provider content | Listings, messages, uploads | Users retain ownership; Platform has licence per Terms |
| AI model outputs / models | Third-party model providers | No ownership of underlying models; prompts/logs subject to privacy |

### Schedule C — exceptions / excluded

| Item | Treatment |
| --- | --- |
| LocalTorque / Polaris product IP (if any distinct) | Excluded from active sale package unless separately listed |
| Secrets, production dumps, private customer exports | Never committed; transferred only under SPA + privacy controls |
| Founder’s personal devices / unrelated Condren Digital assets | Exclude unless expressly listed |

---

## 5. Open-source compliance note

Before sale or major distribution:

1. Generate licence inventories from `composer.lock` and any front-end lockfile in use.
2. Confirm no copyleft obligation is being violated by how the Platform is delivered (typical SaaS/hosted use is different from distributing a combined binary).
3. Preserve licence notices where required.
4. Do not re-licence third-party code as proprietary.

---

## 6. Data and content provenance (link to sale Gate D)

IP ownership of **software** is separate from rights to **publish particular listings or datasets**. Maintain:

- provider dataset provenance classification;
- TowSmart catalogue source notes;
- VanAssist stays/facilities provenance and verification labels;
- third-party API terms register.

See `docs/SALE_READINESS.md` Gate D and `docs/acquisition/ASSET_REGISTER.md`.

---

## 7. Public site IP clause (already reflected in Terms draft)

Keep a short user-facing statement that:
- users retain their content and grant a limited licence;
- Platform branding/software remain protected;
- scraping at scale is prohibited.

Do not put this full assignment schedule on the public website.

---

## 8. Transfer checklist (sale)

- [ ] Founder assignment into OpCo (if incorporating) executed
- [ ] All contractor/contributor assignments executed or risk accepted in SPA
- [ ] Domain registrant/admin contacts transferable
- [ ] GitHub org/repo ownership transferable
- [ ] Brand asset source files packaged
- [ ] OSS licence export attached
- [ ] Third-party data/API licence register attached
- [ ] Excluded LocalTorque/Polaris schedule attached
- [ ] SPA schedules match this register

---

## Draft honesty notes

- Repository history may include multiple contributors; title is only as strong as assignments and employment law defaults. Have a solicitor review Git contributor history if a sale is imminent.
- Do not assert trade mark registration if marks are not actually registered with IP Australia.
- Industry guide PDFs present under `data/sources/` are **source materials**; publishing extracted park data requires a clear licence position — do not treat “we have the PDF” as ownership.
