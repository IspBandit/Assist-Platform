# Commercial and finance

## Purpose

Review global membership plan configuration and invoice exports, and operate the global owner-finance ledger where authorised.

## Intended users

Administrators with billing responsibility and finance users assigned to the owner-finance permissions.

## Permissions

Billing pages require `billing.manage`. Finance dashboards require `owner_finance.view`; account changes require `owner_finance.manage_accounts`; journal creation and reversal require `owner_finance.manage_journals`.

## Fields

- **Plans & billing:** provider-plan rows show Plan, Slug, Monthly, Annual and Status. The page also shows billing feature flags, tax review, gateway/billing state, and recent invoices with Invoice, Customer, Date, Status, Total and Paid.
- **Edit plan:** Internal name, Public name, Monthly price (AUD), Annual price (AUD), Trial days, Display order, Description, Terms summary, Active, Public, Open for signup, Recommended and Legacy (closed), followed by the displayed plan-limit values and feature checkboxes.
- **Finance dashboard:** trial-balance rows show Code, Account, Type, Debit and Credit; recent journals show Entry, Date, Description, Source, Status and Amount.
- **Chart of accounts:** accounts are grouped by type and show Code, Name, Tax default and Status. The account form contains Code, Name, Type and Default tax code.
- **Journals:** the list can be filtered by the visible status tabs and shows Entry, Date, Description, Source, Status and Amount. A manual journal contains Transaction date, overall Description, and repeated Account, line Description, Debit and Credit fields. Journal detail shows Account, Description, Tax, Debit and Credit, plus a required reversal Reason when reversal is available.

## Actions

- **Plans & billing:** **Edit**, **Export for Xero** and **Export for MYOB**.
- **Edit plan:** **Save plan** or **Cancel**.
- **Finance dashboard and Journals:** **New manual journal**, open a journal entry, choose a status tab, **Post journal**, **Cancel**, **Back to journals**, and, for an eligible posted entry, **Reverse entry** with a reason.
- **Chart of accounts:** **New account**, **Edit**, **Archive** or **Reactivate**; the form offers **Create account** or **Save changes**, and **Cancel**.

## Workflows

Do not infer brand scope from the workspace header: Billing and owner-finance controllers currently query global tables. Treat plan configuration separately from live charging. For finance, confirm the account list, prepare balanced journal lines, review before posting, and use reversal rather than rewriting posted history.

## Examples

A finance user records an authorised correcting journal and later reverses it through the reversal action if required. The audit-preserving flow does not delete the original journal.

## Common mistakes

- Assuming editable plans mean payment processing is enabled.
- Assuming Billing or Finance is filtered by the selected workspace.
- Using an invoice export as a substitute for a verified accounting reconciliation.
- Changing an account when a journal-only permission was assigned.
- Attempting to erase a posted entry instead of reversing it.

## Related pages

See **Users, settings and operations** for access assignment and **Current release state** for external billing blockers.

## FAQ

**Is live charging enabled?** Repository configuration and production acceptance must both confirm it; current product documentation keeps charging fail-closed until its prerequisites pass.

**Can finance users manage all administration areas?** No. Finance permissions grant only their declared capabilities.

## Version introduced

Current repository baseline.

## Last updated

2026-07-30.

## Owner

Assist Platform product and engineering.
