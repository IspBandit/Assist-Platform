#!/usr/bin/env python3
"""
Scrape CMCA (Campervan & Motorhome Club of Australia) dump point locations.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: Campervan & Motorhome Club of Australia
Format: Web scraping (public dump point directory/map)

Usage:
    python tools/industry_guides/scrape_cmca_dump_points.py
    python tools/industry_guides/scrape_cmca_dump_points.py --limit=50

Output:
    database/seeds/industry_cmca_dump_points/facilities.json
    database/seeds/industry_cmca_dump_points/extraction-report.json
"""
from __future__ import annotations

import argparse
import json
import re
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

try:
    import requests
    from bs4 import BeautifulSoup
except ImportError:
    print("ERROR: Required libraries not installed. Run:")
    print("  pip install requests beautifulsoup4")
    exit(1)

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "database/seeds/industry_cmca_dump_points"

SOURCE_KEY = "industry_cmca_dump_points"
ATTRIBUTION = "CMCA dump-point directory. Used with permission."
LICENCE = "Permission granted 2026-09-17 for VanAssist directory import"

# Note: Actual URL needs verification - CMCA may require login
BASE_URL = "https://www.cmca.net.au"
DUMP_POINTS_URL = f"{BASE_URL}/RV-Living/dump-points"  # Placeholder


@dataclass
class DumpPoint:
    """CMCA dump point record."""
    external_id: str
    name: str
    formatted_address: str | None
    locality: str | None
    state: str | None
    postcode: str | None
    latitude: float | None
    longitude: float | None
    access_notes: str | None
    facilities: list[str]
    confidence: int
    raw: dict[str, Any]


def normalize_whitespace(text: str) -> str:
    """Collapse whitespace and strip."""
    return re.sub(r"\s+", " ", text).strip()


def scrape_dump_points(session: requests.Session, limit: int | None = None) -> list[DumpPoint]:
    """
    Scrape CMCA dump point directory.
    
    NOTE: This is a STUB. CMCA dump points may require:
    - Member login (not publicly accessible)
    - API access (if they provide one)
    - Alternative: Use Australian government open data sources for dump points
      (already available via National Toilet Map)
    
    If CMCA data is members-only, document that in the source registry
    and rely on government open data instead.
    """
    print(f"⚠️  CMCA dump points may require member login.")
    print(f"    Checking public access at {DUMP_POINTS_URL}...")
    
    dump_points: list[DumpPoint] = []
    
    try:
        resp = session.get(DUMP_POINTS_URL, timeout=30, allow_redirects=True)
        
        if "login" in resp.url.lower() or resp.status_code == 403:
            print("✗ CMCA dump points require authentication.")
            print("   Consider using National Toilet Map (already in GREEN sources).")
            return []
        
        resp.raise_for_status()
        soup = BeautifulSoup(resp.text, "html.parser")
        
        # Parse dump point listings
        # Adjust selectors based on actual HTML structure
        for item in soup.select(".dump-point, .facility-item, article"):
            name_elem = item.select_one("h2, h3, .facility-name")
            if not name_elem:
                continue
            
            name = normalize_whitespace(name_elem.get_text())
            
            # Generate external_id
            external_id = f"cmca-{len(dump_points) + 1}"
            
            # Extract location
            addr_elem = item.select_one(".address, .location")
            formatted_address = None
            if addr_elem:
                formatted_address = normalize_whitespace(addr_elem.get_text())
            
            # Extract coordinates if available
            lat_elem = item.select_one("[data-lat], [data-latitude]")
            lon_elem = item.select_one("[data-lng], [data-longitude]")
            latitude = None
            longitude = None
            if lat_elem and lon_elem:
                try:
                    latitude = float(lat_elem.get("data-lat") or lat_elem.get("data-latitude", ""))
                    longitude = float(lon_elem.get("data-lng") or lon_elem.get("data-longitude", ""))
                except ValueError:
                    pass
            
            # Extract access notes
            notes_elem = item.select_one(".notes, .access-info, .description")
            access_notes = None
            if notes_elem:
                access_notes = normalize_whitespace(notes_elem.get_text())[:300]
            
            dump_points.append(DumpPoint(
                external_id=external_id,
                name=name,
                formatted_address=formatted_address,
                locality=None,
                state=None,
                postcode=None,
                latitude=latitude,
                longitude=longitude,
                access_notes=access_notes,
                facilities=["dump_point"],
                confidence=70 if latitude and longitude else 50,
                raw={"source": "cmca_web"},
            ))
            
            if limit and len(dump_points) >= limit:
                break
        
        print(f"✓ Found {len(dump_points)} dump points")
        return dump_points
        
    except requests.RequestException as e:
        print(f"✗ Failed to fetch dump points: {e}")
        return []


def main() -> None:
    parser = argparse.ArgumentParser(description="Scrape CMCA dump points")
    parser.add_argument("--limit", type=int, help="Limit number of points (testing)")
    args = parser.parse_args()
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    session = requests.Session()
    session.headers.update({
        "User-Agent": "VanAssist Data Acquisition Bot (permission granted; contact: support@vanassist.com.au)",
    })
    
    print("CMCA Dump Points Scraper")
    print("=" * 60)
    
    dump_points = scrape_dump_points(session, limit=args.limit)
    
    if not dump_points:
        print("\n⚠️  No dump points extracted.")
        print("    If CMCA data is members-only, use National Toilet Map instead")
        print("    (already available as GREEN source: au_national_public_toilet_map).")
        print("\n✓  National Toilet Map includes ~26,777 public facilities")
        print("    including dump points, public toilets, and fresh water.")
        return
    
    # Convert to import format
    output_records = []
    for point in dump_points:
        output_records.append({
            "external_id": point.external_id,
            "name": point.name,
            "facility_type": "dump_point",
            "formatted_address": point.formatted_address,
            "locality": point.locality,
            "latitude": point.latitude,
            "longitude": point.longitude,
            "source_url": DUMP_POINTS_URL,
            "licence": LICENCE,
            "attribution": ATTRIBUTION,
            "confidence": point.confidence,
            "raw": asdict(point),
        })
    
    # Write facilities JSON
    facilities_path = OUT_DIR / "facilities.json"
    with open(facilities_path, "w", encoding="utf-8") as f:
        json.dump(output_records, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    # Write extraction report
    report = {
        "source_key": SOURCE_KEY,
        "source_url": DUMP_POINTS_URL,
        "extraction_method": "web_scraping",
        "scraped_at": "2026-09-17",
        "total_dump_points": len(dump_points),
        "with_coordinates": sum(1 for p in dump_points if p.latitude and p.longitude),
    }
    
    report_path = OUT_DIR / "extraction-report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"\n{'=' * 60}")
    print(f"✓ Extracted {len(dump_points)} dump points")
    print(f"✓ With coordinates: {report['with_coordinates']}")
    print(f"\n✓ Output: {OUT_DIR}")
    print(f"✓ Facilities JSON: {facilities_path}")
    print(f"✓ Report: {report_path}")
    
    if args.limit:
        print(f"\n⚠️  Test mode (--limit={args.limit}). Remove limit for full scrape.")


if __name__ == "__main__":
    main()
