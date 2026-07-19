// VanAssist national coverage builder (OpenStreetMap / Overpass API — FREE).
//
// Finds REAL vehicle/caravan/trade businesses across every Australian state from
// OpenStreetMap and writes them to database/seeds/businesses_osm.json as the same
// kind of clearly-marked "unclaimed listing" the NationalImportSeeder imports.
// No API key and no cost: data comes from the community Overpass API.
//
// It is efficient: ONE Overpass query per state (8 total), not one per town. Each
// returned business is assigned to the NEAREST known town (and that town's region)
// from database/seeds/towns_national.json, so listings land in the right place and
// surface on the matching town/region/service pages.
//
// Requires Node 18+ (global fetch).
//
// Usage:
//   node tools/osm-import.js                       # all states, fetch + write
//   node tools/osm-import.js --states QLD,NSW       # a subset of states
//   node tools/osm-import.js --dry-run              # fetch + report, write nothing
//   node tools/osm-import.js --from-cache           # reprocess cached responses only
//   node tools/osm-import.js --max-km 80            # nearest-town distance cap (default 60)
//   node tools/osm-import.js --no-metros            # skip metro bounding-box scans
//   node tools/osm-import.js --endpoint <url>       # alternate Overpass endpoint
//
// Idempotent downstream: each listing's id is the stable OSM id (e.g. osm-n123),
// so re-importing only fills gaps. Only contact details OSM publishes are stored;
// scraped emails are kept private for outreach (the seeder hides them publicly).

"use strict";

const fs = require("fs");
const path = require("path");

const SEEDS_DIR = path.join(__dirname, "..", "database", "seeds");
const TOWNS_FILE = path.join(SEEDS_DIR, "towns_national.json");
const NATIONAL_FILE = path.join(SEEDS_DIR, "national_import.json");
const OUT_FILE = path.join(SEEDS_DIR, "businesses_osm.json");
const CACHE_DIR = path.join(__dirname, ".osm-cache");

const { loadMajorCities, nearestMajorCity } = require("./lib/major-cities");

const ALL_STATES = ["QLD", "NSW", "VIC", "SA", "WA", "TAS", "NT", "ACT"];

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}
function flag(name) {
  return process.argv.includes(name);
}

const STATES = arg("--states", "").trim()
  ? arg("--states", "").split(",").map((s) => s.trim().toUpperCase()).filter((s) => ALL_STATES.includes(s))
  : ALL_STATES;
const ENDPOINT = arg("--endpoint", "https://overpass-api.de/api/interpreter");
const MAX_KM = parseFloat(arg("--max-km", "60"));
const DRY_RUN = flag("--dry-run");
const FROM_CACHE = flag("--from-cache");
const NO_CACHE = flag("--no-cache");
const SKIP_METROS = flag("--no-metros");

// OSM tag selectors -> default VanAssist trade bucket for a hit. Trade buckets map
// to services in App\Services\NationalImportSeeder (TRADE_PRIMARY / TRADE_RELATED).
// Keep in sync with App\Services\OsmRefreshService::SELECTORS.
// Note: Overpass uses POSIX ERE — no \\b word boundaries.
const SELECTORS = [
  { sel: '["shop"="car_repair"]', cat: "mechanical" },
  { sel: '["shop"="tyres"]', cat: "mechanical" },
  { sel: '["shop"="car_parts"]', cat: "mechanical" },
  { sel: '["shop"="caravan"]', cat: "caravan" },
  { sel: '["craft"="caravan"]', cat: "caravan" },
  { sel: '["shop"="trailer"]', cat: "trailer" },
  { sel: '["craft"="plumber"]', cat: "plumber" },
  { sel: '["craft"="electrician"]', cat: "autoelec" },
  { sel: '["craft"="hvac"]', cat: "caravan" },
  { sel: '["craft"="electronics_repair"]', cat: "autoelec" },
  { sel: '["craft"="metal_construction"]', cat: "trailer" },
  { sel: '["craft"="welder"]', cat: "trailer" },
  { sel: '["shop"="gas"]', cat: "gasfitter" },
  { sel: '["amenity"="vehicle_inspection"]', cat: "roadworthy" },
  { sel: '["service:vehicle:car_repair"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:tyres"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:brakes"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:oil_change"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:diagnostics"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:electrical"="yes"]', cat: "autoelec" },
  { sel: '["service:vehicle:air_conditioning"="yes"]', cat: "caravan" },
  { sel: '["service:vehicle:truck_repair"="yes"]', cat: "mechanical" },
  { sel: '["service:vehicle:motorhome_repair"="yes"]', cat: "caravan" },
  { sel: '["service:vehicle:caravan_repair"="yes"]', cat: "caravan" },
];

