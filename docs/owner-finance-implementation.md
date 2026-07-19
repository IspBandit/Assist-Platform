# Owner Finance — implementation notes

VanAssist platform-owner bookkeeping and financial records. This document is the
living architecture/decision record for the **Finance** module described in the
owner-finance specification. It is updated as each increment lands.

> Scope reminder: this module records **only the VanAssist platform owner's**
> finances (platform revenue, expenses, GST, bank, reporting, accountant
> handover). It is **not** an accounting system for the service providers listed
> on VanAssist, nor for customers. A provider's invoices to their own customers
> never enter the VanAssist owner ledger; only the VanAssist fee/commission is
> VanAssist income, and money held on behalf of a provider is a **liability**
> until settled.

---

## 1. Architecture discovered

- **Stack:** PHP 8.1+, custom lightweight MVC, MySQL, PDO wrapper
  (`App\Core\Database`). Deployment target: GoDaddy cPanel / subdomain, document
  root `public/`. No external framework — patterns are bespoke and must be reused.
- **Routing:** `routes/admin.php` registers admin routes under `/admin` with
  `headers,csrf,auth,role:moderator,administrator,super-administrator`
  middleware. Controller strings resolve to `App\Controllers\…`.
- **Views:** PHP templates via `App\Core\View` (`extend`/`section`/`yield`),
  dotted names map to folders. CSP forbids inline JS — no `onclick`/`onsubmit`.
- **Auth/RBAC:** `App\Auth\Auth`, `can('permission.slug')`, permissions seeded
  from `database/seeds/data.php` (`permissions` + `role_permissions`).
  Super-administrator bypasses checks (`'ALL'`).
- **Migrations:** plain `.sql` files in `database/migrations`, run in filename
  order by `App\Services\Migrator`, split on `;`. Idempotent seeding via
  `App\Services\Seeder`.
- **Money in existing billing:** integer **cents** (`BIGINT`).
- **Audit:** `App\Services\AuditLog` → `audit_logs` table.

### Existing billing layer (012_billing.sql) — REUSED, not duplicated

Phase 1 already provides the **sales / accounts-receivable / marketplace
subledger**: `billing_plans` (+prices/features/limits), `billing_customers`,
`provider_subscriptions` (+history), `provider_entitlements`,
`provider_usage_counters`, `payment_methods`, `invoices` (+`invoice_items`),
`payments`, `refunds`, `discount_codes`, `commission_rules`,
`commission_transactions`, `booking_fees`, `billing_events`,
`billing_webhook_events`, `tax_settings`.

## 2. Key architectural decision (confirmed with owner)

The spec lists `owner_finance_invoices/payments/commissions/...` as new tables.
Building those alongside the existing `invoices`/`payments`/`commission_*`/
`booking_fees` tables would create two competing AR systems. **Decision:** keep
the 012 billing tables as the AR/marketplace subledger and add a **general-ledger
layer on top** that posts journals *from* billing events. New owner-only concepts
(chart of accounts, journals, periods, expenses, suppliers, bank/reconciliation,
settlements, deferred revenue, fixed assets, documents, exports) use
`owner_finance_*` tables.

Other confirmed decisions:

- **Marketplace funds → agent treatment.** Only the VanAssist fee/commission is
  income; the provider's gross is `Provider Funds Held` (liability) until settled.
- **GST not registered** (sole trader, ABN 76 553 821 887). Documents are
  "Invoice" (not "Tax Invoice"); no GST is added. GST can be enabled later from an
  effective date after accountant review. `tax_settings.gst_registered = '0'`.
- **Money in the ledger uses `DECIMAL(19,4)`** (never floats). The posting service
  works in integer units of 1/10000 internally to avoid drift.

## 3. What is implemented so far (Foundation increment)

Migration `013_owner_finance.sql` (additive, reversible):

| Table | Purpose |
| --- | --- |
| `owner_finance_accounts` | Chart of accounts (typed; system/control accounts protected) |
| `owner_finance_tax_codes` | GST/tax code definitions |
| `owner_finance_financial_periods` | Monthly periods (`open`/`soft_locked`/`closed`) |
| `owner_finance_journal_entries` | Double-entry headers (immutable once posted) |
| `owner_finance_journal_lines` | Balanced debit/credit lines (DB CHECK: one side, non-negative) |
| `owner_finance_source_events` | Durable, idempotent posting source events |
| `owner_finance_audit_events` | Append-only finance audit log |

Seeds (`database/seeds/owner_finance.php`, wired into `Seeder::seedOwnerFinance`):
a starter chart of accounts (~85 accounts incl. the agent-model control accounts —
`Provider Funds Held` 2400, `Provider Settlements Payable` 2410, `Payment Gateway
Clearing` 1010, `GST Control` 2210), the seven tax codes, and the current monthly
financial period.

