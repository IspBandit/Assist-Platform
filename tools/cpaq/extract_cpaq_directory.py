#!/usr/bin/env python3
"""
Extract every usable record from the CPAQ 2026 Explore Queensland
Caravan Parks Directory PDF into structured JSON for VanAssist import.

Strategy:
  1. Parks-by-name + parks-by-location indexes are the authority for WHICH parks exist.
  2. Detail listing pages supply contact, address, features and description.
  3. Residential section fills pure-residential / long-term rows and missing contacts.
  4. Trade matrix uses rotated column headers + dot x-positions for ALL categories.
  5. Regional repairer sidebars are captured as supplemental trade leads.

Outputs under database/seeds/cpaq-2026/
"""
from __future__ import annotations

import hashlib
import json
import re
import unicodedata
from collections import Counter
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Any

import pymupdf

ROOT = Path(__file__).resolve().parents[2]
PDF_CANDIDATES = [
    ROOT / "storage/imports/cpaq-2026-directory.pdf",
    Path(r"c:\Users\glenc\Downloads\J011629_DIGITAL_Caravan - Parks Directory 112pp (inc Design) 275mm x 210mm_V.pdf"),
]
OUT_DIR = ROOT / "database/seeds/cpaq-2026"

PHONE_RE = re.compile(
    r"(?:\+?61[\s\-]*)?(?:\(?0\d\)?[\s\-]*)?\d{3,4}[\s\-]?\d{3,4}(?:[\s\-]?\d{3})?"
    r"|1300[\s\-]?\d{3}[\s\-]?\d{3}|1800[\s\-]?\d{3}[\s\-]?\d{3}|13[\s\-]?\d{2}[\s\-]?\d{2}"
    r"|04\d{2}[\s\-]?\d{3}[\s\-]?\d{3}"
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)
URL_RE = re.compile(
    r"(?:https?://)?(?:www\.)?[A-Z0-9][A-Z0-9.\-]*\.[A-Z]{2,}(?:/[^\s|,;]*)?",
    re.I,
)
POSTCODE_RE = re.compile(r"\b(4\d{3})\b")
BULLET_CHARS = "•·▪◦●○\u2022\u2023\u25E6\u2043\u2219"

PARK_REGIONS = [
    ("Gold Coast", 11, 16),
    ("Brisbane", 17, 22),
    ("Sunshine Coast", 23, 30),
    ("Queensland Country", 31, 38),
    ("Fraser Coast", 39, 46),
    ("Bundaberg / Southern Great Barrier Reef", 47, 50),
    ("Gladstone / Southern Great Barrier Reef", 51, 54),
    ("Capricorn Coast / Southern Great Barrier Reef", 55, 62),
    ("Mackay", 63, 66),
    ("Whitsundays", 67, 71),
    ("Townsville / North Queensland", 72, 76),
    ("Cairns & Tropical North", 77, 86),
    ("Outback Queensland", 87, 90),
    ("Travelling Interstate", 91, 91),
]

TRADE_PAGE_RANGE = (95, 107)
RESIDENTIAL_PAGE_RANGE = (93, 94)

TRADE_CATEGORIES = [
    "Accessories",
    "Annexe & Awnings",
    "Camper Trailer Sales",
    "Caravan Sales",
    "Communications",
    "Conversions",
    "Driver Instruction or Training",
    "Engineering",
    "Fifth Wheeler Sales",
    "Finance",
    "Gas",
    "Hire",
    "Hybrid Sales",
    "Insurance",
    "Motorhome / Campervan Sales",
    "Refrigeration",
    "Safety Certificate",
    "Service & Repair",
    "Slide-on Sales",
    "Solar Products",
    "Tents",
    "Towing",
    "Vehicle Modification",
    "Weight",
]

TRADE_CATEGORY_X = {
    "Accessories": 217,
    "Annexe & Awnings": 229,
    "Camper Trailer Sales": 248,
    "Caravan Sales": 263,
    "Communications": 278,
    "Conversions": 294,
    "Driver Instruction or Training": 309,
    "Engineering": 324,
    "Fifth Wheeler Sales": 340,
    "Finance": 355,
    "Gas": 370,
    "Hire": 386,
    "Hybrid Sales": 401,
    "Insurance": 416,
    "Motorhome / Campervan Sales": 432,
    "Refrigeration": 447,
    "Safety Certificate": 462,
    "Service & Repair": 477,
    "Slide-on Sales": 493,
    "Solar Products": 508,
    "Tents": 523,
    "Towing": 539,
    "Vehicle Modification": 554,
    "Weight": 570,
}

SECTION_NAMES = {
    "GOLD COAST": "Gold Coast",
    "BRISBANE": "Brisbane",
    "SUNSHINE COAST": "Sunshine Coast",
    "QLD COUNTRY": "Qld Country",
    "QUEENSLAND COUNTRY": "Qld Country",
    "FRASER COAST": "Fraser Coast",
    "BUNDABERG & GLADSTONE": "Bundaberg & Gladstone",
    "CAPRICORN": "Capricorn",
    "MACKAY": "Mackay",
    "TOWNSVILLE": "Townsville",
    "CAIRNS & TROPICAL NORTH": "Cairns & Tropical North",
    "INTERSTATE BUSINESS": "Interstate Business",
    "MOBILE BUSINESSES": "Mobile Businesses",
    "ONLINE BUSINESSES": "Online Businesses",
}

JUNK_INDEX_NAMES = {"motel", "park", "village", "page", "park name", "region/location - park"}