// Extra name-based Overpass selectors (run as a second query per area).
const NAME_SELECTORS = [
  { sel: '["name"~"caravan",i]["name"~"repair|service|servicing",i]', cat: "caravan" },
  { sel: '["name"~"motorhome|campervan|camper trailer| rv ",i]["name"~"repair|service",i]', cat: "caravan" },
  { sel: '["name"~"mobile mechanic|mobile diesel|mobile tyre|mobile tire",i]', cat: "mechanical" },
  { sel: '["name"~"auto.?elect|12.?volt|12v ",i]', cat: "autoelec" },
  { sel: '["name"~"roadworth|safety cert|pink slip|blue slip",i]', cat: "roadworthy" },
  { sel: '["name"~"gas fitt|gas appliance| lpg ",i]', cat: "gasfitter" },
  { sel: '["name"~"trailer",i]["name"~"repair|service|engineer|weld",i]', cat: "trailer" },
  { sel: '["name"~"brake",i]["name"~"bearing|trailer|caravan",i]', cat: "trailer" },
  { sel: '["name"~"mobile weld|mobile plumb",i]', cat: "plumber" },
  { sel: '["name"~"air.?con|aircon|caravan fridge|rv fridge",i]', cat: "caravan" },
  { sel: '["name"~"roadside assist|roadside rescue",i]', cat: "roadside" },
];

// Name heuristics refine/augment the trade buckets a listing is matched to.
const NAME_RULES = [
  { re: /auto\s?elec|auto-?electric|12\s?volt|12v\b|dual\s?battery/i, cat: "autoelec" },
  { re: /caravan|camper|\brv\b|recreational vehicle|motorhome|campervan/i, cat: "caravan" },
  { re: /trailer|horse float|box trailer|brake.?s?\s*(and|&)?\s*bearing/i, cat: "trailer" },
  { re: /roadworth|safety cert|pink slip|blue slip|\brwc\b|e-?safety|inspection station|safety certificate/i, cat: "roadworthy" },
  { re: /roadside\s*(assist|rescue|help)/i, cat: "roadside" },
  { re: /plumb|mobile weld/i, cat: "plumber" },
  { re: /gas\s?(fitt|appliance|service)|gasfitt|\blpg\b/i, cat: "gasfitter" },
  { re: /air\s?con|aircon|caravan fridge|rv fridge|refrigerat/i, cat: "caravan" },
  { re: /tyre|tire|wheel align/i, cat: "mechanical" },
  { re: /mechanic|automotive|\bmotors?\b|car service|vehicle service|\b4wd\b|diesel|mobile diesel/i, cat: "mechanical" },
];

// Short human-readable services blurb per trade bucket.
const TRADE_BLURB = {
  caravan: "Caravan and RV repairs",
  autoelec: "Auto electrical (12-volt, batteries)",
  mechanical: "Mechanical repairs and servicing",
  trailer: "Trailer repairs and engineering",
  plumber: "Plumbing",
  gasfitter: "Gas appliance servicing",
  roadworthy: "Roadworthy / safety-certificate inspections",
  roadside: "Roadside assistance",
};

