# Privacy and security evidence register

Prepared 6 September 2026, OPS-005 / COM-005. This is an engineering inventory,
not legal approval. Current database table/column/type inventory is retained in
`../evidence/sale-2026-09-06/schema-field-inventory.csv`. It contains metadata only,
including historical/excluded-product tables so they cannot be overlooked at transfer.

| Data class and fields | Purpose/access boundary | Retention and disposal position |
| --- | --- | --- |
| users: name, email, phone, internal_notes, password_hash; user_roles | Identity and account administration; hashes and notes private | deleted_at is soft deletion, not demonstrated erasure; seller must approve schedule and legal holds |
| user_sessions: user_id, ip_address, user_agent; password_resets and email_verifications: token_hash, expiry | Authentication; private | Session expiry job recorded successful; token expiry is not proof of physical deletion |
| user_consents: user_id, consent_type, granted, document_version, IP, user agent | Consent evidence; restricted administration | Retain versioned evidence under approved policy; no complete policy supplied |
| user_mfa_methods: user_id, secret_encrypted, verification/enrolment times | MFA secrets restricted | No enabled enrolments in current snapshot; enrol and verify recovery before transfer |
| provider_claim_tokens: provider_id, brand_id, email, token_hash, expiry; caravan_park_claims: claimant contacts and evidence_notes | Claims/review, restricted evidence | Expiry and review exist; complete claim erasure/export rehearsal remains open |
| providers/provider_contacts: contact_name, operator_name, phone/email, address, ABN, consent evidence | Public listing fields versus restricted contact/consent fields | Public visibility is not blanket permission to transfer personal data or market to it |
| provider_documents, caravan_park_documents, request images | Evidence/private uploads; scoped administration/ownership | Private files and all backup copies must be included in retention/deletion planning |
| service_requests: contact details, private_address, registration, descriptions, IP, consent flags; messages/notes | Assistance workflow; relevant owner/provider/admin only | No production requests at snapshot; approve retention before assuming indefinite storage is acceptable |
| towable_assets, tow_vehicles, towing_combinations: user/brand IDs, vehicle details, input/result snapshots | Private owner garage and saved calculations | Zero saved combinations observed; owner isolation and export/deletion acceptance still required |
| email_queue: recipient, subject, html/text bodies, lease/error fields; email_log | Transactional delivery, restricted operations | Sent status is not consent or recipient receipt; approve body/log retention and provider-region handling |
| analytics_events: session/user IDs, route, referral, metadata; page_views | Usage measurement; aggregate buyer exports only | Successful analytics_retention task observed; exact effective policy must be verified against code/config and approved |
| audit_logs: actor, previous/new values, IP/user agent | Security/accountability, restricted | Potential embedded personal data in change values; no tamper-evident claim or approved universal retention period |
| backup database/media/config | Disaster recovery; encrypted restricted custody | Repository defaults 7 daily/4 weekly/3 monthly; independent configuration absent; deletion must be reapplied after any restore |

AI configuration declares 180 days for searches/usage events and 365 for gap events.
The AI retention task had never run in the snapshot. These defaults are not
evidence of enforcement. Do not label data anonymous merely because user IDs are
hashed or pseudonymous. Billing/finance exceptions require an approved policy;
this document does not invent a statutory period.

## Subprocessors and account actions

| Service | Engineering basis | Open transaction evidence |
| --- | --- | --- |
| BinaryLane | Application/database on documented Brisbane host | Contract, account owner, actual region confirmation, dedicated/shared allocation |
| Cloudflare | Public proxy/DNS | Zone/account export, enabled services, processing terms/regions |
| Microsoft Graph/mail | Documented sender path; queue sent records | Tenant owner, region, sender permissions, retention, mailbox transfer |
| Google Routes | Release credential verification/provisioning succeeded | API agreement, actual request data/retention, billing and buyer key |
| Optional AI/CAPTCHA/payment providers | Feature/config dependent | Enabled-provider inventory, data sent, regions, terms and charges; do not infer enablement |
| Independent backup | Not configured; owner confirms no account | Provision account, encryption/recovery custody, retention and restore evidence |

Privacy/terms pages being reachable does not approve their legal accuracy.
Seller review must reconcile this inventory with live behaviour, establish a
verified request identity process, demonstrate export/deletion across dependent
rows/files, document exceptions, and record supplier terms and IP assignments.
Never include secret values, customer exports or backup archives in Git.
