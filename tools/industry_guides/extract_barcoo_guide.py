#!/usr/bin/env python3
"""
Extract parks and facilities from Visit Barcoo Visitor Guide PDF.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: Barcoo Shire Council
Format: PDF regional visitor guide

Usage:
    python tools/industry_guides/extract_barcoo_guide.py

Output:
    database/seeds/regional_barcoo/facilities.json
    database/seeds/regional_barcoo/extraction-report.json
"""
from __future__ import annotations

import hashlib
import json
import re
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

try:
    import pymupdf
except ImportError:
    print("ERROR: pymupdf not installed. Run: pip install pymupdf")
    exit(1)

ROOT = Path(__file__).resolve().parents[2]
PDF_PATH = ROOT / "data/sources/vanassist/regional-guides/barcoo-visitor-guide.pdf"
OUT_DIR = ROOT / "database/seeds/regional_barcoo"

SOURCE_KEY = "regional_barcoo_visitor_guide"
ATTRIBUTION = "Visit Barcoo Visitor Guide — Barcoo Shire Council. Used with permission."
LICENCE = "Permission granted 2026-09-17 for VanAssist directory import"

PHONE_RE = re.compile(
    r"(?:\+?61[\s\-]*)?(?:\(?0\d\)?[\s\-]*)?\d{3,4}[\s\-]?\d{3,4}(?:[\s\-]?\d{3})?"
    r"|1300[\s\-]?\d{3}[\s\-]?\d{3}|1800[\s\-]?\d{3}[\s\-]?\d{3}"
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)


@dataclass
class Facility:
    """Facility from Barcoo visitor guide."""
    external_id: str
    name: str
    facility_type: str
    formatted_address: str | None
    locality: str | None
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


def determine_facility_type(name: str, context: str) -> str:
    """Infer facility type from name and context."""
    name_lower = name.lower()
    context_lower = context.lower()
    
    if any(kw in name_lower for kw in ["caravan", "holiday", "camping", "rv", "park"]):
        return "caravan_park"
    elif any(kw in name_lower for kw in ["dump", "waste"]):
        return "dump_point"
    elif any(kw in name_lower for kw in ["fuel", "service", "station", "bp", "shell"]):
        return "fuel_station"
    elif any(kw in context_lower for kw in ["accommodation", "motel", "hotel"]):
        return "accommodation"
    else:
        return "tourist_attraction"