@dataclass
class Span:
    x0: float
    y0: float
    x1: float
    y1: float
    size: float
    text: str
    page: int


@dataclass
class ParkRecord:
    external_id: str
    name: str
    region: str | None = None
    subregion: str | None = None
    street_address: str | None = None
    suburb: str | None = None
    postcode: str | None = None
    state: str = "QLD"
    phone: str | None = None
    email: str | None = None
    website: str | None = None
    description: str | None = None
    features: list[str] = field(default_factory=list)
    attributes: dict[str, Any] = field(default_factory=dict)
    source_page: int | None = None
    directory_page: int | None = None
    listing_kind: str = "caravan_holiday_park"
    pure_residential: bool = False
    detail_matched: bool = False
    raw: dict[str, Any] = field(default_factory=dict)


@dataclass
class TradeRecord:
    external_id: str
    name: str
    region_section: str | None = None
    suburb: str | None = None
    phone: str | None = None
    website: str | None = None
    categories: list[str] = field(default_factory=list)
    source_page: int | None = None
    is_mobile: bool = False
    is_online: bool = False
    is_interstate: bool = False
    source_note: str | None = None


def find_pdf() -> Path:
    for path in PDF_CANDIDATES:
        if path.is_file():
            return path
    raise SystemExit("CPAQ PDF not found. Ask Glen for the local path.")


def norm_space(text: str) -> str:
    text = unicodedata.normalize("NFKC", text)
    text = text.replace("\xa0", " ").replace("\u200b", "")
    return re.sub(r"\s+", " ", text).strip()


def fold(text: str) -> str:
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    text = text.casefold()
    text = text.replace("&", " and ")
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def slug_key(*parts: str) -> str:
    joined = "|".join(fold(p) for p in parts if p)
    digest = hashlib.sha1(joined.encode("utf-8")).hexdigest()[:10]
    base = re.sub(r"[^a-z0-9]+", "-", joined).strip("-")[:80] or "record"
    return f"cpaq-2026-{base}-{digest}"


def clean_phone(text: str | None) -> str | None:
    if not text:
        return None
    m = PHONE_RE.search(text)
    if not m:
        return None
    digits = re.sub(r"\D", "", m.group(0))
    if digits.startswith("61") and len(digits) >= 11:
        digits = "0" + digits[2:]
    if len(digits) < 8:
        return None
    return digits


def clean_email(text: str | None) -> str | None:
    if not text:
        return None
    m = EMAIL_RE.search(text.replace(" ", ""))
    return m.group(0).lower() if m else None


def clean_website(text: str | None) -> str | None:
    if not text:
        return None
    raw = text.strip().rstrip(".,;")
    if "@" in raw and "://" not in raw:
        return None
    m = URL_RE.search(raw)
    if not m:
        return None
    raw = m.group(0).rstrip("/")
    if raw.lower().startswith("www."):
        raw = "https://" + raw
    elif not raw.lower().startswith("http"):
        raw = "https://" + raw
    host = re.sub(r"^https?://", "", raw, flags=re.I).split("/")[0].lower()
    if host in {"caravanqld.com.au", "www.caravanqld.com.au"}:
        return None
    return raw


def looks_like_domain(text: str) -> bool:
    t = text.strip().lower()
    if " " in t or "@" in t:
        return False
    return bool(re.match(r"^(?:www\.)?[a-z0-9][a-z0-9.\-]*\.[a-z]{2,}(?:/.*)?$", t))


def page_spans(page: pymupdf.Page, page_index: int) -> list[Span]:
    out: list[Span] = []
    for block in page.get_text("dict").get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            for s in line.get("spans", []):
                text = norm_space(s.get("text", ""))
                if not text:
                    continue
                x0, y0, x1, y1 = s["bbox"]
                out.append(Span(x0, y0, x1, y1, float(s["size"]), text, page_index))
    return out


def infer_attributes(features: list[str], description: str | None) -> dict[str, Any]:
    blob = " | ".join(features + ([description] if description else [])).lower()
    attrs: dict[str, Any] = {}

    def has(*needles: str) -> bool:
        return any(n in blob for n in needles)

    if has("no pets"):
        attrs["pets_allowed"] = False
    elif has("pet friendly", "pets welcome", "dog friendly"):
        attrs["pets_allowed"] = True
    elif has("pets on application", "pets by arrangement"):
        attrs["pets_allowed"] = "on_application"

    if has("no dump point"):
        attrs["dump_point"] = False
    elif has("dump point"):
        attrs["dump_point"] = True

    if has("camp kitchen"):
        attrs["camp_kitchen"] = True
    if has("no wi-fi", "no wifi"):
        attrs["wifi"] = False
    elif has("free wifi", "wifi", "wi-fi", "wi fi"):
        attrs["wifi"] = True
    if has("swimming pool", "heated pool", "resort pool") or re.search(r"\bpool\b", blob):
        attrs["pool"] = True
    if has("boat ramp"):
        attrs["boat_ramp"] = True
    if has("beachfront", "absolute beachfront", "close to beach", "waterfront"):
        attrs["waterfront_or_beach"] = True
    if has("rv & motorhome", "motorhome", "large rig", "big rig"):
        attrs["rv_motorhome_sites"] = True
    if has("cabin", "self-contained accom", "villa"):
        attrs["cabins_or_accommodation"] = True
    if has("accessible cabin", "disabled amenities", "accessible"):
        attrs["accessible"] = True
    if has("unpowered"):
        attrs["unpowered_sites"] = True
    if has("powered"):
        attrs["powered_sites"] = True
    if has("tourist information"):
        attrs["tourist_information"] = True
    if has("camping ground", "campground"):
        attrs["camping_ground"] = True
    return attrs


