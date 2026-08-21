#!/usr/bin/env node
/**
 * Budget-locked Google Places discovery for caravan-suitable stays on
 * Queensland touring routes.
 *
 * Discovery only. Results remain private review candidates under
 * storage/imports/qld-stay-coverage. This tool never publishes or imports them.
 * Hotels, motels, hostels and apartment accommodation are deliberately excluded.
 */
"use strict";

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const OUT_DIR = path.join(ROOT, "storage", "imports", "qld-stay-coverage");
const TOWN_SEED = path.join(ROOT, "database", "seeds", "towns_national.json");
const ABSOLUTE_BUDGET_CAP_AUD = 100;
const AUD_PER_REQUEST = 0.055;

// Touring gateways, coastal and inland corridors, Cape/Gulf routes and common
// outback overnight stops. Nearby localities are covered by a 40 km bias.
const ROUTE_HUB_NAMES = [
  "Agnes Water", "Airlie Beach", "Alpha", "Aramac", "Atherton", "Augathella",
  "Ayr", "Bamaga", "Barcaldine", "Beaudesert", "Bedourie", "Biggenden",
  "Biloela", "Birdsville", "Blackall", "Blackbutt", "Blackwater", "Bollon",
  "Boonah", "Boulia", "Bowen", "Brisbane", "Bundaberg", "Burketown",
  "Caboolture", "Cairns", "Calliope", "Caloundra", "Camooweal", "Cania",
  "Canungra", "Capella", "Cardwell", "Charleville", "Charters Towers", "Childers",
  "Chillagoe", "Chinchilla", "Clairview", "Clermont", "Cloncurry", "Coen",
  "Collinsville", "Cooktown", "Coolangatta", "Cooroy", "Cunnamulla", "Daintree",
  "Dalby", "Dirranbandi", "Duaringa", "Dysart", "Eidsvold", "Emerald",
  "Emu Park", "Eromanga", "Esk", "Eungella", "Finch Hatton", "Gayndah",
  "Georgetown", "Gin Gin", "Gladstone", "Goondiwindi", "Goomeri", "Gordonvale",
  "Gympie", "Herberton", "Hervey Bay", "Home Hill", "Hughenden", "Ingham",
  "Injune", "Innisfail", "Inskip Point", "Inglewood", "Isisford", "Jandowae",
  "Jericho", "Julia Creek", "Karumba", "Kenilworth", "Kilcoy", "Kilkivan",
  "Kingaroy", "Kowanyama", "Kuranda", "Laura", "Longreach", "Mackay",
  "Malanda", "Maleny", "Mareeba", "Marlborough", "Maroochydore", "Maryborough",
  "Middlemount", "Miles", "Millmerran", "Miriam Vale", "Mission Beach", "Mitchell",
  "Monto", "Moranbah", "Moreton Island", "Morven", "Mossman", "Mount Garnet",
  "Mount Isa", "Mount Morgan", "Mount Surprise", "Moura", "Mundubbera", "Murgon",
  "Nanango", "Nebo", "Noosa Heads", "Normanton", "Pentland", "Pomona",
  "Port Douglas", "Proserpine", "Quilpie", "Rainbow Beach", "Ravenshoe", "Richmond",
  "Rockhampton", "Rolleston", "Roma", "Sarina", "Seisia", "Seventeen Seventy",
  "Southport", "Springsure", "St George", "St Lawrence", "Stanthorpe", "Surat",
  "Tambo", "Tannum Sands", "Taroom", "Texas", "Thargomindah", "Theodore",
  "Tin Can Bay", "Tinaroo", "Toogoolawah", "Toowoomba", "Townsville", "Tully",
  "Warwick", "Weipa", "Windorah", "Winton", "Wondai", "Woodford", "Woodgate",
  "Yarraman", "Yeppoon", "Yungaburra",
];