def extract_facilities(doc: pymupdf.Document) -> list[Facility]:
    """
    Extract facilities from Barcoo visitor guide.
    
    Barcoo is a remote Queensland shire. Visitor guide likely contains:
    - Caravan parks / camping areas
    - Service stations / fuel stops
    - Tourist attractions
    - Accommodation options
    - Historical sites
    
    Strategy:
    1. Scan for accommodation/services sections
    2. Extract business names and contact details
    3. Most locations will be in Jundah, Stonehenge, or Windorah
    4. Generate stable IDs from facility names
    """
    facilities: list[Facility] = []
    
    print(f"Processing {len(doc)} pages...")
    
    # Barcoo main towns
    known_towns = ["Jundah", "Stonehenge", "Windorah", "Barcoo"]
    current_town = None
    
    for page_num, page in enumerate(doc, 1):
        text = page.get_text()
        
        # Detect town/section headers
        for town in known_towns:
            if re.search(rf"\b{town}\b", text, re.I):
                current_town = town
                print(f"  Town: {current_town}")
                break
        
        # Look for business listings
        # Pattern: Business Name followed by address/contact
        blocks = page.get_text("blocks")
        
        for i, block in enumerate(blocks):
            block_text = normalize_whitespace(block[4])  # block[4] is text
            
            # Skip if too short or looks like paragraph text
            if len(block_text) < 10 or len(block_text) > 200:
                continue
            
            # Look for capitalized names (potential businesses)
            if not re.match(r"^[A-Z]", block_text):
                continue
            
            # Check next few blocks for contact info
            context = block_text
            for j in range(i + 1, min(i + 4, len(blocks))):
                context += " " + normalize_whitespace(blocks[j][4])
            
            # Must have at least phone or street address
            has_phone = bool(PHONE_RE.search(context))
            has_address = bool(re.search(r"\d+\s+[A-Za-z].*(?:Street|St|Road|Rd|Drive|Dr|Avenue|Ave|Highway|Hwy)", context))
            
            if not (has_phone or has_address):
                continue
            
            name = block_text.split("\n")[0][:100]  # First line as name
            
            # Extract contact details
            phone_match = PHONE_RE.search(context)
            email_match = EMAIL_RE.search(context)
            
            # Generate external_id
            slug = re.sub(r"[^a-z0-9]+", "-", name.lower()).strip("-")
            external_id = f"barcoo-{slug[:40]}"
            
            # Skip if we already have this one
            if any(f.external_id == external_id for f in facilities):
                continue
            
            facility_type = determine_facility_type(name, context)
            
            facilities.append(Facility(
                external_id=external_id,
                name=name,
                facility_type=facility_type,
                formatted_address=None,  # Extract from context if pattern matches
                locality=current_town or "Barcoo Shire",
                latitude=None,  # Requires geocoding
                longitude=None,
                phone=phone_match.group(0) if phone_match else None,
                email=email_match.group(0) if email_match else None,
                website=None,
                description=None,
                facilities=[],
                confidence=50,  # Lower confidence without coordinates
                raw={
                    "town": current_town,
                    "page": page_num,
                    "context_snippet": context[:200],
                },
            ))
    
    print(f"✓ Extracted {len(facilities)} potential facilities")
    
    if len(facilities) == 0:
        print("\n⚠️  No facilities extracted. Manual implementation required:")
        print("    1. Open PDF and identify listing format")
        print("    2. Update extraction logic for actual layout")
        print("    3. Add town/section detection patterns")
    
    return facilities


def main() -> None:
    if not PDF_PATH.exists():
        print(f"⚠️  PDF not found: {PDF_PATH}")
        print("    This PDF is listed as archived but not yet in the repository.")
        print("    Once obtained, place it at:")
        print(f"    {PDF_PATH}")
        return
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    print(f"Reading PDF: {PDF_PATH}")
    doc = pymupdf.open(PDF_PATH)
    
    facilities = extract_facilities(doc)
    
    # Convert to import format
    output_records = []
    for facility in facilities:
        output_records.append({
            "external_id": facility.external_id,
            "name": facility.name,
            "facility_type": facility.facility_type,
            "formatted_address": facility.formatted_address,
            "locality": facility.locality,
            "latitude": facility.latitude,
            "longitude": facility.longitude,
            "source_url": "https://www.barcoo.qld.gov.au/council-services/visitor-information-centres",
            "licence": LICENCE,
            "attribution": ATTRIBUTION,
            "confidence": facility.confidence,
            "raw": asdict(facility),
        })
    
    # Write facilities JSON
    facilities_path = OUT_DIR / "facilities.json"
    with open(facilities_path, "w", encoding="utf-8") as f:
        json.dump(output_records, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    # Write extraction report
    report = {
        "source_key": SOURCE_KEY,
        "pdf_path": str(PDF_PATH),
        "pdf_sha256": hashlib.sha256(PDF_PATH.read_bytes()).hexdigest()[:16] if PDF_PATH.exists() else None,
        "extracted_at": "2026-09-17",
        "total_facilities": len(facilities),
        "by_type": {},
        "with_coordinates": sum(1 for f in facilities if f.latitude and f.longitude),
        "with_phone": sum(1 for f in facilities if f.phone),
    }
    
    # Count by type
    for facility in facilities:
        ftype = facility.facility_type
        report["by_type"][ftype] = report["by_type"].get(ftype, 0) + 1
    
    report_path = OUT_DIR / "extraction-report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"\n✓ Output: {OUT_DIR}")
    print(f"✓ Facilities JSON: {facilities_path}")
    print(f"✓ Report: {report_path}")


if __name__ == "__main__":
    main()
