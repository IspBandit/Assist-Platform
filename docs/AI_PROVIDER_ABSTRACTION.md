# AI provider abstraction

**Status:** design (Phase AI-0).  
**ADR:** 0020 (proposed).  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md) §10–§11.

## Port

Internal `AiProviderInterface` (name TBD at implement) supports:

- Provider name, model name (from allowlist only)
- Structured-output request
- Token usage, estimated cost, actual cost when reported
- Timeout, retry policy
- Safety/validation failure
- Cache key, correlation/request ID

## Initial providers

| Provider | Phase |
| --- | --- |
| Rules-only / no network | AI-1 |
| OpenAI (structured outputs) | AI-3 after owner model approval |
| Future hosted / local / specialised classifier | later |

Switching vendor must not require changes to public search controllers or
result rendering.
