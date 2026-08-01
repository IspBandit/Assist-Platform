# OpenAI integration

**Status:** design only until Phase AI-3. **Not used in AI-1.**  
**Gate:** owner approval of model allowlist before Phase AI-3.

## AI-1 boundary

Deterministic keyword intent only. No OpenAI calls, no API keys, no paid
usage.

## Preconditions for AI-3

1. Re-read current official OpenAI docs for Structured Outputs and pricing.  
2. Confirm low-cost models that support strict JSON schema.  
3. Document pricing snapshot in the implementing PR.  
4. Obtain owner approval for selected snapshot model ids.  
5. Configure server-side secret; AI remains disabled until configured.

## Design constraints

- Prefer Structured Outputs (`json_schema`, `strict: true`).  
- Pin snapshot model ids in allowlist settings — **do not hard-code** in PHP.  
- Key: server-side only; never frontend JS; never plaintext MariaDB; never logs.  
- Bounded timeout/retries; schema validation before routing influence.  
- No conversational answers from the intent layer.  
- No automatic upgrade to a more expensive model.

## References (verify again at implement time)

- https://developers.openai.com/api/docs/guides/structured-outputs  
- OpenAI official models and pricing pages  
