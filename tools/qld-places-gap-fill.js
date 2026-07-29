#!/usr/bin/env node
/**
 * Budget-capped Google Places (New) discovery for QLD SEQ + Central Queensland.
 *
 * Discovery only — writes Place IDs + permitted contact fields to
 * storage/imports/qld-coverage/places-gap-fill-*.jsonl for review.
 * Does NOT write the permanent master provider pack by default.
 *
 *   $env:GOOGLE_PLACES_API_KEY="..."
 *   node tools/qld-places-gap-fill.js --dry-run
 *   node tools/qld-places-gap-fill.js --write
 *
 * Hard-stops when estimated AUD spend reaches --budget-aud (default 50).
 */
"use strict";

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const OUT_DIR = path.join(ROOT, "storage", "imports", "qld-coverage");

const HUBS = [
  // SEQ
  { town: "Brisbane", region: "seq", lat: -27.4698, lng: 153.0251 },
  { town: "Ipswich", region: "seq", lat: -27.6144, lng: 152.7609 },
  { town: "Caboolture", region: "seq", lat: -27.0847, lng: 152.9511 },
  { town: "Redcliffe", region: "seq", lat: -27.2307, lng: 153.1094 },
  { town: "Cleveland", region: "seq", lat: -27.5265, lng: 153.2658 },
  { town: "Strathpine", region: "seq", lat: -27.3043, lng: 152.9896 },
  { town: "Beenleigh", region: "seq", lat: -27.7139, lng: 153.2028 },
  { town: "Capalaba", region: "seq", lat: -27.5231, lng: 153.1903 },
  { town: "Springfield", region: "seq", lat: -27.675, lng: 152.918 },
  { town: "Southport", region: "seq", lat: -27.967, lng: 153.4 },
  { town: "Nerang", region: "seq", lat: -27.995, lng: 153.336 },
  { town: "Robina", region: "seq", lat: -28.0706, lng: 153.385 },
  { town: "Maroochydore", region: "seq", lat: -26.655, lng: 153.09 },
  { town: "Caloundra", region: "seq", lat: -26.799, lng: 153.129 },
  { town: "Noosa Heads", region: "seq", lat: -26.39, lng: 153.091 },
  // Central Queensland (cq + fitzroy)
  { town: "Rockhampton", region: "fitzroy", lat: -23.379, lng: 150.51 },
  { town: "Yeppoon", region: "fitzroy", lat: -23.132, lng: 150.739 },
  { town: "Gladstone", region: "fitzroy", lat: -23.843, lng: 151.256 },
  { town: "Emerald", region: "cq", lat: -23.527, lng: 148.159 },
  { town: "Biloela", region: "cq", lat: -24.408, lng: 150.513 },
  { town: "Agnes Water", region: "fitzroy", lat: -24.211, lng: 151.905 },
  { town: "Blackwater", region: "cq", lat: -23.581, lng: 148.879 },
  { town: "Mount Morgan", region: "fitzroy", lat: -23.645, lng: 150.389 },
];

const QUERIES = [
  { q: "caravan repairs", cats: ["caravan-repairs"] },
  { q: "mobile caravan technician", cats: ["caravan-repairs", "mobile-mechanic"] },
  { q: "auto electrician caravan", cats: ["auto-electrician"] },
  { q: "mobile caravan gas fitter", cats: ["gas-certification"] },
  { q: "trailer brakes bearings", cats: ["trailer-brakes", "trailer-bearings"] },
  { q: "roadworthy safety certificate", cats: ["roadworthy"] },
  { q: "diesel mechanic", cats: ["diesel-specialist"] },
  { q: "towbar fitting", cats: ["towbar-installation"] },
];

/** Conservative AUD estimate per Text Search page (Enterprise-ish with phone/website). */
const AUD_PER_REQUEST = 0.055;
const USD_AUD = 1.55;

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}
function flag(name) {
  return process.argv.includes(name);
}

function fieldMask() {
  // Pro/Enterprise fields needed for review-queue discovery. Avoid atmosphere/reviews.
  return [
    "places.id",
    "places.displayName",
    "places.formattedAddress",
    "places.location",
    "places.types",
    "places.nationalPhoneNumber",
    "places.websiteUri",
    "places.businessStatus",
  ].join(",");
}