def parse_suburb_postcode(line: str) -> tuple[str | None, str | None]:
    m = POSTCODE_RE.search(line)
    if not m:
        return None, None
    postcode = m.group(1)
    suburb = norm_space(line[: m.start()].rstrip(","))
    return (suburb or None), postcode


ADDRESS_TOKEN_RE = re.compile(
    r"(?i)\b("
    r"rd|road|st|street|ave|avenue|hwy|highway|drive|drv|dr|cres|crescent|"
    r"esp|esplanade|parade|pde|court|ct|place|pl|lane|ln|way|blvd|terrace|tce|"
    r"close|cl|circuit|boulevard|track|loop|grove|gr|rise|mews|row|quay|"
    r"waters|beach|point|island|highway|motorway"
    r")\b"
)


PROSE_RE = re.compile(
    r"(?i)\b("
    r"offering|exploring|affordable|friendly|located|perfect|enjoy|welcome|"
    r"features|including|minutes|across|surrounded|peaceful|popular|centrally|"
    r"blends|atmosphere|experience|unforgettable|acres of|nature, space"
    r")\b"
)


def is_address_line(text: str) -> bool:
    t = text.strip().rstrip(",")
    if not t or len(t) > 90:
        return False
    if clean_phone(t) and len(t) < 22:
        return False
    if looks_like_domain(t) or EMAIL_RE.search(t):
        return False
    if PROSE_RE.search(t) or t.endswith("."):
        return False
    if POSTCODE_RE.search(t):
        # "Carrara 4211" or "via Yeppoon 4703"
        return True
    # Street lines almost always start with a number in this directory.
    if re.match(r"^\d+[A-Za-z]?\s+\S+", t):
        return True
    # Rare non-numbered sites (e.g. island campgrounds) with a clear road token.
    if ADDRESS_TOKEN_RE.search(t) and len(t.split()) <= 8 and "," not in t:
        return True
    return False


def is_feature_text(text: str, size: float) -> bool:
    t = text.strip()
    if size < 5.5 or size > 7.0:
        return False
    if not t or len(t) > 70:
        return False
    up = t.upper()
    if up in {"CARAVAN/", "HOLIDAY PARK", "CARAVAN/HOLIDAY PARK", "ACCREDITED"}:
        return False
    if "CARAVAN/" in up and "HOLIDAY" in up:
        return False
    return True


def extract_parks_by_name(doc: pymupdf.Document) -> list[dict[str, Any]]:
    names: list[dict[str, Any]] = []
    for idx in range(5, 7):
        lines = [norm_space(x) for x in doc[idx].get_text().splitlines() if norm_space(x)]
        i = 0
        while i < len(lines) - 1:
            name, nxt = lines[i], lines[i + 1]
            if re.fullmatch(r"\d{1,3}", nxt) and not re.fullmatch(r"\d+", name):
                if fold(name) not in JUNK_INDEX_NAMES and len(name) > 3:
                    names.append({"name": name, "directory_page": int(nxt)})
                i += 2
                continue
            i += 1
    seen: set[str] = set()
    out = []
    for row in names:
        key = fold(row["name"])
        if key in seen:
            continue
        seen.add(key)
        out.append(row)
    return out


def extract_parks_by_location(doc: pymupdf.Document) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    region_headers = {
        "GOLD COAST", "BRISBANE", "SUNSHINE COAST", "QUEENSLAND COUNTRY",
        "FRASER COAST", "SGBR - GLADSTONE", "SGBR - CAPRICORN COAST", "MACKAY",
        "WHITSUNDAYS", "TOWNSVILLE/NORTH QUEENSLAND", "CAIRNS AND TROPICAL NORTH",
        "OUTBACK QUEENSLAND", "TRAVELLING INTERSTATE", "RESIDENTIAL PARKS",
    }
    for idx in (3, 4):
        lines = [norm_space(x) for x in doc[idx].get_text().splitlines() if norm_space(x)]
        current_region = None
        i = 0
        while i < len(lines):
            line = lines[i]
            upper = line.upper()
            if upper in region_headers or upper.startswith("BUNDABERG"):
                current_region = line
                i += 1
                continue
            if " - " in line and i + 1 < len(lines) and re.fullmatch(r"\d{1,3}", lines[i + 1]):
                loc, park = line.split(" - ", 1)
                if fold(park) not in JUNK_INDEX_NAMES:
                    rows.append(
                        {
                            "region": current_region,
                            "location": loc.strip(),
                            "name": park.strip(),
                            "directory_page": int(lines[i + 1]),
                        }
                    )
                i += 2
                continue
            if current_region and i + 2 < len(lines) and re.fullmatch(r"\d{1,3}", lines[i + 2]):
                maybe = f"{line} {lines[i + 1]}"
                if " - " in maybe:
                    loc, park = maybe.split(" - ", 1)
                    if fold(park) not in JUNK_INDEX_NAMES:
                        rows.append(
                            {
                                "region": current_region,
                                "location": loc.strip(),
                                "name": park.strip(),
                                "directory_page": int(lines[i + 2]),
                            }
                        )
                    i += 3
                    continue
            i += 1
    seen: set[str] = set()
    out = []
    for row in rows:
        key = fold(row["name"])
        if key in seen:
            continue
        seen.add(key)
        out.append(row)
    return out


