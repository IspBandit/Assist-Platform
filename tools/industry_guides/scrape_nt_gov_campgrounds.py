#!/usr/bin/env python3
"""
Scrape NT Government campground information from official pages.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: Northern Territory Government
Format: Web scraping (government campground pages)

Usage:
    python tools/industry_guides/scrape_nt_gov_campgrounds.py
    python tools/industry_guides/scrape_nt_gov_campgrounds.py --limit=20

Output:
    database/seeds/nt_campground_web_pages/campgrounds.json
    database/seeds/nt_campground_web_pages/extraction-report.json
"""
from __future__ import annotations

import argparse
import json
import re
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any
from urllib.parse import urljoin, urlparse

try:
    import requests
    from bs4 import BeautifulSoup
except ImportError:
    print("ERROR: Required libraries not installed. Run:")
    print("  pip install requests beautifulsoup4")
    exit(1)

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "database/seeds/nt_campground_web_pages"

SOURCE_KEY = "nt_campground_web_pages"
ATTRIBUTION = "Northern Territory Government campground information. Used with permission."
LICENCE = "Permission granted 2026-09-17 for VanAssist directory import"

# NT Government parks and camping pages
BASE_URL = "https://nt.gov.au"
PARKS_BASE = f"{BASE_URL}/leisure/parks-reserves"
CAMPING_SEARCH = f"{BASE_URL}/search?q=camping+campground+caravan"

PHONE_RE = re.compile(
    r"(?:\+?61[\s\-]*)?(?:\(?0\d\)?[\s\-]*)?\d{3,4}[\s\-]?\d{3,4}(?:[\s\-]?\d{3})?"
    r"|1300[\s\-]?\d{3}[\s\-]?\d{3}|1800[\s\-]?\d{3}[\s\-]?\d{3}"
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)


@dataclass
class Campground:
    """NT Government campground record."""
    external_id: str
    name: str
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


def extract_campground_urls(session: requests.Session) -> list[str]:
    """
    Find NT Government campground/camping page URLs.
    
    Strategy:
    1. Search for camping/campground pages
    2. Browse parks reserves directory
    3. Collect relevant page URLs
    """
    urls: set[str] = set()
    
    print("Finding NT Government campground pages...")
    
    try:
        # Try parks/reserves directory
        resp = session.get(PARKS_BASE, timeout=30)
        resp.raise_for_status()
        
        soup = BeautifulSoup(resp.text, "html.parser")
        
        # Find links to park/camping pages
        for link in soup.select("a[href]"):
            href = link.get("href", "")
            if not href:
                continue
            
            full_url = urljoin(BASE_URL, href)
            
            # Filter for camping/campground related pages
            if any(kw in full_url.lower() for kw in ["camp", "caravan", "stay", "park"]):
                if full_url.startswith(BASE_URL):
                    urls.add(full_url)
        
        print(f"✓ Found {len(urls)} potential campground pages")
        
    except Exception as e:
        print(f"⚠️  Failed to browse parks directory: {e}")
        print("    Trying search approach...")
    
    # Also try search
    try:
        time.sleep(1)
        resp = session.get(CAMPING_SEARCH, timeout=30)
        resp.raise_for_status()
        
        soup = BeautifulSoup(resp.text, "html.parser")
        
        for link in soup.select(".search-result a[href], article a[href]"):
            href = link.get("href", "")
            if not href:
                continue
            
            full_url = urljoin(BASE_URL, href)
            if full_url.startswith(BASE_URL):
                urls.add(full_url)
        
        print(f"✓ Search found {len(urls)} total pages")
        
    except Exception as e:
        print(f"⚠️  Search failed: {e}")
    
    return list(urls)


