# Operating-cost schedule

Workstream: COM-005. Status: OPEN, invoice and account evidence still required for
services not yet verified below.
Currency: AUD; GST treatment is explicit where known. Unknown costs are not zero.

## Verified current infrastructure invoice

A paid BinaryLane/Mammoth Media tax invoice received 22 August 2026 covers the
billing period 21 July 2026 to 21 August 2026 for `server.vanassist.com.au`:

| Line item | Ex GST | GST treatment |
| --- | ---: | --- |
| Standard VPS: 2 vCPU, 4 GB RAM, 60 GB disk, 3000 GB transfer | $19.60 | taxable |
| Provider backup option: daily backups retained for 2 days | $6.00 | taxable |
| **Subtotal** | **$25.60** | |
| GST | **$2.56** | |
| **Paid total** | **$28.16** | incl. GST |

The invoice is retained in the seller-controlled mailbox/transaction evidence and
must not be committed to this public repository. The current VPS also hosts
non-Assist workloads, so **$28.16 is the gross shared-host monthly invoice, not a
validated standalone Assist allocation**. A buyer migration/standalone hosting
cost must be priced separately or an allocation method agreed.

The provider's two-day daily-backup option is useful local/provider recovery but
is **not** the independent encrypted off-site backup required by the sale gate.

## Cost register

| Service | Evidence/source | Monthly AUD | Renewal/commitment | Transfer treatment |
| --- | --- | ---: | --- | --- |
| BinaryLane VPS compute | Paid 22 Aug 2026 invoice, 21 Jul-21 Aug period | $21.56 incl. GST ($19.60 ex GST) | Monthly evidence; current invoice period recorded | Current host is shared; transfer account or price a clean buyer host |
| BinaryLane 2-day daily backup option | Same paid invoice | $6.60 incl. GST ($6.00 ex GST) | Monthly evidence; 2-day retention | Do not treat as independent off-site recovery; buyer may replace |
| Current shared BinaryLane invoice total | Same paid invoice | **$28.16 incl. GST** | Monthly evidence | Gross current-host cost only; Assist allocation/standalone buyer cost still required |
| Three included domains | Registrar invoices per domain | Unknown | Expiry per domain required | Confirm holder and transfer procedure |
| DNS/proxy | Cloudflare plan/account evidence | Unknown | Unknown | Transfer/recreate zones and controls |
| Transactional/support email | Provider plan and usage invoice | Unknown | Unknown | Buyer tenant/mailbox and sender setup |
| Independent off-site encrypted storage | Storage, requests and egress invoice | Not configured / unknown future cost | Not yet established | Buyer repository and encryption custody required |
| Uptime/error monitoring | Configured provider and plan | Unknown | Unknown | Buyer alert destinations and test receipts |
| External APIs/AI/maps | Enabled services, usage export and caps | Unknown | Unknown | Buyer keys and agreements |
| GitHub/builds | Plan and Actions usage | Unknown | Unknown | Repository and runner ownership |
| Payment services if enabled | Gateway fees and transaction statement | Not required for sale baseline; actual enabled cost unknown | Confirm only if enabled | Buyer merchant account; do not imply production revenue |
| Maintenance/support | Documented recurring hours and rate | Unknown | Unknown | Record founder tasks and replacement cost |

## Evidence rules

For each remaining entry retain billing period, gross amount, GST, currency,
invoice/account evidence, account custodian and whether dedicated or shared.
Annual charges are divided by 12 for monthly comparison; retain actual cash
renewal dates separately. For shared accounts document the allocation basis and
buyer standalone cost. For foreign-currency invoices record the invoice conversion
rate/date.

Calculate fixed monthly commitments plus measured variable usage for the same
period only when required entries have evidence. Show one-off transfer costs
separately. Do not publish a consolidated operating-cost total while material
entries remain unknown. Record disabled optional services separately from active
costs and forecasts separately from actual invoices. No revenue, margin or
valuation claim is supported by this unfinished schedule.
