#!/usr/bin/env python3
"""
Validate extracted facility data for quality and completeness.

Checks:
- Required fields present
- Data types correct
- Coordinates in valid range
- External IDs unique
- Contact details well-formed
- Ready for import

Usage:
    python tools/industry_guides/validate_extraction.py database/seeds/industry_big4_2026/parks.json
    python tools/industry_guides/validate_extraction.py database/seeds/*/*.json
"""
from __future__ import annotations

import argparse
import json
import re
from collections import Counter
from pathlib import Path
from typing import Any

PHONE_RE = re.compile(r"^\+?[\d\s\-()]{8,20}$")
EMAIL_RE = re.compile(r"^[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}$", re.I)
URL_RE = re.compile(r"^https?://", re.I)

FACILITY_TYPES = {
    "caravan_park", "campground", "dump_point", "rest_area", "fuel_station",
    "public_toilet", "boat_ramp", "accommodation", "tourist_attraction",
    "other_essential"
}


def validate_facility(facility: dict[str, Any], index: int) -> list[str]:
    """
    Validate a single facility record.
    
    Returns list of validation errors (empty if valid).
    """
    errors = []
    
    # Required fields
    if not facility.get("external_id"):
        errors.append(f"[{index}] Missing external_id")
    elif not isinstance(facility["external_id"], str):
        errors.append(f"[{index}] external_id must be string")
    
    if not facility.get("name"):
        errors.append(f"[{index}] Missing name")
    elif not isinstance(facility["name"], str):
        errors.append(f"[{index}] name must be string")
    
    if not facility.get("facility_type"):
        errors.append(f"[{index}] Missing facility_type")
    elif facility["facility_type"] not in FACILITY_TYPES:
        errors.append(f"[{index}] Invalid facility_type: {facility['facility_type']}")
    
    # Attribution fields (required for import)
    if "source_url" not in facility:
        errors.append(f"[{index}] Missing source_url")
    
    if "attribution" not in facility:
        errors.append(f"[{index}] Missing attribution")
    
    if "licence" not in facility:
        errors.append(f"[{index}] Missing licence")
    
    # Coordinates validation
    lat = facility.get("latitude")
    lon = facility.get("longitude")
    
    if lat is not None:
        if not isinstance(lat, (int, float)):
            errors.append(f"[{index}] latitude must be number")
        elif not (-90 <= lat <= 90):
            errors.append(f"[{index}] latitude out of range: {lat}")
        elif not (-44 <= lat <= -10):  # Australia latitude range
            errors.append(f"[{index}] ⚠️  latitude outside Australia: {lat}")
    
    if lon is not None:
        if not isinstance(lon, (int, float)):
            errors.append(f"[{index}] longitude must be number")
        elif not (-180 <= lon <= 180):
            errors.append(f"[{index}] longitude out of range: {lon}")
        elif not (113 <= lon <= 154):  # Australia longitude range
            errors.append(f"[{index}] ⚠️  longitude outside Australia: {lon}")
    
    # Phone validation
    phone = facility.get("phone")
    if phone and not PHONE_RE.match(str(phone)):
        errors.append(f"[{index}] ⚠️  phone format unusual: {phone}")
    
    # Email validation
    email = facility.get("email")
    if email and not EMAIL_RE.match(str(email)):
        errors.append(f"[{index}] ⚠️  email format invalid: {email}")
    
    # URL validation
    source_url = facility.get("source_url")
    if source_url and not URL_RE.match(str(source_url)):
        errors.append(f"[{index}] ⚠️  source_url should be full URL: {source_url}")
    
    # Confidence validation
    confidence = facility.get("confidence")
    if confidence is not None:
        if not isinstance(confidence, int):
            errors.append(f"[{index}] confidence must be integer")
        elif not (0 <= confidence <= 100):
            errors.append(f"[{index}] confidence out of range: {confidence}")
    
    return errors


