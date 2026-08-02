# AI provider abstraction

**Status:** implemented (Phase AI-3).  
**ADR:** [0020](DECISIONS/0020-provider-neutral-ai-abstraction.md) (accepted).

## Port

`App\Platform\AiSearch\Provider\AiProviderInterface`

| Concern | Type / behaviour |
| --- | --- |
| Provider name | `name(): string` |
| Model | From `ai_settings.model_allowlist` only — never hard-coded as selected |
| Structured request | `AiCompletionRequest` (messages, JSON schema, timeout, correlation id) |
| Result | `AiCompletionResult` (ok, parsed, tokens, costs, duration, failure) |
| Cost estimate | `AiCostEstimator` before/after call |
| Failure | Schema, timeout, allowlist, network — never invent listings |

## Providers

| Provider | Class | Phase |
| --- | --- | --- |
| Rules-only / no network | `IntentRuleEngine` (not a vendor port) | AI-1 |
| OpenAI Structured Outputs | `OpenAiProvider` | AI-3 |
| Future hosted / local / classifier | Implement `AiProviderInterface` | later |

## Budget interaction

`AIBudgetService::evaluatePaidAiAttempt` runs before any vendor call. Hard stop
or disabled AI → no `IntentInterpreter` network call. Empty allowlist or
non-allowlisted model → fail closed (`model_not_allowlisted` /
`no_allowlisted_model`). No silent upgrade to a more expensive model.

## Non-goals

- Conversational chat completions as product UX  
- Calling vendors from controllers, cron, or RIC against production DB  
- Storing API keys in MariaDB  

Switching vendor must not require changes to public search controllers or
result rendering — only the interpreter’s injected provider.
