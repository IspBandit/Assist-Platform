// One-off extractor: reads the Cursor canvas research file and emits a clean
// JSON dataset (states, regions, businesses) for the PHP NationalImportSeeder.
//
// Usage:  node tools/extract-canvas.js "<path-to-canvas.tsx>" [outFile]
//
// The canvas BUSINESSES array is pure data (object/array/string literals), so
// it is safe to evaluate as a JS expression after isolating it from the file.

const fs = require("fs");
const path = require("path");

const canvasPath =
  process.argv[2] ||
  "C:\\Users\\glenc\\.cursor\\projects\\d-Works-in-progress-VanAssist\\canvases\\queensland-service-businesses.canvas.tsx";
const outFile =
  process.argv[3] ||
  path.join(__dirname, "..", "database", "seeds", "national_import.json");

// States and regions are stable and small, so we mirror them here rather than
// parse them out of the TSX. Keep in sync with the canvas if regions change.
const STATE_LABELS = {
  QLD: "Queensland",
  NSW: "New South Wales",
  VIC: "Victoria",
  SA: "South Australia",
  WA: "Western Australia",
  TAS: "Tasmania",
  NT: "Northern Territory",
  ACT: "Australian Capital Territory",
};
const STATE_ORDER = ["QLD", "NSW", "VIC", "SA", "WA", "TAS", "NT", "ACT"];

const REGIONS = {
  QLD: [
    { id: "seq", label: "South East Queensland" },
    { id: "downs", label: "Darling Downs" },
    { id: "widebay", label: "Wide Bay\u2013Burnett" },
    { id: "cq", label: "Central Queensland" },
    { id: "fitzroy", label: "Fitzroy" },
    { id: "mackay", label: "Mackay" },
    { id: "nq", label: "North Queensland" },
    { id: "fnq", label: "Far North Queensland" },
    { id: "outback", label: "Outback Queensland" },
  ],
  NSW: [
    { id: "nsw-sydney", label: "Sydney & Greater Sydney" },
    { id: "nsw-hunter", label: "Hunter & Central Coast" },
    { id: "nsw-north-coast", label: "North Coast" },
    { id: "nsw-northern-inland", label: "Northern Inland / New England" },
    { id: "nsw-central-west", label: "Central West & Orana" },
    { id: "nsw-riverina", label: "Riverina & Murray" },
    { id: "nsw-south-coast", label: "South Coast & Illawarra" },
    { id: "nsw-far-west", label: "Far West (Broken Hill)" },
  ],
  VIC: [
    { id: "vic-melb", label: "Melbourne & surrounds" },
    { id: "vic-geelong", label: "Geelong / Surf Coast" },
    { id: "vic-ballarat", label: "Ballarat & Goldfields" },
    { id: "vic-bendigo", label: "Bendigo & Loddon" },
    { id: "vic-gippsland", label: "Gippsland" },
    { id: "vic-goulburn", label: "Goulburn / Hume" },
    { id: "vic-westvic", label: "Western Victoria" },
    { id: "vic-murray", label: "Murray (Mildura/Swan Hill)" },
  ],
  SA: [
    { id: "sa-adelaide", label: "Adelaide & surrounds" },
    { id: "sa-fleurieu", label: "Fleurieu & Kangaroo Island" },
    { id: "sa-barossa", label: "Barossa & Mid North" },
    { id: "sa-riverland", label: "Riverland & Murraylands" },
    { id: "sa-yorke-eyre", label: "Yorke & Eyre Peninsula" },
    { id: "sa-southeast", label: "Limestone Coast (SE)" },
    { id: "sa-outback", label: "Outback SA (Flinders/Far North)" },
  ],
  WA: [
    { id: "wa-perth", label: "Perth & Peel" },
    { id: "wa-southwest", label: "South West" },
    { id: "wa-greatsouthern", label: "Great Southern (Albany)" },
    { id: "wa-wheatbelt", label: "Wheatbelt" },
    { id: "wa-midwest", label: "Mid West (Geraldton)" },
    { id: "wa-goldfields", label: "Goldfields (Kalgoorlie)" },
    { id: "wa-gascoyne", label: "Gascoyne (Carnarvon/Exmouth)" },
    { id: "wa-pilbara", label: "Pilbara" },
    { id: "wa-kimberley", label: "Kimberley" },
  ],
  TAS: [
    { id: "tas-hobart", label: "Hobart & South" },
    { id: "tas-launceston", label: "Launceston & North" },
    { id: "tas-northwest", label: "North West (Devonport/Burnie)" },
    { id: "tas-east", label: "East Coast" },
  ],
  NT: [
    { id: "nt-darwin", label: "Darwin & Top End" },
    { id: "nt-katherine", label: "Katherine" },
    { id: "nt-tennant", label: "Barkly (Tennant Creek)" },
    { id: "nt-alice", label: "Alice Springs & Central" },
    { id: "nt-eastarnhem", label: "East Arnhem (Nhulunbuy)" },
  ],
  ACT: [{ id: "act-canberra", label: "Canberra & region" }],
};

function extractArrayLiteral(src, marker) {
  const at = src.indexOf(marker);
  if (at === -1) throw new Error("Marker not found: " + marker);
  const eq = src.indexOf("=", at);
  if (eq === -1) throw new Error("Assignment not found for " + marker);
  const open = src.indexOf("[", eq);
  if (open === -1) throw new Error("Opening bracket not found for " + marker);

  let depth = 0;
  let inStr = false;
  let quote = "";
  for (let i = open; i < src.length; i++) {
    const ch = src[i];
    if (inStr) {
      if (ch === "\\") {
        i++; // skip escaped char
        continue;
      }
      if (ch === quote) inStr = false;
      continue;
    }
    if (ch === '"' || ch === "'" || ch === "`") {
      inStr = true;
      quote = ch;
      continue;
    }
    if (ch === "[") depth++;
    else if (ch === "]") {
      depth--;
      if (depth === 0) return src.slice(open, i + 1);
    }
  }
  throw new Error("Unbalanced brackets for " + marker);
}

const src = fs.readFileSync(canvasPath, "utf8");
const arrayText = extractArrayLiteral(src, "const BUSINESSES");
// eslint-disable-next-line no-eval
const businesses = eval(arrayText);

if (!Array.isArray(businesses) || businesses.length === 0) {
  throw new Error("No businesses extracted.");
}

const states = STATE_ORDER.map((id) => ({
  id,
  name: STATE_LABELS[id],
  abbr: id === "ACT" ? "ACT" : id,
}));

const regions = [];
for (const s of STATE_ORDER) {
  for (const r of REGIONS[s]) {
    regions.push({ id: r.id, label: r.label, state: s });
  }
}

const out = {
  generated_at: new Date().toISOString(),
  source: path.basename(canvasPath),
  states,
  regions,
  businesses,
};

fs.mkdirSync(path.dirname(outFile), { recursive: true });
fs.writeFileSync(outFile, JSON.stringify(out, null, 2), "utf8");

const byState = {};
for (const r of regions) byState[r.state] = byState[r.state] || 0;
console.log(
  `Extracted ${businesses.length} businesses, ${regions.length} regions, ${states.length} states.`
);
console.log("Wrote " + outFile);