def build_name_lookup(index_names: list[str]) -> dict[str, str]:
    """Map folded name → canonical display name."""
    lookup = {fold(n): n for n in index_names}
    # also without common suffixes for softer match
    for n in index_names:
        f = fold(n)
        for suffix in (" caravan park", " holiday park", " tourist park", " holiday village", " holiday resort"):
            if f.endswith(suffix):
                lookup.setdefault(f[: -len(suffix)], n)
    return lookup


def match_index_name(text: str, lookup: dict[str, str]) -> str | None:
    f = fold(text)
    if f in lookup:
        return lookup[f]
    # tolerate trailing punctuation / slight wrap differences
    for key, canon in lookup.items():
        if f == key or f.startswith(key + " ") or key.startswith(f + " "):
            if abs(len(f) - len(key)) <= 8:
                return canon
    return None


def harvest_detail_cards(doc: pymupdf.Document, lookup: dict[str, str]) -> dict[str, dict[str, Any]]:
    """Return folded-name → extracted detail fields from listing pages."""
    cards: dict[str, dict[str, Any]] = {}
    for region, start, end in PARK_REGIONS:
        for idx in range(start, end + 1):
            if idx >= doc.page_count:
                continue
            spans = page_spans(doc[idx], idx)
            if len(spans) < 8:
                continue
            name_spans = [s for s in spans if 11.0 <= s.size <= 13.5 and (s.x1 - s.x0) > 40]
            # Sort so we can bound each card by the next name in the same column.
            name_spans.sort(key=lambda s: (0 if s.x0 < 280 else 1, s.y0))
            for name_i, name_span in enumerate(name_spans):
                canon = match_index_name(name_span.text, lookup)
                if canon is None:
                    continue
                col_left = name_span.x0 < 280
                x_min = 0 if col_left else 275
                x_max = 305 if col_left else 620
                y0 = name_span.y0 - 22
                # Some cards put a photo between the title and the address/features.
                y1 = name_span.y0 + 340
                for later in name_spans[name_i + 1 :]:
                    same_col = (later.x0 < 280) == col_left
                    if same_col and later.y0 > name_span.y0 + 20:
                        y1 = min(y1, later.y0 - 8)
                        break
                related = [
                    s for s in spans
                    if x_min <= s.x0 <= x_max and y0 <= s.y0 <= y1 and s is not name_span
                ]
                related.sort(key=lambda s: (s.y0, s.x0))

                subregion = None
                for s in related:
                    if (
                        7.5 <= s.size <= 9.0
                        and s.y0 < name_span.y0
                        and s.text.isupper()
                        and 3 < len(s.text) < 60
                    ):
                        subregion = s.text
                        break

                features: list[str] = []
                desc_bits: list[str] = []
                address_bits: list[str] = []
                phone = email = website = None

                for s in related:
                    t = s.text
                    if s.y0 < name_span.y0:
                        continue
                    # feature bullets sit to the right of the name block
                    if t[:1] in BULLET_CHARS or (
                        s.x0 >= name_span.x0 + 140 and is_feature_text(t, s.size)
                    ):
                        feat = t.lstrip(BULLET_CHARS + " -").strip()
                        if feat and is_feature_text(feat, max(s.size, 6.0)) and feat not in features:
                            features.append(feat)
                        continue
                    if EMAIL_RE.search(t):
                        email = email or clean_email(t)
                        continue
                    if looks_like_domain(t):
                        website = website or clean_website(t)
                        continue
                    if clean_phone(t) and len(t) <= 24:
                        phone = phone or clean_phone(t)
                        continue
                    if is_address_line(t) and s.x0 < name_span.x0 + 110:
                        address_bits.append(t)
                        continue
                    if (
                        6.0 <= s.size <= 7.5
                        and s.x0 < name_span.x0 + 100
                        and name_span.y0 < s.y0 < name_span.y0 + 95
                        and not is_address_line(t)
                        and not looks_like_domain(t)
                    ):
                        desc_bits.append(t)

                street = None
                suburb = None
                postcode = None
                # Prefer the postcode line and only keep street lines directly above it.
                postcode_idx = next((i for i, line in enumerate(address_bits) if POSTCODE_RE.search(line)), None)
                if postcode_idx is not None:
                    sub, pc = parse_suburb_postcode(address_bits[postcode_idx])
                    suburb, postcode = sub, pc
                    street_candidates = [
                        line for line in address_bits[:postcode_idx]
                        if is_address_line(line) and not POSTCODE_RE.search(line)
                    ]
                    street = ", ".join(street_candidates) if street_candidates else None
                else:
                    for line in address_bits:
                        if is_address_line(line):
                            street = f"{street}, {line}".strip(", ") if street else line

                description = norm_space(" ".join(desc_bits)) or None
                # strip accidental address bleed from description
                if description and POSTCODE_RE.search(description):
                    description = None

                key = fold(canon)
                candidate = {
                    "name": canon,
                    "region": region,
                    "subregion": subregion,
                    "street_address": street,
                    "suburb": suburb,
                    "postcode": postcode,
                    "phone": phone,
                    "email": email,
                    "website": website,
                    "description": description,
                    "features": features,
                    "attributes": infer_attributes(features, description),
                    "source_page": idx,
                    "detail_matched": True,
                    "score": sum(
                        [
                            3 if phone else 0,
                            2 if email else 0,
                            2 if website else 0,
                            2 if street else 0,
                            1 if suburb else 0,
                            len(features),
                        ]
                    ),
                }
                prev = cards.get(key)
                if prev is None or candidate["score"] > prev["score"]:
                    cards[key] = candidate
    return cards