def validate_extraction(input_path: Path) -> dict[str, Any]:
    """
    Validate an extraction JSON file.
    
    Returns validation report.
    """
    if not input_path.exists():
        raise RuntimeError(f"File not found: {input_path}")
    
    with open(input_path, encoding="utf-8") as f:
        facilities = json.load(f)
    
    if not isinstance(facilities, list):
        raise RuntimeError("Expected JSON array of facilities")
    
    report = {
        "file": str(input_path),
        "total": len(facilities),
        "valid": 0,
        "errors": [],
        "warnings": [],
        "external_ids": set(),
        "duplicate_ids": [],
        "stats": {
            "with_coordinates": 0,
            "with_phone": 0,
            "with_email": 0,
            "with_website": 0,
            "by_type": Counter(),
            "by_confidence": Counter(),
        },
    }
    
    # Validate each facility
    for i, facility in enumerate(facilities):
        errors = validate_facility(facility, i)
        
        if errors:
            report["errors"].extend(errors)
        else:
            report["valid"] += 1
        
        # Check for duplicate external_ids
        ext_id = facility.get("external_id")
        if ext_id:
            if ext_id in report["external_ids"]:
                report["duplicate_ids"].append(ext_id)
            report["external_ids"].add(ext_id)
        
        # Collect statistics
        if facility.get("latitude") and facility.get("longitude"):
            report["stats"]["with_coordinates"] += 1
        if facility.get("phone"):
            report["stats"]["with_phone"] += 1
        if facility.get("email"):
            report["stats"]["with_email"] += 1
        if facility.get("website") or facility.get("source_url"):
            report["stats"]["with_website"] += 1
        
        ftype = facility.get("facility_type", "unknown")
        report["stats"]["by_type"][ftype] += 1
        
        conf = facility.get("confidence", 0)
        if conf >= 80:
            report["stats"]["by_confidence"]["high (80+)"] += 1
        elif conf >= 60:
            report["stats"]["by_confidence"]["medium (60-79)"] += 1
        else:
            report["stats"]["by_confidence"]["low (<60)"] += 1
    
    # Remove set from report (not JSON serializable)
    del report["external_ids"]
    
    return report


def main() -> None:
    parser = argparse.ArgumentParser(description="Validate extraction data quality")
    parser.add_argument("input", type=Path, nargs="+", help="Input JSON file(s)")
    parser.add_argument("--json", action="store_true", help="Output report as JSON")
    args = parser.parse_args()
    
    all_reports = []
    
    for input_path in args.input:
        # Handle globs
        if "*" in str(input_path):
            files = list(input_path.parent.glob(input_path.name))
        else:
            files = [input_path]
        
        for file_path in files:
            try:
                report = validate_extraction(file_path)
                all_reports.append(report)
                
                if not args.json:
                    print(f"\n{'=' * 60}")
                    print(f"Validation Report: {file_path.name}")
                    print('=' * 60)
                    print(f"Total facilities: {report['total']}")
                    print(f"Valid: {report['valid']} ({report['valid']/report['total']*100:.1f}%)")
                    print(f"Errors: {len(report['errors'])}")
                    
                    if report['duplicate_ids']:
                        print(f"\n⚠️  Duplicate external_ids found: {len(report['duplicate_ids'])}")
                        for dup_id in report['duplicate_ids'][:5]:
                            print(f"  - {dup_id}")
                        if len(report['duplicate_ids']) > 5:
                            print(f"  ... and {len(report['duplicate_ids']) - 5} more")
                    
                    print(f"\nData Completeness:")
                    print(f"  With coordinates: {report['stats']['with_coordinates']} ({report['stats']['with_coordinates']/report['total']*100:.1f}%)")
                    print(f"  With phone: {report['stats']['with_phone']} ({report['stats']['with_phone']/report['total']*100:.1f}%)")
                    print(f"  With email: {report['stats']['with_email']} ({report['stats']['with_email']/report['total']*100:.1f}%)")
                    print(f"  With website: {report['stats']['with_website']} ({report['stats']['with_website']/report['total']*100:.1f}%)")
                    
                    print(f"\nBy Facility Type:")
                    for ftype, count in report['stats']['by_type'].most_common():
                        print(f"  {ftype}: {count}")
                    
                    print(f"\nBy Confidence:")
                    for level, count in sorted(report['stats']['by_confidence'].items()):
                        print(f"  {level}: {count}")
                    
                    if report['errors']:
                        print(f"\n⚠️  Validation Errors ({len(report['errors'])}):")
                        for error in report['errors'][:10]:
                            print(f"  {error}")
                        if len(report['errors']) > 10:
                            print(f"  ... and {len(report['errors']) - 10} more")
                        print("\n❌ Fix errors before importing")
                    else:
                        print("\n✓ All validations passed! Ready for import.")
                    
            except Exception as e:
                print(f"✗ Error validating {file_path}: {e}")
    
    if args.json:
        print(json.dumps(all_reports, indent=2))


if __name__ == "__main__":
    main()
