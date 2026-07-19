// VanAssist national TOWN builder.
//
// Produces database/seeds/towns_national.json: every Australian locality
// (state, name, postcode, lat/lng) assigned to one of VanAssist's regions, so
// the NationalTownSeeder can create a complete town list for all states/
// territories - independent of whether a business exists there yet. This makes
// postcode / town search resolve anywhere in Australia, and every town surfaces
// the relevant regional + statewide providers.
//
// Source data: the open "australian_postcodes" dataset (Australia Post derived)
//   https://github.com/matthewproctor/australianpostcodes
// Download australian_postcodes.json, then:
//   node tools/build-national-towns.js --src "C:\\path\\au_postcodes.json"
// (defaults to %TEMP%/au_postcodes.json).
//
// Region assignment: each locality is mapped to the nearest VanAssist region
// CENTROID within the same state (centroids below mirror the region taxonomy in
// national_import.json / tools/places-import.js). Slugs are NOT generated here -
// the PHP seeder derives the town slug from the name via str_slug(), so dedupe
// against existing towns stays consistent.

"use strict";
const fs = require("fs");
const path = require("path");

const OUT_FILE = path.join(__dirname, "..", "database", "seeds", "towns_national.json");

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}
const SRC = arg("--src", path.join(process.env.TEMP || "/tmp", "au_postcodes.json"));

// Approximate centre (lat,lng) of every VanAssist region, keyed by region slug
// (the `id` used in national_import.json). Used to assign each locality to the
// nearest region within its state.
const REGION_CENTROIDS = {
  // QLD
  seq: [-27.47, 153.02], downs: [-27.56, 151.95], widebay: [-24.87, 152.35],
  cq: [-23.38, 150.51], fitzroy: [-23.52, 148.16], mackay: [-21.14, 149.19],
  nq: [-19.26, 146.82], fnq: [-16.92, 145.77], outback: [-23.44, 144.25],
  // NSW
  "nsw-sydney": [-33.87, 151.21], "nsw-hunter": [-32.93, 151.78],
  "nsw-north-coast": [-30.30, 153.12], "nsw-northern-inland": [-31.09, 150.93],
  "nsw-central-west": [-33.28, 149.10], "nsw-riverina": [-35.12, 147.37],
  "nsw-south-coast": [-34.88, 150.60], "nsw-far-west": [-31.95, 141.47],
  // VIC
  "vic-melb": [-37.81, 144.96], "vic-geelong": [-38.15, 144.36],
  "vic-ballarat": [-37.56, 143.86], "vic-bendigo": [-36.76, 144.28],
  "vic-gippsland": [-38.20, 146.54], "vic-goulburn": [-36.38, 145.40],
  "vic-westvic": [-36.71, 142.20], "vic-murray": [-34.19, 142.16],
  // SA
  "sa-adelaide": [-34.93, 138.60], "sa-fleurieu": [-35.55, 138.62],
  "sa-barossa": [-34.48, 138.99], "sa-riverland": [-34.28, 140.60],
  "sa-yorke-eyre": [-34.00, 136.20], "sa-southeast": [-37.50, 140.40],
  "sa-outback": [-30.50, 135.50],
  // WA
  "wa-perth": [-31.95, 115.86], "wa-southwest": [-33.33, 115.64],
  "wa-greatsouthern": [-35.03, 117.88], "wa-wheatbelt": [-31.65, 116.67],
  "wa-midwest": [-28.77, 114.61], "wa-goldfields": [-30.75, 121.47],
  "wa-gascoyne": [-24.88, 113.66], "wa-pilbara": [-20.74, 116.85],
  "wa-kimberley": [-17.96, 122.24],
  // TAS
  "tas-hobart": [-42.88, 147.33], "tas-launceston": [-41.43, 147.14],
  "tas-northwest": [-41.05, 145.91], "tas-east": [-41.65, 148.10],
  // NT
  "nt-darwin": [-12.46, 130.84], "nt-katherine": [-14.46, 132.26],
  "nt-tennant": [-19.65, 134.19], "nt-alice": [-23.70, 133.88],
  "nt-eastarnhem": [-12.20, 136.78],
  // ACT
  "act-canberra": [-35.28, 149.13],
};

