#!/usr/bin/env node
/**
 * Build individualized Outlook draft .eml files for VanAssist organisation outreach.
 * Does NOT send mail. Opens as unsent drafts in Outlook (X-Unsent: 1).
 *
 *   node tools/generate-organisation-outlook-drafts.js
 *   node tools/generate-organisation-outlook-drafts.js --from support@vanassist.com.au
 *
 * Excludes rows with no_unsolicited_warning=1 or personal_or_ambiguous=1.
 */
"use strict";

const fs = require("fs");
const path = require("path");
const {
  parseCsv,
  truthy,
  slug,
  csvEscape,
  toEml,
  clearGeneratedDrafts,
} = require("./lib/outreach-drafts");

const ROOT = path.join(__dirname, "..");
const CSV = path.join(ROOT, "database", "seeds", "outreach", "vanassist-organisations.csv");
const OUT = path.join(ROOT, "storage", "outreach", "outlook-drafts");

const FROM_DEFAULT = "support@vanassist.com.au";
const SENDER_NAME = "Glen Condren";
const BRAND = "VanAssist";
const SITE = "https://vanassist.com.au/";

function arg(name, def) {
  const i = process.argv.indexOf(name);
  return i >= 0 && process.argv[i + 1] ? process.argv[i + 1] : def;
}

function styleFor(type) {
  switch (type) {
    case "club":
    case "club_federation":
    case "touring_association":
      return "club_member_resource";
    case "industry_association":
      return "industry_data_collaboration";
    case "manufacturer":
    case "dealer_network":
    case "rental_fleet":
      return "fleet_dealer_owner_support";
    case "publication":
      return "editorial_story";
    case "tourism_body":
    case "park_network":
      return "tourism_visitor_resource";
    default:
      return "tourism_visitor_resource";
  }
}

function roleGreeting(role, org) {
  const r = String(role || "").trim();
  if (!r) return `Hello,`;
  // Avoid awkward "Hello, Reception," — keep natural.
  if (/^(reception|general|admin|administration)$/i.test(r)) {
    return `Hello ${org} team,`;
  }
  return `Hello ${r} at ${org},`;
}

function coveragePhrase(coverage, state) {
  const c = String(coverage || "").trim();
  const s = String(state || "").trim();
  if (/^national$/i.test(c)) return "Australia";
  if (c && s) return `${c} (${s})`;
  return c || s || "Australia";
}

function buildSubject(style, org) {
  switch (style) {
    case "club_member_resource":
      return `A free Australian travel-help resource for ${org} members to review`;
    case "industry_data_collaboration":
      return `VanAssist collaboration enquiry for ${org}`;
    case "fleet_dealer_owner_support":
      return `A free location-based support resource — enquiry for ${org}`;
    case "editorial_story":
      return `Story lead for ${org}: free location-first tool for Australian caravan travellers`;
    case "tourism_visitor_resource":
      return `A free road-travel support resource — enquiry for ${org}`;
    default:
      return `VanAssist launch — free resource for ${org}`;
  }
}

