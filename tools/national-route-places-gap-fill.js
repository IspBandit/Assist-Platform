#!/usr/bin/env node
/**
 * Budget-locked Google Places discovery along Australia's main caravan routes.
 *
 * Discovery only. Results are written to storage/imports/national-route-coverage
 * for independent verification and import review; they are never published by
 * this tool. Queensland is excluded because its route hubs have already been
 * searched by qld-places-gap-fill.js.
 */
"use strict";

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const OUT_DIR = path.join(ROOT, "storage", "imports", "national-route-coverage");
const TOWN_SEED = path.join(ROOT, "database", "seeds", "towns_national.json");
const ABSOLUTE_BUDGET_CAP_AUD = 125;
const AUD_PER_REQUEST = 0.055;

// Major touring corridors, regional service centres and common overnight hubs.
// Small adjoining localities are intentionally represented by their nearest hub.
const ROUTE_HUB_NAMES = {
  NSW: [
    "Sydney", "Newcastle", "Wollongong", "Tweed Heads", "Ballina", "Lismore",
    "Grafton", "Coffs Harbour", "Port Macquarie", "Taree", "Forster", "Maitland",
    "Singleton", "Muswellbrook", "Tamworth", "Armidale", "Glen Innes", "Tenterfield",
    "Moree", "Narrabri", "Coonabarabran", "Dubbo", "Gilgandra", "Coonamble",
    "Bourke", "Broken Hill", "Wilcannia", "Nyngan", "Cobar", "Orange", "Bathurst",
    "Lithgow", "Mudgee", "Parkes", "Forbes", "Cowra", "Young", "West Wyalong",
    "Griffith", "Narrandera", "Wagga Wagga", "Albury", "Deniliquin", "Hay",
    "Balranald", "Wentworth", "Goulburn", "Yass", "Queanbeyan", "Cooma",
    "Jindabyne", "Bega", "Eden", "Batemans Bay", "Nowra", "Kiama", "Mittagong",
    "Bowral", "Nambucca Heads", "Kempsey",
  ],
  VIC: [
    "Melbourne", "Geelong", "Ballarat", "Bendigo", "Shepparton", "Wodonga",
    "Wangaratta", "Benalla", "Seymour", "Echuca", "Mildura", "Swan Hill", "Kerang",
    "Horsham", "Stawell", "Ararat", "Hamilton", "Portland", "Warrnambool", "Colac",
    "Apollo Bay", "Torquay", "Lorne", "Lakes Entrance", "Bairnsdale", "Sale",
    "Traralgon", "Warragul", "Cowes", "Leongatha", "Foster", "Yarram", "Orbost",
    "Omeo", "Bright", "Mansfield", "Alexandra", "Maryborough", "Castlemaine", "Kyneton",
    "Daylesford", "Donald", "Ouyen", "Nhill", "Robinvale",
  ],
  SA: [
    "Adelaide", "Mount Gambier", "Millicent", "Naracoorte", "Keith", "Bordertown",
    "Murray Bridge", "Victor Harbor", "Goolwa", "Strathalbyn", "Tailem Bend", "Renmark",
    "Berri", "Waikerie", "Loxton", "Barmera", "Port Augusta", "Whyalla", "Port Lincoln",
    "Ceduna", "Streaky Bay", "Coffin Bay", "Clare", "Port Pirie", "Kadina",
    "Coober Pedy", "Roxby Downs",
  ],
  WA: [
    "Perth", "Mandurah", "Bunbury", "Busselton", "Margaret River", "Augusta", "Pemberton",
    "Manjimup", "Albany", "Denmark", "Esperance", "Ravensthorpe", "Narrogin", "Katanning",
    "Williams", "Northam", "York", "Merredin", "Southern Cross", "Kalgoorlie", "Coolgardie",
    "Geraldton", "Dongara", "Jurien Bay", "Cervantes", "Moora", "Dalwallinu",
    "Mount Magnet", "Meekatharra", "Carnarvon", "Exmouth", "Coral Bay", "Karratha",
    "Dampier", "Port Hedland", "Broome", "Derby", "Kununurra", "Halls Creek",
    "Fitzroy Crossing", "Newman", "Tom Price", "Paraburdoo", "Collie", "Harvey",
    "Donnybrook", "Bridgetown",
  ],
  TAS: [
    "Hobart", "Launceston", "Devonport", "Burnie", "Ulverstone", "Smithton", "Stanley",
    "Queenstown", "Strahan", "Zeehan", "Cradle Mountain", "Deloraine", "Longford",
    "Scottsdale", "St Helens", "Bicheno", "Swansea", "Coles Bay", "Triabunna",
    "New Norfolk", "Huonville", "Port Arthur",
  ],
  NT: [
    "Darwin", "Palmerston", "Katherine", "Mataranka", "Daly Waters", "Tennant Creek",
    "Alice Springs", "Yulara", "Nhulunbuy", "Timber Creek", "Borroloola",
  ],
  ACT: ["Canberra"],
};

