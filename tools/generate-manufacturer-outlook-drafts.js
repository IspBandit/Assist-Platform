#!/usr/bin/env node
/**
 * Build individualized Outlook draft .eml files introducing VanAssist to
 * Australian caravan and camper manufacturers.
 * Does NOT send mail. Opens as unsent drafts in Outlook (X-Unsent: 1).
 *
 *   node tools/generate-manufacturer-outlook-drafts.js
 *   node tools/generate-manufacturer-outlook-drafts.js --from support@vanassist.com.au
 *
 * Rows without a verified published email, or flagged
 * no_unsolicited_warning / personal_or_ambiguous, are excluded and listed in
 * INDEX.csv so they can be followed up manually through the official form.
 */
"use strict";

const fs = require("fs");
const path = require("path");
const {
  parseCsv,
  slug,
  csvEscape,
  toEml,
  excludeReasonFor,
  clearGeneratedDrafts,
} = require("./lib/outreach-drafts");

const ROOT = path.join(__dirname, "..");
const CSV = path.join(ROOT, "database", "seeds", "outreach", "vanassist-manufacturers.csv");
const OUT = path.join(ROOT, "storage", "outreach", "outlook-drafts-manufacturers");

const FROM_DEFAULT = "support@vanassist.com.au";
const SENDER_NAME = "Glen Condren";
const BRAND = "VanAssist";
const SITE = "https://vanassist.com.au/";
const DRAFT_TAG = "manufacturer-outreach";

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}

/**
 * Per-build-type language so each manufacturer reads copy about what they
 * actually make, rather than a generic "RV" message.
 */
const BUILD_TYPES = {
  manufacturer_caravan: {
    product: "caravan",
    owners: "caravan owners",
    scenario:
      "a bearing, brake, water-pump or 12-volt problem hundreds of kilometres from the dealer who sold the van",
  },
  manufacturer_camper_trailer: {
    product: "camper trailer",
    owners: "camper trailer owners",
    scenario:
      "a suspension, coupling or canvas problem well past the last town with a workshop",
  },
  manufacturer_hybrid_camper: {
    product: "hybrid camper",
    owners: "hybrid camper owners",
    scenario:
      "a solar, battery or water-system fault a long way from anyone who has seen a hybrid before",
  },
  manufacturer_campervan: {
    product: "campervan",
    owners: "campervan owners",
    scenario:
      "a mechanical or fit-out problem in a town where nobody advertises campervan work",
  },
  manufacturer_motorhome: {
    product: "motorhome",
    owners: "motorhome owners",
    scenario:
      "a breakdown in a vehicle too long or too heavy for the nearest general workshop",
  },
  manufacturer_slide_on: {
    product: "slide-on camper",
    owners: "slide-on camper owners",
    scenario:
      "a jack, mount or 12-volt failure somewhere remote, with the camper still on the tray",
  },
};

function buildType(type) {
  return BUILD_TYPES[type] || BUILD_TYPES.manufacturer_caravan;
}

// Only a real department name reads well in a salutation; anything else
// ("Bookings and enquiries", "Getting in touch") is addressed to the company.
const DEPARTMENT = /^(sales|marketing|media|editorial|support|customer service|customer support)\b/i;

function roleGreeting(role, org) {
  const match = String(role || "").trim().match(DEPARTMENT);
  if (!match) return `Hello ${org} team,`;
  const department = match[1].replace(/\b\w/g, (c) => c.toUpperCase());
  return `Hello ${department} team at ${org},`;
}

const STATE_NAMES = {
  "new south wales": "NSW",
  victoria: "VIC",
  queensland: "QLD",
  "south australia": "SA",
  "western australia": "WA",
  tasmania: "TAS",
  "northern territory": "NT",
  "australian capital territory": "ACT",
};

function coveragePhrase(coverage, state) {
  const c = String(coverage || "").trim();
  const s = String(state || "").trim().toUpperCase();
  if (/^national$/i.test(c)) return "Australia";
  if (!c) return s || "Australia";
  if (!s || STATE_NAMES[c.toLowerCase()] === s) return c;
  return `${c} (${s})`;
}

function buildSubject(rec) {
  const org = rec.organisation_name;
  const t = buildType(rec.organisation_type);
  return `Introducing VanAssist to ${org} — a free service finder for ${t.product} owners`;
}

