# Polaris — Manufacturer Portal

- **Status:** Partially implemented (claim-first portal; Phase 7)
- **Date:** 2026-08-01
- **Backlog:** POL-007

---

## Purpose

Enable verified manufacturer organisations to maintain **their** model catalogue
data without admin intermediation — while preserving review, audit and platform
boundaries.

---

## Access model

1. Signed-in user searches existing manufacturer profiles at `/portal/manufacturer/claims`.
2. User submits a claim with contact email and authority evidence (draft-first).
3. Admin approves/rejects via `/admin/polaris/review-queue` (`polaris.manage`).
4. On approval, `claimed_by_user_id` is set; that user can edit models (verification returns to pending).

Object-level checks: claimed manufacturer must match the authenticated user.

---

## Portal sections

| Section | Capabilities | Status |
| --- | --- | --- |
| Dashboard | Claimed manufacturer summary + model list | Partially implemented |
| Claims | Search + submit claim; duplicate hints | Partially implemented |
| Models | List manufacturer models | Partially implemented |
| Model editor | Description + production status (pending verification) | Partially implemented |
| Variant editor | Spec values, floorplans, media | Planned |
| Pricing | Submit RRP rows with effective date | Planned |
| Media library | Upload within `config/uploads.php` limits | Planned |
| Team | Invite org users (platform team UI) | Planned |
| Analytics | Views and saves (7/30/90 day); find impressions planned | Partially implemented |
| Data quality | Completeness gaps for models/variants (ATM, length, berths, price) | Partially implemented |

---

## Edit workflow

```
Manufacturer edit → Draft (optional) → Submit → Admin review → Published
```

Early Phase 7 may require **all** submissions to admin review. Trust tiers
(auto-publish for verified manufacturers) are Planned post-launch.

Manufacturers cannot:

- Edit other manufacturers’ models
- Set tow vehicle data
- Create VanAssist listings
- Delete records (archive request only)

---

## Validation

- Server-side against `polaris_spec_definitions`
- Required fields for publish: category, name, model year, core weight specs
- Media virus/type checks via platform `ImageProcessor`
- Price must include `effective_from`

---

## Notifications

Email/in-app (platform mailer) on:

- Review approved/rejected
- Claim verified
- Stale price reminder (Phase 9)

---

## UI

Reuse provider portal shell patterns where possible — Polaris branding via
`DESIGN_SYSTEM.md` tokens inside shared portal layout.

---

## Implementation status

| Item | Status |
| --- | --- |
| Claim → manufacturer link | Planned |
| Portal routes | Planned |
| Variant editor | Planned |
| Review queue integration | Planned |
| Auto-publish trust tier | Planned (post v1) |

---

## Related documents

- [DEALER_PORTAL.md](DEALER_PORTAL.md)
- [ADMINISTRATION.md](ADMINISTRATION.md)
- [DATA_ACQUISITION.md](DATA_ACQUISITION.md)
