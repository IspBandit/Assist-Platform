#!/usr/bin/env node
/**
 * Queensland locality × service-category coverage matrix (offline, no production DB).
 *
 * Builds evidence-backed coverage from committed LocalTorque / VanAssist seed packs.
 * Distinguishes base_town / explicit (same town match only for pack seeds) / nearby.
 * Does not invent contacts, categories, or service areas.
 *
 * Usage:
 *   node tools/qld-coverage-matrix.js
 *   node tools/qld-coverage-matrix.js --batch brisbane-moreton-bay
 *   node tools/qld-coverage-matrix.js --resume
 *   node tools/qld-coverage-matrix.js --list-batches
 *
 * Outputs (committed summaries under database/seeds/qld-coverage/;
 * large matrix JSONL under storage/imports/qld-coverage/).
 */
"use strict";

const fs = require("fs");
const path = require("path");

const ROOT = path.join(__dirname, "..");
const TOWNS_FILE = path.join(ROOT, "database", "seeds", "towns_national.json");
const PACK_FILE = path.join(
  ROOT,
  "database",
  "seeds",
  "localtorque",
  "providers-publishable.json"
);
const CATS_FILE = path.join(
  ROOT,
  "database",
  "seeds",
  "localtorque",
  "categories.json"
);
const NATIONAL_BIZ = path.join(
  ROOT,
  "database",
  "seeds",
  "national_import.json"
);
const LOCALITY_BIZ = path.join(
  ROOT,
  "database",
  "seeds",
  "businesses_locality_businesses.json"
);
const OSM_BIZ = path.join(ROOT, "database", "seeds", "businesses_osm.json");
const OUT_SEED = path.join(ROOT, "database", "seeds", "qld-coverage");
const OUT_RUNTIME = path.join(ROOT, "storage", "imports", "qld-coverage");

const LOCAL_KM = 8;
const NEARBY_KM = 40;
const AUDITED = new Date().toISOString().slice(0, 10);

/** User-facing regional batches → town filters. */
const BATCHES = [
  {
    id: "brisbane-moreton-bay",
    name: "Brisbane and Moreton Bay",
    regions: ["seq"],
    // North of Gold Coast belt, south of Sunshine Coast belt, includes Brisbane/Ipswich/Moreton
    bbox: [152.4, -27.85, 153.5, -26.95],
  },
  {
    id: "gold-coast-scenic-rim",
    name: "Gold Coast and Scenic Rim",
    regions: ["seq"],
    bbox: [152.6, -28.4, 153.6, -27.75],
  },
  {
    id: "sunshine-coast-noosa",
    name: "Sunshine Coast and Noosa",
    regions: ["seq"],
    bbox: [152.6, -26.95, 153.3, -26.2],
  },
  {
    id: "darling-downs-south-west",
    name: "Darling Downs and South West",
    regions: ["downs"],
  },
  {
    id: "wide-bay-burnett",
    name: "Wide Bay–Burnett",
    regions: ["widebay"],
  },
  {
    id: "central-queensland",
    name: "Central Queensland",
    regions: ["cq", "fitzroy"],
  },
  {
    id: "mackay-whitsunday",
    name: "Mackay–Whitsunday",
    regions: ["mackay"],
  },
  {
    id: "townsville-north-queensland",
    name: "Townsville and North Queensland",
    regions: ["nq"],
  },
  {
    id: "cairns-far-north",
    name: "Cairns and Far North Queensland",
    regions: ["fnq"],
    // Exclude deep cape/gulf — those go to remote batch (lat north of ~-14.5)
    excludeBbox: null,
    maxLat: -14.5, // towns south of this stay in FNQ coastal/tablelands
  },
  {
    id: "gulf-cape-remote",
    name: "Gulf, Cape York and remote Queensland",
    regions: ["outback", "fnq", "nq", "cq"],
    // Outback always; FNQ/NQ/CQ only when remote (north of -14.5 or west of 142)
    remoteOnly: true,
  },
];

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}
function flag(name) {
  return process.argv.includes(name);
}

