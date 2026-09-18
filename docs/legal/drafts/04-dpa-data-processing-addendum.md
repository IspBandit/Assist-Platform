# Data Processing Addendum (DPA)

**Effective 18 September 2026.** Published for contract/data-room use; formal solicitor review may refine wording later. Not legal advice. Not a public website page.  
**Use as:** contract annex for B2B customers, enterprise partners, park/operator integrations, acquisition data-room diligence, or vendor onboarding where one party processes personal information for the other.

**Platform operator (“Processor” or “Provider” depending on role):** Glen Condren (sole trader), ABN 76 553 821 887, trading as VanAssist / TowSmart / TrailerWise / Assist Platform Enterprise.

---

## When you need a DPA

| Scenario | Need DPA? |
| --- | --- |
| Ordinary consumer uses VanAssist/TowSmart/TrailerWise | **No** — Privacy policy + Terms are the public documents |
| Provider receives leads and handles customer data as independent business | Usually **controller-to-controller** sharing under Privacy policy / Provider terms; DPA optional |
| Enterprise customer engages you to host/process their staff or customer data under their instructions | **Yes** — you act as processor |
| You engage hosting/email/AI vendors | **Yes** — vendor DPA / processing terms with them |
| Share sale / buyer diligence of production personal data | **Data room + transfer agreement**; DPA-style schedules help |

Australian law does not copy the EU GDPR “Article 28 DPA” mandate for every website. Still, a written processing addendum is good practice for B2B clarity, overseas disclosure, and sale readiness.

---

## 1. Definitions

- **Personal Information** has the meaning in the *Privacy Act 1988* (Cth).
- **Customer** means the counterparty named in the Order Form / Master Agreement attaching this DPA.
- **Platform Services** means the Assist Platform software and brand sites as described in the agreement.
- **Subprocessor** means a third party engaged to process Personal Information to help deliver the Platform Services.
- **Security Incident** means a confirmed unauthorised access to, or disclosure/loss of, Personal Information in Processor’s control that is likely to result in serious harm, or that must be assessed under the Notifiable Data Breaches scheme.

---

## 2. Roles

2.1 **Default public Platform model:** for end-user accounts and assistance workflows operated by the sole trader, the operator typically determines purposes of collection and is a **APP entity / controller-equivalent** for that Platform data (subject to Privacy Act applicability).

2.2 **Customer-instructed processing:** where Customer uploads or supplies Personal Information and documents specific processing instructions (for example a private labelled deployment, managed content operations, or integration feed), Processor will process that Personal Information only:
- to provide the Platform Services;
- in accordance with Customer’s documented instructions;
- as required by Australian law (in which case Processor will inform Customer unless legally prohibited).

2.3 Each party remains responsible for its own compliance with the Privacy Act and other applicable privacy laws for its role.

---

## 3. Customer responsibilities

Customer must:
- have a lawful basis to collect and share Personal Information with Processor;
- not instruct Processor to process Personal Information unlawfully;
- configure access permissions appropriately;
- not upload unnecessary sensitive information (health, children’s data, payment card PAN, government identifiers) unless the Services expressly support that use and both parties agree in writing;
- handle end-user notices and consents for Customer-controlled collection points.

---

## 4. Processor obligations

Processor will:
- implement reasonable administrative, technical and physical safeguards appropriate to the sensitivity of the Personal Information and the nature of the Services;
- ensure personnel with access are bound by confidentiality obligations;
- assist Customer with reasonable access/correction/deletion requests relating to Customer-instructed Personal Information, at Customer’s expense if the request is excessive;
- not sell Personal Information;
- notify Customer without undue delay after becoming aware of a Security Incident affecting Customer-instructed Personal Information, and provide information reasonably available to help Customer meet its notification duties;
- upon termination, delete or return Customer-instructed Personal Information from live systems within a mutually agreed period, except copies retained in backups until rotated or required by law.

---

## 5. Details of processing (Schedule A — complete per deal)

| Item | Platform default description (edit per contract) |
| --- | --- |
| Subject matter | Hosting and operation of Assist Platform brand software and related workflows |
| Duration | Term of the agreement plus retention/backup periods |
| Nature and purpose | Account administration, directory/listing operations, messaging/requests, calculators, analytics, security, support |
| Types of Personal Information | Names, contact details, account credentials (hashed), location/town, vehicle/trailer details supplied by users, message content, IP/technical logs, consent records |
| Categories of individuals | End users, provider contacts, administrators, claimants |
| Frequency | Continuous during service use |

---

## 6. Subprocessors

6.1 Customer authorises Processor to engage Subprocessors listed in Schedule B (as updated).

6.2 Processor will impose data-protection obligations on Subprocessors no less protective than this DPA in material respects.

6.3 Processor remains responsible for Subprocessor performance under this DPA.

6.4 Material changes to Subprocessors will be notified by updating Schedule B or by email/site notice with reasonable advance notice where practicable. Customer may object on reasonable privacy grounds; if unresolved, Customer may terminate the affected Services for convenience (prepaid unused fees refundable only if the main agreement says so).

### Schedule B — current engineering candidates

Confirm live enablement before contracting:

1. BinaryLane — hosting/database (Australia / Brisbane documented)
2. Cloudflare — DNS/CDN/proxy
3. Microsoft — transactional email (Graph / M365)
4. Google — Routes / Maps Platform APIs if enabled
5. Optional AI provider (e.g. OpenAI) if AI features enabled
6. Optional CAPTCHA provider if enabled
7. Optional payment processor if billing enabled
8. Optional independent backup provider if configured

---

## 7. International transfers

Personal Information may be processed in Australia and in other countries where Subprocessors operate. Processor will take reasonable steps consistent with APP 8 when disclosing Personal Information overseas, including contractual and due-diligence measures where appropriate.

---

## 8. Security measures (summary)

Measures include, as implemented for the Services:
- access control and role/permission checks;
- password hashing; MFA capability for elevated accounts where enabled;
- TLS for public HTTPS endpoints;
- application secrets managed outside Git;
- logging and rate limiting;
- database and media backups according to then-current operations practice;
- change control via immutable releases where used in production.

This summary is not a certification (ISO/SOC or similar) unless a separate report is attached.

---

## 9. Audits

On written request no more than once per 12 months (unless a Security Incident warrants earlier review), Processor will provide reasonable written information about security practices relevant to Customer-instructed processing. On-site audits require mutual agreement, confidentiality, and Customer bearing reasonable costs. Processor may satisfy audit rights by providing current architecture/security summaries and third-party host attestations where available.

---

## 10. Liability and order of precedence

Liability caps and exclusions in the main agreement apply to this DPA unless mandatory law provides otherwise. If there is conflict: (1) Privacy Act mandatory requirements; (2) this DPA for privacy processing conflicts; (3) the main agreement; (4) online Terms of use.

---

## 11. Governing law

Queensland, Australia, unless the main agreement specifies otherwise.

---

## Signature block (template)

**Processor:** Glen Condren (sole trader) ABN 76 553 821 887  
Signature: ________________  Date: ________  Name/title: ________________

**Customer:** ________________  
Signature: ________________  Date: ________  Name/title: ________________

---

## Draft honesty notes (do not publish)

- If you incorporate an OpCo, replace the Processor party with the company and update ABN/ACN.
- Do not claim GDPR readiness unless you actually target/offer services to EU/UK residents and implement GDPR-specific clauses (SCCs, UK IDTA, etc.).
- Independent off-site backup was recorded as absent in sale evidence at last review — do not promise it in a signed DPA until configured.