function distKm(a, b, c, d) {
  const R = 6371, toRad = (x) => (x * Math.PI) / 180;
  const dLat = toRad(c - a), dLng = toRad(d - b);
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a)) * Math.cos(toRad(c)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

function loadTownsByState() {
  if (!fs.existsSync(TOWNS_FILE)) {
    console.error(`Missing ${TOWNS_FILE}. Run tools/build-national-towns.js first.`);
    process.exit(1);
  }
  const data = JSON.parse(fs.readFileSync(TOWNS_FILE, "utf8"));
  const byState = {};
  for (const t of data.towns || []) {
    (byState[t.state] = byState[t.state] || []).push(t);
  }
  return byState;
}

// Dedup keys from the already-curated national import so we never duplicate a
// business that is already listed (by phone, website host, or name+town).
function loadExistingKeys() {
  const phones = new Set(), hosts = new Set(), nameTown = new Set();
  if (!fs.existsSync(NATIONAL_FILE)) return { phones, hosts, nameTown };
  try {
    const data = JSON.parse(fs.readFileSync(NATIONAL_FILE, "utf8"));
    for (const b of data.businesses || []) {
      const ph = digits(b.phone);
      if (ph) phones.add(ph);
      const h = host(b.website);
      if (h) hosts.add(h);
      if (b.name && b.town) nameTown.add(slug(b.name) + "@" + slug(b.town));
    }
  } catch (_) {}
  return { phones, hosts, nameTown };
}

function digits(s) {
  const d = String(s || "").replace(/\D+/g, "");
  return d.length >= 6 ? d.replace(/^0/, "").slice(-9) : "";
}
function host(u) {
  if (!u) return "";
  try { return new URL(/^https?:\/\//i.test(u) ? u : "https://" + u).host.replace(/^www\./, "").toLowerCase(); }
  catch (_) { return ""; }
}
function slug(s) {
  return String(s || "").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
}

function overpassQL(state, selectors = SELECTORS) {
  const parts = selectors.map((s) => `  nwr${s.sel}(area.a);`).join("\n");
  return `[out:json][timeout:240];\narea["ISO3166-2"="AU-${state}"]->.a;\n(\n${parts}\n);\nout center tags;`;
}

function overpassQLAround(lat, lng, radiusKm, selectors = SELECTORS) {
  const meters = Math.round(radiusKm * 1000);
  const parts = selectors.map((s) => `  nwr${s.sel}(around:${meters},${lat},${lng});`).join("\n");
  return `[out:json][timeout:180];\n(\n${parts}\n);\nout center tags;`;
}

async function fetchOverpass(body, cacheFile) {
  if (FROM_CACHE) {
    if (!fs.existsSync(cacheFile)) {
      return null;
    }
    return JSON.parse(fs.readFileSync(cacheFile, "utf8")).elements || [];
  }

  let lastErr;
  for (let attempt = 1; attempt <= 4; attempt++) {
    try {
      const res = await fetch(ENDPOINT, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
          "User-Agent": "VanAssist-OSM-Import/1.3 (vanassist@condrendigital.com.au)",
        },
        body,
      });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const json = await res.json();
      const elements = json.elements || [];
      if (!NO_CACHE) {
        fs.mkdirSync(CACHE_DIR, { recursive: true });
        fs.writeFileSync(cacheFile, JSON.stringify({ elements }, null, 0));
      }
      return elements;
    } catch (e) {
      lastErr = e;
      const wait = [0, 5000, 15000, 30000][attempt] || 30000;
      console.warn(`  attempt ${attempt} failed (${e.message}); retrying in ${wait / 1000}s`);
      await new Promise((r) => setTimeout(r, wait));
    }
  }
  console.error(`  giving up: ${lastErr && lastErr.message}`);
  return [];
}

async function fetchState(state) {
  const cacheFile = path.join(CACHE_DIR, `${state}.json`);
  if (FROM_CACHE && !fs.existsSync(cacheFile)) {
    console.warn(`  [${state}] no cache, skipping (remove --from-cache to fetch)`);
    return [];
  }
  // State-wide: tag selectors only (name regex on whole states times out / 429s).
  const body = "data=" + encodeURIComponent(overpassQL(state, SELECTORS));
  console.log(`Fetching ${state} (Overpass tags)…`);
  return (await fetchOverpass(body, cacheFile)) || [];
}

async function fetchMetro(metro) {
  const cacheFile = path.join(CACHE_DIR, `metro-${metro.id}.json`);
  if (FROM_CACHE && !fs.existsSync(cacheFile)) {
    console.warn(`  [metro:${metro.id}] no cache, skipping`);
    return [];
  }
  const radiusKm = metro.scan_radius_km || 50;
  const body = "data=" + encodeURIComponent(overpassQLAround(metro.lat, metro.lng, radiusKm, SELECTORS));
  console.log(`Fetching ${metro.name}, ${metro.state} (${radiusKm} km tags)…`);
  const tagged = (await fetchOverpass(body, cacheFile)) || [];

  // Name matches only on metro/city radii — denser per-suburb coverage without
  // hammering Overpass on whole-state queries.
  const nameCache = path.join(CACHE_DIR, `metro-${metro.id}-names.json`);
  const nameBody = "data=" + encodeURIComponent(overpassQLAround(metro.lat, metro.lng, radiusKm, NAME_SELECTORS));
  console.log(`Fetching ${metro.name} (name matches)…`);
  const named = (await fetchOverpass(nameBody, nameCache)) || [];
  return mergeElements(tagged, named);
}

function mergeElements(a, b) {
  const map = new Map();
  for (const el of [...a, ...b]) {
    const key = `${el.type}:${el.id}`;
    if (!map.has(key)) map.set(key, el);
  }
  return [...map.values()];
}

function elementLatLng(el) {
  if (typeof el.lat === "number" && typeof el.lon === "number") return [el.lat, el.lon];
  if (el.center && typeof el.center.lat === "number") return [el.center.lat, el.center.lon];
  return null;
}

function catsFor(el, defCat) {
  const set = new Set([defCat]);
  const name = (el.tags && el.tags.name) || "";
  for (const rule of NAME_RULES) if (rule.re.test(name)) set.add(rule.cat);
  return [...set];
}

function buildAddress(t) {
  const line = [t["addr:housenumber"], t["addr:street"]].filter(Boolean).join(" ");
  const locality = localityFromTags(t);
  return [line, locality, t["addr:postcode"]].filter(Boolean).join(", ").trim();
}

function localityFromTags(t) {
  return (t["addr:suburb"] || t["addr:city"] || t["addr:town"] || "").trim();
}

function buildTownNameIndex(townsByState) {
  const out = {};
  for (const [state, towns] of Object.entries(townsByState)) {
    const map = new Map();
    for (const town of towns) {
      map.set(String(town.name).toLowerCase(), town);
    }
    out[state] = map;
  }
  return out;
}

function resolveTown(tags, lat, lng, state, towns, townIndex) {
  const locality = localityFromTags(tags);
  if (locality) {
    const hit = townIndex[state]?.get(locality.toLowerCase());
    if (hit) {
      return { town: hit, km: distKm(lat, lng, hit.lat, hit.lng) };
    }
  }
  return nearestTown(towns, lat, lng, state);
}

function nearestTown(towns, lat, lng, state = null) {
  let best = null;
  let bestD = Infinity;
  for (const t of towns) {
    const dd = distKm(lat, lng, t.lat, t.lng);
    if (dd < bestD) {
      bestD = dd;
      best = t;
    }
  }
  if (best && bestD <= MAX_KM) {
    return { town: best, km: bestD };
  }

  const major = nearestMajorCity(lat, lng, state);
  if (major) {
    const radius = major.scan_radius_km || 80;
    let majorBest = null;
    let majorBestD = Infinity;
    for (const t of towns) {
      const dd = distKm(lat, lng, t.lat, t.lng);
      if (dd < majorBestD) {
        majorBestD = dd;
        majorBest = t;
      }
    }
    if (majorBest && majorBestD <= radius) {
      return { town: majorBest, km: majorBestD, majorCity: major.name };
    }
  }

  return null;
}

function processElement(el, towns, state, existing, seen) {
  const t = el.tags || {};
  const name = (t.name || "").trim();
  if (!name) {
    seen.dropped.unnamed++;
    return null;
  }

  const osmId = `osm-${el.type[0]}${el.id}`;
  if (seen.osmIds.has(osmId)) return null;

  const ll = elementLatLng(el);
  if (!ll) {
    seen.dropped.noCoord++;
    return null;
  }

  const near = resolveTown(t, ll[0], ll[1], state, towns, seen.townIndex);
  if (!near) {
    seen.dropped.noTown++;
    return null;
  }

  const phone = (t.phone || t["contact:phone"] || "").trim();
  const website = (t.website || t["contact:website"] || "").trim();
  const email = (t.email || t["contact:email"] || "").trim();

  const ph = digits(phone);
  const hh = host(website);
  const nt = slug(name) + "@" + slug(near.town.name);
  if ((ph && seen.phones.has(ph)) || (hh && seen.hosts.has(hh)) || seen.nameTown.has(nt)) {
    seen.dropped.dup++;
    return null;
  }

  let defCat = "mechanical";
  for (const s of [...SELECTORS, ...NAME_SELECTORS]) {
    // Tag equality selectors look like ["shop"="car_repair"]; name selectors are skipped here.
    const m = /^\[\"([^\"]+)\"=\"([^\"]+)\"\]$/.exec(s.sel);
    if (m && t[m[1]] === m[2]) {
      defCat = s.cat;
      break;
    }
  }
  const cats = catsFor(el, defCat);
  const modes = /mobile/i.test(name) ? ["mobile", "workshop"] : ["workshop"];
  const services = [...new Set(cats.map((c) => TRADE_BLURB[c]).filter(Boolean))].join("; ");

  seen.osmIds.add(osmId);
  if (ph) seen.phones.add(ph);
  if (hh) seen.hosts.add(hh);
  seen.nameTown.add(nt);

  return {
    id: osmId,
    name,
    town: near.town.name,
    region: near.town.region,
    state,
    cats,
    phone: phone || "",
    website: website ? (/^https?:\/\//i.test(website) ? website : "https://" + website) : "",
    email: email || "",
    address: buildAddress(t),
    modes,
    services,
    source_type: "osm",
    note: "Sourced from OpenStreetMap (community-maintained). Details may be incomplete — please confirm before booking.",
  };
}

async function main() {
  const townsByState = loadTownsByState();
  const townIndex = buildTownNameIndex(townsByState);
  const existing = loadExistingKeys();
  const { metros, cities } = loadMajorCities();

  const businesses = [];
  const perState = {};
  const perMetro = {};
  const perCity = {};
  const seen = {
    phones: new Set(existing.phones),
    hosts: new Set(existing.hosts),
    nameTown: new Set(existing.nameTown),
    osmIds: new Set(),
    townIndex,
    dropped: { unnamed: 0, noCoord: 0, noTown: 0, dup: 0 },
  };

  for (const state of STATES) {
    const towns = townsByState[state] || [];
    if (towns.length === 0) {
      console.warn(`No towns for ${state}; skipping`);
      continue;
    }
    const elements = await fetchState(state);
    console.log(`  ${state}: ${elements.length} raw OSM elements`);

    let added = 0;
    for (const el of elements) {
      const row = processElement(el, towns, state, existing, seen);
      if (!row) continue;
      businesses.push(row);
      added++;
    }
    perState[state] = added;
    console.log(`  ${state}: +${added} listings`);
    if (!FROM_CACHE && state !== STATES[STATES.length - 1]) {
      await new Promise((r) => setTimeout(r, 6000));
    }
  }

  if (!SKIP_METROS) {
    console.log("\nMetro radius scans (major cities)…");
    for (const metro of metros) {
      const towns = townsByState[metro.state] || [];
      if (towns.length === 0) continue;
      const elements = await fetchMetro(metro);
      console.log(`  ${metro.name}: ${elements.length} raw elements`);
      let added = 0;
      for (const el of elements) {
        const row = processElement(el, towns, metro.state, existing, seen);
        if (!row) continue;
        businesses.push(row);
        added++;
      }
      perMetro[metro.id] = added;
      console.log(`  ${metro.name}: +${added} new listings`);
      if (!FROM_CACHE) {
        await new Promise((r) => setTimeout(r, 5000));
      }
    }

    console.log("\nRegional city radius scans…");
    for (const city of cities) {
      const towns = townsByState[city.state] || [];
      if (towns.length === 0) continue;
      const scanCity = {
        id: slug(city.name) + "-" + String(city.state).toLowerCase(),
        name: city.name,
        state: city.state,
        lat: city.lat,
        lng: city.lng,
        scan_radius_km: city.scan_radius_km || 45,
      };
      const elements = await fetchMetro(scanCity);
      console.log(`  ${city.name}: ${elements.length} raw elements`);
      let added = 0;
      for (const el of elements) {
        const row = processElement(el, towns, city.state, existing, seen);
        if (!row) continue;
        businesses.push(row);
        added++;
      }
      perCity[scanCity.id] = added;
      console.log(`  ${city.name}: +${added} new listings`);
      if (!FROM_CACHE) {
        await new Promise((r) => setTimeout(r, 5000));
      }
    }
  }

  businesses.sort((a, b) => (a.state < b.state ? -1 : a.state > b.state ? 1 : a.town.localeCompare(b.town)));

  console.log("\nSummary:");
  for (const s of STATES) console.log("  " + s + ":", perState[s] || 0);
  if (!SKIP_METROS) {
    console.log("  metros:");
    for (const [id, n] of Object.entries(perMetro)) console.log("    " + id + ":", n);
    console.log("  regional cities:");
    for (const [id, n] of Object.entries(perCity)) console.log("    " + id + ":", n);
  }
  console.log("  TOTAL:", businesses.length);
  console.log("  dropped:", JSON.stringify(seen.dropped));

  if (DRY_RUN) {
    console.log("\n--dry-run: nothing written.");
    return;
  }

  const out = {
    _comment:
      "Generated by tools/osm-import.js from OpenStreetMap via the Overpass API. Includes state-wide scans plus metro and regional-city radius passes. Consumed by App\\Services\\NationalImportSeeder::seedOsm().",
    generated_at: new Date().toISOString(),
    source: "OpenStreetMap contributors, via Overpass API (ODbL)",
    count: businesses.length,
    perState,
    perMetro,
    perCity,
    businesses,
  };
  fs.writeFileSync(OUT_FILE, JSON.stringify(out, null, 0));
  console.log("\nWrote", businesses.length, "businesses ->", OUT_FILE);
}

main().catch((e) => { console.error(e); process.exit(1); });