// region slugs available per state (must match REGION_CENTROIDS keys)
const STATE_REGIONS = {
  QLD: ["seq", "downs", "widebay", "cq", "fitzroy", "mackay", "nq", "fnq", "outback"],
  NSW: ["nsw-sydney", "nsw-hunter", "nsw-north-coast", "nsw-northern-inland", "nsw-central-west", "nsw-riverina", "nsw-south-coast", "nsw-far-west"],
  VIC: ["vic-melb", "vic-geelong", "vic-ballarat", "vic-bendigo", "vic-gippsland", "vic-goulburn", "vic-westvic", "vic-murray"],
  SA: ["sa-adelaide", "sa-fleurieu", "sa-barossa", "sa-riverland", "sa-yorke-eyre", "sa-southeast", "sa-outback"],
  WA: ["wa-perth", "wa-southwest", "wa-greatsouthern", "wa-wheatbelt", "wa-midwest", "wa-goldfields", "wa-gascoyne", "wa-pilbara", "wa-kimberley"],
  TAS: ["tas-hobart", "tas-launceston", "tas-northwest", "tas-east"],
  NT: ["nt-darwin", "nt-katherine", "nt-tennant", "nt-alice", "nt-eastarnhem"],
  ACT: ["act-canberra"],
};
const STATES = Object.keys(STATE_REGIONS);

function distKm(a, b, c, d) {
  const R = 6371, toRad = (x) => (x * Math.PI) / 180;
  const dLat = toRad(c - a), dLng = toRad(d - b);
  const s = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a)) * Math.cos(toRad(c)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

function nearestRegion(state, lat, lng) {
  let best = null, bestD = Infinity;
  for (const slug of STATE_REGIONS[state] || []) {
    const c = REGION_CENTROIDS[slug];
    if (!c) continue;
    const dd = distKm(lat, lng, c[0], c[1]);
    if (dd < bestD) { bestD = dd; best = slug; }
  }
  return best || (STATE_REGIONS[state] || [])[0] || null;
}

// Title-case an ALL-CAPS locality name for display (keeps small joining words
// lower except first, restores common abbreviations/letters).
const LOWER = new Set(["of", "the", "and", "on", "in", "at", "de", "la"]);
function titleCase(name) {
  const words = String(name).toLowerCase().split(/\s+/);
  return words
    .map((w, i) => {
      if (i > 0 && LOWER.has(w)) return w;
      // hyphenated parts
      return w.split("-").map((p) => (p ? p[0].toUpperCase() + p.slice(1) : p)).join("-");
    })
    .join(" ")
    .replace(/\bMc([a-z])/g, (m, c) => "Mc" + c.toUpperCase());
}

function main() {
  if (!fs.existsSync(SRC)) {
    console.error(`Source dataset not found: ${SRC}`);
    console.error('Download australian_postcodes.json and pass --src "<path>".');
    process.exit(1);
  }
  const raw = JSON.parse(fs.readFileSync(SRC, "utf8"));
  const rows = Array.isArray(raw) ? raw : raw.data || [];

  const seen = {}; // state -> Set(localityUpper)
  const towns = [];
  const perState = {};
  for (const r of rows) {
    const state = String(r.state || "").toUpperCase();
    if (!STATES.includes(state)) continue;
    if ((r.type || "") !== "Delivery Area") continue;
    const locality = String(r.locality || "").trim();
    if (!locality) continue;
    const lat = parseFloat(r.lat), lng = parseFloat(r.long);
    if (!lat || !lng || Math.abs(lat) < 0.001) continue;

    const key = locality.toUpperCase();
    (seen[state] = seen[state] || new Set());
    if (seen[state].has(key)) continue;
    seen[state].add(key);

    const region = nearestRegion(state, lat, lng);
    if (!region) continue;
    towns.push({
      name: titleCase(locality),
      state,
      region,
      pc: String(r.postcode || "").padStart(4, "0") || null,
      lat: Math.round(lat * 1e6) / 1e6,
      lng: Math.round(lng * 1e6) / 1e6,
    });
    perState[state] = (perState[state] || 0) + 1;
  }

  towns.sort((a, b) => (a.state < b.state ? -1 : a.state > b.state ? 1 : a.name.localeCompare(b.name)));

  const out = {
    _comment: "Generated by tools/build-national-towns.js from the open australian_postcodes dataset. Every Australian locality, assigned to the nearest VanAssist region within its state. Consumed by App\\Services\\NationalTownSeeder (creates towns idempotently; never overwrites existing rows).",
    generated_at: new Date().toISOString(),
    source: "australian_postcodes (Australia Post derived, github.com/matthewproctor/australianpostcodes)",
    count: towns.length,
    towns,
  };
  fs.writeFileSync(OUT_FILE, JSON.stringify(out, null, 0));
  console.log("Wrote", towns.length, "towns ->", OUT_FILE);
  for (const st of STATES) console.log("  " + st + ":", perState[st] || 0);
}

main();