def extract_residential(doc: pymupdf.Document) -> list[dict[str, Any]]:
    rows: list[dict[str, Any]] = []
    start, end = RESIDENTIAL_PAGE_RANGE
    current_region = None
    region_headers = {
        "GOLD COAST", "BRISBANE", "SUNSHINE COAST", "FRASER COAST",
        "QUEENSLAND COUNTRY", "SGBR - GLADSTONE", "SGBR - CAPRICORN",
        "MACKAY", "WHITSUNDAYS", "TOWNSVILLE / NQ", "CAIRNS AND TROPICAL NORTH",
    }
    for idx in range(start, min(end + 1, doc.page_count)):
        lines = [norm_space(x) for x in doc[idx].get_text().splitlines() if norm_space(x)]
        i = 0
        while i < len(lines):
            line = lines[i]
            up = line.upper()
            if up in region_headers or up.startswith("OUTBACK"):
                current_region = line
                i += 1
                continue
            if up.startswith("RESIDENTIAL") or up.startswith("(PR)") or up in {
                "REGION - PARK", "PAGE", "QUEENSLAND CARAVAN PARKS DIRECTORY"
            }:
                i += 1
                continue
            if re.fullmatch(r"\d{1,3}", line) or len(line) < 3:
                i += 1
                continue

            name = line
            address = None
            phone = email = None
            pure = False
            directory_page = None
            j = i + 1
            while j < len(lines) and j < i + 7:
                nxt = lines[j]
                if nxt == "PR":
                    pure = True
                    j += 1
                    continue
                if re.fullmatch(r"\d{1,3}", nxt):
                    directory_page = int(nxt)
                    j += 1
                    break
                if EMAIL_RE.search(nxt) or clean_phone(nxt):
                    phone = phone or clean_phone(nxt)
                    email = email or clean_email(nxt.replace(" ", ""))
                    j += 1
                    continue
                if is_address_line(nxt) or POSTCODE_RE.search(nxt):
                    address = f"{address} {nxt}".strip() if address else nxt
                    j += 1
                    continue
                break

            street = suburb = postcode = None
            if address:
                parts = [p.strip() for p in re.split(r",\s*", address) if p.strip()]
                if parts:
                    last = parts[-1]
                    suburb, postcode = parse_suburb_postcode(last)
                    if suburb is None and POSTCODE_RE.search(address):
                        suburb, postcode = parse_suburb_postcode(address)
                    street_parts = parts[:-1] if postcode else parts
                    street = ", ".join(street_parts) if street_parts else None
                    if street is None and not postcode:
                        street = address

            rows.append(
                {
                    "name": name,
                    "region": current_region,
                    "street_address": street,
                    "suburb": suburb,
                    "postcode": postcode,
                    "phone": phone,
                    "email": email,
                    "pure_residential": pure,
                    "directory_page": directory_page,
                    "source_page": idx,
                }
            )
            i = max(j, i + 1)

    best: dict[str, dict[str, Any]] = {}
    for row in rows:
        name = row["name"]
        if fold(name) in JUNK_INDEX_NAMES:
            continue
        if looks_like_domain(name) or name.lower() in {"com", "com.au", "au"}:
            continue
        if clean_phone(name) and len(name) < 20:
            continue
        if len(name) < 4:
            continue
        # Reject address fragments accidentally captured as names.
        if re.match(r"^\d", name) or name.upper().startswith("CNR ") or name.upper().startswith("SGBR"):
            continue
        if is_address_line(name) and POSTCODE_RE.search(name):
            continue
        best[fold(name)] = row
    return list(best.values())


def category_centers_for_page(page: pymupdf.Page) -> dict[str, float]:
    """Derive category column centres from rotated headers on each trade page."""
    centers: dict[str, list[float]] = {name: [] for name in TRADE_CATEGORIES}
    data = page.get_text("dict")
    for block in data.get("blocks", []):
        if block.get("type") != 0:
            continue
        for line in block.get("lines", []):
            direction = line.get("dir") or (1, 0)
            # Rotated headers read upward on these pages.
            if abs(direction[0]) > 0.2:
                continue
            for s in line.get("spans", []):
                if not (5.0 <= float(s["size"]) <= 6.2):
                    continue
                text = norm_space(s.get("text", ""))
                if not text:
                    continue
                x_mid = (s["bbox"][0] + s["bbox"][2]) / 2.0
                low = text.casefold()
                if low == "accessories":
                    centers["Accessories"].append(x_mid)
                elif low in {"annexe &", "awnings"}:
                    centers["Annexe & Awnings"].append(x_mid)
                elif low in {"camper trailer", "sales"} and "camper" in low:
                    centers["Camper Trailer Sales"].append(x_mid)
                elif low == "caravan sales":
                    centers["Caravan Sales"].append(x_mid)
                elif low == "communications":
                    centers["Communications"].append(x_mid)
                elif low == "conversions":
                    centers["Conversions"].append(x_mid)
                elif low in {"driver instruction", "or training"}:
                    centers["Driver Instruction or Training"].append(x_mid)
                elif low == "engineering":
                    centers["Engineering"].append(x_mid)
                elif low in {"fifth wheeler"}:
                    centers["Fifth Wheeler Sales"].append(x_mid)
                elif low == "finance":
                    centers["Finance"].append(x_mid)
                elif low == "gas":
                    centers["Gas"].append(x_mid)
                elif low == "hire":
                    centers["Hire"].append(x_mid)
                elif low == "hybrid sales":
                    centers["Hybrid Sales"].append(x_mid)
                elif low == "insurance":
                    centers["Insurance"].append(x_mid)
                elif low in {"motorhome /", "campervan sales"}:
                    centers["Motorhome / Campervan Sales"].append(x_mid)
                elif low == "refrigeration":
                    centers["Refrigeration"].append(x_mid)
                elif low == "safety certificate":
                    centers["Safety Certificate"].append(x_mid)
                elif low == "service & repair":
                    centers["Service & Repair"].append(x_mid)
                elif low == "slide-on sales":
                    centers["Slide-on Sales"].append(x_mid)
                elif low == "solar products":
                    centers["Solar Products"].append(x_mid)
                elif low == "tents":
                    centers["Tents"].append(x_mid)
                elif low == "towing":
                    centers["Towing"].append(x_mid)
                elif low in {"vehicle", "modification"}:
                    centers["Vehicle Modification"].append(x_mid)
                elif low == "weight":
                    centers["Weight"].append(x_mid)
    out = dict(TRADE_CATEGORY_X)
    for name, xs in centers.items():
        if xs:
            out[name] = sum(xs) / len(xs)
    return out


