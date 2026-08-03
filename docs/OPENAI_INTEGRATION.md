# OpenAI integration

**Status:** Phase AI-3 implemented (disabled until configured).  
**Gate:** owner must place an approved snapshot model id in the admin allowlist
and set caps before enabling paid AI.

## Official API review (2026-08-01)

Sources (re-verify before production enablement):

- [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs)
- [GPT-4o mini](https://developers.openai.com/api/docs/models/gpt-4o-mini)
- [GPT-4.1 nano](https://developers.openai.com/api/docs/models/gpt-4.1-nano)
- [Pricing](https://developers.openai.com/api/docs/pricing)

### Structured Outputs

- Prefer Chat Completions `response_format.type = json_schema` with
  `json_schema.strict = true`.
- All schema properties must be listed in `required`; use nullable unions for
  optional values; `additionalProperties: false`.
- Compatible with `gpt-4o-mini`, `gpt-4o-2024-08-06`, and later models that
  advertise Structured Outputs support.
- Platform still runs `IntentSchemaValidator` after parse (defence in depth).

### Pricing snapshot (USD per 1M tokens — verify live before enable)

| Model class | Input | Output | Notes |
| --- | --- | --- | --- |
| `gpt-4.1-nano` | ~$0.10 | ~$0.40 | Lowest published cost in 4.1 family |
| `gpt-4o-mini` | ~$0.15 | ~$0.60 | Strong Structured Outputs track record |

**Owner recommendation:** allowlist a **pinned snapshot** of the lowest-cost
model that reliably supports strict `json_schema` in your OpenAI project.
Default documentation recommendation: **`gpt-4o-mini`** (or its dated snapshot)
for reliability; evaluate **`gpt-4.1-nano`** only after a golden-intent smoke
test against Structured Outputs in your account.

Do **not** hard-code model ids in PHP application logic. Store allowlist in
`ai_settings.model_allowlist_json`. Config may hold **cost estimate rates**
keyed by model prefix for budget pre-checks only.

## Secret management

| Rule | Implementation |
| --- | --- |
| Server-side only | `OPENAI_API_KEY` via `.env` / secret store |
| Not in MariaDB | Never written to `ai_settings` or logs |
| Not in frontend | Controllers never expose the key |
| Revocable | Rotate env secret; disable `openai_enabled` / `ai_enabled` |

## Runtime behaviour

1. Deterministic rules + intent cache first.  
2. Paid AI only when confidence/adapters insufficient **and** budget gate allows.  
3. First allowlisted model only — no automatic upgrade.  
4. Timeout/retries from `ai_settings`; estimate cost before call.  
5. Invalid schema / timeout / refusal → rules/clarification fallback; no inventing listings.  
6. No conversational answer field in the intent schema.

## Enable checklist

1. Set `OPENAI_API_KEY` in server env.  
2. Apply migration 086 (AI-2).  
3. On `/admin/ai-search`: set non-zero caps, allowlist approved snapshot, enable
   OpenAI, then global AI.  
4. Smoke-test golden queries on non-production.  
5. Keep `assist_ai_search` flag separate (public UI).