async function searchText(key, hub, query) {
  const body = {
    textQuery: `${query.q} ${hub.town} QLD`,
    maxResultCount: 20,
    locationBias: {
      circle: {
        center: { latitude: hub.lat, longitude: hub.lng },
        radius: 25000.0,
      },
    },
    regionCode: "AU",
    languageCode: "en",
  };
  const res = await fetch("https://places.googleapis.com/v1/places:searchText", {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Goog-Api-Key": key,
      "X-Goog-FieldMask": fieldMask(),
    },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`Places ${res.status}: ${text.slice(0, 400)}`);
  }
  return res.json();
}

function toCandidate(place, hub, query) {
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
    category_slugs: query.cats,
    town: hub.town,
    region: hub.region,
    state: "QLD",
    source_name: "google-places",
    source_licence: "Google Maps Platform terms — discovery only",
    marketing_consent: false,
    needs_independent_retention_check: true,
    query: query.q,
    discovered_at: new Date().toISOString().slice(0, 10),
  };
}

async function main() {
  const budgetAud = Number(arg("--budget-aud", "50")) || 50;
  const dryRun = !flag("--write");
  const key = arg("--key", process.env.GOOGLE_PLACES_API_KEY || "");
  const maxRequests = Math.max(1, Math.floor(budgetAud / AUD_PER_REQUEST));

  const planned = HUBS.length * QUERIES.length;
  console.log(
    JSON.stringify(
      {
        mode: dryRun ? "dry-run" : "write",
        hubs: HUBS.length,
        queries: QUERIES.length,
        planned_requests: planned,
        budget_aud: budgetAud,
        aud_per_request_estimate: AUD_PER_REQUEST,
        hard_cap_requests: maxRequests,
        note:
          planned > maxRequests
            ? `Will stop early at ${maxRequests} requests to stay within A$${budgetAud}`
            : `Planned ${planned} requests fit under A$${budgetAud} estimate`,
      },
      null,
      2
    )
  );

  if (dryRun && !key) {
    console.log(
      "\nNo GOOGLE_PLACES_API_KEY set — plan only. Set the key to execute."
    );
    return;
  }
  if (!key) {
    console.error("GOOGLE_PLACES_API_KEY required for --write");
    process.exit(1);
  }

  fs.mkdirSync(OUT_DIR, { recursive: true });
  const outPath = path.join(
    OUT_DIR,
    `places-gap-fill-seq-cq-${new Date().toISOString().slice(0, 10)}.jsonl`
  );
  const seen = new Set();
  let requests = 0;
  let written = 0;
  let estimatedAud = 0;
  const fh = dryRun ? null : fs.createWriteStream(outPath, { flags: "w" });

  outer: for (const hub of HUBS) {
    for (const query of QUERIES) {
      if (requests >= maxRequests) {
        console.log(`Hard stop at ${requests} requests (budget cap).`);
        break outer;
      }
      requests++;
      estimatedAud = Math.round(requests * AUD_PER_REQUEST * 100) / 100;
      process.stdout.write(
        `[${requests}/${maxRequests}] ${hub.town} · ${query.q} … `
      );
      try {
        const data = await searchText(key, hub, query);
        const places = data.places || [];
        let added = 0;
        for (const place of places) {
          const row = toCandidate(place, hub, query);
          if (!row.external_id || seen.has(row.external_id)) continue;
          seen.add(row.external_id);
          if (fh) {
            fh.write(JSON.stringify(row) + "\n");
            written++;
            added++;
          }
        }
        console.log(`${places.length} hits, ${added} new`);
      } catch (e) {
        console.log("ERROR", e.message || e);
        break outer;
      }
      // Be polite to the API.
      await new Promise((r) => setTimeout(r, 350));
    }
  }

  if (fh) fh.end();
  const summary = {
    mode: dryRun ? "dry-run-executed" : "write",
    requests,
    estimated_aud: estimatedAud,
    unique_places: seen.size,
    written,
    output: dryRun ? null : path.relative(ROOT, outPath),
    usd_aud_fx_note: `Estimate uses ~A$${AUD_PER_REQUEST}/request (~US$${(
      AUD_PER_REQUEST / USD_AUD
    ).toFixed(3)}); check Cloud billing for actuals.`,
    retention_note:
      "Place IDs may be retained as discovery provenance. Full Places content needs independent retention before permanent master publish.",
  };
  const summaryPath = path.join(OUT_DIR, "places-gap-fill-summary.json");
  fs.writeFileSync(summaryPath, JSON.stringify(summary, null, 2));
  console.log(JSON.stringify(summary, null, 2));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