function buildBody(rec, from) {
  const org = rec.organisation_name;
  const t = buildType(rec.organisation_type);
  const area = coveragePhrase(rec.coverage, rec.state_code);
  const why =
    rec.relevance_reason ||
    "your owners travel widely and benefit from accurate service and assistance information";

  return [
    roleGreeting(rec.contact_role, org),
    ``,
    `I am writing to your published ${String(rec.contact_role || "general").toLowerCase()} contact at ${org} to introduce VanAssist, a free Australian service for people travelling with caravans, campers and motorhomes.`,
    ``,
    `The problem it addresses is the one your ${t.owners} hit hardest: ${t.scenario}. At that moment a traveller does not need a national call centre or another app to install — they need to know who is actually nearby, whether they handle ${t.product}s, and whether they are open.`,
    ``,
    `VanAssist works in a phone browser, with no app and no charge to travellers. From wherever they are standing, or along a planned route across ${area}, it helps them find:`,
    ``,
    `  - repairers, mobile technicians and roadside help suited to ${t.product}s`,
    `  - parts, tyres, batteries, gas and 12-volt specialists`,
    `  - fuel and EV charging`,
    `  - places to stay that genuinely suit their rig, not just any accommodation`,
    ``,
    `Listings show whether details are claimed by the business, independently verified, or drawn from public sources, so travellers can judge how much to rely on them rather than being shown a confident-looking result with no provenance. Travellers can also report a gap or a correction, which is how the directory improves.`,
    ``,
    `Why I am writing to ${org} specifically: ${why}.`,
    ``,
    `Two things would be genuinely useful, and neither costs you anything:`,
    ``,
    `  1. Look at the site and tell me where it is wrong or thin for owners of your product. Critical feedback is more valuable to me than agreement.`,
    `  2. If your dealer, service or warranty-repair locations are listed inaccurately, point me at the correct source and I will fix it.`,
    ``,
    `You can review the live service at ${SITE}`,
    ``,
    `To be clear about what this is not: I am not asking for your customer or owner lists, I am not selling advertising in this note, and I will not describe ${org} as a partner or imply endorsement without your written agreement.`,
    ``,
    `If this sits outside your role, a pointer to the right person would help. Otherwise reply with "unsubscribe" and I will not contact this address again.`,
    ``,
    `Regards,`,
    SENDER_NAME,
    BRAND,
    from,
    SITE,
    ``,
  ].join("\n");
}

function main() {
  const from = arg("--from", FROM_DEFAULT);
  if (!fs.existsSync(CSV)) {
    console.error("Missing CSV:", CSV);
    process.exit(1);
  }
  const records = parseCsv(fs.readFileSync(CSV, "utf8"));
  fs.mkdirSync(OUT, { recursive: true });
  clearGeneratedDrafts(OUT, fs, path);

  const index = [
    [
      "filename",
      "organisation_name",
      "organisation_type",
      "state_code",
      "to_email",
      "subject",
      "excluded",
      "exclude_reason",
      "manual_followup_url",
    ].join(","),
  ];
  let created = 0;
  let excluded = 0;
  const seenEmail = new Set();

  for (const rec of records) {
    const org = rec.organisation_name || "Organisation";
    const type = rec.organisation_type || "manufacturer_caravan";
    const email = String(rec.email || "").toLowerCase().trim();
    const reason = excludeReasonFor(rec, seenEmail);

    if (reason) {
      excluded++;
      index.push(
        [
          "",
          csvEscape(org),
          type,
          rec.state_code || "",
          email,
          "",
          "1",
          reason,
          csvEscape(rec.source_url || rec.website_url || ""),
        ].join(",")
      );
      continue;
    }
    seenEmail.add(email);

    const subject = buildSubject(rec);
    const body = buildBody(rec, from);
    const file = `${String(created + 1).padStart(2, "0")}-${slug(type.replace(/^manufacturer_/, ""))}-${slug(org)}.eml`;
    fs.writeFileSync(
      path.join(OUT, file),
      toEml({ from, fromName: SENDER_NAME, to: email, subject, bodyText: body, draftTag: DRAFT_TAG }),
      "utf8"
    );
    created++;
    index.push(
      [
        file,
        csvEscape(org),
        type,
        rec.state_code || "",
        email,
        csvEscape(subject),
        "0",
        "",
        "",
      ].join(",")
    );
  }

  fs.writeFileSync(path.join(OUT, "INDEX.csv"), index.join("\n") + "\n", "utf8");
  fs.writeFileSync(
    path.join(OUT, "README.txt"),
    [
      "VanAssist manufacturer outreach — Outlook drafts",
      "================================================",
      "",
      `From: ${SENDER_NAME} <${from}>`,
      `Generated: ${new Date().toISOString()}`,
      `Created: ${created}`,
      `Excluded: ${excluded} (see INDEX.csv)`,
      "",
      "These files are UNSENT drafts (X-Unsent: 1). They do not send themselves.",
      "",
      "How to open in Outlook (Windows):",
      "1. Browse to this folder.",
      "2. Double-click a .eml file — Outlook opens a compose window.",
      "3. Review the individualised text.",
      "4. Send only after an internal test, then in small batches.",
      "",
      "Sending requires Send As permission on the From mailbox. If Outlook",
      "accepts the message but you receive a delivery failure notice, the",
      "permission is missing — fix that before sending anything else.",
      "",
      "Excluded rows are not failures. Most publish no email at all and must be",
      "approached through their official contact form. INDEX.csv lists the URL.",
      "",
      "Do not BCC everyone into one email.",
      "A published business address is not marketing consent; keep every message",
      "relevant to the published role.",
      "",
      `Folder: ${OUT}`,
      "",
    ].join("\n"),
    "utf8"
  );

  console.log(
    JSON.stringify(
      {
        from,
        created,
        excluded,
        out_dir: path.relative(ROOT, OUT).replace(/\\/g, "/"),
        index: "storage/outreach/outlook-drafts-manufacturers/INDEX.csv",
      },
      null,
      2
    )
  );
}

main();
