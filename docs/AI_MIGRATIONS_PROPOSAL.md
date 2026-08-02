# AI migrations proposal

**Status:** historical AI-0 proposal + **applied** migration map.  
Forward-only SQL under `database/migrations/`. Do not edit applied files.

## Applied map (this workstream)

| Migration | Phase | Purpose |
| --- | --- | --- |
| `101_assist_ai_search.sql` | AI-1 | `assist_searches`, flag `assist_ai_search` |
| `102_assist_ai_cache_budget.sql` | AI-2 | `ai_settings`, `ai_intent_cache`, usage tables |
| `105_assist_knowledge_gaps.sql` | AI-4 | `knowledge_gaps`, `knowledge_gap_events` |
| `106_assist_ai_datasets.sql` | AI-5 | flag `assist_ai_datasets` |
| `107_assist_ai_hardening.sql` | AI-7 | retention indexes + `ai_retention` cron |
| `108_assist_traveller_facilities.sql` | AI-6 | `traveller_facilities` + flag |
| `109_government_datasets.sql` | DATA-012 | catalogue + gov connectors |
| `110_government_dataset_au_toilet_map.sql` | DATA-012 | curated Toilet Map rows (disabled) |
| `113_osm_offline_seed_connector.sql` | AI-5 | `osm_offline_seed` connector |

Note: `095` / `096` on this repo are **Polaris** migrations, not Assist AI.

## Design notes (still valid)

- Prefer new NL tables over widening `provider_searches`.  
- Privacy: town_id / precision only in `assist_searches` (no long-term raw GPS).  
- Secrets remain outside DB (env/vault).  
- `trusted_automatic` never auto-publishes without an explicit owner decision
  recorded outside code defaults.

## Explicitly out of scope for AI migrations

- Admin API Phase 1 auth/token tables (CORE-011).  
- Editing applied migrations.  
- Storing API keys in MariaDB plaintext.
