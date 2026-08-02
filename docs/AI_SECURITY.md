# AI security

**Status:** AI-7 controls implemented (flags/rate limits/retention/CAPTCHA
escalation).  
**Related:** [`SECURITY.md`](SECURITY.md), OPS-010, [`AI_RELEASE_CRITERIA.md`](AI_RELEASE_CRITERIA.md).

## Controls

- Rate limiting on `/ask` (`public.ask-vanassist`, 20/hour/IP) via
  `AskVanAssistRateLimit`  
- When Cloudflare Turnstile is enabled and the Ask rate limit blocks, `/ask`
  returns a 429 unlock page; `POST /ask/unlock` (Turnstile + CSRF) clears the
  bucket so legitimate users can continue  
- Honeypot field on the Ask form (`website`)  
- Request-size limits (`max_query_length`)  
- Authentication where required  
- Safe logging and error redaction  
- Prompt injection resistance (user/source text never becomes instructions)  
- Structured output validation before routing  
- Source and URL allowlists for connectors; SSRF prevention  
- Timeout/retry limits  
- Secret isolation (env/vault; never MariaDB)  
- Audit logs for enablement, budget changes, staging decisions  
- Retention purge for raw NL telemetry (`ai_retention` cron)  
- No raw GPS retained in `assist_searches` (town_id + precision only)
- No live Overpass from Ask (offline OSM seed staging only)

## Untrusted data

External source content is untrusted data. It must not instruct the model or
alter system behaviour. AI output is interpretation metadata only — never
authority to invent providers, facilities, addresses, phones, hours or
availability.