function buildBody(rec, style) {
  const org = rec.organisation_name;
  const role = rec.contact_role;
  const area = coveragePhrase(rec.coverage, rec.state_code);
  const why = rec.relevance_reason || "your published role and audience align with caravan and RV travellers";
  const greeting = roleGreeting(role, org);

  const commonClose = [
    `You can review the live service at ${SITE}`,
    ``,
    `If this is outside your role, a pointer to the correct contact would help. Otherwise reply or use unsubscribe/opt-out and we will not follow up on this address.`,
    ``,
    `We are not asking for member, subscriber or customer lists, and we will not imply endorsement or partnership without your agreement.`,
    ``,
    `Regards,`,
    SENDER_NAME,
    BRAND,
    FROM_DEFAULT,
    SITE,
  ].join("\n");

  let middle = "";
  switch (style) {
    case "club_member_resource":
      middle = [
        `I am writing to your published ${role} contact because VanAssist may be useful to people travelling with caravans, motorhomes and RVs across ${area}.`,
        ``,
        `VanAssist has launched as a free service for travellers. It helps people find nearby caravan and vehicle services, fuel, EV charging and caravan-suitable places to stay. Listings show whether information is claimed or verified, and public-source details should still be checked before relying on them.`,
        ``,
        `Why this note to ${org}: ${why}.`,
        ``,
        `Would your committee be willing to review the site? If you consider it genuinely useful, you are welcome to share it with members in whatever way suits ${org}. We are not asking for your member list.`,
      ].join("\n");
      break;
    case "industry_data_collaboration":
      middle = [
        `I am contacting your published ${role} role at ${org} because VanAssist is a free, national location-first directory for caravan and RV travellers covering ${area}.`,
        ``,
        `The platform helps travellers find relevant repairers, mobile technicians, parts, fuel, charging and caravan-suitable stays. We are also working to improve listing accuracy, source transparency and provider claim workflows.`,
        ``,
        `Why this note: ${why}.`,
        ``,
        `I would value a short discussion about whether ${org} can help direct listing-accuracy questions to the right channel, and whether the finished traveller resource may be appropriate for your members or audience.`,
      ].join("\n");
      break;
    case "fleet_dealer_owner_support":
      middle = [
        `I am writing to your published ${role} contact at ${org} because VanAssist may complement the support your owners or renters already receive across ${area}.`,
        ``,
        `VanAssist is free for travellers and helps them find relevant caravan/RV repairers, mobile help, fuel, charging and suitable stays near them or along a route. It is designed for phones and does not require an app install.`,
        ``,
        `Why this note: ${why}.`,
        ``,
        `I would welcome a simple resource or data collaboration discussion—particularly keeping dealer, service and support locations accurate. This is not a request for customer data.`,
      ].join("\n");
      break;
    case "editorial_story":
      middle = [
        `I am sending this to your published ${role} contact at ${org} as a possible reader-service story, not asking for access to your subscriber list.`,
        ``,
        `VanAssist has launched as a free Australian platform that helps caravan, motorhome and RV travellers find nearby repairs, mobile help, fuel, charging and caravan-suitable places to stay. It is location-first, works in a phone browser, and makes claimed, verified and public-source listing status visible.`,
        ``,
        `Why this may interest ${org}: ${why}.`,
        ``,
        `The useful story is also the difficult bit: building a genuinely accurate national service directory and giving travellers a simple way to report gaps or corrections.`,
        ``,
        `If it interests your editor, I can provide background, screenshots and answer questions.`,
      ].join("\n");
      break;
    case "tourism_visitor_resource":
    default:
      middle = [
        `I am contacting your published ${role} role at ${org} because VanAssist may help caravan and RV visitors travel more confidently through ${area}.`,
        ``,
        `The free mobile-friendly website helps travellers find nearby repair and mobile services, fuel, charging and caravan-suitable stays. It can also expose service or accommodation gaps that matter on well-used touring routes.`,
        ``,
        `Why this note: ${why}.`,
        ``,
        `Would your team be willing to review the site and advise whether it belongs in your visitor or industry resources?`,
      ].join("\n");
      break;
  }

  return `${greeting}\n\n${middle}\n\n${commonClose}\n`;
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
      "style",
      "to_email",
      "subject",
      "excluded",
      "exclude_reason",
    ].join(","),
  ];
  let created = 0;
  let excluded = 0;
  const seenEmail = new Set();

  for (const rec of records) {
    const email = String(rec.email || "").toLowerCase().trim();
    const org = rec.organisation_name || "Organisation";
    const type = rec.organisation_type || "other";
    let excludeReason = "";
    if (!email || !email.includes("@")) excludeReason = "missing_email";
    else if (truthy(rec.no_unsolicited_warning)) excludeReason = "no_unsolicited_warning";
    else if (truthy(rec.personal_or_ambiguous)) excludeReason = "personal_or_ambiguous";
    else if (seenEmail.has(email)) excludeReason = "duplicate_email";

    if (excludeReason) {
      excluded++;
      index.push(
        [
          "",
          csvEscape(org),
          type,
          "",
          email,
          "",
          "1",
          excludeReason,
        ].join(",")
      );
      continue;
    }
    seenEmail.add(email);

    const style = styleFor(type);
    const subject = buildSubject(style, org);
    const body = buildBody(rec, style);
    const file = `${String(created + 1).padStart(2, "0")}-${slug(type)}-${slug(org)}.eml`;
    const eml = toEml({
      from,
      fromName: SENDER_NAME,
      to: email,
      subject,
      bodyText: body,
      draftTag: "organisation-outreach",
    });
    fs.writeFileSync(path.join(OUT, file), eml, "utf8");
    created++;
    index.push(
      [
        file,
        csvEscape(org),
        type,
        style,
        email,
        csvEscape(subject),
        "0",
        "",
      ].join(",")
    );
  }

  fs.writeFileSync(path.join(OUT, "INDEX.csv"), index.join("\n") + "\n", "utf8");
  fs.writeFileSync(
    path.join(OUT, "README.txt"),
    [
      "VanAssist organisation outreach — Outlook drafts",
      "================================================",
      "",
      `From: ${SENDER_NAME} <${from}>`,
      `Generated: ${new Date().toISOString()}`,
      `Created: ${created}`,
      `Excluded: ${excluded} (see INDEX.csv)`,
      "",
      "These files are UNSENT drafts (X-Unsent: 1).",
      "They do not send themselves.",
      "",
      "How to open in Outlook (Windows):",
      "1. Browse to this folder.",
      "2. Double-click a .eml file — Outlook opens a compose window.",
      "3. Review the individualised text.",
      "4. Send only after you are satisfied (staged: internal test, then small batches).",
      "",
      "Do not BCC everyone into one email.",
      "Do not send RACQ or any row marked excluded.",
      "Published emails are not marketing consent; keep messages role-relevant.",
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
        index: "storage/outreach/outlook-drafts/INDEX.csv",
      },
      null,
      2
    )
  );
}

main();