// Important NT touring stops absent from the current national locality seed.
const EXPLICIT_HUBS = [
  { town: "Pine Creek", state: "NT", region: "nt-katherine", lat: -13.823, lng: 131.835 },
  { town: "Jabiru", state: "NT", region: "nt-darwin", lat: -12.666, lng: 132.833 },
  { town: "Batchelor", state: "NT", region: "nt-darwin", lat: -13.050, lng: 131.030 },
];

const QUERIES = [
  { q: "caravan RV repairs", cats: ["caravan-repairs"] },
  { q: "mobile caravan technician", cats: ["caravan-repairs", "mobile-mechanic"] },
  { q: "auto electrician caravan 12 volt", cats: ["auto-electrician", "12-volt-electrical"] },
  { q: "mobile mechanic diesel", cats: ["mobile-mechanic", "diesel-specialist"] },
  { q: "caravan gas refrigeration air conditioning", cats: ["gas-certification", "caravan-appliances"] },
  { q: "trailer brakes bearings suspension", cats: ["trailer-brakes", "trailer-bearings", "suspension"] },
  { q: "tyre service", cats: ["tyres"] },
  { q: "roadside assistance towing", cats: ["roadside-assistance", "towing"] },
  { q: "fuel station diesel", cats: ["fuel-station"] },
  { q: "EV charging station", cats: ["ev-charging"] },
];

function arg(name, fallback) {
  const index = process.argv.indexOf(name);
  return index >= 0 && process.argv[index + 1] ? process.argv[index + 1] : fallback;
}

function flag(name) {
  return process.argv.includes(name);
}

function loadHubs() {
  const seed = JSON.parse(fs.readFileSync(TOWN_SEED, "utf8"));
  const byStateAndName = new Map(
    seed.towns.map((town) => [`${town.state}|${String(town.name).toLowerCase()}`, town])
  );
  const hubs = [];
  for (const [state, names] of Object.entries(ROUTE_HUB_NAMES)) {
    for (const name of names) {
      const town = byStateAndName.get(`${state}|${name.toLowerCase()}`);
      if (!town || !Number.isFinite(town.lat) || !Number.isFinite(town.lng)) {
        throw new Error(`Missing route-hub coordinates: ${name}, ${state}`);
      }
      hubs.push({ town: name, state, region: town.region, lat: town.lat, lng: town.lng });
    }
  }
  return hubs.concat(EXPLICIT_HUBS);
}

function fieldMask() {
  return [
    "places.id", "places.displayName", "places.formattedAddress", "places.location",
    "places.types", "places.nationalPhoneNumber", "places.websiteUri", "places.businessStatus",
  ].join(",");
}

async function searchText(key, hub, query) {
  const response = await fetch("https://places.googleapis.com/v1/places:searchText", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Goog-Api-Key": key,
      "X-Goog-FieldMask": fieldMask(),
    },
    body: JSON.stringify({
      textQuery: `${query.q} near ${hub.town} ${hub.state} Australia`,
      maxResultCount: 20,
      locationBias: {
        circle: {
          center: { latitude: hub.lat, longitude: hub.lng },
          radius: 35000,
        },
      },
      regionCode: "AU",
      languageCode: "en",
    }),
  });
  if (!response.ok) {
    const body = await response.text();
    throw new Error(`Places ${response.status}: ${body.slice(0, 400)}`);
  }
  return response.json();
}