Permissions (`data.php`): `owner_finance.view`, `.manage_accounts`,
`.manage_journals`, `.view_reports`, `.export`, `.manage_settings`, `.view_audit`,
`.close_period`, `.reopen_period`. Granted to `administrator` and (via `ALL`)
`super-administrator`.

Domain services (`App\Services\Finance\…`):

- **`ChartOfAccounts`** — account lookup/create/update/archive; protects system
  accounts and accounts with postings.
- **`JournalPostingService`** — `post()` (balanced, idempotent, period-gated,
  immutable) and `reverse()` (equal-and-opposite). Integer-unit arithmetic;
  sequential `JE-000001` numbering.
- **`FinancialPeriodService`** — monthly periods, `ensureForDate`, close/reopen.
- **`FinanceReport`** — trial balance + dashboard aggregates from posted lines.
- **`FinanceAudit`** — append-only finance audit writer/reader.

Admin UI (under `/admin/finance`, gated per permission):

- **Dashboard** — headline metrics + live **trial balance** (with balanced/out-of-
  balance indicator) + recent journals. GST-not-registered notice.
- **Chart of accounts** — grouped list, create/edit, archive (blocked for system
  accounts and accounts with postings).
- **Journals** — list/filter, detail view, **manual journal** entry (balanced
  posting enforced), and audited **reversal** of posted entries.

## 4. Posting design

Every posted financial event produces a balanced journal entry where
`SUM(debit) = SUM(credit) > 0`. Enforced at three layers: domain validation in
`JournalPostingService`, DB `CHECK` constraints on lines, and the unique
`idempotency_key` on entries (a retried event returns the existing entry id).
Posted entries are immutable — corrections are made by reversal/adjustment, never
by editing or deleting. Posting into a `closed` period is rejected.

Planned standard postings (to be wired in later increments):

- **Subscription/featured/verification invoice (issued):**
  Dr `Accounts Receivable` / Cr relevant income (Cr `GST Control` if GST on).
- **Invoice payment:** Dr `Bank`/`Payment Gateway Clearing` / Cr `Accounts Receivable`.
- **Marketplace payment received (agent):** Dr `Payment Gateway Clearing` /
  Cr `Provider Funds Held` (provider share) / Cr fee/commission income (+ GST if on).
- **Provider settlement paid:** Dr `Provider Funds Held` / Cr `Bank`.
- **Owner expense:** Dr expense / (Dr GST Receivable if on) / Cr `Bank`/`Accounts Payable`.

## 5. Accounting boundaries / provider & customer separation

- Provider→customer invoices, quotes, labour, parts, call-out fees are **never**
  VanAssist income.
- Provider gross marketplace value is a **liability** (`Provider Funds Held`).
- Finance records reference enquiries/bookings by id only and must **not** copy
  private customer messages, images, addresses or personal data.
- The Finance module appears only in the platform-owner admin area, never in
  provider or customer interfaces.

## 6. cPanel / hosting considerations

- Pure PHP + MySQL; no services unavailable on shared cPanel.
- `DECIMAL(19,4)` and `BIGINT` are standard MySQL/MariaDB types.
- `CHECK` constraints are enforced on MySQL ≥ 8.0.16 / MariaDB ≥ 10.2; on older
  MySQL 5.7 they are parsed but ignored — the posting service enforces the same
  rules in PHP, so correctness does not depend on the DB engine version.

## 7. Known limitations / roadmap (not yet built)

The foundation deliberately stops at the general-ledger core. Still to come, as
agreed (build in increments):

1. Finance settings record + GST enablement workflow.
2. Source-event → journal automation from billing (subscriptions, invoices,
   payments, refunds, commissions, booking fees) with idempotency.
3. Lead charges / lead credits; provider settlements; advertising & sponsorship
   billing; deferred-revenue schedules; fixed assets.
4. Owner expenses + suppliers; documents/receipts storage.
5. Bank accounts, CSV import (dedupe by fingerprint), reconciliation.
6. Full report suite (P&L, Balance Sheet, AR/AP ageing, GST summary, etc.).
7. Accountant export pack (manifest + checksums + verification), Xero/MYOB
   mapping adapters, accountant adjustment import.
8. Finance integrity checker, dedicated finance roles, automated test suite,
   and `docs/owner-finance-verification.md`.

## 8. Migration / rollback

- **Apply:** `013_owner_finance.sql` runs automatically via the Migrator in
  filename order; re-seed (`Seeder`) to populate the chart of accounts/tax codes.
- **Rollback:** drop the seven `owner_finance_*` tables (child→parent order:
  `owner_finance_journal_lines`, `owner_finance_journal_entries`,
  `owner_finance_source_events`, `owner_finance_audit_events`,
  `owner_finance_financial_periods`, `owner_finance_tax_codes`,
  `owner_finance_accounts`) and remove the `owner_finance.*` permission rows. No
  existing tables are altered, so rollback does not affect 012 billing data.