function haversineKm(aLat, aLng, bLat, bLng) {
  const R = 6371;
  const toRad = (x) => (x * Math.PI) / 180;
  const dLat = toRad(bLat - aLat);
  const dLng = toRad(bLng - aLng);
  const s =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(toRad(aLat)) * Math.cos(toRad(bLat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(s));
}

function normName(n) {
  return String(n || "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, " ")
    .trim();
}

function brandsFor(catIds, catById) {
  const brands = new Set();
  for (const id of catIds || []) {
    const c = catById.get(id);
    if (c?.brands) c.brands.forEach((b) => brands.add(b));
  }
  return [...brands].sort();
}

function inBbox(lat, lng, bbox) {
  if (!bbox) return true;
  const [minLng, minLat, maxLng, maxLat] = bbox;
  return lng >= minLng && lng <= maxLng && lat >= minLat && lat <= maxLat;
}

function isRemoteTown(t) {
  // Cape/Gulf/outback-style: north of -14.5 or west of 142, or outback region
  if (t.region === "outback") return true;
  if (t.lat != null && t.lat > -14.5) return true;
  if (t.lng != null && t.lng < 142) return true;
  return false;
}

function townInBatch(t, batch) {
  if (!batch.regions.includes(t.region)) return false;
  if (batch.remoteOnly) {
    if (t.region === "outback") return true;
    // Cape / Gulf / far west only — not every NQ/CQ/FNQ town
    return isRemoteTown(t) && t.region !== "outback";
  }
  // Coastal/tableland batches must not steal remote Cape/Gulf towns
  if (
    ["fnq", "nq", "cq"].includes(t.region) &&
    isRemoteTown(t) &&
    t.region !== "outback"
  ) {
    return false;
  }
  if (batch.maxLat != null && t.lat != null && t.lat > batch.maxLat) return false;
  if (batch.bbox && !inBbox(t.lat, t.lng, batch.bbox)) return false;
  return true;
}

/**
 * Assign every SEQ town not matching GC or Sunshine Coast bboxes to Brisbane/Moreton;
 * if somehow missed, attach to nearest SEQ batch by centroid.
 */
function assignSeqTowns(qldTowns) {
  const seqBatches = BATCHES.filter((b) => b.regions.includes("seq") && b.bbox);
  const assigned = new Map(); // townKey -> batchId
  for (const t of qldTowns) {
    if (t.region !== "seq") continue;
    const key = `${t.name}|${t.pc}`;
    if (inBbox(t.lat, t.lng, seqBatches[1].bbox)) {
      assigned.set(key, "gold-coast-scenic-rim");
    } else if (inBbox(t.lat, t.lng, seqBatches[2].bbox)) {
      assigned.set(key, "sunshine-coast-noosa");
    } else if (inBbox(t.lat, t.lng, seqBatches[0].bbox)) {
      assigned.set(key, "brisbane-moreton-bay");
    } else {
      // Inland SEQ / Scenic Rim west — attach to GC-Scenic or Brisbane by latitude
      assigned.set(
        key,
        t.lat < -27.75 ? "gold-coast-scenic-rim" : "brisbane-moreton-bay"
      );
    }
  }
  return assigned;
}

function loadCategories() {
  const doc = JSON.parse(fs.readFileSync(CATS_FILE, "utf8"));
  const list = [];
  const byId = new Map();
  for (const g of doc.groups || []) {
    for (const c of g.categories || []) {
      list.push({ ...c, group_id: g.id, group_name: g.name });
      byId.set(c.id, c);
    }
  }
  return { list, byId };
}

function isQldCoords(lat, lng) {
  if (lat == null || lng == null) return false;
  return lat >= -29.5 && lat <= -9.0 && lng >= 138.0 && lng <= 154.0;
}

function normalizeProvider(raw, origin) {
  const cats = raw.categories || raw.category_slugs || [];
  let state = String(raw.state || "").toUpperCase();
  const region = raw.region || null;
  const qldRegions = new Set([
    "seq",
    "downs",
    "widebay",
    "cq",
    "fitzroy",
    "mackay",
    "nq",
    "fnq",
    "outback",
  ]);
  const lat = raw.lat ?? raw.latitude ?? null;
  const lng = raw.lng ?? raw.longitude ?? null;

  // Accept only explicit QLD, or QLD region key with in-bounds coordinates.
  if (state && state !== "QLD") return null;
  if (!state) {
    if (region && qldRegions.has(region) && isQldCoords(lat, lng)) state = "QLD";
    else if (isQldCoords(lat, lng) && origin !== "national") state = "QLD";
    else if (region && qldRegions.has(region)) state = "QLD";
    else return null;
  }
  if (lat != null && lng != null && !isQldCoords(lat, lng)) return null;

  const modes = raw.modes || [];
  let service_model = raw.service_model || "unknown";
  if (service_model === "unknown") {
    if (modes.includes("mobile") && modes.includes("workshop")) service_model = "both";
    else if (modes.includes("mobile")) service_model = "mobile";
    else if (modes.includes("workshop")) service_model = "workshop";
  }

  const catIds = Array.isArray(cats) ? cats.map(String) : [];
  const sourceName =
    raw.source ||
    (origin === "osm"
      ? "openstreetmap"
      : origin === "national"
        ? "national-research"
        : origin === "locality"
          ? "locality-research"
          : "localtorque-pack");

  const licence =
    raw.source_licence ||
    (sourceName === "qld-fuel-reporting" || sourceName === "geoscience-australia"
      ? "CC BY 4.0"
      : sourceName.includes("osm") || sourceName === "openstreetmap"
        ? "ODbL"
        : "internal-research");

  const checked = AUDITED;
  const source_records = [
    {
      source_name: sourceName,
      source_url: raw.source_url || null,
      source_licence: licence,
      external_id: String(raw.id || raw.slug || ""),
      checked_at: checked,
      fields_supported: Object.keys(raw).filter(
        (k) => raw[k] != null && raw[k] !== ""
      ),
    },
  ];

  const confidence = Number(raw.confidence) || 0;
  const verified = !!raw.verified;
  const publishable = raw.publishable !== false && confidence >= 60 && !raw.needs_review
    ? confidence >= 80
    : !!raw.publishable && !raw.needs_review;

  return {
    id: String(raw.id || `${origin}-${normName(raw.name || raw.business_name)}`),
    business_name: raw.name || raw.business_name || "",
    trading_name: raw.trading_name || null,
    abn: raw.abn || null,
    phone: raw.phone || raw.public_phone || null,
    public_email: raw.email || raw.public_email || null,
    website: raw.website || null,
    street_address: raw.address || raw.street_address || null,
    town: raw.town || null,
    suburb: raw.suburb || raw.town || null,
    postcode: raw.postcode || raw.pc || null,
    state: "QLD",
    region: region,
    latitude: raw.lat ?? raw.latitude ?? null,
    longitude: raw.lng ?? raw.longitude ?? null,
    service_model,
    service_radius_km: raw.max_travel_km ?? raw.service_radius_km ?? null,
    explicit_service_areas: raw.explicit_service_areas || [],
    category_slugs: catIds,
    brand_visibility: raw.brands || [],
    operational_status: raw.operational_status
      ? String(raw.operational_status).toLowerCase().includes("closed")
        ? "closed"
        : "operational"
      : "unknown",
    claimed_status: raw.claimed ? "claimed" : "unclaimed",
    source_records,
    field_evidence: {
      categories: catIds.map((id) => ({
        category: id,
        evidence: `Listed under category in ${sourceName} pack`,
        source: sourceName,
      })),
    },
    confidence,
    last_checked_at: checked,
    publishable: !!raw.publishable && !raw.needs_review,
    needs_review: !!raw.needs_review || confidence < 80,
    review_reasons: [
      ...(raw.needs_review ? ["pack_needs_review"] : []),
      ...(confidence < 80 ? ["confidence_below_80"] : []),
      ...(!raw.phone ? ["missing_phone"] : []),
      ...(!(raw.email || raw.public_email) ? ["missing_email"] : []),
    ],
    verified,
    _origin: origin,
  };
}

function dedupeKey(p) {
  const phone = String(p.phone || "").replace(/\D/g, "").replace(/^61/, "0");
  if (phone.length >= 8) return `p:${phone.slice(-9)}`;
  if (p.source_records?.[0]?.external_id)
    return `x:${p.source_records[0].source_name}:${p.source_records[0].external_id}`;
  try {
    if (p.website) {
      const host = new URL(
        p.website.startsWith("http") ? p.website : "https://" + p.website
      ).hostname.replace(/^www\./, "");
      return `w:${host}|${normName(p.business_name)}`;
    }
  } catch {
    /* ignore */
  }
  return `n:${normName(p.business_name)}|${p.postcode || ""}|${normName(p.town)}`;
}

function loadAllProviders(catById) {
  const byKey = new Map();
  function add(list, origin) {
    if (!list) return;
    for (const raw of list) {
      const p = normalizeProvider(raw, origin);
      if (!p || !p.business_name) continue;
      if (!p.brand_visibility.length) {
        p.brand_visibility = brandsFor(p.category_slugs, catById);
      }
      const k = dedupeKey(p);
      if (byKey.has(k)) {
        const ex = byKey.get(k);
        ex.category_slugs = [
          ...new Set([...ex.category_slugs, ...p.category_slugs]),
        ];
        ex.source_records = [...ex.source_records, ...p.source_records];
        ex.brand_visibility = brandsFor(ex.category_slugs, catById);
        if (!ex.phone && p.phone) ex.phone = p.phone;
        if (!ex.website && p.website) ex.website = p.website;
        if (!ex.public_email && p.public_email) ex.public_email = p.public_email;
        continue;
      }
      byKey.set(k, p);
    }
  }

  add(JSON.parse(fs.readFileSync(PACK_FILE, "utf8")), "localtorque-pack");

  if (fs.existsSync(NATIONAL_BIZ)) {
    const nat = JSON.parse(fs.readFileSync(NATIONAL_BIZ, "utf8"));
    const biz = nat.businesses || nat;
    if (Array.isArray(biz)) add(biz, "national");
  }
  if (fs.existsSync(LOCALITY_BIZ)) {
    const loc = JSON.parse(fs.readFileSync(LOCALITY_BIZ, "utf8"));
    add(Array.isArray(loc) ? loc : Object.values(loc), "locality");
  }
  if (fs.existsSync(OSM_BIZ)) {
    const osm = JSON.parse(fs.readFileSync(OSM_BIZ, "utf8"));
    add(Array.isArray(osm) ? osm : osm.businesses || [], "osm");
  }

  return [...byKey.values()].filter((p) => p.state === "QLD");
}

function buildGrid(providers) {
  const grid = new Map();
  for (const p of providers) {
    if (p.latitude == null || p.longitude == null) continue;
    const k = `${Math.floor(p.latitude * 2)},${Math.floor(p.longitude * 2)}`;
    if (!grid.has(k)) grid.set(k, []);
    grid.get(k).push(p);
  }
  return grid;
}

function candidatesNear(grid, town, ring = 2) {
  if (town.lat == null) return [];
  const i0 = Math.floor(town.lat * 2);
  const j0 = Math.floor(town.lng * 2);
  const out = [];
  for (let di = -ring; di <= ring; di++)
    for (let dj = -ring; dj <= ring; dj++) {
      const cell = grid.get(`${i0 + di},${j0 + dj}`);
      if (cell) out.push(...cell);
    }
  return out;
}

function classifyRelation(town, provider) {
  const townKey = normName(town.name);
  const pTown = normName(provider.town || provider.suburb);
  const sameTown =
    pTown === townKey ||
    (provider.explicit_service_areas || [])
      .map(normName)
      .includes(townKey);

  let dist = null;
  if (town.lat != null && provider.latitude != null) {
    dist = haversineKm(town.lat, town.lng, provider.latitude, provider.longitude);
  }

  if (sameTown) {
    return {
      relation: "base_town",
      straight_line_km: dist,
      road_distance_km: null,
      road_duration_minutes: null,
      distance_source: dist != null ? "haversine_straight_line" : null,
    };
  }
  if (
    provider.explicit_service_areas &&
    provider.explicit_service_areas.map(normName).includes(townKey)
  ) {
    return {
      relation: "explicit_service_area",
      straight_line_km: dist,
      road_distance_km: null,
      road_duration_minutes: null,
      distance_source: dist != null ? "haversine_straight_line" : null,
    };
  }
  if (dist != null && dist <= LOCAL_KM) {
    return {
      relation: "base_town",
      straight_line_km: dist,
      road_distance_km: null,
      road_duration_minutes: null,
      distance_source: "haversine_straight_line",
      note: "Within local radius of town centroid; not an explicit service-area claim",
    };
  }
  if (dist != null && dist <= NEARBY_KM) {
    return {
      relation: "nearby_candidate",
      straight_line_km: dist,
      road_distance_km: null,
      road_duration_minutes: null,
      distance_source: "haversine_straight_line",
    };
  }
  return null;
}

function coverageStatus(counts) {
  if (counts.verified_local > 0 || counts.verified_mobile > 0) return "verified";
  if (counts.unclaimed > 0 && (counts.local > 0 || counts.mobile > 0))
    return "unclaimed_only";
  if (counts.unclaimed > 0 || counts.local > 0) return "partially_verified";
  if (counts.nearby > 0) return "nearby_only";
  if (counts.review > 0) return "needs_review";
  return "no_coverage";
}

function recommendedAction(status) {
  switch (status) {
    case "verified":
      return "Maintain; refresh annually";
    case "partially_verified":
      return "Confirm category evidence and contacts";
    case "unclaimed_only":
      return "Invite claim; verify licence for regulated services";
    case "nearby_only":
      return "Do not claim coverage; seek local or explicit service-area provider";
    case "needs_review":
      return "Hold from public until conflicts resolved";
    default:
      return "Discover from permitted sources; record gap if none found";
  }
}

function ensureDirs() {
  fs.mkdirSync(OUT_SEED, { recursive: true });
  fs.mkdirSync(path.join(OUT_SEED, "by-batch"), { recursive: true });
  fs.mkdirSync(OUT_RUNTIME, { recursive: true });
  fs.mkdirSync(path.join(OUT_RUNTIME, "matrix"), { recursive: true });
}

function loadCheckpoint() {
  const p = path.join(OUT_SEED, "checkpoint.json");
  if (!flag("--resume") || !fs.existsSync(p)) {
    return {
      completed_batches: [],
      started_at: new Date().toISOString(),
      updated_at: null,
    };
  }
  return JSON.parse(fs.readFileSync(p, "utf8"));
}

function saveCheckpoint(cp) {
  cp.updated_at = new Date().toISOString();
  fs.writeFileSync(
    path.join(OUT_SEED, "checkpoint.json"),
    JSON.stringify(cp, null, 2)
  );
}

function processBatch(batch, towns, providers, categories, catById, seqAssign) {
  const batchTowns = towns.filter((t) => {
    if (t.region === "seq" && seqAssign) {
      return seqAssign.get(`${t.name}|${t.pc}`) === batch.id;
    }
    if (batch.regions.includes("seq") && t.region === "seq") return false; // handled via seqAssign
    return townInBatch(t, batch);
  });

  const grid = buildGrid(providers);
  const matrixPath = path.join(OUT_RUNTIME, "matrix", `${batch.id}.jsonl`);
  const reportPath = path.join(OUT_RUNTIME, "matrix", `${batch.id}-report.jsonl`);
  const mLines = [];
  const rLines = [];
  let zero = 0;
  let weak = 0;
  let verifiedCells = 0;
  const providerIdsUsed = new Set();

  for (const town of batchTowns) {
    const nearbyProviders = candidatesNear(grid, town, 3);
    for (const p of providers) {
      if (normName(p.town) === normName(town.name) && !nearbyProviders.includes(p)) {
        nearbyProviders.push(p);
      }
    }

    for (const cat of categories) {
      const matched = [];
      for (const p of nearbyProviders) {
        if (!(p.category_slugs || []).includes(cat.id)) continue;
        const rel = classifyRelation(town, p);
        if (!rel) continue;
        matched.push({ provider: p, rel });
        providerIdsUsed.add(p.id);
      }

      const counts = {
        verified_local: 0,
        verified_mobile: 0,
        unclaimed: 0,
        local: 0,
        mobile: 0,
        nearby: 0,
        review: 0,
      };
      for (const { provider: p, rel } of matched) {
        const isMobile =
          p.service_model === "mobile" || p.service_model === "both";
        if (rel.relation === "nearby_candidate") {
          counts.nearby++;
          continue;
        }
        if (p.needs_review && !p.publishable) counts.review++;
        if (p.claimed_status === "unclaimed") counts.unclaimed++;
        if (isMobile) {
          counts.mobile++;
          if (p.verified || (p.publishable && p.confidence >= 80))
            counts.verified_mobile++;
        } else {
          counts.local++;
          if (p.verified || (p.publishable && p.confidence >= 80))
            counts.verified_local++;
        }
      }

      const status = coverageStatus(counts);
      if (status === "no_coverage") zero++;
      if (status === "nearby_only" || status === "unclaimed_only") weak++;
      if (status === "verified") verifiedCells++;

      const cell = {
        batch_id: batch.id,
        region: batch.name,
        town: town.name,
        postcode: town.pc,
        town_region: town.region,
        category: cat.id,
        category_name: cat.name,
        status,
        counts,
        providers: matched.map(({ provider: p, rel }) => ({
          id: p.id,
          name: p.business_name,
          relation: rel.relation,
          straight_line_km:
            rel.straight_line_km != null
              ? Math.round(rel.straight_line_km * 10) / 10
              : null,
          road_distance_km: null,
          distance_source: rel.distance_source,
          service_model: p.service_model,
          publishable: p.publishable,
          confidence: p.confidence,
          brands: p.brand_visibility,
        })),
        last_audited: AUDITED,
      };
      mLines.push(JSON.stringify(cell));

      rLines.push(
        JSON.stringify({
          Region: batch.name,
          "Town/suburb": town.name,
          Postcode: town.pc,
          "Service category": cat.name,
          "Verified local providers": counts.verified_local,
          "Verified mobile providers": counts.verified_mobile,
          "Unclaimed sourced providers": counts.unclaimed,
          "Nearby candidates": counts.nearby,
          "Last audited": AUDITED,
          "Coverage status": status,
          "Recommended action": recommendedAction(status),
        })
      );
    }
  }

  fs.writeFileSync(matrixPath, mLines.join("\n") + (mLines.length ? "\n" : ""));
  fs.writeFileSync(reportPath, rLines.join("\n") + (rLines.length ? "\n" : ""));

  const summary = {
    batch_id: batch.id,
    batch_name: batch.name,
    towns: batchTowns.length,
    categories: categories.length,
    cells: batchTowns.length * categories.length,
    verified_cells: verifiedCells,
    zero_coverage_cells: zero,
    weak_coverage_cells: weak,
    providers_referenced: providerIdsUsed.size,
    matrix_file: path.relative(ROOT, matrixPath),
    report_file: path.relative(ROOT, reportPath),
    last_audited: AUDITED,
  };

  fs.writeFileSync(
    path.join(OUT_SEED, "by-batch", `${batch.id}.json`),
    JSON.stringify(summary, null, 2)
  );

  return { summary, providerIdsUsed, batchTowns };
}

function writeAggregates(allProviders, catById, batchResults, allTowns) {
  const usedIds = new Set();
  for (const b of batchResults) b.providerIdsUsed.forEach((id) => usedIds.add(id));

  const qldProviders = allProviders;
  const publishable = qldProviders.filter((p) => p.publishable && !p.needs_review);
  const review = qldProviders.filter((p) => p.needs_review || !p.publishable);
  const closed = qldProviders.filter((p) => p.operational_status === "closed");
  const missingPhone = qldProviders.filter((p) => !p.phone);
  const missingEmail = qldProviders.filter((p) => !p.public_email);
  const missingCoords = qldProviders.filter(
    (p) => p.latitude == null || p.longitude == null
  );
  const conflicting = qldProviders.filter((p) =>
    (p.review_reasons || []).includes("conflicting_evidence")
  );
  const regulatedCats = new Set([
    "gas-certification",
    "roadworthy",
    "engineering-certification",
    "compliance-engineering",
    "licensed-electrician",
  ]);
  const regulatedMissingLicence = qldProviders.filter((p) =>
    (p.category_slugs || []).some((c) => regulatedCats.has(c))
  );

  // Possible duplicates by name+postcode soft collision across origins
  const namePc = new Map();
  const duplicates = [];
  for (const p of qldProviders) {
    const k = `${normName(p.business_name)}|${p.postcode || ""}`;
    if (!namePc.has(k)) namePc.set(k, []);
    namePc.get(k).push(p.id);
  }
  for (const [k, ids] of namePc) {
    if (ids.length > 1) duplicates.push({ key: k, ids });
  }

  fs.writeFileSync(
    path.join(OUT_SEED, "providers-candidates.json"),
    JSON.stringify(qldProviders)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "providers-publishable.json"),
    JSON.stringify(publishable)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "providers-review-queue.json"),
    JSON.stringify(review)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "possible-duplicates.json"),
    JSON.stringify(duplicates, null, 2)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "possible-closed.json"),
    JSON.stringify(closed, null, 2)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "missing-phone.json"),
    JSON.stringify(
      missingPhone.map((p) => ({ id: p.id, name: p.business_name, town: p.town })),
      null,
      2
    )
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "missing-email.json"),
    JSON.stringify(
      missingEmail.map((p) => ({ id: p.id, name: p.business_name, town: p.town })),
      null,
      2
    )
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "missing-coordinates.json"),
    JSON.stringify(
      missingCoords.map((p) => ({ id: p.id, name: p.business_name, town: p.town })),
      null,
      2
    )
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "conflicting-evidence.json"),
    JSON.stringify(conflicting, null, 2)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "regulated-missing-licence.json"),
    JSON.stringify(
      regulatedMissingLicence.map((p) => ({
        id: p.id,
        name: p.business_name,
        categories: (p.category_slugs || []).filter((c) => regulatedCats.has(c)),
        note: "Regulated category present without separate licence register evidence in this offline pack",
      })),
      null,
      2
    )
  );

  // Source / licence rollup
  const sources = {};
  for (const p of qldProviders) {
    for (const s of p.source_records || []) {
      const k = `${s.source_name}|${s.source_licence}`;
      sources[k] = sources[k] || {
        source_name: s.source_name,
        source_licence: s.source_licence,
        count: 0,
      };
      sources[k].count++;
    }
  }
  fs.writeFileSync(
    path.join(OUT_SEED, "source-licence-records.json"),
    JSON.stringify(Object.values(sources), null, 2)
  );

  // Prefer batch summary totals (authoritative) over re-scan
  let zeroN = 0;
  let weakN = 0;
  let verifiedN = 0;
  let cellN = 0;
  for (const b of batchResults) {
    const s = b.summary || {};
    cellN += Number(s.cells) || 0;
    verifiedN += Number(s.verified_cells) || 0;
    zeroN += Number(s.zero_coverage_cells) || 0;
    weakN += Number(s.weak_coverage_cells) || 0;
  }

  // Full gap lists live under storage/imports (gitignored); commit compact samples only.
  const zeroFull = path.join(OUT_RUNTIME, "zero-coverage.jsonl");
  const weakFull = path.join(OUT_RUNTIME, "weak-coverage.jsonl");
  const zeroSample = path.join(OUT_SEED, "zero-coverage.sample.jsonl");
  const weakSample = path.join(OUT_SEED, "weak-coverage.sample.jsonl");
  const zFull = fs.createWriteStream(zeroFull, { flags: "w" });
  const wFull = fs.createWriteStream(weakFull, { flags: "w" });
  const zSampleLines = [];
  const wSampleLines = [];
  const SAMPLE_CAP = 500;

  for (const b of batchResults) {
    const reportFile = path.join(
      OUT_RUNTIME,
      "matrix",
      `${b.summary.batch_id}-report.jsonl`
    );
    if (!fs.existsSync(reportFile)) continue;
    const lines = fs.readFileSync(reportFile, "utf8").split(/\r?\n/);
    for (const line of lines) {
      if (!line.trim()) continue;
      const row = JSON.parse(line);
      const st = row["Coverage status"];
      if (st === "no_coverage") {
        zFull.write(line + "\n");
        if (zSampleLines.length < SAMPLE_CAP) zSampleLines.push(line);
      }
      if (st === "nearby_only" || st === "unclaimed_only" || st === "needs_review") {
        wFull.write(line + "\n");
        if (wSampleLines.length < SAMPLE_CAP) wSampleLines.push(line);
      }
    }
  }
  zFull.end();
  wFull.end();
  fs.writeFileSync(zeroSample, zSampleLines.join("\n") + (zSampleLines.length ? "\n" : ""));
  fs.writeFileSync(weakSample, wSampleLines.join("\n") + (wSampleLines.length ? "\n" : ""));
  // Pointer files for tooling that still expects the seed path
  fs.writeFileSync(
    path.join(OUT_SEED, "zero-coverage.jsonl"),
    zSampleLines.join("\n") + (zSampleLines.length ? "\n" : "")
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "weak-coverage.jsonl"),
    wSampleLines.join("\n") + (wSampleLines.length ? "\n" : "")
  );

  const importSummary = {
    generated_at: new Date().toISOString(),
    scope: "Queensland only — offline seed analysis, no production DB writes",
    towns_suburbs_processed: allTowns.length,
    service_categories_processed: catById.size,
    existing_providers_reviewed: qldProviders.length,
    candidates_discovered: qldProviders.length,
    publishable_records: publishable.length,
    held_for_review: review.length,
    duplicates_flagged: duplicates.length,
    with_phone: qldProviders.filter((p) => p.phone).length,
    with_sourced_email: qldProviders.filter((p) => p.public_email).length,
    regulated_without_licence_evidence: regulatedMissingLicence.length,
    town_category_cells: cellN,
    verified_coverage_cells: verifiedN,
    zero_coverage_cells: zeroN,
    weak_coverage_cells: weakN,
    batches: batchResults.map((b) => b.summary),
    sources: Object.values(sources),
    notes: [
      "Road distance is not claimed; only straight-line km is stored until a routing source is authorised.",
      "Nearby candidates are never counted as confirmed coverage.",
      "Category evidence is inherited from pack taxonomy assignment; website-level re-verification is still required for regulated services.",
      "Google Places API was not called by this tool; some pack rows retain historical google-places provenance and must follow Places storage rules.",
      "Production database was not modified.",
    ],
    import_ready_files: [
      "database/seeds/qld-coverage/providers-publishable.json",
      "database/seeds/qld-coverage/providers-review-queue.json",
      "database/seeds/qld-coverage/coverage-summary.json",
      "database/seeds/qld-coverage/zero-coverage.jsonl",
      "database/seeds/qld-coverage/by-batch/*.json",
    ],
  };

  fs.writeFileSync(
    path.join(OUT_SEED, "coverage-summary.json"),
    JSON.stringify(importSummary, null, 2)
  );
  fs.writeFileSync(
    path.join(OUT_SEED, "import-summary.json"),
    JSON.stringify(importSummary, null, 2)
  );

  return importSummary;
}

