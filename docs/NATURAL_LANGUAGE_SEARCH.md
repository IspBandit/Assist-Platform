# Natural-language search (Ask VanAssist)

**Status:** implemented (AI-1–AI-7); prepared as VanAssist's primary
plain-language journey for the September 2026 release candidate. The
`assist_ai_search` flag remains off by default and is enabled in production
only for a release candidate that passes the Platform Quality Gate.
**Backlog:** VAN-011 / CORE-012.
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md),
[`AI_QUALITY_GATE_EVIDENCE.md`](AI_QUALITY_GATE_EVIDENCE.md).

## UX rule

Keep existing structured search available as the explicit fallback:

- State / town / category / Near Me / automatic location (`/find`, `/stays`,
  location JSON endpoints).

The VanAssist homepage presents this as the primary search interface:

**Ask VanAssist** — `GET /ask` — “What do you need help finding?”

Examples:

- Public toilets near me  
- Dump point near Batehaven  
- Mobile caravan repairer near Emerald  
- LPG refill near Batemans Bay  
- Caravan park nearby  
- Auto electrician within 50 km  
- Someone who can repair caravan brakes  

NL search must not remove dropdown search. The category/town form remains
keyboard-accessible behind a clearly labelled disclosure. Provider cards reuse
the existing result partial; stays and facilities use labelled sections on
`assist-search.php`.

## Runtime behaviour

1. Feature flag + VanAssist brand required (else 404).  
2. Rate limit `public.ask-vanassist` (20/hour/IP); Turnstile unlock page when
   blocked and Turnstile enabled; honeypot `website` field.  
3. `SearchOrchestrator` validates → rules → cache → optional paid AI → adapters
   → aggregate → log → knowledge gaps.  
4. Flags: `assist_ai_datasets`, `assist_ai_traveller_facilities` independently
   gate external candidates and facilities.

The initial flagship release uses deterministic rules and reviewed canonical
data with paid AI and pending-dataset answers disabled. State-qualified towns,
request radius, Australian coordinate bounds, facility brand scope and safe
source URL schemes are enforced. Ambiguous duplicate town names require a state;
unique minor town-name typos may be corrected with an explicit message.

Safety-critical wording adds visible emergency guidance. Ask remains a locator,
not emergency dispatch, diagnosis, engineering approval or legal certification.

## Behaviour when AI is off / budget exhausted

Keyword/deterministic intent continues. Cached intents continue. Local DB and
imported dataset search continue. Structured `/find` unchanged. No paid vendor
calls. User sees clarification or category-search CTA.

## Brand

First surface: VanAssist. Orchestrator remains shared for future brands.
