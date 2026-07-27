#!/usr/bin/env python3
"""Audit ACT seed-town coordinates against the official national gazetteer.

The Composite Gazetteer is fed by the ACT naming authority and published by
Geoscience Australia under CC BY 4.0. This script is report-only: exact ACT
name matches produce reviewable proposals, while conflicts and implausibly
large moves are quarantined. It never mutates the national town seed.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import re
import tempfile
import unicodedata
import urllib.parse
import urllib.request
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
DEFAULT_REPORT = ROOT / "database" / "seeds" / "act_coordinate_quality_report.json"

SERVICE_URL = "https://services.ga.gov.au/gis/rest/services/Composite_Gazetteer_of_Australia/MapServer/0"
QUERY_URL = f"{SERVICE_URL}/query"
DATASET_URL = "https://pid.geoscience.gov.au/dataset/ga/150710"
LICENCE_URL = "https://creativecommons.org/licenses/by/4.0/"
COPYRIGHT_URL = "https://www.ga.gov.au/copyright"
ATTRIBUTION = "© Commonwealth of Australia (Geoscience Australia)"
DIVISION_SERVICE_URL = "https://services1.arcgis.com/E5n4f1VY84i0xSjy/arcgis/rest/services/ACTGOV_DIVISIONS/FeatureServer/0"
DIVISION_ITEM_URL = "https://www.arcgis.com/home/item.html?id=fab46ae9304f407499f104bb63c9eb77"
ACT_ATTRIBUTION = "© Australian Capital Territory"

TYPE_PRIORITY = {
    "POPULATION CENTRE": 1,
    "LOCALITY": 2,
}


def normalized_name(value: str) -> str:
    value = unicodedata.normalize("NFKC", value)
    value = value.replace("\u2019", "'").replace("\u2018", "'")
    return re.sub(r"\s+", " ", value.strip()).casefold()


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    radius = 6371.0088
    lat_delta = math.radians(lat2 - lat1)
    lng_delta = math.radians(lng2 - lng1)
    value = (
        math.sin(lat_delta / 2) ** 2
        + math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.sin(lng_delta / 2) ** 2
    )
    return 2 * radius * math.asin(math.sqrt(value))


def read_json_url(url: str) -> tuple[dict[str, Any], dict[str, str]]:
    request = urllib.request.Request(url, headers={"User-Agent": "Assist-Platform-ACT-coordinate-audit/1.0"})
    with urllib.request.urlopen(request, timeout=90) as response:
        headers = {key.lower(): value for key, value in response.headers.items()}
        return json.load(response), headers


def fetch_act_gazetteer(cache_path: Path) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    query = urllib.parse.urlencode({
        "where": "authority='ACT'",
        "outFields": "id,auth_id,name,feature,category,theme,latitude,longitude,authority,supply_date",
        "returnGeometry": "false",
        "resultRecordCount": "2000",
        "f": "json",
    })
    payload, headers = read_json_url(f"{QUERY_URL}?{query}")
    if "error" in payload:
        raise RuntimeError(f"Composite Gazetteer query failed: {payload['error']}")
    rows = [feature["attributes"] for feature in payload.get("features", [])]
    if len(rows) >= 2000 or not rows:
        raise RuntimeError("Unexpected ACT result count; pagination or service investigation required")
    cache_path.parent.mkdir(parents=True, exist_ok=True)
    cache_path.write_text(json.dumps(rows, ensure_ascii=False), encoding="utf-8")
    service, _ = read_json_url(f"{SERVICE_URL}?f=pjson")
    return rows, {
        "etag": headers.get("etag"),
        "last_modified": headers.get("last-modified"),
        "service_description": service.get("description"),
        "copyright_text": service.get("copyrightText"),
    }


def fetch_act_divisions(cache_path: Path) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    query = urllib.parse.urlencode({
        "where": "1=1",
        "outFields": "DIVISION_CODE,DIVISION_NAME,LAST_UPDATE_DATE,GlobalID",
        "returnGeometry": "false",
        "returnCentroid": "true",
        "outSR": "4326",
        "resultRecordCount": "1000",
        "f": "json",
    })
    payload, headers = read_json_url(f"{DIVISION_SERVICE_URL}/query?{query}")
    if "error" in payload:
        raise RuntimeError(f"ACTGOV DIVISION query failed: {payload['error']}")
    rows: list[dict[str, Any]] = []
    for feature in payload.get("features", []):
        attributes = feature["attributes"]
        centroid = feature.get("centroid") or {}
        if centroid.get("x") is None or centroid.get("y") is None:
            continue
        rows.append({
            "id": attributes.get("GlobalID"),
            "auth_id": str(attributes.get("DIVISION_CODE") or ""),
            "name": attributes.get("DIVISION_NAME"),
            "feature": "LOCALITY",
            "category": "ADMINISTRATIVE AREA",
            "latitude": centroid["y"],
            "longitude": centroid["x"],
            "authority": "ACT",
            "supply_date": attributes.get("LAST_UPDATE_DATE"),
            "source": "actgov-division",
        })
    if not rows or len(rows) >= 1000:
        raise RuntimeError("Unexpected ACTGOV DIVISION result count")
    cache_path.write_text(json.dumps(rows, ensure_ascii=False), encoding="utf-8")
    item, _ = read_json_url("https://www.arcgis.com/sharing/rest/content/items/fab46ae9304f407499f104bb63c9eb77?f=json")
    return rows, {
        "etag": headers.get("etag"),
        "last_modified": headers.get("last-modified"),
        "item_modified_epoch_ms": item.get("modified"),
        "licence_info": item.get("licenseInfo"),
        "access_information": item.get("accessInformation"),
    }


def load_source(source_json: Path | None) -> tuple[list[dict[str, Any]], dict[str, Any], Path]:
    if source_json is not None:
        return json.loads(source_json.read_text(encoding="utf-8")), {}, source_json
    cache_path = Path(tempfile.gettempdir()) / "assist-platform-act-composite-gazetteer.json"
    rows, metadata = fetch_act_gazetteer(cache_path)
    return rows, metadata, cache_path


def load_divisions(source_json: Path | None) -> tuple[list[dict[str, Any]], dict[str, Any], Path]:
    if source_json is not None:
        return json.loads(source_json.read_text(encoding="utf-8")), {}, source_json
    cache_path = Path(tempfile.gettempdir()) / "assist-platform-act-divisions.json"
    rows, metadata = fetch_act_divisions(cache_path)
    return rows, metadata, cache_path


def candidate_summary(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": row.get("id"),
        "authority_id": row.get("auth_id"),
        "feature": row["feature"],
        "category": row.get("category"),
        "latitude": float(row["latitude"]),
        "longitude": float(row["longitude"]),
        "supply_date_epoch_ms": row.get("supply_date"),
        "source": row.get("source", "composite-gazetteer"),
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--towns", type=Path, default=DEFAULT_TOWNS)
    parser.add_argument("--report", type=Path, default=DEFAULT_REPORT)
    parser.add_argument("--source-json", type=Path, help="Use an already downloaded ACT Gazetteer JSON array")
    parser.add_argument("--division-source-json", type=Path, help="Use an already downloaded ACTGOV DIVISION JSON array")
    parser.add_argument("--max-automatic-movement-km", type=float, default=25.0)
    args = parser.parse_args()

    source_rows, metadata, cache_path = load_source(args.source_json)
    division_rows, division_metadata, division_cache_path = load_divisions(args.division_source_json)
    gazetteer_index: dict[str, list[dict[str, Any]]] = defaultdict(list)
    division_index: dict[str, list[dict[str, Any]]] = defaultdict(list)
    eligible_types: Counter[str] = Counter()
    for row in source_rows:
        if str(row.get("authority", "")).upper() != "ACT":
            continue
        feature = str(row.get("feature", "")).strip().upper()
        if feature not in TYPE_PRIORITY:
            continue
        try:
            float(row["latitude"])
            float(row["longitude"])
        except (KeyError, TypeError, ValueError):
            continue
        row["feature"] = feature
        row.setdefault("source", "composite-gazetteer")
        gazetteer_index[normalized_name(str(row.get("name", "")))].append(row)
        eligible_types[feature] += 1
    for row in division_rows:
        division_index[normalized_name(str(row.get("name", "")))].append(row)

    town_data = json.loads(args.towns.read_text(encoding="utf-8"))
    act_towns = [town for town in town_data["towns"] if town.get("state") == "ACT"]
    proposals: list[dict[str, Any]] = []
    quarantined: list[dict[str, Any]] = []
    unmatched: list[str] = []

    for town in act_towns:
        name_key = normalized_name(str(town["name"]))
        # The current ACT boundary register is primary for suburbs. The
        # Composite Gazetteer supplies Canberra and other population centres,
        # and is the fallback where no exact division exists.
        candidates = division_index.get(name_key) or gazetteer_index.get(name_key, [])
        if not candidates:
            unmatched.append(str(town["name"]))
            continue
        priority = min(TYPE_PRIORITY[row["feature"]] for row in candidates)
        best = [row for row in candidates if TYPE_PRIORITY[row["feature"]] == priority]
        points = {(float(row["latitude"]), float(row["longitude"])) for row in best}
        if len(points) != 1:
            quarantined.append({
                "town": town["name"],
                "postcode": town.get("pc"),
                "reason": "multiple equal-priority official coordinates",
                "candidates": [candidate_summary(row) for row in best],
            })
            continue
        selected = sorted(best, key=lambda row: str(row.get("id") or ""))[0]
        movement = round(haversine_km(
            float(town["lat"]),
            float(town["lng"]),
            float(selected["latitude"]),
            float(selected["longitude"]),
        ), 3)
        proposal = {
            "town": town["name"],
            "postcode": town.get("pc"),
            "current": {"latitude": float(town["lat"]), "longitude": float(town["lng"])},
            "official": candidate_summary(selected),
            "movement_km": movement,
        }
        if movement > args.max_automatic_movement_km:
            quarantined.append({
                **proposal,
                "reason": "official candidate is too far from the current seed for automatic acceptance",
            })
            continue
        proposals.append(proposal)

    canberra = [row for row in proposals if normalized_name(str(row["town"])) == "canberra"]
    if len(canberra) != 1 or canberra[0]["official"]["feature"] != "POPULATION CENTRE":
        raise RuntimeError("Canberra did not resolve uniquely to the ACT population-centre record")
    if canberra[0]["official"]["authority_id"] != "ACT000118":
        raise RuntimeError("Canberra control did not resolve to authority record ACT000118")

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "scope": "ACT town-coordinate verification; report-only, no seed mutations",
        "sources": {
          "composite_gazetteer": {
            "name": "Composite Gazetteer of Australia",
            "publisher": "Geoscience Australia; ACT records supplied by the ACT naming authority",
            "dataset_url": DATASET_URL,
            "service_url": SERVICE_URL,
            "licence": "Creative Commons Attribution 4.0 International (CC BY 4.0)",
            "licence_url": LICENCE_URL,
            "copyright_url": COPYRIGHT_URL,
            "required_attribution": ATTRIBUTION,
            "service_copyright": metadata.get("copyright_text"),
            "etag": metadata.get("etag"),
            "last_modified": metadata.get("last_modified"),
            "download_cache": str(cache_path),
            "sha256": hashlib.sha256(cache_path.read_bytes()).hexdigest(),
            "act_source_records": len(source_rows),
            "latest_supply_date_epoch_ms": max((row.get("supply_date") or 0 for row in source_rows), default=None),
            "accuracy_notice": "Gazetteer points are authoritative names but are not for navigation, precise positioning, or safety-of-life use.",
          },
          "actgov_division": {
            "name": "ACTGOV DIVISION",
            "publisher": "ACT Government Geospatial Data Catalogue (ACTmapi)",
            "item_url": DIVISION_ITEM_URL,
            "service_url": DIVISION_SERVICE_URL,
            "licence": "Creative Commons Attribution 4.0 International (CC BY 4.0)",
            "licence_url": LICENCE_URL,
            "required_attribution": ACT_ATTRIBUTION,
            "item_modified_epoch_ms": division_metadata.get("item_modified_epoch_ms"),
            "etag": division_metadata.get("etag"),
            "last_modified": division_metadata.get("last_modified"),
            "download_cache": str(division_cache_path),
            "sha256": hashlib.sha256(division_cache_path.read_bytes()).hexdigest(),
            "source_records": len(division_rows),
          },
        },
        "matching_policy": {
            "name": "exact after Unicode, apostrophe, case and whitespace normalisation",
            "authority": "ACT",
            "feature_priority": TYPE_PRIORITY,
            "source_priority": ["ACTGOV DIVISION for exact suburb/locality", "Composite Gazetteer fallback and population centres"],
            "maximum_automatic_movement_km": args.max_automatic_movement_km,
            "ambiguity": "quarantine equal-priority coordinate conflicts and excessive movement",
        },
        "summary": {
            "act_seed_towns": len(act_towns),
            "authoritative_proposals": len(proposals),
            "ambiguous_quarantined": len(quarantined),
            "unmatched": len(unmatched),
            "eligible_official_records_by_feature": dict(sorted(eligible_types.items())),
            "moves_over_1_km": sum(row["movement_km"] > 1 for row in proposals),
            "moves_over_10_km": sum(row["movement_km"] > 10 for row in proposals),
        },
        "canberra_control": canberra[0],
        "proposals": sorted(proposals, key=lambda row: (str(row["town"]).casefold(), str(row.get("postcode") or ""))),
        "ambiguous": sorted(quarantined, key=lambda row: str(row["town"]).casefold()),
        "unmatched": sorted(unmatched, key=str.casefold),
    }
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({"summary": report["summary"], "canberra_control": canberra[0]}, indent=2))


if __name__ == "__main__":
    main()
