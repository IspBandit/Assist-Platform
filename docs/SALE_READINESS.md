# Assist Platform sale readiness

## Acquisition boundary

The active sale package is:

1. **VanAssist** — `vanassist.com.au`
2. **TowSmart** — `towsmart.com.au`
3. **TrailerWise** — `trailerwise.com.au`
4. Shared Assist Platform source code, database schema, administration, deployment tooling and documentation required to operate those three brands.
5. Transferable provider, location, catalogue and content data only to the extent supported by recorded provenance/licensing.

**Excluded:** LocalTorque and Polaris. They are retired product experiments and are not active brands, sale assets, dependencies or implied future products. Historical migrations, ADRs and evidence files may remain where removal would damage the integrity of the database upgrade history or technical audit trail.

No buyer-facing document, runtime brand registry, active host mapping, navigation, sitemap, administrator menu or current product status may present LocalTorque or Polaris as part of the active platform.

## Sale-ready definition

Assist Platform is sale-ready when a competent buyer can diligence, acquire, transfer, deploy and operate the three-brand platform without undocumented founder knowledge or undisclosed material technical dependencies.

## Gate A — product acceptance

- [ ] VanAssist: search → provider → contact/request journey accepted on desktop and mobile.
- [ ] VanAssist: Ask → useful result → provider/facility action accepted.
- [ ] VanAssist: provider claim → approval → account access accepted.
- [ ] VanAssist: stays and town/GPS search accepted.
- [ ] TowSmart: vehicle/trailer selection → calculation → explanation/warnings accepted.
- [ ] TowSmart: saved combinations and specialist-provider handoff accepted.
- [ ] TrailerWise: provider/service discovery accepted.
- [ ] TrailerWise: service-first positioning consistent across homepage, navigation and search.
- [ ] TrailerWise: marketplace clearly secondary and does not confuse the primary proposition.
- [ ] No retired-brand route, host, navigation or admin entry is reachable as an active product.

## Gate B — production reliability

- [ ] Exact release commit passes CI.
- [ ] All three public `/healthz` and `/readyz` checks pass.
- [ ] Scheduled jobs complete without stale-running records.
- [ ] Independent encrypted off-site backup is automated.
- [ ] Current backup is restored into an isolated environment and evidence retained.
- [ ] Rollback procedure is rehearsed against the sale candidate.
- [ ] External uptime/error alerting reaches an owner-controlled destination.
- [ ] Cron/task failure alerts reach an owner-controlled destination.

## Gate C — security and privacy

- [ ] Previously exposed temporary credentials are rotated.
- [ ] Administrative MFA position is documented; implement where supported before transfer.
- [ ] APP_KEY rotation procedure is tested/documented.
- [ ] Field-level personal-data inventory completed.
- [ ] Retention schedules documented for requests, messages, accounts, analytics, logs and backups.
- [ ] User export/deletion/anonymisation position documented, including legal/finance exceptions.
- [ ] Subprocessors, data regions and external APIs recorded.
- [ ] Privacy policy and terms reviewed against actual production behaviour.

## Gate D — data and IP provenance

- [ ] Provider datasets have source/provenance classification.
- [ ] TowSmart vehicle and trailer catalogue sources and update method documented.
- [ ] VanAssist stay data provenance and verification labels documented.
- [ ] Third-party datasets/APIs identified with licence/terms and transfer implications.
- [ ] Assets, logos, photos, icons and generated marketing material have ownership/licence position recorded.
- [ ] Open-source dependencies and licences exported from lock files.
- [ ] No excluded LocalTorque/Polaris data is required for active three-brand operation.

## Gate E — buyer-grade business records

- [ ] Monthly hosting/infrastructure cost documented.
- [ ] API, email, monitoring and domain costs documented.
- [ ] Current user, search, enquiry, provider-claim and conversion metrics exported.
- [ ] Revenue, refunds, payment fees and recurring commitments documented where applicable.
- [ ] Provider/listing counts are reproducible from production queries rather than marketing estimates.
- [ ] Material known defects and deferred features listed plainly.

## Gate F — transferability

- [ ] Asset register lists domains, GitHub repositories, hosting, Cloudflare/DNS, email, analytics, monitoring, payment services and external APIs.
- [ ] Each account records current owner, transfer method and whether credentials must be regenerated.
- [ ] Buyer can build from a clean checkout using documented steps.
- [ ] Buyer can restore production data/media into a clean environment.
- [ ] Buyer can deploy an immutable release and roll it back.
- [ ] Buyer can add/remove an administrator without developer assistance.
- [ ] Buyer can manage providers, claims, content, email queues, backups and core settings from documented interfaces/runbooks.
- [ ] No operational step relies only on Glen's memory or workstation.

## Gate G — acquisition data room

Create a buyer-facing data-room index containing:

- executive product overview;
- three-brand product map;
- architecture diagram;
- current production-state report;
- asset register;
- data/provenance register;
- dependency/licence register;
- operating-cost schedule;
- analytics/traction pack;
- known-issues register;
- security/privacy summary;
- backup/restore evidence;
- deployment/handover runbook;
- domain/account transfer checklist;
- source-code and IP assignment schedule;
- excluded-assets schedule (LocalTorque and Polaris).

## Non-goals before sale

Do not delay sale readiness for speculative feature expansion. In particular, the following are not required unless a real buyer or production defect justifies them:

- shared cross-domain login;
- major UI redesigns;
- new brands;
- new marketplaces;
- broad AI feature expansion;
- infrastructure scaling unsupported by measured load.

The priority order is **stability → transferability → evidence → commercial polish**, not feature count.