def nearest_category(x: float, centers: dict[str, float] | None = None) -> str | None:
    table = centers or TRADE_CATEGORY_X
    best = None
    best_dist = 16.0
    for name, cx in table.items():
        dist = abs(x - cx)
        if dist < best_dist:
            best_dist = dist
            best = name
    return best


def extract_trade(doc: pymupdf.Document) -> list[TradeRecord]:
    records: list[TradeRecord] = []
    current_section = "Gold Coast"
    start, end = TRADE_PAGE_RANGE
    for idx in range(start, min(end + 1, doc.page_count)):
        page = doc[idx]
        spans = page_spans(page, idx)
        words = page.get_text("words")
        dots = [(w[0], w[1], w[2], w[3]) for w in words if w[4].strip() in (".", "·", "•")]
        centers = category_centers_for_page(page)

        for s in spans:
            up = s.text.upper().strip()
            if up in SECTION_NAMES and (s.x0 < 100 or s.size >= 10):
                current_section = SECTION_NAMES[up]

        left = [s for s in spans if s.x0 < 190 and s.size >= 6.5]
        left.sort(key=lambda s: s.y0)

        # Pre-scan business name candidates so dots can be assigned to the
        # nearest name row (avoids stealing marks from the row above/below).
        name_candidates: list[Span] = []
        i = 0
        while i < len(left):
            name_span = left[i]
            name = name_span.text.strip()
            up = name.upper()
            if up in SECTION_NAMES:
                i += 1
                continue
            if up in {
                "TRADE PRODUCTS & SERVICES",
                "TRADE PRODUCTS AND SERVICES",
                "QUEENSLAND CARAVAN PARKS DIRECTORY",
            } or re.fullmatch(r"\d{2,3}", name):
                i += 1
                continue
            if clean_phone(name) and len(name) < 20:
                i += 1
                continue
            if looks_like_domain(name) or re.fullmatch(r"[\d\s]+", name):
                i += 1
                continue
            # Peek ahead for phone/website evidence this is a business row.
            has_contact = False
            for peek in left[i + 1 : i + 5]:
                if peek.y0 - name_span.y0 > 48:
                    break
                if clean_phone(peek.text) or looks_like_domain(peek.text):
                    has_contact = True
                    break
            if has_contact:
                name_candidates.append(name_span)
            i += 1
        name_ys = [n.y0 for n in name_candidates]

        def dots_for_name(name_y: float) -> list[str]:
            cats: list[str] = []
            for dx0, dy0, dx1, dy1 in dots:
                # Only consider dots near this band of the page.
                if abs(dy0 - name_y) > 28:
                    continue
                # Assign to nearest business name row.
                nearest_name_y = min(name_ys, key=lambda y: abs(y - dy0)) if name_ys else name_y
                if abs(nearest_name_y - name_y) > 0.5:
                    continue
                cat = nearest_category((dx0 + dx1) / 2.0, centers)
                if cat and cat not in cats:
                    cats.append(cat)
            return [c for c in TRADE_CATEGORIES if c in cats]

        i = 0
        while i < len(left):
            name_span = left[i]
            name = name_span.text.strip()
            up = name.upper()
            if up in SECTION_NAMES:
                current_section = SECTION_NAMES[up]
                i += 1
                continue
            if up in {
                "TRADE PRODUCTS & SERVICES",
                "TRADE PRODUCTS AND SERVICES",
                "QUEENSLAND CARAVAN PARKS DIRECTORY",
            } or re.fullmatch(r"\d{2,3}", name):
                i += 1
                continue
            if clean_phone(name) and len(name) < 20:
                i += 1
                continue
            if looks_like_domain(name):
                i += 1
                continue

            phone = website = suburb = None
            j = i + 1
            while j < len(left) and left[j].y0 - name_span.y0 < 48:
                t = left[j].text.strip()
                if clean_phone(t) and not phone and len(t) < 24:
                    phone = clean_phone(t)
                elif looks_like_domain(t) and not website:
                    website = clean_website(t)
                elif t.isupper() and 2 < len(t) < 40 and not looks_like_domain(t):
                    suburb = t
                else:
                    break
                j += 1

            if not phone and not website:
                i += 1
                continue
            if re.fullmatch(r"[\d\s]+", name):
                i = max(j, i + 1)
                continue

            cats = dots_for_name(name_span.y0)

            is_mobile = current_section == "Mobile Businesses" or (suburb or "").startswith("MOBILE")
            is_online = current_section == "Online Businesses" or (suburb or "").startswith("ONLINE")
            is_interstate = current_section == "Interstate Business"
            suburb_out = None if is_mobile or is_online else (suburb.title() if suburb else None)

            records.append(
                TradeRecord(
                    external_id=slug_key(name, suburb_out or "", phone or "", current_section),
                    name=name,
                    region_section=current_section,
                    suburb=suburb_out,
                    phone=phone,
                    website=website,
                    categories=cats,
                    source_page=idx,
                    is_mobile=is_mobile,
                    is_online=is_online,
                    is_interstate=is_interstate,
                )
            )
            i = max(j, i + 1)

    best: dict[str, TradeRecord] = {}
    for rec in records:
        key = fold(rec.name) + "|" + fold(rec.suburb or "") + "|" + (rec.phone or "")
        prev = best.get(key)
        if prev is None or len(rec.categories) > len(prev.categories):
            best[key] = rec
    return list(best.values())


