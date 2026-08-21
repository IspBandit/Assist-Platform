# LocalTorque provider-pack integration

## Integration summary

LocalTorque owns the master Australian automotive directory. The supplied pack
is additional evidence for the shared canonical provider record, not a second
provider database. Public results use only publishable rows that pass location,
confidence and review checks. Taxonomy `category.brands` is authoritative for
brand visibility: fuel stations and EV charging appear on LocalTorque and
VanAssist, not TowSmart or TrailerWise.

## Search and product UX

- `Fuel near me` and `EV charging near me` are first-class VanAssist traveller
  service choices and LocalTorque automotive categories.
- Nearby results show distance, operational status, address, available fuel
  types, hours and contact actions only when the source supplies them.
- `Diesel` or HGV suitability is filterable only when `fuel_types` explicitly
  contains supporting data. An ordinary fuel listing must not be labelled HGV.
- A result opens the device's maps application through a normal map link. The
  platform must not claim road distance until a routing response is available;
  its current geographic sort is straight-line distance.
- Sponsored local results may appear in a clearly labelled placement after
  organic results. Payment never changes official data or organic ranking.

## Data mapping

| Pack field | Platform field |
| --- | --- |
| `id` + `source` | `provider_source_records.external_id` + `source_key` |
| `name`, `trading_name`, `operator` | canonical provider names |
| `lat`, `lng`, `_coords_approx` | provider coordinates and approximation flag |
| `address`, `town`, `state`, `postcode` | address plus resolved canonical town |
| `phone`, `email`, `website` | public contact fields when valid and supplied |
| `opening_hours`, `operational_status`, `fuel_types` | typed provider metadata |
| `source_url`, `source_licence`, `confidence` | immutable source evidence |
| `categories` | brand-category assignments controlled by taxonomy |

The importer never modifies claimed providers. It only fills blank fields on
unclaimed records, preserves the complete source payload and is safe to resume.

## API/filter contract

The logical filter object is:

```json
{
  "publishable": true,
  "needs_review": false,
  "category": "fuel-station",
  "state": "QLD",
  "town": "Gladstone",
  "origin": {"lat": -23.842, "lng": 151.255},
  "radius_km": 50,
  "fuel_type": "diesel",
  "operational_only": true
}
```

Exact pack-only selection logic:

```text
row.publishable === true
AND row.needs_review !== true
AND "fuel-station" IN row.categories
AND (state omitted OR UPPER(row.state) === state)
AND (town omitted OR casefold(row.town) === casefold(town))
AND (origin/radius omitted OR haversine(origin,row.lat,row.lng) <= radius_km)
AND (fuel_type omitted OR fuel_type IN row.fuel_types)
AND (operational_only is false OR row.operational_status is empty
     OR UPPER(row.operational_status) === "OPERATIONAL")
```

Examples:

- “Nearest servo to me” → `category=fuel-station`, origin, selected radius.
- “Diesel near Dubbo NSW” → fuel category, state `NSW`, town `Dubbo`, and
  `fuel_type=diesel`; do not exclude otherwise relevant stations from a broader
  unfiltered search merely because fuel types are unknown.
- “EV charging within 100 km” → `category=ev-charging`, origin, radius 100.
- “Fuel stations in Tasmania” → `category=fuel-station`, state `TAS`.

## Ranking

Filter eligibility first, then rank by exact-coordinate distance, known
operational status, contact richness, source confidence and verified/claimed
status. Featured or paid placement is rendered separately and labelled; it does
not modify the organic order. Town-centre coordinates are a fallback and must be
marked approximate.

## Attribution and gaps

Geoscience Australia Liquid Fuel Facilities/Petrol Stations records retain
CC BY 4.0 attribution. OpenStreetMap fuel and charging records retain ODbL
attribution and the OSM copyright link. Shared franchise websites are brand-level
unless source evidence proves a site-specific page. Missing phone, email, hours
or fuel type stays blank.

Known launch gaps: the supplied fuel pack contains no South Australian fuel
records; EV coverage is uneven; many GA rows are place-only; some records lack
exact coordinates or a canonical town; and 200 supplied rows are marked for
review. These remain hidden or honestly incomplete rather than being invented.

## Assist tool contract

An Assist answering provider questions from this pack must say that unclaimed
public-source listings are not operator verified, use only rows passing the
eligibility filter above, cite the row source/licence, never invent missing
contacts or trading status, and recommend confirming details directly before
travel. Its tool accepts `category`, `state`, `town`, `lat`, `lng`, `radius_km`,
`fuel_type`, `operational_only`, and `limit`; `radius_km` requires valid row
coordinates and `limit` is bounded to 50.
