#!/usr/bin/env python3
"""
Scrape BIG4 Holiday Parks directory from their public website.

Alternative to PDF extraction - scrapes park data directly from
https://www.big4.com.au/ directory using Selenium for JavaScript-rendered content.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: BIG4 Holiday Parks of Australia
Format: Web scraping (public directory)

Usage:
    python tools/industry_guides/scrape_big4_web.py
    python tools/industry_guides/scrape_big4_web.py --limit=50  # Test mode

Output:
    database/seeds/industry_big4_2026/parks.json
    database/seeds/industry_big4_2026/extraction-report.json
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
    from selenium import webdriver
    from selenium.webdriver.chrome.options import Options
    from selenium.webdriver.common.by import By
    from selenium.webdriver.support.ui import WebDriverWait
    from selenium.webdriver.support import expected_conditions as EC
except ImportError:
    print("ERROR: Required libraries not installed. Run:")
    print("  pip install requests beautifulsoup4 selenium")
    exit(1)

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "database/seeds/industry_big4_2026"

SOURCE_KEY = "industry_big4_holiday_guide_2026"
ATTRIBUTION = "BIG4 Holiday Parks of Australia directory. Used with permission."
LICENCE = "Permission granted 2026-09-17 for VanAssist directory import"
BASE_URL = "https://www.big4.com.au"
SEARCH_URL = f"{BASE_URL}/search"

PHONE_RE = re.compile(
    r"(?:\+?61[\s\-]*)?(?:\(?0\d\)?[\s\-]*)?\d{3,4}[\s\-]?\d{3,4}(?:[\s\-]?\d{3})?"
    r"|1300[\s\-]?\d{3}[\s\-]?\d{3}|1800[\s\-]?\d{3}[\s\-]?\d{3}"
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)

STATE_ABBR = {
    "new-south-wales": "NSW",
    "victoria": "VIC",
    "queensland": "QLD",
    "south-australia": "SA",
    "western-australia": "WA",
    "tasmania": "TAS",
    "northern-territory": "NT",
    "nsw": "NSW",
    "vic": "VIC",
    "qld": "QLD",
    "sa": "SA",
    "wa": "WA",
    "tas": "TAS",
    "nt": "NT",
}


@dataclass
class Park:
    """BIG4 park record from web scrape."""
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


def extract_state_from_url(url: str) -> str | None:
    """Extract state abbreviation from BIG4 park URL."""
    # URL format: /caravan-parks/{state}/{region}/{park-slug}
    parts = urlparse(url).path.strip("/").split("/")
    if len(parts) >= 2 and parts[0] == "caravan-parks":
        state_slug = parts[1].lower()
        return STATE_ABBR.get(state_slug)
    return None


def create_driver() -> webdriver.Chrome:
    """Create a headless Chrome driver for Selenium."""
    chrome_options = Options()
    chrome_options.add_argument("--headless=new")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--disable-gpu")
    chrome_options.add_argument("--window-size=1920,1080")
    chrome_options.add_argument("--user-agent=VanAssist Data Acquisition Bot (permission granted; contact: support@vanassist.com.au)")
    
    driver = webdriver.Chrome(options=chrome_options)
    return driver


def scrape_park_list_selenium(limit: int | None = None) -> list[str]:
    """
    Scrape the search page using Selenium to get all park URLs.
    
    Returns list of park detail page URLs.
    """
    print(f"Fetching park list from {SEARCH_URL} (using Selenium)...")
    
    driver = create_driver()
    park_urls: list[str] = []
    
    try:
        driver.get(SEARCH_URL)
        
        # Wait for park listings to load (up to 10 seconds)
        print("Waiting for park listings to load...")
        wait = WebDriverWait(driver, 10)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "a[href*='/caravan-parks/'][href*='/']")))
        
        # Give extra time for all listings to render
        time.sleep(2)
        
        # Get page source and parse with BeautifulSoup
        soup = BeautifulSoup(driver.page_source, "html.parser")
        
        # Find park links - looking for full park URLs (not just state/category pages)
        # Pattern: /caravan-parks/{state}/{region}/{park-slug}
        for link in soup.select("a[href*='/caravan-parks/']"):
            href = link.get("href", "")
            if not href:
                continue
                
            # Build absolute URL
            full_url = urljoin(BASE_URL, href)
            
            # Skip if already in list
            if full_url in park_urls:
                continue
            
            # Only park detail pages (not category/state pages)
            # Park pages have format: /caravan-parks/{state}/{region}/{park-slug}
            path_parts = urlparse(full_url).path.strip("/").split("/")
            if len(path_parts) >= 4:  # Must have state, region, and park slug
                park_urls.append(full_url)
                print(f"  Found: {full_url}")
                
                if limit and len(park_urls) >= limit:
                    break
        
        print(f"✓ Found {len(park_urls)} park URLs")
        return park_urls
        
    except Exception as e:
        print(f"✗ Failed to fetch park list: {e}")
        import traceback
        traceback.print_exc()
        return []
    finally:
        driver.quit()


def scrape_park_detail(session: requests.Session, url: str) -> Park | None:
    """
    Scrape individual park detail page.
    
    Extracts name, address, contact, coordinates, facilities, description.
    """
    try:
        time.sleep(0.5)  # Polite crawling delay
        resp = session.get(url, timeout=30)
        resp.raise_for_status()
        
        soup = BeautifulSoup(resp.text, "html.parser")
        
        # Extract park name - common selectors
        name = None
        for selector in ["h1", ".park-name", ".property-title", "[data-testid='park-name']"]:
            name_elem = soup.select_one(selector)
            if name_elem:
                name = normalize_whitespace(name_elem.get_text())
                if name:
                    break
        
        if not name:
            print(f"  ⚠️  Could not extract park name from {url}")
            return None
        
        # Generate stable external_id from URL slug
        slug = urlparse(url).path.strip("/").split("/")[-1]
        external_id = f"big4-{slug}"
        
        # Extract state from URL
        state = extract_state_from_url(url)
        
        # Extract address
        address_elem = soup.select_one(".park-address, .address, [itemprop='address'], [data-testid='address']")
        formatted_address = None
        locality = None
        postcode = None
        
        if address_elem:
            addr_text = normalize_whitespace(address_elem.get_text())
            formatted_address = addr_text
            
            # Try to parse locality and postcode
            # Pattern: "123 Street, Locality STATE POSTCODE"
            if match := re.search(r",\s*([^,]+)\s+([A-Z]{2,3})\s+(\d{4})$", addr_text):
                locality = match.group(1).strip()
                postcode = match.group(3)
        
        # Extract phone
        phone_elem = soup.select_one("[href^='tel:'], .phone, .contact-phone")
        phone = None
        if phone_elem:
            phone_text = phone_elem.get("href", "") or phone_elem.get_text()
            if phone_match := PHONE_RE.search(phone_text):
                phone = phone_match.group(0)
        
        # Extract email
        email_elem = soup.select_one("[href^='mailto:'], .email")
        email = None
        if email_elem:
            email_text = email_elem.get("href", "").replace("mailto:", "") or email_elem.get_text()
            if email_match := EMAIL_RE.search(email_text):
                email = email_match.group(0)
        
        # Extract coordinates from map embed or schema.org markup
        latitude = None
        longitude = None
        
        # Try schema.org geo markup
        lat_elem = soup.select_one("[itemprop='latitude']")
        lon_elem = soup.select_one("[itemprop='longitude']")
        if lat_elem and lon_elem:
            try:
                latitude = float(lat_elem.get("content", "") or lat_elem.get_text())
                longitude = float(lon_elem.get("content", "") or lon_elem.get_text())
            except ValueError:
                pass
        
        # Try data attributes
        if not latitude:
            for elem in soup.select("[data-lat], [data-latitude]"):
                try:
                    latitude = float(elem.get("data-lat") or elem.get("data-latitude", ""))
                    break
                except ValueError:
                    pass
        
        if not longitude:
            for elem in soup.select("[data-lng], [data-lon], [data-longitude]"):
                try:
                    longitude = float(elem.get("data-lng") or elem.get("data-lon") or elem.get("data-longitude", ""))
                    break
                except ValueError:
                    pass
        
        # Extract description
        desc_elem = soup.select_one(".park-description, .description, [itemprop='description'], [data-testid='description']")
        description = None
        if desc_elem:
            description = normalize_whitespace(desc_elem.get_text())[:500]
        
        # Extract facilities (icons, checkboxes, tags)
        facilities: list[str] = []
        for fac_elem in soup.select(".facility, .amenity, .feature, [data-facility], .facility-item"):
            fac_text = normalize_whitespace(fac_elem.get_text())
            if fac_text and len(fac_text) < 50:
                facilities.append(fac_text)
        
        # Confidence based on data completeness
        confidence = 70
        if latitude and longitude:
            confidence += 10
        if phone:
            confidence += 10
        if formatted_address:
            confidence += 5
        if email:
            confidence += 5
        
        return Park(
            external_id=external_id,
            name=name,
            formatted_address=formatted_address,
            locality=locality,
            state=state,
            postcode=postcode,
            latitude=latitude,
            longitude=longitude,
            phone=phone,
            email=email,
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
    parser = argparse.ArgumentParser(description="Scrape BIG4 parks directory")
    parser.add_argument("--limit", type=int, help="Limit number of parks (testing)")
    args = parser.parse_args()
    
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
    session = requests.Session()
    session.headers.update({
        "User-Agent": "VanAssist Data Acquisition Bot (permission granted; contact: support@vanassist.com.au)",
    })
    
    print("BIG4 Holiday Parks Web Scraper (Selenium)")
    print("=" * 60)
    
    # Get park URLs using Selenium
    park_urls = scrape_park_list_selenium(limit=args.limit)
    
    if not park_urls:
        print("\n⚠️  No park URLs found. Check selectors or network connection.")
        return
    
    # Scrape each park
    parks: list[Park] = []
    for i, url in enumerate(park_urls, 1):
        print(f"[{i}/{len(park_urls)}] Scraping {url}...")
        if park := scrape_park_detail(session, url):
            parks.append(park)
            print(f"  ✓ {park.name}")
    
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
            "source_url": park.website or BASE_URL,
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
        "source_url": SEARCH_URL,
        "extraction_method": "web_scraping_selenium",
        "scraped_at": "2026-09-17",
        "total_parks": len(parks),
        "parks_with_coordinates": sum(1 for p in parks if p.latitude and p.longitude),
        "parks_with_phone": sum(1 for p in parks if p.phone),
        "parks_with_email": sum(1 for p in parks if p.email),
        "parks_by_state": {},
    }
    
    # Count by state
    for park in parks:
        state = park.state or "unknown"
        report["parks_by_state"][state] = report["parks_by_state"].get(state, 0) + 1
    
    report_path = OUT_DIR / "extraction-report.json"
    with open(report_path, "w", encoding="utf-8") as f:
        json.dump(report, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    print(f"\n{'=' * 60}")
    print(f"✓ Extracted {len(parks)} parks")
    print(f"✓ With coordinates: {report['parks_with_coordinates']}")
    print(f"✓ With phone: {report['parks_with_phone']}")
    print(f"✓ With email: {report['parks_with_email']}")
    print(f"\n✓ Output: {OUT_DIR}")
    print(f"✓ Parks JSON: {parks_path}")
    print(f"✓ Report: {report_path}")
    
    if args.limit:
        print(f"\n⚠️  Test mode (--limit={args.limit}). Remove limit for full scrape.")


if __name__ == "__main__":
    main()
