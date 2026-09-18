#!/usr/bin/env python3
"""
Geocode facilities that are missing coordinates using address or locality.

Adds latitude/longitude to extracted records that don't have coordinates.
Uses Nominatim (OpenStreetMap) geocoding service.

Usage:
    python tools/industry_guides/geocode_facilities.py database/seeds/industry_big4_2026/parks.json
    python tools/industry_guides/geocode_facilities.py database/seeds/*/parks.json --output=geocoded
    
Output:
    Updates JSON file in place or creates new file with --output
"""
from __future__ import annotations

import argparse
import json
import time
from pathlib import Path
from typing import Any

try:
    import requests
except ImportError:
    print("ERROR: requests not installed. Run: pip install requests")
    exit(1)

# Nominatim API (OpenStreetMap geocoding)
NOMINATIM_URL = "https://nominatim.openstreetmap.org/search"
USER_AGENT = "VanAssist Data Acquisition (contact: support@vanassist.com.au)"


def geocode_address(address: str, locality: str | None = None, state: str | None = None) -> tuple[float, float] | None:
    """
    Geocode an address using Nominatim.
    
    Returns (latitude, longitude) or None if not found.
    """
    # Build query
    query_parts = []
    if address:
        query_parts.append(address)
    if locality:
        query_parts.append(locality)
    if state:
        query_parts.append(state)
    query_parts.append("Australia")
    
    query = ", ".join(query_parts)
    
    try:
        time.sleep(1)  # Nominatim rate limit: 1 request per second
        
        resp = requests.get(
            NOMINATIM_URL,
            params={
                "q": query,
                "format": "json",
                "limit": 1,
                "countrycodes": "au",
            },
            headers={"User-Agent": USER_AGENT},
            timeout=10,
        )
        resp.raise_for_status()
        
        results = resp.json()
        if results:
            result = results[0]
            lat = float(result["lat"])
            lon = float(result["lon"])
            return (lat, lon)
        
        return None
        
    except Exception as e:
        print(f"  ✗ Geocoding failed: {e}")
        return None


def geocode_facilities(input_path: Path, output_path: Path | None = None, force: bool = False) -> dict[str, Any]:
    """
    Geocode facilities in a JSON file.
    
    Args:
        input_path: Path to facilities JSON file
        output_path: Optional output path (default: update in place)
        force: If True, re-geocode even if coordinates exist
    
    Returns:
        Statistics dict
    """
    if not input_path.exists():
        raise RuntimeError(f"File not found: {input_path}")
    
    with open(input_path, encoding="utf-8") as f:
        facilities = json.load(f)
    
    if not isinstance(facilities, list):
        raise RuntimeError("Expected JSON array of facilities")
    
    stats = {
        "total": len(facilities),
        "already_geocoded": 0,
        "geocoded": 0,
        "failed": 0,
        "skipped": 0,
    }
    
    for i, facility in enumerate(facilities, 1):
        name = facility.get("name", "Unknown")
        
        # Skip if already has coordinates (unless force)
        has_coords = facility.get("latitude") and facility.get("longitude")
        if has_coords and not force:
            stats["already_geocoded"] += 1
            continue
        
        # Need address or locality to geocode
        address = facility.get("formatted_address")
        locality = facility.get("locality")
        
        if not address and not locality:
            print(f"[{i}/{len(facilities)}] ⚠️  {name} - no address or locality, skipping")
            stats["skipped"] += 1
            continue
        
        print(f"[{i}/{len(facilities)}] Geocoding: {name}")
        print(f"  Address: {address or locality}")
        
        # Extract state from raw data if available
        state = None
        if raw := facility.get("raw"):
            state = raw.get("state")
        
        # Geocode
        coords = geocode_address(address or "", locality, state)
        
        if coords:
            facility["latitude"] = coords[0]
            facility["longitude"] = coords[1]
            
            # Update confidence if present
            if "confidence" in facility:
                facility["confidence"] = min(100, facility["confidence"] + 10)
            
            print(f"  ✓ {coords[0]:.5f}, {coords[1]:.5f}")
            stats["geocoded"] += 1
        else:
            print(f"  ✗ Not found")
            stats["failed"] += 1
    
    # Write output
    output = output_path or input_path
    with open(output, "w", encoding="utf-8") as f:
        json.dump(facilities, f, indent=2, ensure_ascii=False)
        f.write("\n")
    
    return stats


def main() -> None:
    parser = argparse.ArgumentParser(description="Geocode facilities missing coordinates")
    parser.add_argument("input", type=Path, help="Input JSON file (or glob pattern)")
    parser.add_argument("--output", type=str, help="Output suffix (e.g., 'geocoded' creates file-geocoded.json)")
    parser.add_argument("--force", action="store_true", help="Re-geocode even if coordinates exist")
    args = parser.parse_args()
    
    input_path: Path = args.input
    
    # Handle glob patterns
    if "*" in str(input_path):
        files = list(input_path.parent.glob(input_path.name))
        if not files:
            print(f"No files matching: {input_path}")
            return
    else:
        files = [input_path]
    
    total_stats = {
        "total": 0,
        "already_geocoded": 0,
        "geocoded": 0,
        "failed": 0,
        "skipped": 0,
    }
    
    for file_path in files:
        print(f"\n{'=' * 60}")
        print(f"Processing: {file_path}")
        print('=' * 60)
        
        # Determine output path
        if args.output:
            output_path = file_path.parent / f"{file_path.stem}-{args.output}.json"
        else:
            output_path = None
        
        try:
            stats = geocode_facilities(file_path, output_path, args.force)
            
            # Accumulate stats
            for key in total_stats:
                total_stats[key] += stats[key]
            
            print(f"\n✓ Statistics for {file_path.name}:")
            print(f"  Total: {stats['total']}")
            print(f"  Already geocoded: {stats['already_geocoded']}")
            print(f"  Newly geocoded: {stats['geocoded']}")
            print(f"  Failed: {stats['failed']}")
            print(f"  Skipped (no address): {stats['skipped']}")
            
            if output_path:
                print(f"  Output: {output_path}")
            else:
                print(f"  Updated in place")
                
        except Exception as e:
            print(f"✗ Error processing {file_path}: {e}")
    
    if len(files) > 1:
        print(f"\n{'=' * 60}")
        print("Total Statistics:")
        print('=' * 60)
        print(f"Files processed: {len(files)}")
        print(f"Total facilities: {total_stats['total']}")
        print(f"Already geocoded: {total_stats['already_geocoded']}")
        print(f"Newly geocoded: {total_stats['geocoded']}")
        print(f"Failed: {total_stats['failed']}")
        print(f"Skipped: {total_stats['skipped']}")
    
    print("\n⚠️  Note: Geocoding uses Nominatim (OpenStreetMap) with 1 req/sec rate limit.")
    print("    Large datasets will take time. Consider running overnight.")


if __name__ == "__main__":
    main()
