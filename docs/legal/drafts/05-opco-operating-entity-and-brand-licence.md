# Operating company (OpCo) pack — current state and recommended structure

**Effective 18 September 2026.** Published for internal/data-room use; formal solicitor and accountant review may refine structure later. Not legal, tax or accounting advice.  
**Audience:** founder, accountant, solicitor, and acquisition data room.  
**Not for** the public website footer (except accurate operator identity in Terms/Privacy).

---

## 1. Do you need an “OpCo”?

### Short answer

- **To operate the websites today:** you already have an operating entity — **Glen Condren, sole trader, ABN 76 553 821 887**. You do **not** need a separate company merely to publish Terms/Privacy.
- **To scale, employ, raise capital, or sell cleanly:** a dedicated **operating company (OpCo)** (typically an Australian Pty Ltd) is commonly recommended so that:
  - contracts, domains, hosting, and IP sit in one transferable legal person;
  - personal liability is better ring-fenced (never eliminated);
  - brand licences and employment/contractor arrangements are clearer;
  - buyer due diligence is simpler than purchasing from a sole trader.

### What “OpCo” is not

OpCo is **not** a public legal page like Terms or Privacy. It is the **legal person that owns and operates** the Assist Platform business and licences the brands.

---

## 2. Current truthful operating picture (from repository records)

| Item | Current position |
| --- | --- |
| Legal person | Sole trader: Glen Condren |
| ABN | 76 553 821 887 |
| GST | Not registered (owner-finance notes); invoices are not tax invoices |
| Product | Assist Platform Enterprise serving VanAssist, TowSmart, TrailerWise |
| Domains | vanassist.com.au, towsmart.com.au, trailerwise.com.au (+ www) |
| Hosting | BinaryLane VPS (Brisbane) via Docker Compose |
| Edge/DNS | Cloudflare |
| Mail | Microsoft Graph / brand support mailboxes |
| Public operator wording in seeded legal pages | “Glen Condren (sole trader), ABN 76 553 821 887” |
| Brand `legal_name` config defaults | Trading labels (“VanAssist”, “TowSmart”, “TrailerWise”), **not** separate companies |
| Excluded brands | LocalTorque, Polaris (retired/excluded from sale/runtime) |

Until incorporation + assignment, **the sole trader is the OpCo-equivalent**.

---

## 3. Recommended target structure (for solicitor/accountant to implement)

```text
[Founder / shareholders]
        |
        v
 Assist Platform Pty Ltd  ← OpCo (proposed name — confirm availability)
        |
        +-- owns: source code, trademarks/brand assets, domains, customer contracts
        +-- operates: VanAssist, TowSmart, TrailerWise as trading names / brands
        +-- licences brands internally (optional Brand Licence schedule)
        +-- contracts: hosting, Cloudflare, Microsoft, Google, AI, payments
```

### Optional HoldCo / IPCo split (only if advised)

Some sale or investment structures use:
- **IPCo** holds IP and licences OpCo; or
- **HoldCo** owns OpCo shares.

For a small Australian multi-brand SaaS/marketplace at current scale, a **single OpCo Pty Ltd** is usually enough unless tax/legal advisers recommend otherwise. Do not create complexity without a reason.

---

## 4. Trading names / brand registration checklist

After OpCo exists (or while remaining sole trader):

1. Register business names for **VanAssist**, **TowSmart**, **TrailerWise** (and “Assist Platform Enterprise” if used commercially) against the correct ABN/ACN.
2. Align website footer and legal pages: “VanAssist is a trading name of [Legal Person], ABN …”.
3. Keep env `*_LEGAL_NAME` consistent with the registered legal person or approved trading style.
4. Update Stripe/Microsoft/Google/Cloudflare account legal entity names when transferring.

---

## 5. Brand licence (internal schedule — template)

**Licensor:** [OpCo legal name]  
**Licensed brands:** VanAssist, TowSmart, TrailerWise (logos, wordmarks, domain uses)  
**Territory:** Australia (expand if needed)  
**Term:** perpetual while OpCo operates the brands, or until sale assignment  
**Scope:** non-exclusive internal right to operate public websites and marketing under the brands  
**Quality control:** OpCo may set brand/UX standards  
**No separate royalty** while brands are operated only by OpCo  
**On sale:** licence terminates or assigns with the business assets as the SPA provides  

*(If everything is owned and operated by one OpCo, this licence is often a short schedule in the constitution/IP register rather than a heavy agreement.)*

---

## 6. OpCo operational charter (one page)

**Purpose:** Own and operate Assist Platform Enterprise and its three public brands.

**Non-goals:** Do not reintroduce LocalTorque/Polaris as active brands without a separate board/product decision and legal review.

**Governance minimums:**
- sole director/secretary appointments recorded;
- bank account in OpCo name;
- all material vendor contracts in OpCo name;
- IP assignment from founder/contractors into OpCo executed before sale marketing;
- privacy/retention approvals owned by OpCo;
- Quality Gate / production changes follow `docs/OPERATIONS_RUNBOOK.md`.

**Financial honesty:**
- GST registration decision revisited with accountant when turnover/credits require;
- marketplace agent vs principal treatment documented if provider funds are ever held;
- no representation that live billing is active until gateway flags and legal wording are on.

---

## 7. Sale / transfer implications

Sale readiness (`docs/SALE_READINESS.md`) already requires:
- asset register;
- IP assignment schedule;
- privacy/subprocessor records;
- domain and account transfer checklist.

**Buyer preference:** acquiring shares in a clean OpCo (share sale) or assets from a sole trader/OpCo (asset sale). Either path needs the IP and contracts paper in files `05` and `06` of this drafts folder.

---

## 8. Decision record (fill in)

| Decision | Choice | Date | Adviser |
| --- | --- | --- | --- |
| Remain sole trader vs incorporate OpCo | ________ | ________ | ________ |
| Proposed company name | ________ | ________ | ________ |
| Share structure | ________ | ________ | ________ |
| GST registration plan | ________ | ________ | ________ |
| Effective date for website legal identity update | ________ | ________ | ________ |

---

## Draft honesty notes

- Do not publish “Assist Platform Pty Ltd” on the sites until ASIC registration and ABN/ACN exist and contracts/domains are transferred.
- Changing legal person mid-flight requires updating Terms, Privacy, invoices, vendor accounts and consent records carefully.