function candidate(place, hub, query) {
  const placeId = String(place.id || "").replace(/^places\//, "");
  return {
    external_id: placeId ? `places:${placeId}` : null,
    google_place_id: placeId || null,
    business_name: place.displayName?.text || "",
    formatted_address: place.formattedAddress || null,
    phone: place.nationalPhoneNumber || null,
    website: place.websiteUri || null,
    latitude: place.location?.latitude ?? null,
    longitude: place.location?.longitude ?? null,
    business_status: place.businessStatus || null,
    place_types: place.types || [],
    category_slugs: [...query.cats],
    route_hubs: [`${hub.town}, ${hub.state}`],
    discovery_queries: [query.q],
    state: hub.state,
    region: hub.region,
    source_name: "google-places",
    source_licence: "Google Maps Platform terms — discovery only",
    marketing_consent: false,
    publication_status: "review_only",
    needs_independent_retention_check: true,
    discovered_at: new Date().toISOString().slice(0, 10),
  };
}

function mergeCandidate(existing, incoming) {
  for (const key of ["category_slugs", "route_hubs", "discovery_queries"]) {
    existing[key] = [...new Set(existing[key].concat(incoming[key]))];
  }
  return existing;
}

async function main() {
  const requestedBudget = Number(arg("--budget-aud", "125"));
  if (!Number.isFinite(requestedBudget) || requestedBudget <= 0) {
    throw new Error("--budget-aud must be a positive number");
  }
  const budgetAud = Math.min(requestedBudget, ABSOLUTE_BUDGET_CAP_AUD);
  const hardCapRequests = Math.floor(budgetAud / AUD_PER_REQUEST);
  const hubs = loadHubs();
  const plannedRequests = hubs.length * QUERIES.length;
  const dryRun = !flag("--write");

  const preview = {
    mode: dryRun ? "dry-run" : "write",
    excludes: ["QLD"],
    hubs: hubs.length,
    hubs_by_state: Object.fromEntries(
      [...new Set(hubs.map((hub) => hub.state))].sort().map((state) => [
        state,
        hubs.filter((hub) => hub.state === state).length,
      ])
    ),
    queries: QUERIES.length,
    planned_requests: plannedRequests,
    hard_cap_requests: hardCapRequests,
    budget_aud: budgetAud,
    aud_per_request_estimate: AUD_PER_REQUEST,
    planned_cost_aud: Math.round(Math.min(plannedRequests, hardCapRequests) * AUD_PER_REQUEST * 100) / 100,
  };
  console.log(JSON.stringify(preview, null, 2));
  if (dryRun) return;

  const key = String(arg("--key", process.env.GOOGLE_PLACES_API_KEY || "")).trim();
  if (!key) throw new Error("GOOGLE_PLACES_API_KEY is required for --write");

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const found = new Map();
  let requests = 0;
  const failures = [];

  outer: for (const hub of hubs) {
    for (const query of QUERIES) {
      if (requests >= hardCapRequests) break outer;
      requests++;
      process.stdout.write(`[${requests}/${Math.min(plannedRequests, hardCapRequests)}] ${hub.town}, ${hub.state} · ${query.q} … `);
      try {
        const result = await searchText(key, hub, query);
        const places = result.places || [];
        for (const place of places) {
          const row = candidate(place, hub, query);
          if (!row.google_place_id) continue;
          if (found.has(row.google_place_id)) mergeCandidate(found.get(row.google_place_id), row);
          else found.set(row.google_place_id, row);
        }
        console.log(`${places.length} (${found.size} unique)`);
      } catch (error) {
        failures.push({ hub: `${hub.town}, ${hub.state}`, query: query.q, error: error.message });
        console.log(`FAILED: ${error.message}`);
      }
    }
  }

  const rows = [...found.values()].sort((a, b) => a.business_name.localeCompare(b.business_name));
  const date = new Date().toISOString().slice(0, 10);
  const dataPath = path.join(OUT_DIR, `route-candidates-${date}.jsonl`);
  fs.writeFileSync(dataPath, rows.map((row) => JSON.stringify(row)).join("\n") + "\n");

  const summary = {
    ...preview,
    mode: "write",
    requests,
    estimated_aud: Math.round(requests * AUD_PER_REQUEST * 100) / 100,
    unique_places: rows.length,
    with_phone: rows.filter((row) => row.phone).length,
    with_website: rows.filter((row) => row.website).length,
    operational: rows.filter((row) => row.business_status === "OPERATIONAL").length,
    failures,
    output: path.relative(ROOT, dataPath),
    publication_status: "review_only",
    retention_note: "Place IDs are discovery provenance. Verify retention rights, category accuracy and public-source evidence before publishing.",
  };
  fs.writeFileSync(
    path.join(OUT_DIR, `route-candidates-${date}-summary.json`),
    JSON.stringify(summary, null, 2) + "\n"
  );
  console.log(JSON.stringify(summary, null, 2));
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
