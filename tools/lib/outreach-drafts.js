"use strict";

/**
 * Shared helpers for building unsent Outlook draft .eml files from outreach CSVs.
 * Used by generate-organisation-outlook-drafts.js and
 * generate-manufacturer-outlook-drafts.js.
 */

function parseCsv(text) {
  const rows = [];
  let i = 0;
  let field = "";
  let row = [];
  let inQuotes = false;
  while (i < text.length) {
    const c = text[i];
    if (inQuotes) {
      if (c === '"') {
        if (text[i + 1] === '"') {
          field += '"';
          i += 2;
          continue;
        }
        inQuotes = false;
        i++;
        continue;
      }
      field += c;
      i++;
      continue;
    }
    if (c === '"') {
      inQuotes = true;
      i++;
      continue;
    }
    if (c === ",") {
      row.push(field);
      field = "";
      i++;
      continue;
    }
    if (c === "\n" || (c === "\r" && text[i + 1] === "\n")) {
      row.push(field);
      field = "";
      if (row.some((v) => v.trim() !== "")) rows.push(row);
      row = [];
      i += c === "\r" ? 2 : 1;
      continue;
    }
    if (c === "\r") {
      row.push(field);
      field = "";
      if (row.some((v) => v.trim() !== "")) rows.push(row);
      row = [];
      i++;
      continue;
    }
    field += c;
    i++;
  }
  if (field.length || row.length) {
    row.push(field);
    if (row.some((v) => v.trim() !== "")) rows.push(row);
  }
  if (!rows.length) return [];
  const header = rows[0].map((h) => h.trim().toLowerCase().replace(/^\ufeff/, ""));
  return rows.slice(1).map((cols) => {
    const obj = {};
    header.forEach((h, idx) => {
      obj[h] = (cols[idx] || "").trim();
    });
    return obj;
  });
}

function truthy(v) {
  return ["1", "true", "yes", "y"].includes(String(v || "").toLowerCase());
}

function slug(s) {
  return String(s || "")
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 80);
}

function csvEscape(v) {
  const s = String(v ?? "");
  if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
  return s;
}

function encodeSubject(subject) {
  // RFC 2047 for non-ASCII; keep simple ASCII path otherwise.
  if (/^[\x20-\x7E]+$/.test(subject)) return subject;
  return `=?UTF-8?B?${Buffer.from(subject, "utf8").toString("base64")}?=`;
}

function toEml({ from, fromName, to, subject, bodyText, draftTag }) {
  return [
    `X-Unsent: 1`,
    `From: ${fromName} <${from}>`,
    `To: ${to}`,
    `Reply-To: ${from}`,
    `Subject: ${encodeSubject(subject)}`,
    `Date: ${new Date().toUTCString()}`,
    `MIME-Version: 1.0`,
    `Content-Type: text/plain; charset="UTF-8"`,
    `Content-Transfer-Encoding: 8bit`,
    `X-VanAssist-Draft: ${draftTag}`,
    ``,
    bodyText.replace(/\r?\n/g, "\r\n"),
  ].join("\r\n");
}

/**
 * Reason a row must not be turned into a draft, or "" when it is safe.
 * A published address is never treated as marketing consent.
 */
function excludeReasonFor(rec, seenEmail) {
  const email = String(rec.email || "").toLowerCase().trim();
  if (!email || !email.includes("@")) return "missing_email";
  if (truthy(rec.no_unsolicited_warning)) return "no_unsolicited_warning";
  if (truthy(rec.personal_or_ambiguous)) return "personal_or_ambiguous";
  if (seenEmail.has(email)) return "duplicate_email";
  return "";
}

function clearGeneratedDrafts(dir, fs, path) {
  for (const f of fs.readdirSync(dir)) {
    if (f.endsWith(".eml") || f === "INDEX.csv" || f === "README.txt") {
      fs.unlinkSync(path.join(dir, f));
    }
  }
}

module.exports = {
  parseCsv,
  truthy,
  slug,
  csvEscape,
  encodeSubject,
  toEml,
  excludeReasonFor,
  clearGeneratedDrafts,
};
