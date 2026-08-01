# Natural-language search (Ask VanAssist)

**Status:** design (Phase AI-0).  
**Backlog:** VAN-011.  
**Gate:** [`PHASE_AI0_DESIGN.md`](PHASE_AI0_DESIGN.md).

## UX rule

Keep existing structured search unchanged:

- State / town / category / Near Me / automatic location (`/find`, `/stays`,
  location JSON endpoints).

Add a **separate** interface, for example:

**Ask VanAssist** — “What do you need help finding?”

Examples:

- Public toilets near me  
- Dump point near Batehaven  
- Mobile caravan repairer near Emerald  
- LPG refill near Batemans Bay  
- Caravan park nearby  
- Auto electrician within 50 km  
- Someone who can repair caravan brakes  

NL search must not replace or hide dropdown search. Present results with the
same list (and later map) patterns where practical.

## Behaviour when AI is off

Keyword/deterministic intent may still run (AI-1+). Cached intents may run.
Local DB and imported dataset search continue. No paid vendor calls.

## Brand

First surface: VanAssist. Orchestrator remains shared for future brands.
