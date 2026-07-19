// VanAssist national coverage builder (Google Places API, New).
//
// Finds REAL, caravan-relevant trade businesses (caravan/RV repairers, mobile
// mechanics, auto electricians, mobile gas/plumbing, cooling) per town using
// Google Places Text Search, dedupes them against the businesses already in
// database/seeds/national_import.json, and (with --write) appends the new ones
// as clearly-marked unclaimed listings the existing NationalImportSeeder can
// import. Only contact details Google publishes for the business are stored.
//
// Requires Node 18+ (global fetch) and a Google Places API (New) key.
//
// Setup:
//   1. Google Cloud console -> enable "Places API (New)".
//   2. Create an API key; restrict it to the Places API.
//   3. Export it:   set GOOGLE_PLACES_API_KEY=...   (PowerShell: $env:GOOGLE_PLACES_API_KEY="...")
//
// Usage:
//   node tools/places-import.js --launch --dry-run      # preview the 12 launch towns
//   node tools/places-import.js --major-cities --dry-run # all major cities nationwide
//   node tools/places-import.js --launch --write         # add launch-town results
//   node tools/places-import.js --state QLD --dry-run     # every QLD town we know
//   node tools/places-import.js --town "Cairns" --town "Mackay" --write
//   node tools/places-import.js --state NSW --radius 40000 --max-pages 2 --write
//
// Flags:
//   --launch              use the built-in 12 launch towns (Central QLD / Wide Bay)
//   --major-cities        all major cities from database/seeds/major_cities.json
//   --state ABBR          all towns for that state from database/seeds/town_details.json
//   --town "Name"         a specific town (repeatable); pairs with --state, else inferred
//   --region id           force a region id for the selected towns (else auto-resolved)
//   --radius metres       search bias radius (default 35000)
//   --max-pages n         result pages per query, 1-3 (default 2; each page = up to 20)
//   --dry-run             print what WOULD be added; write nothing (default if no --write)
//   --write               append new listings into national_import.json (makes a .bak)
//   --key KEY             API key (else GOOGLE_PLACES_API_KEY env)
//
// Cost note: each Text Search page and the field mask used here are billed by
// Google (Text Search + Contact/Atmosphere data). A launch-town run is a few
// hundred calls; a full state is more. Start with --dry-run and a small scope.

"use strict";

const fs = require("fs");
const path = require("path");

const IMPORT_FILE = path.join(__dirname, "..", "database", "seeds", "national_import.json");
const TOWN_DETAILS_FILE = path.join(__dirname, "..", "database", "seeds", "town_details.json");
const { loadMajorCities } = require("./lib/major-cities");

// Region taxonomy (kept in sync with tools/extract-canvas.js / national_import.json).
const REGIONS = {
  QLD: ["seq", "downs", "widebay", "cq", "fitzroy", "mackay", "nq", "fnq", "outback"],
  NSW: ["nsw-sydney", "nsw-hunter", "nsw-north-coast", "nsw-northern-inland", "nsw-central-west", "nsw-riverina", "nsw-south-coast", "nsw-far-west"],
  VIC: ["vic-melb", "vic-geelong", "vic-ballarat", "vic-bendigo", "vic-gippsland", "vic-goulburn", "vic-westvic", "vic-murray"],
  SA: ["sa-adelaide", "sa-fleurieu", "sa-barossa", "sa-riverland", "sa-yorke-eyre", "sa-southeast", "sa-outback"],
  WA: ["wa-perth", "wa-southwest", "wa-greatsouthern", "wa-wheatbelt", "wa-midwest", "wa-goldfields", "wa-gascoyne", "wa-pilbara", "wa-kimberley"],
  TAS: ["tas-hobart", "tas-launceston", "tas-northwest", "tas-east"],
  NT: ["nt-darwin", "nt-katherine", "nt-tennant", "nt-alice", "nt-eastarnhem"],
  ACT: ["act-canberra"],
};
const REGION_TO_STATE = {};
for (const [state, ids] of Object.entries(REGIONS)) {
  for (const id of ids) REGION_TO_STATE[id] = state;
}

