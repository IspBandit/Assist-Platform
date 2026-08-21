#!/usr/bin/env python3
"""Audit Tasmanian town coordinates against official LIST Nomenclature.

The script is report-only and never rewrites towns_national.json. It accepts
only exact, case-insensitive names for normal, official populated/locality
features. Equal-priority duplicates are quarantined for human review.

  python tools/enrich-tas-town-coordinates.py --download
  python tools/enrich-tas-town-coordinates.py --geojson C:/Temp/tas-list-nomenclature.geojson
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
REPORT = ROOT / "database" / "seeds" / "tas_coordinate_quality_report.json"
METADATA_URL = "https://www.thelist.tas.gov.au/app/content/data/geo-meta-data-record?detailRecordUID=d193cd7a-d93a-4ca8-a0a3-670929ad247a"
LAYER_URL = "https://services.thelist.tas.gov.au/arcgis/rest/services/Public/OpenDataWFS/MapServer/34"
QUERY_URL = f"{LAYER_URL}/query"
PAGE_SIZE = 1000

TYPE_PRIORITY = {
    "suburb/locality": 1,
    "town": 2,
    "village": 3,
}


def normalized_name(value: object) -> str:
    """Normalize presentation only; do not fuzzy-match or infer aliases."""
    return " ".join(str(value or "").split()).casefold()


def request_json(parameters: dict[str, object]) -> dict[str, Any]:
    query = urllib.parse.urlencode(parameters)
    request = urllib.request.Request(
        f"{QUERY_URL}?{query}",
        headers={"User-Agent": "AssistPlatform-LIST-Nomenclature-Audit/1.0"},
    )
    with urllib.request.urlopen(request, timeout=120) as response:
        return json.load(response)


def download_nomenclature(destination: Path) -> dict[str, Any]:
    """Download the complete ArcGIS layer in bounded result pages."""
    count_result = request_json({"where": "1=1", "returnCountOnly": "true", "f": "json"})
    expected = int(count_result["count"])
    features: list[dict[str, Any]] = []
    offset = 0
    while offset < expected:
        page = request_json({
            "where": "1=1",
            "outFields": "*",
            "returnGeometry": "true",
            "outSR": "4326",
            "resultOffset": offset,
            "resultRecordCount": PAGE_SIZE,
            "orderByFields": "OBJECTID ASC",
            "f": "geojson",
        })
        returned = page.get("features", [])
        if not returned:
            break
        features.extend(returned)
        offset += len(returned)

    if len(features) != expected:
        raise RuntimeError(f"Incomplete LIST download: expected {expected}, received {len(features)}")
    document = {"type": "FeatureCollection", "features": features}
    destination.parent.mkdir(parents=True, exist_ok=True)
    destination.write_text(json.dumps(document, separators=(",", ":")), encoding="utf-8")
    return document


def point(feature: dict[str, Any]) -> tuple[float, float] | None:
    properties = feature.get("properties") or {}
    latitude = properties.get("LATITUDE")
    longitude = properties.get("LONGITUDE")
    if latitude is None or longitude is None:
        coordinates = (feature.get("geometry") or {}).get("coordinates") or []
        if len(coordinates) >= 2:
            longitude, latitude = coordinates[0], coordinates[1]
    try:
        return round(float(latitude), 7), round(float(longitude), 7)
    except (TypeError, ValueError):
        return None


def candidate_summary(candidate: dict[str, Any]) -> dict[str, Any]:
    properties = candidate["properties"]
    return {
        "nom_reg_no": properties.get("NOM_REG_NO"),
        "object_id": properties.get("OBJECTID"),
        "feature_type": properties.get("FEAT_TYPE"),
        "feature_class": properties.get("FEAT_CLASS"),
        "official": properties.get("OFFICIAL"),
        "status": properties.get("STATUS"),
        "municipality": properties.get("MUNY"),
        "lat": candidate["lat"],
        "lng": candidate["lng"],
        "gazette_date": properties.get("GAZ_DATE"),
        "official_date": properties.get("OFFIC_DATE"),
        "list_guid": properties.get("LIST_GUID"),
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    source = parser.add_mutually_exclusive_group(required=True)
    source.add_argument("--download", action="store_true", help="Download current LIST Nomenclature to the system temp directory")
    source.add_argument("--geojson", help="Previously downloaded LIST Nomenclature GeoJSON")
    parser.add_argument("--towns", default=str(TOWNS))
    parser.add_argument("--report", default=str(REPORT))
    args = parser.parse_args()

    if args.download:
        source_path = Path(tempfile.gettempdir()) / "tas-list-nomenclature.geojson"
        document = download_nomenclature(source_path)
    else:
        source_path = Path(args.geojson)
        document = json.loads(source_path.read_text(encoding="utf-8"))

    candidates: dict[str, list[dict[str, Any]]] = defaultdict(list)
    source_status_counts: dict[str, int] = defaultdict(int)
    source_type_counts: dict[str, int] = defaultdict(int)
    for feature in document.get("features", []):
        properties = feature.get("properties") or {}
        status = normalized_name(properties.get("STATUS"))
        official = normalized_name(properties.get("OFFICIAL"))
        feature_type = normalized_name(properties.get("FEAT_TYPE"))
        source_status_counts[str(properties.get("STATUS") or "UNKNOWN")] += 1
        source_type_counts[str(properties.get("FEAT_TYPE") or "UNKNOWN")] += 1
        if status != "normal" or official != "official" or feature_type not in TYPE_PRIORITY:
            continue
        coordinates = point(feature)
        name = normalized_name(properties.get("FEAT_NAME"))
        if not coordinates or not name:
            continue
        candidates[name].append({"properties": properties, "lat": coordinates[0], "lng": coordinates[1]})

    towns_document = json.loads(Path(args.towns).read_text(encoding="utf-8"))
    tas_towns = [town for town in towns_document["towns"] if town.get("state") == "TAS"]
    verified: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[str] = []

    for town in tas_towns:
        rows = candidates.get(normalized_name(town.get("name")), [])
        if not rows:
            unmatched.append(str(town.get("name")))
            continue
        best_priority = min(TYPE_PRIORITY[normalized_name(row["properties"]["FEAT_TYPE"])] for row in rows)
        best = [row for row in rows if TYPE_PRIORITY[normalized_name(row["properties"]["FEAT_TYPE"])] == best_priority]
        unique = {(row["lat"], row["lng"]) for row in best}
        if len(best) != 1 and len(unique) != 1:
            ambiguous.append({
                "town": town["name"],
                "reason": "multiple_equal_priority_official_places",
                "candidates": [candidate_summary(row) for row in best],
            })
            continue
        selected = best[0]
        verified.append({
            "town": town["name"],
            "lat": selected["lat"],
            "lng": selected["lng"],
            "nom_reg_no": selected["properties"].get("NOM_REG_NO"),
            "object_id": selected["properties"].get("OBJECTID"),
            "feature_type": selected["properties"].get("FEAT_TYPE"),
            "status": selected["properties"].get("STATUS"),
            "official": selected["properties"].get("OFFICIAL"),
            "municipality": selected["properties"].get("MUNY"),
            "list_guid": selected["properties"].get("LIST_GUID"),
            "rejected_candidates": [candidate_summary(row) for row in rows if row is not selected],
        })

    hobart = next((row for row in verified if row["town"].casefold() == "hobart"), None)
    hobart_ambiguous = next((row for row in ambiguous if row["town"].casefold() == "hobart"), None)
    report = {
        "source": {
            "name": "LIST Place Names (Nomenclature)",
            "metadata_url": METADATA_URL,
            "arcgis_layer_url": LAYER_URL,
            "licence": "Creative Commons Attribution 3.0 Australia",
            "commercial_reuse": "permitted with attribution under CC BY 3.0 AU",
            "attribution": "the LIST, Copyright State of Tasmania",
            "source_revision": "2025-04-04",
            "downloaded_file": source_path.name,
            "downloaded_location": "system temporary directory" if args.download else "caller-supplied file",
            "sha256": hashlib.sha256(source_path.read_bytes()).hexdigest(),
            "source_features": len(document.get("features", [])),
        },
        "method": {
            "name_match": "exact after case-folding and whitespace normalization; no fuzzy aliases",
            "accepted_records": "Normal status and Official designation only",
            "feature_priority": TYPE_PRIORITY,
            "ambiguity_policy": "quarantine equal-priority records at different coordinates",
            "mutation": "report only; towns_national.json is not changed",
        },
        "counts": {
            "tas_towns": len(tas_towns),
            "authoritative_matches": len(verified),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched": len(unmatched),
        },
        "hobart_verification": hobart or hobart_ambiguous or {"town": "Hobart", "result": "unmatched"},
        "verified": verified,
        "ambiguous": ambiguous,
        "unmatched": sorted(unmatched, key=str.casefold),
        "source_status_counts": dict(sorted(source_status_counts.items())),
        "source_feature_type_counts": dict(sorted(source_type_counts.items())),
    }
    Path(args.report).write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report["counts"] | {
        "hobart": report["hobart_verification"],
        "source_file": os.fspath(source_path),
    }, indent=2))


if __name__ == "__main__":
    main()
