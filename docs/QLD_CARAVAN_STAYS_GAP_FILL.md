# Queensland caravan-stay route discovery

**Backlog ownership:** VAN-001, supported by DATA-001 and DATA-006.

`tools/qld-caravan-stays-gap-fill.js` is a budget-locked, discovery-only Google
Places search for caravan-suitable places to stay along likely Queensland
touring routes. It is deliberately separate from provider discovery.

## Scope

The search covers 160 Queensland touring hubs across coastal, inland, Cape,
Gulf and outback corridors. It searches for:

- caravan and RV parks;
- campgrounds and bush camps;
- free and low-cost camps;
- national-park campgrounds;
- showground and council camping;
- farm and station stays suitable for camping;
- overnight rest areas where caravans may be permitted.

Hotels, motels, hostels, apartments and general resort accommodation are
excluded. A candidate is retained only when its Google place types or name
indicate caravan/camping relevance.

## Budget safety

The absolute application-side cap is **A$100**. The planning estimate is
A$0.055 per request, giving an absolute maximum of 1,818 requests. The current
run plan is 160 hubs by nine queries: 1,440 requests, estimated at **A$79.20**.

Always dry-run before using the paid API:

```text
node tools/qld-caravan-stays-gap-fill.js --budget-aud 100
```

The write run reads `GOOGLE_PLACES_API_KEY` from the process environment. Never
place the key on the command line, in this repository or in captured logs.

```text
node tools/qld-caravan-stays-gap-fill.js --budget-aud 100 --write
```

## 29 July 2026 discovery result

- Requests: 1,440
- Estimated cost: A$79.20
- Unique candidates: 1,544
- With phone: 990
- With website: 1,039
- Operational according to Google: 1,544
- General-accommodation results excluded: 3,930
- Request failures: 0

Normalised review types:

| Type | Candidates |
| --- | ---: |
| Campground | 632 |
| Caravan park | 604 |
| Showground | 97 |
| Rest area | 91 |
| National park | 48 |
| Free camp | 43 |
| Farm stay | 24 |
| Station stay | 5 |

Runtime output is written under `storage/imports/qld-stay-coverage/` and is not
committed. The candidate pack remains `review_only`; this tool never inserts or
publishes stays.

## Mandatory review

Google Places is discovery evidence, not sufficient publication evidence.
Before a candidate can be retained or published, review must confirm:

1. it genuinely accepts caravans, campervans or motorhomes;
2. overnight access is lawful and currently permitted;
3. any booking, permit, vehicle-size, seasonal, road-access or maximum-stay
   restrictions are recorded accurately;
4. fees are not inferred when unavailable;
5. a current operator, council, Queensland Government or national-park source
   supports the public claims;
6. duplicates are merged with the existing stays directory;
7. Google content retention and attribution requirements are respected.

National parks, rest areas, free camps and council/showground stays should be
held for review whenever access conditions cannot be independently confirmed.

## Rollback

There is no public-data rollback because discovery does not modify the
database. Delete the private runtime candidate pack if it should not proceed to
review. Any later import requires its own preview, audit trail and rollback
plan under DATA-001.
