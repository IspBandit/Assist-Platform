# Natural-language search (Ask VanAssist)

**Status:** implemented (AI-1–AI-7); public flag `assist_ai_search` **off** by
default.  
**Backlog:** VAN-011 / CORE-012.  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md),
[`AI_QUALITY_GATE_EVIDENCE.md`](AI_QUALITY_GATE_EVIDENCE.md).

## UX rule

Keep existing structured search unchanged:

- State / town / category / Near Me / automatic location (`/find`, `/stays`,
  location JSON endpoints).

Add a **separate** interface:

**Ask VanAssist** — `GET /ask` — “What do you need help finding?”

Examples:

- Public toilets near me  
- Dump point near Batehaven  
- Mobile caravan repairer near Emerald  
- LPG refill near Batemans Bay  
- Caravan park nearby  
- Auto electrician within 50 km  
- Someone who can repair caravan brakes  

NL search must not replace or hide dropdown search. Provider cards reuse the
existing result partial; stays and facilities use labelled sections on
`assist-search.php`.

## Runtime behaviour

1. Feature flag + VanAssist brand required (else 404).  
2. Rate limit `public.ask-vanassist` (20/hour/IP); Turnstile unlock page when
   blocked and Turnstile enabled; honeypot `website` field.  
3. `SearchOrchestrator` validates → rules → cache → optional paid AI → adapters
   → aggregate → log → knowledge gaps.  
4. Flags: `assist_ai_datasets`, `assist_ai_traveller_facilities` independently
   gate external candidates and facilities.
5. When an origin and radius are resolved, the aggregator applies a final
   fail-closed radius invariant to every adapter result. Cards without a
   measurable location, or beyond the unrounded boundary, are not returned.
6. When Google Routes is configured, the geographically safe candidate set is
   enriched in one deduplicated route matrix. Road kilometres become the final
   boundary and sort value; drive time is displayed as an estimate. A named
   location that cannot be resolved returns no national fallback results.
7. Public Ask results start with a compact 20-card window. **Show up to 40
   results** is explicit; the same bound limits each route matrix and prevents a
   routing outage from exposing an unbounded national list.
8. Reviewed stay-facility evidence is searched alongside standalone traveller
   facilities. It remains attached to the stay and retains its source and
   precedence; it is not copied into the standalone facility table. Confirmed
   absent, unknown or vague water claims are never presented as available.

The Routes credential is resolved from the root environment first, then the
encrypted `google_routes` connector, then the encrypted `google_places`
connector. Health exposes only whether routing is configured and which safe
credential source was selected; it never returns the key.

## Behaviour when AI is off / budget exhausted

Keyword/deterministic intent continues. Cached intents continue. Local DB and
imported dataset search continue. Structured `/find` unchanged. No paid vendor
calls. User sees clarification or category-search CTA.

## Brand

First surface: VanAssist. Orchestrator remains shared for future brands.

Ask uses the device's current GPS coordinates whenever the question does not
name a location. The browser may require the traveller to grant location
permission. A town, suburb, postcode, campground or other supported landmark
written in the question always overrides device coordinates; an unresolved
typed location never falls back to stale GPS or an Australia-wide result set.