// The 12 launch towns, with explicit region + town-centre coordinates so the
// launch batch never depends on region inference.
const LAUNCH_TOWNS = [
  { town: "Gladstone", state: "QLD", region: "fitzroy", lat: -23.843, lng: 151.256 },
  { town: "Boyne Island", state: "QLD", region: "fitzroy", lat: -23.945, lng: 151.35 },
  { town: "Tannum Sands", state: "QLD", region: "fitzroy", lat: -23.948, lng: 151.368 },
  { town: "Calliope", state: "QLD", region: "fitzroy", lat: -24.007, lng: 151.201 },
  { town: "Agnes Water", state: "QLD", region: "fitzroy", lat: -24.211, lng: 151.905 },
  { town: "Seventeen Seventy", state: "QLD", region: "fitzroy", lat: -24.166, lng: 151.883 },
  { town: "Miriam Vale", state: "QLD", region: "fitzroy", lat: -24.329, lng: 151.565 },
  { town: "Biloela", state: "QLD", region: "cq", lat: -24.408, lng: 150.513 },
  { town: "Emerald", state: "QLD", region: "cq", lat: -23.527, lng: 148.159 },
  { town: "Rockhampton", state: "QLD", region: "fitzroy", lat: -23.379, lng: 150.51 },
  { town: "Yeppoon", state: "QLD", region: "fitzroy", lat: -23.132, lng: 150.739 },
  { town: "Bundaberg", state: "QLD", region: "widebay", lat: -24.866, lng: 152.349 },
];

// Caravan-relevant search queries. `cats` is the default trade bucket(s) for a
// hit on that query; name heuristics refine it further.
const QUERIES = [
  { q: "caravan repairs", cats: ["caravan"] },
  { q: "caravan and RV service centre", cats: ["caravan"] },
  { q: "mobile caravan repairs", cats: ["caravan"] },
  { q: "mobile mechanic", cats: ["mechanical"] },
  { q: "auto electrician caravan dual battery", cats: ["autoelec"] },
  { q: "caravan air conditioning and fridge repairs", cats: ["caravan"] },
  { q: "mobile caravan gas fitter", cats: ["gasfitter"] },
  { q: "caravan trailer brakes and bearings", cats: ["trailer"] },
  { q: "mobile roadworthy safety certificate caravan trailer", cats: ["roadworthy"] },
  { q: "roadworthy inspection station", cats: ["roadworthy"] },
  { q: "roadside assistance breakdown towing", cats: ["roadside"] },
];

// Words that must appear somewhere (name/types/query) for a result to count as
// a caravan-relevant trade. Filters out generic Places noise.
const TRADE_HINTS = [
  "caravan", "rv", "motorhome", "camper", "mechanic", "mechanical", "automotive",
  "auto elect", "auto-elect", "electrician", "electrical", "trailer", "tyre",
  "tire", "brake", "suspension", "gas", "plumb", "refrigeration", "air condition",
  "airconditioning", "12v", "12 volt", "solar", "battery", "batteries", "4x4",
  "4wd", "diesel", "repairs", "service",
  "roadworthy", "safety cert", "roadside", "breakdown", "towing", "tow truck", "inspection",
];

// Things that should never be listed as a service business here.
const EXCLUDE_HINTS = [
  "caravan park", "holiday park", "tourist park", "campground", "camping ground",
  "accommodation", "real estate", "hire ", "rental", "dealership", "for sale",
  "supercheap", "repco", "autobarn", "bcf ", "anaconda",
];

