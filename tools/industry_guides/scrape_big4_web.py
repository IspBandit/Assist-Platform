#!/usr/bin/env python3
"""
Scrape BIG4 Holiday Parks directory from their public website.

Alternative to PDF extraction - scrapes park data directly from
https://www.big4.com.au/ directory using state-by-state Selenium scraping
with incremental saves after each state.

Permission: Granted 2026-09-17 for VanAssist national directory import.
Source: BIG4 Holiday Parks of Australia
Format: Web scraping (public directory)

Usage:
    python tools/industry_guides/scrape_big4_web.py

Output:
    database/seeds/industry_big4_2026/parks.json (updated after each state)
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
    from selenium.webdriver.common.keys import Keys
    from selenium.common.exceptions import TimeoutException, WebDriverException
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

PHONE_RE = re.compile(
    r"(?:\+?61[\s\-]*)?(?:\(?0\d\)?[\s\-]*)?\d{3,4}[\s\-]?\d{3,4}(?:[\s\-]?\d{3})?"
    r"|1300[\s\-]?\d{3}[\s\-]?\d{3}|1800[\s\-]?\d{3}[\s\-]?\d{3}"
)
EMAIL_RE = re.compile(r"[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}", re.I)

STATE_CODES = ["nsw", "vic", "qld", "sa", "wa", "tas", "nt"]

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
    chrome_options.add_argument("--window-size=1920,3000")
    chrome_options.add_argument("--user-agent=VanAssist Data Acquisition Bot (permission granted; contact: support@vanassist.com.au)")
    chrome_options.add_argument("--disable-blink-features=AutomationControlled")
    chrome_options.page_load_strategy = 'normal'
    
    driver = webdriver.Chrome(options=chrome_options)
    driver.set_page_load_timeout(30)
    driver.set_script_timeout(30)
    
    return driver


def scrape_state_parks(state_code: str) -> list[str]:
    """
    Scrape a single state page to get park URLs for that state.
    
    Returns list of park detail page URLs.
    """
    state_url = f"{BASE_URL}/caravan-parks/{state_code}"
    print(f"\n{'='*60}")
    print(f"SCRAPING STATE: {state_code.upper()}")
    print(f"URL: {state_url}")
    print(f"{'='*60}")
    
    driver = None
    park_urls: list[str] = []
    
    try:
        driver = create_driver()
        driver.get(state_url)
        
        # Wait for park listings to load
        wait = WebDriverWait(driver, 15)
        wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "a[href*='/caravan-parks/']")))
        
        time.sleep(2)
        
        # Scroll to load all parks on state page
        print(f"  Scrolling to load all {state_code.upper()} parks...")
        previous_park_count = 0
        scroll_attempts = 0
        max_scrolls = 30
        stall_count = 0
        
        while scroll_attempts < max_scrolls and stall_count < 3:
            # Scroll methods
            driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
            time.sleep(1.5)
            
            body = driver.find_element(By.TAG_NAME, "body")
            for _ in range(2):
                body.send_keys(Keys.PAGE_DOWN)
                time.sleep(0.3)
            
            # Count current parks
            soup = BeautifulSoup(driver.page_source, "html.parser")
            links = soup.select("a[href*='/caravan-parks/']")
            park_count = 0
            for link in links:
                href = link.get("href", "")
                if href:
                    full_url = urljoin(BASE_URL, href)
                    path_parts = urlparse(full_url).path.strip("/").split("/")
                    if len(path_parts) >= 4:
                        park_count += 1
            
            scroll_attempts += 1
            
            if park_count > previous_park_count:
                print(f"    Scroll {scroll_attempts}: {park_count} parks loaded")
                previous_park_count = park_count
                stall_count = 0
            else:
                stall_count += 1
        
        # Final parse
        soup = BeautifulSoup(driver.page_source, "html.parser")
        
        seen_urls = set()
        for link in soup.select("a[href*='/caravan-parks/']"):
            href = link.get("href", "")
            if not href:
                continue
                
            full_url = urljoin(BASE_URL, href)
            path_parts = urlparse(full_url).path.strip("/").split("/")
            
            if len(path_parts) >= 4 and path_parts[1].lower() == state_code.lower():
                if full_url not in seen_urls:
                    seen_urls.add(full_url)
                    park_urls.append(full_url)
        
        print(f"  ✓ Found {len(park_urls)} parks in {state_code.upper()}")
        return park_urls
        
    except Exception as e:
        print(f"  ✗ Failed to fetch {state_code.upper()} parks: {e}")
        return []
    finally:
        if driver:
            try:
                driver.quit()
            except:
                pass


def scrape_park_detail_selenium(url: str, retry_count: int = 0) -> Park | None:
    """
    Scrape individual park detail page using Selenium with retry logic.
    
    Returns Park object or None if failed.
    """
    driver = None
    
    try:
        time.sleep(0.3)  # Polite crawling delay
        
        driver = create_driver()
        driver.get(url)
        
        # Wait for page to load
        wait = WebDriverWait(driver, 10)
        wait.until(EC.presence_of_element_located((By.TAG_NAME, "h1")))
        
        time.sleep(1)
        
        soup = BeautifulSoup(driver.page_source, "html.parser")
        
        # Extract park name
        name = None
        for selector in ["h1"]:
            name_elem = soup.select_one(selector)
            if name_elem:
                name = normalize_whitespace(name_elem.get_text())
                if name:
                    break
        
        if not name:
            return None
        
        # Generate stable external_id from URL slug
        slug = urlparse(url).path.strip("/").split("/")[-1]
        external_id = f"big4-{slug}"
        
        # Extract state from URL
        state = extract_state_from_url(url)
        
        # Extract address
        formatted_address = None
        locality = None
        postcode = None
        
        address_parts = []
        for p in soup.select("address p, [data-testid='address'] p, .SidebarInfoBlock_subtitle__Euh4"):
            text = normalize_whitespace(p.get_text())
            if text and len(text) > 3 and text not in address_parts:
                address_parts.append(text)
        
        if address_parts:
            formatted_address = ", ".join(address_parts[:3])
            
            for part in address_parts:
                if postcode_match := re.search(r'\b(\d{4})\b', part):
                    postcode = postcode_match.group(1)
                    if locality_match := re.search(r'([A-Za-z\s]+)\s+\d{4}', part):
                        locality = normalize_whitespace(locality_match.group(1))
        
        # Extract phone
        phone = None
        for selector in ["a[href^='tel:']", ".phone", "[data-testid='phone']"]:
            phone_elem = soup.select_one(selector)
            if phone_elem:
                phone_text = phone_elem.get("href", "") or phone_elem.get_text()
                if phone_match := PHONE_RE.search(phone_text):
                    phone = phone_match.group(0)
                    break
        
        # Extract email
        email = None
        for selector in ["a[href^='mailto:']", ".email", "[data-testid='email']"]:
            email_elem = soup.select_one(selector)
            if email_elem:
                email_text = email_elem.get("href", "").replace("mailto:", "") or email_elem.get_text()
                if email_match := EMAIL_RE.search(email_text):
                    email = email_match.group(0)
                    break
        
        # Coordinates will be None (to be geocoded)
        latitude = None
        longitude = None
        
        # Extract description
        description = None
        for selector in [".park-description", ".description", "[data-testid='description']", "p"]:
            desc_elem = soup.select_one(selector)
            if desc_elem:
                desc_text = normalize_whitespace(desc_elem.get_text())
                if len(desc_text) > 50:
                    description = desc_text[:500]
                    break
        
        # Extract facilities
        facilities: list[str] = []
        for fac_elem in soup.select(".facility, .amenity, .feature, [data-facility], .facility-item"):
            fac_text = normalize_whitespace(fac_elem.get_text())
            if fac_text and len(fac_text) < 50 and fac_text not in facilities:
                facilities.append(fac_text)
        
        # Confidence based on data completeness
        confidence = 70
        if phone:
            confidence += 10
        if formatted_address:
            confidence += 10
        if email:
            confidence += 5
        if locality and postcode:
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
        
    except (TimeoutException, WebDriverException) as e:
        if retry_count < 2:
            print(f"    ⚠ Timeout/error, retrying... (attempt {retry_count + 2}/3)")
            time.sleep(2)
            return scrape_park_detail_selenium(url, retry_count + 1)
        else:
            print(f"    ✗ Failed after 3 attempts: {type(e).__name__}")
            return None
    except Exception as e:
        print(f"    ✗ Error: {e}")
        return None
    finally:
        if driver:
            try:
                driver.quit()
            except:
                pass


def save_parks_data(parks: list[Park], final: bool = False) -> None:
    """
    Save parks data and report to JSON files.
    
    Args:
        parks: List of Park objects to save
        final: Whether this is the final save (affects logging)
    """
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    
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
        "source_url": f"{BASE_URL}/caravan-parks",
        "extraction_method": "web_scraping_selenium_state_by_state",
        "scraped_at": "2026-09-17",
        "total_parks": len(parks),
        "parks_with_coordinates": sum(1 for p in parks if p.latitude and p.longitude),
        "parks_with_phone": sum(1 for p in parks if p.phone),
        "parks_with_email": sum(1 for p in parks if p.email),
        "parks_with_address": sum(1 for p in parks if p.formatted_address),
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
    
    status = "FINAL" if final else "CHECKPOINT"
    print(f"\n  💾 {status} SAVE: {len(parks)} parks written to {parks_path}")


def main() -> None:
    parser = argparse.ArgumentParser(description="Scrape BIG4 parks directory state-by-state")
    args = parser.parse_args()
    
    print("=" * 60)
    print("BIG4 Holiday Parks Web Scraper")
    print("State-by-State with Incremental Saves")
    print("=" * 60)
    
    all_parks: list[Park] = []
    
    # Scrape each state
    for state_idx, state_code in enumerate(STATE_CODES, 1):
        print(f"\nSTATE {state_idx}/{len(STATE_CODES)}: {state_code.upper()}")
        
        try:
            # Get park URLs for this state
            park_urls = scrape_state_parks(state_code)
            
            if not park_urls:
                print(f"  ⚠️  No parks found for {state_code.upper()}, skipping...")
                continue
            
            # Scrape each park in this state
            state_parks: list[Park] = []
            for i, url in enumerate(park_urls, 1):
                park_slug = url.split('/')[-1]
                print(f"  [{i}/{len(park_urls)}] {park_slug}...")
                
                if park := scrape_park_detail_selenium(url):
                    state_parks.append(park)
                    all_parks.append(park)
                    print(f"    ✓ {park.name}")
                    if park.formatted_address:
                        print(f"      {park.formatted_address}")
            
            # Save progress after each state
            print(f"\n  ✓ Completed {state_code.upper()}: {len(state_parks)} parks scraped")
            save_parks_data(all_parks, final=False)
            
        except Exception as e:
            print(f"  ✗ ERROR in {state_code.upper()}: {e}")
            print(f"  Saving progress so far ({len(all_parks)} parks)...")
            save_parks_data(all_parks, final=False)
            continue
    
    # Final save and report
    print(f"\n{'=' * 60}")
    print("EXTRACTION COMPLETE")
    print(f"{'=' * 60}")
    
    save_parks_data(all_parks, final=True)
    
    # Print final statistics
    parks_path = OUT_DIR / "parks.json"
    report_path = OUT_DIR / "extraction-report.json"
    
    with open(report_path) as f:
        report = json.load(f)
    
    print(f"✓ Extracted {len(all_parks)} parks total")
    print(f"✓ With addresses: {report['parks_with_address']}")
    print(f"✓ With phone: {report['parks_with_phone']}")
    print(f"✓ With email: {report['parks_with_email']}")
    print(f"✓ With coordinates: {report['parks_with_coordinates']} (to be geocoded)")
    print(f"\n✓ Output: {OUT_DIR}")
    print(f"✓ Parks JSON: {parks_path}")
    print(f"✓ Report: {report_path}")
    
    print(f"\nParks by state:")
    for state, count in sorted(report["parks_by_state"].items()):
        print(f"  {state}: {count}")


if __name__ == "__main__":
    main()