def scrape_campground_page(session: requests.Session, url: str) -> Campground | None:
    """
    Extract campground details from an NT Government page.
    """
    try:
        time.sleep(0.5)  # Polite delay
        resp = session.get(url, timeout=30)
        resp.raise_for_status()
        
        soup = BeautifulSoup(resp.text, "html.parser")
        
        # Extract title/name
        title_elem = soup.select_one("h1, .page-title, title")
        if not title_elem:
            return None
        
        name = normalize_whitespace(title_elem.get_text())
        
        # Skip if not a campground page
        if not any(kw in name.lower() for kw in ["camp", "caravan", "park", "stay"]):
            return None
        
        # Generate external_id from URL
        path_parts = urlparse(url).path.strip("/").split("/")
        slug = path_parts[-1] if path_parts else "unknown"
        external_id = f"nt-gov-{slug[:40]}"
        
        # Extract main content
        content_elem = soup.select_one("main, article, .content, #content")
        content_text = normalize_whitespace(content_elem.get_text()) if content_elem else ""
        
        # Extract contact details
        phone_match = PHONE_RE.search(content_text)
        email_match = EMAIL_RE.search(content_text)
        
        # Extract description (first paragraph or meta description)
        description = None
        meta_desc = soup.select_one("meta[name='description']")
        if meta_desc:
            description = meta_desc.get("content", "")[:300]
        elif first_p := soup.select_one("main p, article p"):
            description = normalize_whitespace(first_p.get_text())[:300]
        
        # Extract locality from content or URL
        locality = None
        # Common NT locations
        nt_locations = ["Darwin", "Alice Springs", "Katherine", "Tennant Creek", 
                        "Kakadu", "Litchfield", "Uluru", "Kings Canyon"]
        for loc in nt_locations:
            if loc.lower() in content_text.lower():
                locality = loc
                break
        
        # Extract facilities
        facilities: list[str] = []
        facility_keywords = {
            "toilet": "toilets",
            "shower": "showers",
            "water": "water",
            "power": "powered_sites",
            "dump": "dump_point",
            "bbq": "bbq",
            "fire": "campfires",
        }
        
        content_lower = content_text.lower()
        for keyword, facility in facility_keywords.items():
            if keyword in content_lower:
                facilities.append(facility)
        
        # Confidence based on data quality
        confidence = 60
        if phone_match:
            confidence += 10
        if locality:
            confidence += 10
        if len(facilities) > 0:
            confidence += 10
        
        return Campground(
            external_id=external_id,
            name=name,
            formatted_address=None,  # Usually not structured address
            locality=locality,
            latitude=None,  # Requires geocoding or map parsing
            longitude=None,
            phone=phone_match.group(0) if phone_match else None,
            email=email_match.group(0) if email_match else None,
            website=url,
            description=description,
            facilities=facilities,
            confidence=confidence,
            raw={
                "source_url": url,
                "scrape_date": "2026-09-17",
            },
        )
        
    except Exception as e:
        print(f"✗ Failed to scrape {url}: {e}")
        return None


def main() -> None:
    parser = argparse.ArgumentParser(description="Scrape NT Government campgrounds")
    parser.add_argument("--limit", type=int, help="Limit number of pages (testing)")
    args = parser.parse_args()
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    session = requests.Session()
    session.headers.update({
        "User-Agent": "VanAssist Data Acquisition Bot (permission granted; contact: support@vanassist.com.au)",
    })
    
    print("NT Government Campgrounds Scraper")
    print("=" * 60)
    
    # Find campground pages
    urls = extract_campground_urls(session)
    
    if not urls:
        print("\n⚠️  No campground pages found.")
        print("    NT Government website structure may have changed.")
        print("    Manual URL collection required.")
        return
    
    if args.limit:
        urls = urls[:args.limit]
    
    # Scrape each page
    campgrounds: list[Campground] = []
    for i, url in enumerate(urls, 1):
        print(f"[{i}/{len(urls)}] Scraping {url}...")
        if campground := scrape_campground_page(session, url):
            campgrounds.append(campground)
    
    # Convert to import format
    output_records = []
    for campground in campgrounds:
        output_records.append({
            "external_id": campground.external_id,
            "name": campground.name,
            "facility_type": "campground",
            "formatted_address": campground.formatted_address,
            "locality": campground.locality,
            "latitude": campground.latitude,
            "longitude": campground.longitude,
            "source_url": campground.website or BASE_URL,
            "licence": LICENCE,
            "attribution": ATTRIBUTION,
            "confidence": campground.confidence,
            "raw": asdict(campground),
        })
    
    # Write campgrounds JSON
    campgrounds_path = OUT_DIR / "campgrounds.json"
    with open(campgrounds_path, "w", encoding="utf-8") as f:
        json.dump(output_records, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    # Write extraction report
    report = {
        "source_key": SOURCE_KEY,
        "source_url": PARKS_BASE,
        "extraction_method": "web_scraping",
        "scraped_at": "2026-09-17",
        "pages_found": len(urls),
        "total_campgrounds": len(campgrounds),
        "with_coordinates": sum(1 for c in campgrounds if c.latitude and c.longitude),
        "with_phone": sum(1 for c in campgrounds if c.phone),
        "with_facilities": sum(1 for c in campgrounds if len(c.facilities) > 0),
    }
    
    report_path = OUT_DIR / "extraction-report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"\n{'=' * 60}")
    print(f"✓ Extracted {len(campgrounds)} campgrounds")
    print(f"✓ With phone: {report['with_phone']}")
    print(f"✓ With facilities: {report['with_facilities']}")
    print(f"\n✓ Output: {OUT_DIR}")
    print(f"✓ Campgrounds JSON: {campgrounds_path}")
    print(f"✓ Report: {report_path}")
    
    if args.limit:
        print(f"\n⚠️  Test mode (--limit={args.limit}). Remove limit for full scrape.")
    
    print("\n⚠️  NOTE: Coordinates require geocoding or manual map extraction.")
    print("    Run geocoding after import or extract from embedded maps.")


if __name__ == "__main__":
    main()
