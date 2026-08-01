# AI usage and observability

**Status:** design (Phase AI-0).

## Per-call record

Request ID, brand, operation type, provider, model, input/output tokens,
cached status, estimated/actual cost, duration, success/failure, fallback
reason, user-facing search ID, intent confidence, budget state.

## Admin reporting

Requests and estimated spend today/month; budget remaining; cost by operation
and model; cache hit rate; % resolved without AI; % requiring AI; failed and
budget-blocked requests; average cost per interpreted search.

## Secrets

Never log API keys or raw credentials. Redact provider error bodies that may
echo prompts containing sensitive data.