function main() {
  if (flag("--list-batches")) {
    for (const b of BATCHES) console.log(`${b.id}\t${b.name}`);
    return;
  }

  ensureDirs();
  const { list: categories, byId: catById } = loadCategories();
  const townsDoc = JSON.parse(fs.readFileSync(TOWNS_FILE, "utf8"));
  const qldTowns = (townsDoc.towns || []).filter((t) => t.state === "QLD");
  const seqAssign = assignSeqTowns(qldTowns);
  const providers = loadAllProviders(catById);

  console.log(
    `QLD towns=${qldTowns.length} categories=${categories.length} providers=${providers.length}`
  );

  const cp = loadCheckpoint();
  const only = arg("--batch", "");
  const batchResults = [];

  for (const batch of BATCHES) {
    if (only && batch.id !== only) continue;
    if (!only && cp.completed_batches.includes(batch.id) && flag("--resume")) {
      const prev = path.join(OUT_SEED, "by-batch", `${batch.id}.json`);
      if (fs.existsSync(prev)) {
        const summary = JSON.parse(fs.readFileSync(prev, "utf8"));
        batchResults.push({
          summary,
          providerIdsUsed: new Set(),
          batchTowns: [],
        });
        console.log(`Skip completed batch ${batch.id}`);
        continue;
      }
    }
    console.log(`Processing ${batch.id}…`);
    const result = processBatch(
      batch,
      qldTowns,
      providers,
      categories,
      catById,
      seqAssign
    );
    batchResults.push(result);
    if (!cp.completed_batches.includes(batch.id)) {
      cp.completed_batches.push(batch.id);
    }
    saveCheckpoint(cp);
    console.log(
      `  towns=${result.summary.towns} zero_cells=${result.summary.zero_coverage_cells} verified_cells=${result.summary.verified_cells}`
    );
  }

  // If --batch single, still need other batch summaries for aggregate — reload completed
  if (only) {
    for (const batch of BATCHES) {
      if (batch.id === only) continue;
      const prev = path.join(OUT_SEED, "by-batch", `${batch.id}.json`);
      if (fs.existsSync(prev)) {
        batchResults.push({
          summary: JSON.parse(fs.readFileSync(prev, "utf8")),
          providerIdsUsed: new Set(),
          batchTowns: [],
        });
      }
    }
  }

  const summary = writeAggregates(providers, catById, batchResults, qldTowns);
  console.log(JSON.stringify(summary, null, 2));
}

main();
