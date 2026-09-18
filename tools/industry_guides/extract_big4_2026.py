#!/usr/bin/env python3
"""
Extract parks and campgrounds from BIG4 Holiday Guide 2026 PDF into
VanAssist import format.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: BIG4 Holiday Parks of Australia
Format: PDF holiday guide

Usage:
    python tools/industry_guides/extract_big4_2026.py

Output:
    database/seeds/industry_big4_2026/parks.json
    database/seeds/industry_big4_2026/extraction-report.json
"""
from __future__ import annotations

import hashlib
import json
import re
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

try:
    import pymupdf
except ImportError:
    print("ERROR: pymupdf not installed. Run: pip install pymupdf")
    exit(1)

ROOT = Path(__file__).resolve().parents[2]
PDF_PATH = ROOT / "data/sources/vanassist/industry/big4-holiday-guide-2026.pdf"
OUT_DIR = ROOT / "database/seeds/industry_big4_2026"

SOURCE_KEY = "industry_big4_holiday_guide_2026"
ATTRIBUTION = "BIG4 Holiday Guide 2026 — BIG4 Holiday Parks of Australia. Used with permission."
LICENCE = "Permission granted 2026-09-17 for VanAssist directory import"

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
POSTCODE_RE = re.compile(r"\b([2-7]\d{3})\b")


@dataclass
class Park:
    """BIG4 park record."""
    external_id: str
    name: str
    formatted_address: str | None
    locality: str | None
    state: str | None
    postcode: str | None
    latitude: float | None
    longitude: float | None
    phone: str | None
    email: str | None
    website: str | None
    description: str | None
    facilities: list[str]
    confidence: int
    raw: dict[str, Any]


def normalize_whitespace(text: str) -> str:
    """Collapse whitespace and strip."""
    return re.sub(r"\s+", " ", text).strip()


def extract_contact(text: str) -> dict[str, str | None]:
    """Extract phone, email, website from text block."""
    phones = PHONE_RE.findall(text)
    emails = EMAIL_RE.findall(text)
    urls = URL_RE.findall(text)
    
    return {
        "phone": phones[0] if phones else None,
        "email": emails[0] if emails else None,
        "website": urls[0] if urls else None,
    }


def extract_parks(doc: pymupdf.Document) -> list[Park]:
    """
    Extract all BIG4 parks from the PDF.
    
    Strategy:
    1. Scan pages for park name headings (typically bold, larger font)
    2. Extract address, contact details, description from following text
    3. Parse facilities/amenities (icons or bullet lists)
    4. Generate stable external_id from park name + state
    
    NOTE: This is a STUB. Actual implementation requires:
    - PDF structure analysis (heading detection, text blocks)
    - State/region inference from page context or explicit markers
    - Facility icon/text mapping to VanAssist facility types
    - Coordinate lookup (BIG4 website scrape or geocoding)
    """
    parks: list[Park] = []
    
    print(f"Processing {len(doc)} pages...")
    
    # PLACEHOLDER: Real extraction logic goes here
    # For now, return empty list with guidance
    
    print("⚠️  STUB EXTRACTOR: Manual implementation required.")
    print("    Required steps:")
    print("    1. Identify park listing structure (headings, text blocks)")
    print("    2. Extract name, address, contact per park")
    print("    3. Map facility icons/text to facility types")
    print("    4. Geocode addresses or scrape coordinates from BIG4 website")
    print("    5. Generate stable external_id (e.g., slug from name)")
    
    return parks


def main() -> None:
    if not PDF_PATH.exists():
        raise RuntimeError(
            f"PDF not found: {PDF_PATH}\n"
            f"Expected: data/sources/vanassist/industry/big4-holiday-guide-2026.pdf"
        )
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    print(f"Reading PDF: {PDF_PATH}")
    doc = pymupdf.open(PDF_PATH)
    
    parks = extract_parks(doc)
    
    # Convert to import format
    output_records = []
    for park in parks:
        output_records.append({
            "external_id": park.external_id,
            "name": park.name,
            "facility_type": "caravan_park",
            "formatted_address": park.formatted_address,
            "locality": park.locality,
            "latitude": park.latitude,
            "longitude": park.longitude,
            "source_url": park.website or "https://www.big4.com.au/",
            "licence": LICENCE,
            "attribution": ATTRIBUTION,
            "confidence": park.confidence,
            "raw": asdict(park),
        })
    
    # Write parks JSON
    parks_path = OUT_DIR / "parks.json"
    with open(parks_path, "w", encoding="utf-8") as f:
        json.dump(output_records, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    # Write extraction report
    report = {
        "source_key": SOURCE_KEY,
        "pdf_path": str(PDF_PATH),
        "pdf_sha256": hashlib.sha256(PDF_PATH.read_bytes()).hexdigest()[:16],
        "extracted_at": "2026-09-17",
        "total_parks": len(parks),
        "parks_with_coordinates": sum(1 for p in parks if p.latitude and p.longitude),
        "parks_with_phone": sum(1 for p in parks if p.phone),
        "parks_with_email": sum(1 for p in parks if p.email),
        "parks_with_website": sum(1 for p in parks if p.website),
    }
    
    report_path = OUT_DIR / "extraction-report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"\n✓ Extracted {len(parks)} parks")
    print(f"✓ Output: {OUT_DIR}")
    print(f"✓ Parks JSON: {parks_path}")
    print(f"✓ Report: {report_path}")
    
    if len(parks) == 0:
        print("\n⚠️  No parks extracted (stub extractor).")
        print("    Implement park extraction logic in extract_parks() function.")


if __name__ == "__main__":
    main()
