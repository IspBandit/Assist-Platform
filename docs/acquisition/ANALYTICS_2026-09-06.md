# Production aggregate evidence

Collected 6 September 2026 against deployed `ccb8fb2c96c85dc1760fca0a407627aef8e6728e`.
Queries and JSON are in `../evidence/sale-2026-09-06/`. No personal rows exported.

| Metric | Observed value | Qualification |
| --- | --- | --- |
| Accounts | 4 | Includes administrator/test possibilities; not four paying customers |
| Provider records | 14,098 | All rows; not unique verified active businesses |
| Raw brand listings | 35,693 | Includes 13,807 historical LocalTorque rows; must not be sold as active scope |
| Active/search-visible VanAssist listings | 7,198 | Non-deleted active listing rows; directory relevance filters can narrow this |
| Active/search-visible TowSmart listings | 6,322 | Same query basis |
| Active/search-visible TrailerWise listings | 6,268 | Same query basis |
| Caravan parks/stays | 10,551 | All rows; 10,224 non-deleted in source aggregate |
| Service requests | 0 | No recorded assistance conversion in this table |
| Saved towing combinations | 0 | No saved-use traction established |
| Invoices/payments/refunds | 0 / 0 / 0 | No recurring revenue proved; external statements not supplied |
| Page-view records in last 30 days | 7,855 | Event rows, not unique visitors; legacy table not a qualified conversion funnel |

## Last 30 days: analytics_events, is_excluded = 0

| Brand | Provider searches | Profile views | Phone clicks | Email clicks | Website clicks | Direction clicks |
| --- | --- | --- | --- | --- | --- | --- |
| VanAssist | 123 | 440 | 324 | 2 | 25 | 469 |
| TowSmart | 53 | 429 | 157 | 47 | 119 | 226 |
| TrailerWise | 31 | 162 | 64 | 29 | 44 | 96 |

These are event totals, not deduplicated people, enquiries received or completed
jobs. Historical bot/test filtering and attribution have not been independently
validated. Do not derive a conversion percentage by dividing unrelated events.
Capture the same reporting window and queries at transaction close; record
operator/test exclusions and funnel definitions before making growth claims.

`OPERATING_COSTS.md` remains incomplete: no invoices/account statements were
available. Shared hosting also runs CQDiggings and SignConsole, which are outside
this sale. A buyer needs both an allocated actual cost and a standalone replacement
cost; neither can truthfully be inferred from machine capacity or empty billing tables.