def extract_sidebar_repairers(doc: pymupdf.Document) -> list[TradeRecord]:
    leads: list[TradeRecord] = []
    for region, start, end in PARK_REGIONS:
        for idx in range(start, end + 1):
            if idx >= doc.page_count:
                continue
            spans = page_spans(doc[idx], idx)
            if not any("repairer" in s.text.casefold() for s in spans):
                continue
            left = [s for s in spans if s.x0 < 220 and s.y0 < 300]
            left.sort(key=lambda s: s.y0)
            i = 0
            while i < len(left) - 1:
                name = left[i].text
                nxt = left[i + 1].text
                if "repairer" in name.casefold() or "for more products" in name.casefold():
                    i += 1
                    continue
                if clean_phone(nxt) or "|" in nxt:
                    phone = clean_phone(nxt)
                    website = None
                    if "|" in nxt:
                        parts = [p.strip() for p in nxt.split("|")]
                        phone = phone or clean_phone(parts[0])
                        if len(parts) > 1:
                            website = clean_website(parts[1])
                    display = name.title() if name.isupper() else name
                    leads.append(
                        TradeRecord(
                            external_id=slug_key(display, region, phone or "", "sidebar"),
                            name=display,
                            region_section=region,
                            phone=phone,
                            website=website,
                            categories=["Service & Repair"],
                            source_page=idx,
                            source_note="Regional Caravan & RV Repairers sidebar",
                        )
                    )
                    i += 2
                    continue
                i += 1
    best: dict[str, TradeRecord] = {}
    for rec in leads:
        best[fold(rec.name) + "|" + (rec.phone or "")] = rec
    return list(best.values())


def merge_parks(
    by_name: list[dict[str, Any]],
    by_location: list[dict[str, Any]],
    details: dict[str, dict[str, Any]],
    residential: list[dict[str, Any]],
) -> list[ParkRecord]:
    loc_by_name = {fold(r["name"]): r for r in by_location}
    res_by_name = {fold(r["name"]): r for r in residential}
    parks: list[ParkRecord] = []

    # Start from authoritative name index
    for row in by_name:
        key = fold(row["name"])
        loc = loc_by_name.get(key)
        detail = details.get(key)
        res = res_by_name.get(key)

        region = None
        subregion = None
        if detail:
            region = detail.get("region")
            subregion = detail.get("subregion")
        if loc:
            region = region or loc.get("region")
            subregion = subregion or loc.get("location")
        if res:
            region = region or res.get("region")
        region = norm_region(region)

        street = (detail or {}).get("street_address") or (res or {}).get("street_address")
        suburb = (detail or {}).get("suburb") or (res or {}).get("suburb")
        postcode = (detail or {}).get("postcode") or (res or {}).get("postcode")
        phone = (detail or {}).get("phone") or (res or {}).get("phone")
        email = (detail or {}).get("email") or (res or {}).get("email")
        website = (detail or {}).get("website")
        features = list((detail or {}).get("features") or [])
        description = (detail or {}).get("description")
        attrs = dict((detail or {}).get("attributes") or {})
        pure = bool((res or {}).get("pure_residential"))
        listing_kind = "pure_residential" if pure and not detail else "caravan_holiday_park"

        parks.append(
            ParkRecord(
                external_id=slug_key(row["name"], suburb or "", postcode or "", region or ""),
                name=row["name"],
                region=region,
                subregion=subregion,
                street_address=street,
                suburb=suburb,
                postcode=postcode,
                phone=phone,
                email=email,
                website=website,
                description=description,
                features=features,
                attributes=attrs,
                source_page=(detail or {}).get("source_page") or (res or {}).get("source_page"),
                directory_page=row.get("directory_page") or (loc or {}).get("directory_page"),
                listing_kind=listing_kind,
                pure_residential=pure,
                detail_matched=bool(detail),
                raw={
                    "from_name_index": True,
                    "from_location_index": bool(loc),
                    "from_residential": bool(res),
                },
            )
        )

    # Add residential-only parks not already in tourist name index
    existing = {fold(p.name) for p in parks}
    for res in residential:
        key = fold(res["name"])
        if key in existing:
            continue
        parks.append(
            ParkRecord(
                external_id=slug_key(res["name"], res.get("suburb") or "", res.get("postcode") or "", "residential"),
                name=res["name"],
                region=norm_region(res.get("region")),
                street_address=res.get("street_address"),
                suburb=res.get("suburb"),
                postcode=res.get("postcode"),
                phone=res.get("phone"),
                email=res.get("email"),
                listing_kind="pure_residential" if res.get("pure_residential") else "residential_long_term",
                pure_residential=bool(res.get("pure_residential")),
                source_page=res.get("source_page"),
                directory_page=res.get("directory_page"),
                detail_matched=False,
                raw={"from_residential_only": True},
            )
        )
    return parks