function parseArgs(argv) {
  const args = { towns: [], dryRun: true, write: false, radius: 35000, maxPages: 2 };
  for (let i = 2; i < argv.length; i++) {
    const a = argv[i];
    if (a === "--launch") args.launch = true;
    else if (a === "--major-cities") args.majorCities = true;
    else if (a === "--write") { args.write = true; args.dryRun = false; }
    else if (a === "--dry-run") args.dryRun = true;
    else if (a === "--state") args.state = (argv[++i] || "").toUpperCase();
    else if (a === "--town") args.towns.push(argv[++i]);
    else if (a === "--region") args.region = argv[++i];
    else if (a === "--radius") args.radius = parseInt(argv[++i], 10) || 35000;
    else if (a === "--max-pages") args.maxPages = Math.min(3, Math.max(1, parseInt(argv[++i], 10) || 2));
    else if (a === "--key") args.key = argv[++i];
    else console.warn("Ignoring unknown arg:", a);
  }
  return args;
}

function strSlug(s) {
  return String(s)
    .toLowerCase()
    .replace(/['’.]/g, "")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 180);
}

function normName(s) {
  return String(s || "").toLowerCase().replace(/[^a-z0-9]+/g, " ").trim();
}

function phoneDigits(s) {
  return String(s || "").replace(/\D+/g, "").replace(/^61/, "0");
}

function hostOf(url) {
  try { return new URL(url).host.replace(/^www\./, "").toLowerCase(); } catch { return ""; }
}

function distKm(a, b, c, d) {
  const R = 6371, toRad = (x) => (x * Math.PI) / 180;
  const dLat = toRad(c - a), dLng = toRad(d - b);
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a)) * Math.cos(toRad(c)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

// Build town -> region and region -> centroid from existing data so unknown
// towns can be auto-assigned to the nearest region within their state.
function buildRegionResolver(existing, townDetails) {
  const townRegion = {}; // "state|townslug" -> region
  const regionPts = {}; // region -> [{lat,lng}]
  for (const b of existing.businesses || []) {
    const region = b.region;
    const state = REGION_TO_STATE[region];
    if (!state || !b.town) continue;
    townRegion[state + "|" + strSlug(b.town)] = region;
    const d = (townDetails[state] || {})[b.town];
    if (d) (regionPts[region] = regionPts[region] || []).push(d);
  }
  const centroids = {};
  for (const [region, pts] of Object.entries(regionPts)) {
    centroids[region] = {
      lat: pts.reduce((s, p) => s + p.lat, 0) / pts.length,
      lng: pts.reduce((s, p) => s + p.lng, 0) / pts.length,
    };
  }
  return function resolve(state, town, lat, lng, forced) {
    if (forced) return forced;
    const known = townRegion[state + "|" + strSlug(town)];
    if (known) return known;
    let best = null, bestD = Infinity;
    for (const region of REGIONS[state] || []) {
      const c = centroids[region];
      if (!c || lat == null) continue;
      const dd = distKm(lat, lng, c.lat, c.lng);
      if (dd < bestD) { bestD = dd; best = region; }
    }
    return best || (REGIONS[state] || [])[0] || null;
  };
}

function resolveTargets(args, townDetails) {
  if (args.launch) return LAUNCH_TOWNS.slice();
  if (args.majorCities) {
    const { metros, cities } = loadMajorCities();
    return [...metros, ...cities].map((row) => ({
      town: row.name,
      state: row.state,
      lat: row.lat,
      lng: row.lng,
      region: row.region,
    }));
  }
  const out = [];
  const pushTown = (state, town) => {
    const d = (townDetails[state] || {})[town];
    if (!d) { console.warn(`  ! no coordinates for ${town} (${state}) in town_details.json - skipping`); return; }
    out.push({ town, state, lat: d.lat, lng: d.lng, region: args.region });
  };
  if (args.state && args.towns.length === 0) {
    for (const town of Object.keys(townDetails[args.state] || {})) pushTown(args.state, town);
  } else if (args.towns.length) {
    for (const town of args.towns) {
      if (args.state) { pushTown(args.state, town); continue; }
      const state = Object.keys(townDetails).find((st) => townDetails[st][town]);
      if (!state) { console.warn(`  ! ${town} not found in town_details.json (use --state) - skipping`); continue; }
      pushTown(state, town);
    }
  }
  return out;
}

function inferCats(name, types, queryCats) {
  const hay = (name + " " + (types || []).join(" ")).toLowerCase();
  const cats = new Set(queryCats);
  if (/auto.?elect|electrician|dual battery|12 ?v|12 volt/.test(hay)) cats.add("autoelec");
  if (/caravan|motorhome|\brv\b|camper/.test(hay)) cats.add("caravan");
  if (/mechanic|automotive|car (repair|service)|diesel/.test(hay)) cats.add("mechanical");
  if (/trailer|tyre|tire|wheel align|bearing/.test(hay)) cats.add("trailer");
  if (/\bgas\b|gasfit/.test(hay)) cats.add("gasfitter");
  if (/plumb/.test(hay)) cats.add("plumber");
  if (/roadworthy|safety cert|safety certificate|\brwc\b|inspection station|certificate of inspection/.test(hay)) cats.add("roadworthy");
  if (/roadside|breakdown|tow truck|towing service/.test(hay)) cats.add("roadside");
  return [...cats];
}

function inferModes(name, hasStreet) {
  const mobile = /mobile/i.test(name);
  const modes = [];
  if (mobile) modes.push("mobile");
  if (hasStreet || !mobile) modes.push("workshop");
  return modes.length ? modes : ["workshop"];
}

function looksRelevant(name, types) {
  const hay = (name + " " + (types || []).join(" ")).toLowerCase();
  if (EXCLUDE_HINTS.some((h) => hay.includes(h))) return false;
  return TRADE_HINTS.some((h) => hay.includes(h));
}

async function searchText(key, query, center, radius, maxPages) {
  const results = [];
  let pageToken = null;
  for (let page = 0; page < maxPages; page++) {
    const body = {
      textQuery: query,
      regionCode: "AU",
      maxResultCount: 20,
      locationBias: { circle: { center: { latitude: center.lat, longitude: center.lng }, radius } },
    };
    if (pageToken) body.pageToken = pageToken;
    const res = await fetch("https://places.googleapis.com/v1/places:searchText", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Goog-Api-Key": key,
        "X-Goog-FieldMask":
          "places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.websiteUri,places.location,places.types,places.primaryType,places.businessStatus,nextPageToken",
      },
      body: JSON.stringify(body),
    });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`Places API ${res.status}: ${text.slice(0, 300)}`);
    }
    const data = await res.json();
    for (const p of data.places || []) results.push(p);
    pageToken = data.nextPageToken;
    if (!pageToken) break;
    await sleep(2200); // next-page token needs a short delay before it is valid
  }
  return results;
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function main() {
  const args = parseArgs(process.argv);
  const key = args.key || process.env.GOOGLE_PLACES_API_KEY;
  if (!key) {
    console.error("Missing API key. Set GOOGLE_PLACES_API_KEY or pass --key. (Dry-run still needs it to query.)");
    process.exit(1);
  }

  const existing = JSON.parse(fs.readFileSync(IMPORT_FILE, "utf8"));
  const townDetails = JSON.parse(fs.readFileSync(TOWN_DETAILS_FILE, "utf8"));
  delete townDetails._comment;

  const resolveRegion = buildRegionResolver(existing, townDetails);
  const targets = resolveTargets(args, townDetails);
  if (targets.length === 0) {
    console.error("No target towns. Use --launch, --major-cities, --state ABBR, or --town \"Name\".");
    process.exit(1);
  }

  // Indexes for dedupe against what we already have.
  const existingNames = new Set();
  const existingPhones = new Set();
  const existingHosts = new Set();
  const existingSlugs = new Set();
  for (const b of existing.businesses) {
    if (b.id) existingSlugs.add(strSlug(b.id));
    existingNames.add(normName(b.name) + "|" + strSlug(b.town || ""));
    if (b.tel) existingPhones.add(phoneDigits(b.tel));
    if (b.website) existingHosts.add(hostOf(b.website));
  }

  const additions = [];
  const seenIds = new Set();
  let apiCalls = 0;

  for (const t of targets) {
    const region = resolveRegion(t.state, t.town, t.lat, t.lng, t.region);
    if (!region) { console.warn(`! could not resolve region for ${t.town} (${t.state}); skipping`); continue; }
    console.log(`\n== ${t.town}, ${t.state}  (region: ${region}) ==`);
    const townHits = new Map(); // place.id -> merged record

    for (const { q, cats } of QUERIES) {
      let places;
      try {
        places = await searchText(key, `${q} in ${t.town} ${t.state}`, t, args.radius, args.maxPages);
        apiCalls += args.maxPages;
      } catch (e) {
        console.warn(`  query "${q}" failed: ${e.message}`);
        continue;
      }
      for (const p of places) {
        const name = (p.displayName && p.displayName.text) || "";
        if (!name) continue;
        if (p.businessStatus && p.businessStatus !== "OPERATIONAL") continue;
        if (!looksRelevant(name, p.types)) continue;
        const rec = townHits.get(p.id) || { place: p, name, cats: new Set() };
        inferCats(name, p.types, cats).forEach((c) => rec.cats.add(c));
        townHits.set(p.id, rec);
      }
      await sleep(250);
    }

    for (const rec of townHits.values()) {
      const p = rec.place;
      const name = rec.name;
      const phone = p.nationalPhoneNumber || "";
      const website = p.websiteUri || "";
      const cats = [...rec.cats];
      if (cats.length === 0) continue;

      // Dedupe.
      const nameKey = normName(name) + "|" + strSlug(t.town);
      if (existingNames.has(nameKey)) continue;
      if (phone && existingPhones.has(phoneDigits(phone))) continue;
      if (website && existingHosts.has(hostOf(website))) continue;
      if (seenIds.has(p.id)) continue;
      seenIds.add(p.id);

      let id = strSlug(name) + "-" + strSlug(t.town);
      while (existingSlugs.has(id)) id += "-x";
      existingSlugs.add(id);

      const hasStreet = /\d/.test(p.formattedAddress || "");
      const business = {
        id,
        name,
        town: t.town,
        region,
        cats,
        modes: inferModes(name, hasStreet),
        services: `Listed via Google Places for ${t.town}. Caravan-relevant trade (${cats.join(", ")}). Details unverified - confirm services and suitability with the business before booking.`,
        source: "google-places",
        source_place_id: p.id,
      };
      if (phone) { business.phone = phone; business.tel = phoneDigits(phone); }
      if (website) { business.website = website; business.websiteLabel = hostOf(website); }
      if (p.formattedAddress) business.address = p.formattedAddress;

      // Record so later towns dedupe against this run too.
      existingNames.add(nameKey);
      if (phone) existingPhones.add(phoneDigits(phone));
      if (website) existingHosts.add(hostOf(website));

      additions.push(business);
      console.log(`  + ${name}  [${cats.join(",")}]  ${phone || website || "(no contact)"}`);
    }
  }

  console.log(`\nFound ${additions.length} new caravan-relevant businesses across ${targets.length} town(s). ~${apiCalls} Places pages requested.`);

  if (!args.write) {
    console.log("Dry run - nothing written. Re-run with --write to append to national_import.json.");
    return;
  }
  if (additions.length === 0) {
    console.log("Nothing new to write.");
    return;
  }

  const backup = IMPORT_FILE + ".bak";
  fs.copyFileSync(IMPORT_FILE, backup);
  existing.businesses.push(...additions);
  existing.generated_at = new Date().toISOString();
  existing.source = (existing.source || "") + " + google-places";
  fs.writeFileSync(IMPORT_FILE, JSON.stringify(existing, null, 2) + "\n");
  console.log(`Wrote ${additions.length} listings to ${IMPORT_FILE} (backup: ${path.basename(backup)}).`);
  console.log("Next: deploy, then re-run the national import on the server (idempotent).");
}

main().catch((e) => { console.error(e); process.exit(1); });
