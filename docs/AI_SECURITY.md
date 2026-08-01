# AI security

**Status:** design (Phase AI-0).  
**Related:** [`SECURITY.md`](SECURITY.md), OPS-010.

## Controls

- Rate limiting and anonymous-user throttling  
- Request-size limits  
- Authentication where required; CAPTCHA escalation when justified  
- Safe logging and error redaction  
- Prompt injection resistance (user/source text never becomes instructions)  
- Structured output validation before routing  
- Source and URL allowlists for connectors; SSRF prevention  
- Timeout/retry limits  
- Secret isolation  
- Audit logs for enablement, budget changes, staging decisions  

## Untrusted data

External source content is untrusted data. It must not instruct the model or
alter system behaviour. AI output is interpretation metadata only — never
authority to invent providers, facilities, addresses, phones, hours or
availability.
