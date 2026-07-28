#!/usr/bin/env python3
"""Audit Victorian town coordinates against the official VICNAMES register.

The script is deliberately report-only: it never rewrites towns_national.json.
It accepts only exact, case-insensitive names with registered place-name status
and an approved locality/place feature type. Current Vicmap-linked records are
preferred; unresolved equal-priority duplicates are quarantined.

Examples:

  python tools/enrich-vic-town-coordinates.py --download
  python tools/enrich-vic-town-coordinates.py --geojson C:/Temp/vicnames-gnr.geojson
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import tempfile
import urllib.parse
import urllib.request
from collections import defaultdict
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
REPORT = ROOT / "database" / "seeds" / "vic_coordinate_quality_report.json"
DATASET_URL = "https://discover.data.vic.gov.au/dataset/vicmap-features-vicnames-place-name-register-point"
WFS_URL = "https://opendata.maps.vic.gov.au/geoserver/wfs"
WFS_LAYER = "open-data-platform:gnr"
PAGE_SIZE = 5000

# VICNAMES feature codes are intentionally allow-listed. A matching river,
# bridge, reserve or historical feature must never become a town coordinate.
TYPE_PRIORITY = {
    "LOCB": 1,  # registered bounded locality
    "POPL": 2,  # populated place, where present
}
REGISTERED_STATUSES = {"REG", "REGISTERED"}


def normalized_name(value: object) -> str:
    """Normalize presentation only; no fuzzy, alias or punctuation matching."""
    return " ".join(str(value or "").split()).casefold()


def download_vicnames(destination: Path) -> dict[str, Any]:
    """Download the complete WFS layer in bounded pages and save one GeoJSON."""
    destination.parent.mkdir(parents=True, exist_ok=True)
    features: list[dict[str, Any]] = []
    matched: int | None = None
    start = 0

    while matched is None or start < matched:
        query = urllib.parse.urlencode({
            "service": "WFS",
            "version": "2.0.0",
            "request": "GetFeature",
            "typeNames": WFS_LAYER,
            "outputFormat": "application/json",
            "srsName": "EPSG:4326",
            "count": PAGE_SIZE,
            "startIndex": start,
        })
        request = urllib.request.Request(
            f"{WFS_URL}?{query}",
            headers={"User-Agent": "AssistPlatform-VICNAMES-Audit/1.0"},
        )
        with urllib.request.urlopen(request, timeout=120) as response:
            page = json.load(response)
        if matched is None:
            matched = int(page.get("numberMatched", 0))
        returned = int(page.get("numberReturned", len(page.get("features", []))))
        if returned == 0:
            break
        features.extend(page.get("features", []))
        start += returned

    document = {
        "type": "FeatureCollection",
        "numberMatched": matched or len(features),
        "numberReturned": len(features),
        "features": features,
    }
    destination.write_text(json.dumps(document, separators=(",", ":")), encoding="utf-8")
    return document


def coordinate(properties: dict[str, Any], feature: dict[str, Any]) -> tuple[float, float] | None:
    longitude = properties.get("longitude")
    latitude = properties.get("latitude")
    if longitude is None or latitude is None:
        geometry = feature.get("geometry") or {}
        values = geometry.get("coordinates") or []
        if len(values) >= 2:
            longitude, latitude = values[0], values[1]
    try:
        return round(float(latitude), 7), round(float(longitude), 7)
    except (TypeError, ValueError):
        return None


def candidate_summary(candidate: dict[str, Any]) -> dict[str, Any]:
    properties = candidate["properties"]
    return {
        "ufi": properties.get("ufi"),
        "place_id": properties.get("place_id"),
        "vicmap_id": properties.get("vicmap_id"),
        "feature_code": properties.get("feature_code"),
        "feature": properties.get("feature"),
        "status": properties.get("name_status"),
        "lat": candidate["lat"],
        "lng": candidate["lng"],
        "registration_date": properties.get("registration_date"),
        "gazette_reference": properties.get("gazette_reference"),
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument("--download", action="store_true", help="Download current VICNAMES WFS data to the system temp directory")
    source.add_argument("--geojson", help="Previously downloaded VICNAMES GNR GeoJSON")
    parser.add_argument("--towns", default=str(TOWNS))
    parser.add_argument("--report", default=str(REPORT))
    args = parser.parse_args()

    if args.download:
        source_path = Path(tempfile.gettempdir()) / "vicnames-gnr.geojson"
        document = download_vicnames(source_path)
    else:
        source_path = Path(args.geojson)
        document = json.loads(source_path.read_text(encoding="utf-8"))

    candidates: dict[str, list[dict[str, Any]]] = defaultdict(list)
    source_status_counts: dict[str, int] = defaultdict(int)
    source_type_counts: dict[str, int] = defaultdict(int)
    for feature in document.get("features", []):
        properties = feature.get("properties") or {}
        status_code = str(properties.get("name_status_code") or "").upper()
        status_name = str(properties.get("name_status") or "").upper()
        feature_code = str(properties.get("feature_code") or "").upper()
        source_status_counts[status_code or "UNKNOWN"] += 1
        source_type_counts[feature_code or "UNKNOWN"] += 1
        if status_code not in REGISTERED_STATUSES and status_name not in REGISTERED_STATUSES:
            continue
        if feature_code not in TYPE_PRIORITY:
            continue
        point = coordinate(properties, feature)
        name = normalized_name(properties.get("place_name"))
        if not point or not name:
            continue
        candidates[name].append({
            "properties": properties,
            "lat": point[0],
            "lng": point[1],
        })

    towns_document = json.loads(Path(args.towns).read_text(encoding="utf-8"))
    vic_towns = [town for town in towns_document["towns"] if town.get("state") == "VIC"]
    verified: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[str] = []

    for town in vic_towns:
        rows = candidates.get(normalized_name(town.get("name")), [])
        if not rows:
            unmatched.append(str(town.get("name")))
            continue

        best_type = min(TYPE_PRIORITY[str(row["properties"]["feature_code"]).upper()] for row in rows)
        best = [row for row in rows if TYPE_PRIORITY[str(row["properties"]["feature_code"]).upper()] == best_type]

        # A non-null Vicmap identifier means the registered name is currently
        # linked to the mapped feature in the official Vicmap product.
        mapped = [row for row in best if row["properties"].get("vicmap_id") is not None]
        if mapped:
            best = mapped

        unique = {(row["lat"], row["lng"]) for row in best}
        if len(best) != 1 and len(unique) != 1:
            ambiguous.append({
                "town": town["name"],
                "reason": "multiple_equal_priority_registered_places",
                "candidates": [candidate_summary(row) for row in best],
            })
            continue

        selected = best[0]
        rejected = [row for row in rows if row is not selected]
        verified.append({
            "town": town["name"],
            "lat": selected["lat"],
            "lng": selected["lng"],
            "ufi": selected["properties"].get("ufi"),
            "place_id": selected["properties"].get("place_id"),
            "vicmap_id": selected["properties"].get("vicmap_id"),
            "feature_code": selected["properties"].get("feature_code"),
            "status": selected["properties"].get("name_status"),
            "rejected_candidates": [candidate_summary(row) for row in rejected],
        })

    melbourne = next((row for row in verified if row["town"].casefold() == "melbourne"), None)
    melbourne_ambiguous = next((row for row in ambiguous if row["town"].casefold() == "melbourne"), None)
    report = {
        "source": {
            "name": "Vicmap Features - VICNAMES Place Name Register Point",
            "dataset_url": DATASET_URL,
            "wfs_url": WFS_URL,
            "wfs_layer": WFS_LAYER,
            "licence": "Creative Commons Attribution 4.0 International",
            "attribution": "Copyright (c) The State of Victoria, Department of Energy, Environment and Climate Action",
            "source_last_updated": "2026-07-03",
            "downloaded_file": source_path.name,
            "downloaded_location": "system temporary directory" if args.download else "caller-supplied file",
            "sha256": hashlib.sha256(source_path.read_bytes()).hexdigest(),
            "source_features": len(document.get("features", [])),
        },
        "method": {
            "name_match": "exact after case-folding and whitespace normalization; no fuzzy aliases",
            "accepted_status": "REGISTERED only",
            "feature_priority": TYPE_PRIORITY,
            "tie_break": "prefer a non-null current Vicmap feature identifier; quarantine remaining equal-priority duplicates",
            "mutation": "report only; towns_national.json is not changed",
        },
        "counts": {
            "vic_towns": len(vic_towns),
            "authoritative_matches": len(verified),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched": len(unmatched),
        },
        "melbourne_verification": melbourne or melbourne_ambiguous or {"town": "Melbourne", "result": "unmatched"},
        "verified": verified,
        "ambiguous": ambiguous,
        "unmatched": sorted(unmatched, key=str.casefold),
        "source_status_counts": dict(sorted(source_status_counts.items())),
        "source_feature_code_counts": dict(sorted(source_type_counts.items())),
    }
    Path(args.report).write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report["counts"] | {
        "melbourne": report["melbourne_verification"],
        "source_file": os.fspath(source_path),
    }, indent=2))


if __name__ == "__main__":
    main()