def norm_region(value: str | None) -> str | None:
    if not value:
        return None
    mapping = {
        "GOLD COAST": "Gold Coast",
        "BRISBANE": "Brisbane",
        "SUNSHINE COAST": "Sunshine Coast",
        "QUEENSLAND COUNTRY": "Queensland Country",
        "FRASER COAST": "Fraser Coast",
        "SGBR - GLADSTONE": "Gladstone / Southern Great Barrier Reef",
        "SGBR - CAPRICORN": "Capricorn Coast / Southern Great Barrier Reef",
        "SGBR - CAPRICORN COAST": "Capricorn Coast / Southern Great Barrier Reef",
        "CAIRNS AND TROPICAL NORTH": "Cairns & Tropical North",
        "TOWNSVILLE / NQ": "Townsville / North Queensland",
        "TOWNSVILLE/NORTH QUEENSLAND": "Townsville / North Queensland",
        "OUTBACK QUEENSLAND": "Outback Queensland",
        "TRAVELLING INTERSTATE": "Travelling Interstate",
        "MACKAY": "Mackay",
        "WHITSUNDAYS": "Whitsundays",
    }
    return mapping.get(value.upper(), value)


def write_json(path: Path, data: Any) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(data, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")


def main() -> None:
    pdf = find_pdf()
    print(f"Reading {pdf}")
    doc = pymupdf.open(pdf)

    by_name = extract_parks_by_name(doc)
    by_location = extract_parks_by_location(doc)
    lookup = build_name_lookup([r["name"] for r in by_name] + [r["name"] for r in by_location])
    details = harvest_detail_cards(doc, lookup)
    residential = extract_residential(doc)
    parks = merge_parks(by_name, by_location, details, residential)
    trade = extract_trade(doc)
    sidebar = extract_sidebar_repairers(doc)

    # Merge sidebar leads not already present in trade (by phone or folded name+suburb)
    trade_keys = {
        fold(t.name) + "|" + (t.phone or "") for t in trade
    }
    trade_phones = {t.phone for t in trade if t.phone}
    for lead in sidebar:
        key = fold(lead.name) + "|" + (lead.phone or "")
        if key in trade_keys:
            continue
        if lead.phone and lead.phone in trade_phones:
            continue
        trade.append(lead)

    parks_out = [asdict(p) for p in sorted(parks, key=lambda p: ((p.region or ""), p.name))]
    trade_out = [asdict(t) for t in sorted(trade, key=lambda t: ((t.region_section or ""), t.name))]

    indexed = {fold(r["name"]) for r in by_name}
    detailed = {fold(p.name) for p in parks if p.detail_matched}
    missing_detail = sorted(indexed - detailed)

    report = {
        "source_file": str(pdf),
        "source_name": "CPAQ Explore Queensland Caravan Parks Directory 2026",
        "source_organisation": "Caravan Parks Association of Queensland Ltd",
        "source_year": 2026,
        "authorised_import": True,
        "pdf_pages": doc.page_count,
        "parks_by_name_index": len(by_name),
        "parks_by_location_index": len(by_location),
        "parks_detail_cards_matched": len(details),
        "parks_total_merged": len(parks_out),
        "parks_tourist_with_detail": sum(1 for p in parks if p.detail_matched),
        "parks_missing_detail": missing_detail,
        "parks_residential_only_added": sum(1 for p in parks if p.raw.get("from_residential_only")),
        "parks_pure_residential": sum(1 for p in parks if p.pure_residential),
        "parks_without_website": sum(1 for p in parks if not p.website),
        "parks_without_address": sum(1 for p in parks if not p.street_address and not p.suburb),
        "parks_without_phone": sum(1 for p in parks if not p.phone),
        "residential_extracted": len(residential),
        "trade_extracted": len(trade_out),
        "trade_from_matrix": sum(1 for t in trade if not t.source_note),
        "trade_from_sidebar": sum(1 for t in trade if t.source_note),
        "trade_multi_category": sum(1 for t in trade if len(t.categories) > 1),
        "trade_without_categories": sum(1 for t in trade if not t.categories),
        "trade_without_website": sum(1 for t in trade if not t.website),
        "feature_frequency": Counter(f for p in parks for f in p.features).most_common(50),
        "attribute_frequency": Counter(
            k for p in parks for k, v in p.attributes.items() if v not in (None, False)
        ).most_common(),
        "trade_category_frequency": Counter(c for t in trade for c in t.categories).most_common(),
        "regions": sorted({p.region for p in parks if p.region}),
    }

    write_json(OUT_DIR / "parks.json", parks_out)
    write_json(OUT_DIR / "trade.json", trade_out)
    write_json(OUT_DIR / "residential.json", residential)
    write_json(OUT_DIR / "sidebar-repairers.json", [asdict(s) for s in sidebar])
    write_json(OUT_DIR / "parks-by-name-index.json", by_name)
    write_json(OUT_DIR / "parks-by-location-index.json", by_location)
    write_json(OUT_DIR / "extraction-report.json", report)

    summary = {
        k: report[k]
        for k in report
        if k
        not in {
            "feature_frequency",
            "attribute_frequency",
            "trade_category_frequency",
            "parks_missing_detail",
        }
    }
    print(json.dumps(summary, indent=2))
    print("Missing detail:", missing_detail)
    print(f"Wrote outputs to {OUT_DIR}")


if __name__ == "__main__":
    main()