const QUERIES = [
  { q: "caravan park RV park", suggested_type: "caravan_park" },
  { q: "campground camping area", suggested_type: "campground" },
  { q: "free camp caravan overnight", suggested_type: "free_camp" },
  { q: "national park campground", suggested_type: "campground" },
  { q: "showground camping council camp", suggested_type: "showground" },
  { q: "farm stay camping station stay caravans", suggested_type: "farm_stay" },
  { q: "overnight rest area caravans", suggested_type: "rest_area" },
  { q: "bush camp campsite", suggested_type: "campground" },
  { q: "low cost camping reserve", suggested_type: "free_camp" },
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
  const byName = new Map(
    seed.towns
      .filter((town) => town.state === "QLD")
      .map((town) => [String(town.name).toLowerCase(), town])
  );
  return ROUTE_HUB_NAMES.map((name) => {
    const town = byName.get(name.toLowerCase());
    if (!town || !Number.isFinite(town.lat) || !Number.isFinite(town.lng)) {
      throw new Error(`Missing Queensland route-hub coordinates: ${name}`);
    }
    return { town: name, state: "QLD", region: town.region, lat: town.lat, lng: town.lng };
  });
}

function fieldMask() {
  return [
    "places.id", "places.displayName", "places.formattedAddress", "places.location",
    "places.types", "places.nationalPhoneNumber", "places.websiteUri",
    "places.businessStatus",
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
      textQuery: `${query.q} near ${hub.town} Queensland Australia -hotel -motel`,
      maxResultCount: 20,
      locationBias: {
        circle: {
          center: { latitude: hub.lat, longitude: hub.lng },
          radius: 40000,
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

function looksCaravanSuitable(place) {
  const name = String(place.displayName?.text || "").toLowerCase();
  const types = new Set(place.types || []);
  const positive = /(caravan|rv park|campground|camp ground|camping|camp site|campsite|holiday park|tourist park|showground|showgrounds|rest area|free camp|bush camp|station stay|farm stay|camping reserve)/i;
  const accommodationOnly = /(hotel|motel|hostel|apartment|serviced apartment|bed\s*(and|&)\s*breakfast|b&b|lodge|resort)/i;
  const accommodationUnit = /[-–—]\s*.*\b(room|apartment|suite|villa)\b/i;
  if (accommodationUnit.test(name)) return false;
  if (types.has("rv_park") || types.has("campground")) return true;
  if (accommodationOnly.test(name) && !positive.test(name)) return false;
  return positive.test(name);
}

function inferStayType(place, query) {
  const name = String(place.displayName?.text || "").toLowerCase();
  const types = new Set(place.types || []);
  if (/national park/.test(name)) return "national_park";
  if (/council camp|council reserve/.test(name)) return "council_camp";
  if (/showground/.test(name)) return "showground";
  if (/rest area|roadside stop|overnight stop/.test(name)) return "rest_area";
  if (/free camp|freedom camp/.test(name)) return "free_camp";
  if (/station stay|station camp/.test(name)) return "station_stay";
  if (/farm stay/.test(name)) return "farm_stay";
  if (types.has("rv_park") || /caravan park|holiday park|tourist park|rv park/.test(name)) return "caravan_park";
  if (types.has("campground") || /campground|camp ground|camping|camp site|campsite|bush camp/.test(name)) return "campground";
  return query.suggested_type;
}

function inferPriceType(place, stayType) {
  const name = String(place.displayName?.text || "").toLowerCase();
  if (stayType === "free_camp" || /\bfree\b/.test(name)) return "free";
  if (/donation/.test(name)) return "donation";
  if (/low cost|low-cost|budget/.test(name)) return "low_cost";
  return "unknown";
}

function candidate(place, hub, query) {
  const placeId = String(place.id || "").replace(/^places\//, "");
  const stayType = inferStayType(place, query);
  return {
    external_id: placeId ? `places:${placeId}` : null,
    google_place_id: placeId || null,
    name: place.displayName?.text || "",
    address: place.formattedAddress || null,
    phone: place.nationalPhoneNumber || null,
    website: place.websiteUri || null,
    latitude: place.location?.latitude ?? null,
    longitude: place.location?.longitude ?? null,
    business_status: place.businessStatus || null,
    place_types: place.types || [],
    stay_type: stayType,
    price_type: inferPriceType(place, stayType),
    route_hubs: [`${hub.town}, QLD`],
    discovery_queries: [query.q],
    state: "QLD",
    region: hub.region,
    source_name: "google-places",
    source_licence: "Google Maps Platform terms — discovery only",
    publication_status: "review_only",
    needs_independent_retention_check: true,
    discovered_at: new Date().toISOString().slice(0, 10),
  };
}

function mergeCandidate(existing, incoming) {
  for (const key of ["route_hubs", "discovery_queries"]) {
    existing[key] = [...new Set(existing[key].concat(incoming[key]))];
  }
  return existing;
}

async function main() {
  const requestedBudget = Number(arg("--budget-aud", "100"));
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
    scope: "Queensland caravan-suitable stays on touring routes",
    excludes: ["hotels", "motels", "hostels", "apartments", "general resorts"],
    hubs: hubs.length,
    queries: QUERIES.length,
    planned_requests: plannedRequests,
    hard_cap_requests: hardCapRequests,
    budget_aud: budgetAud,
    aud_per_request_estimate: AUD_PER_REQUEST,
    planned_cost_aud: Math.round(Math.min(plannedRequests, hardCapRequests) * AUD_PER_REQUEST * 100) / 100,
  };
  console.log(JSON.stringify(preview, null, 2));
  if (dryRun) return;

  const key = String(process.env.GOOGLE_PLACES_API_KEY || "").trim();
  if (!key) throw new Error("GOOGLE_PLACES_API_KEY is required for --write");

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const found = new Map();
  let requests = 0;
  let excludedAccommodation = 0;
  const failures = [];

  outer: for (const hub of hubs) {
    for (const query of QUERIES) {
      if (requests >= hardCapRequests) break outer;
      requests++;
      process.stdout.write(`[${requests}/${Math.min(plannedRequests, hardCapRequests)}] ${hub.town}, QLD · ${query.q} … `);
      try {
        const result = await searchText(key, hub, query);
        const places = result.places || [];
        let accepted = 0;
        for (const place of places) {
          if (!looksCaravanSuitable(place)) {
            excludedAccommodation++;
            continue;
          }
          const row = candidate(place, hub, query);
          if (!row.google_place_id) continue;
          accepted++;
          if (found.has(row.google_place_id)) mergeCandidate(found.get(row.google_place_id), row);
          else found.set(row.google_place_id, row);
        }
        console.log(`${places.length} returned, ${accepted} accepted (${found.size} unique)`);
      } catch (error) {
        failures.push({ hub: `${hub.town}, QLD`, query: query.q, error: error.message });
        console.log(`FAILED: ${error.message}`);
      }
    }
  }

  const rows = [...found.values()].sort((a, b) => a.name.localeCompare(b.name));
  const date = new Date().toISOString().slice(0, 10);
  const dataPath = path.join(OUT_DIR, `stay-candidates-${date}.jsonl`);
  fs.writeFileSync(dataPath, rows.map((row) => JSON.stringify(row)).join("\n") + "\n");
  const countsByType = Object.fromEntries(
    [...new Set(rows.map((row) => row.stay_type))].sort().map((stayType) => [
      stayType,
      rows.filter((row) => row.stay_type === stayType).length,
    ])
  );
  const summary = {
    ...preview,
    mode: "write",
    requests,
    estimated_aud: Math.round(requests * AUD_PER_REQUEST * 100) / 100,
    unique_places: rows.length,
    by_stay_type: countsByType,
    with_phone: rows.filter((row) => row.phone).length,
    with_website: rows.filter((row) => row.website).length,
    operational: rows.filter((row) => row.business_status === "OPERATIONAL").length,
    excluded_accommodation_results: excludedAccommodation,
    failures,
    output: path.relative(ROOT, dataPath),
    publication_status: "review_only",
    retention_note: "Verify caravan suitability, lawful overnight access, current restrictions, fees and independent public-source evidence before publishing.",
  };
  fs.writeFileSync(
    path.join(OUT_DIR, `stay-candidates-${date}-summary.json`),
    JSON.stringify(summary, null, 2) + "\n"
  );
  console.log(JSON.stringify(summary, null, 2));
}

main().catch((error) => {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
