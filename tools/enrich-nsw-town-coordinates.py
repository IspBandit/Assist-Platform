#!/usr/bin/env python3
"""Audit NSW seed-town coordinates against the official NSW GNR.

The script is intentionally report-only. It fetches the Geographical Names
Board's Geographical Names Register through Data.NSW, exact-matches NSW town
names, applies a conservative feature-type priority, and quarantines ties. It
does not rewrite the national seed: a reviewed migration/import step must apply
accepted proposals.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import math
import re
import tempfile
import unicodedata
import urllib.request
from collections import Counter, defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
DEFAULT_REPORT = ROOT / "database" / "seeds" / "nsw_coordinate_quality_report.json"

DATASET_URL = "https://data.nsw.gov.au/data/dataset/geographical-name-register-of-nsw"
PACKAGE_API_URL = "https://data.nsw.gov.au/data/api/3/action/package_show?id=geographical-name-register-of-nsw"
CURRENT_EXPORT_URL = "https://dcok8xuap4.execute-api.ap-southeast-2.amazonaws.com/prod/public/placenames/geonames/download"
LICENCE = "Creative Commons Attribution (CC BY)"

# Administrative/populated-place features only. The lowest number wins. The
# priority makes Sydney resolve to the assigned CITY record, not the same-name
# SUBURB or recorded HISTORIC AREA. Unsupported landscape/facility types never
# become town coordinates.
TYPE_PRIORITY = {
    "CITY": 1,
    "TOWN": 2,
    "VILLAGE": 3,
    "SUBURB": 4,
    "LOCALITY": 5,
    "URBAN PLACE": 6,
}
ACCEPTED_STATUS = {"OFFICIAL ASSIGNED"}


def normalized_name(value: str) -> str:
    """Normalise presentation-only differences without performing fuzzy matching."""
    value = unicodedata.normalize("NFKC", value)
    value = value.replace("\u2019", "'").replace("\u2018", "'")
    return re.sub(r"\s+", " ", value.strip()).casefold()


def dms_to_decimal(value: str) -> float | None:
    parts = re.findall(r"-?\d+(?:\.\d+)?", value or "")
    if len(parts) < 3:
        return None
    degrees, minutes, seconds = map(float, parts[:3])
    sign = -1.0 if value.strip().startswith("-") or degrees < 0 else 1.0
    return sign * (abs(degrees) + minutes / 60.0 + seconds / 3600.0)


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


def read_json_url(url: str) -> dict[str, Any]:
    request = urllib.request.Request(url, headers={"User-Agent": "Assist-Platform-NSW-coordinate-audit/1.0"})
    with urllib.request.urlopen(request, timeout=90) as response:
        return json.load(response)


def read_gnr_csv(cache_path: Path) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    with cache_path.open(encoding="utf-8-sig", newline="") as source:
        raw_rows = list(csv.reader(source))
    header_index = next(
        (index for index, row in enumerate(raw_rows) if row and row[0] == "REFERENCE"),
        None,
    )
    if header_index is None:
        raise RuntimeError("Current NSW GNR export did not contain its expected header")
    header = raw_rows[header_index]
    rows = [dict(zip(header, row)) for row in raw_rows[header_index + 1 :] if len(row) >= len(header)]
    export_metadata = {
        "search_date": raw_rows[2][2] if len(raw_rows) > 2 and len(raw_rows[2]) > 2 else None,
    }
    return rows, export_metadata


def fetch_gnr(cache_path: Path) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    metadata = read_json_url(PACKAGE_API_URL)["result"]
    cache_path.parent.mkdir(parents=True, exist_ok=True)
    request = urllib.request.Request(
        CURRENT_EXPORT_URL,
        headers={"User-Agent": "Assist-Platform-NSW-coordinate-audit/1.0"},
    )
    with urllib.request.urlopen(request, timeout=180) as response, cache_path.open("wb") as target:
        target.write(response.read())
        metadata["export_http_last_modified"] = response.headers.get("Last-Modified")
        metadata["export_etag"] = response.headers.get("ETag")
    rows, export_metadata = read_gnr_csv(cache_path)
    metadata.update(export_metadata)
    return rows, metadata


def load_gnr(source_csv: Path | None) -> tuple[list[dict[str, Any]], dict[str, Any], Path]:
    if source_csv is not None:
        rows, export_metadata = read_gnr_csv(source_csv)
        return rows, export_metadata, source_csv
    cache_path = Path(tempfile.gettempdir()) / "assist-platform-nsw-gnr-current.csv"
    rows, metadata = fetch_gnr(cache_path)
    return rows, metadata, cache_path


def candidate_summary(row: dict[str, Any]) -> dict[str, Any]:
    return {
        "reference": str(row.get("REFERENCE") or "").strip() or None,
        "gnb_file_reference": str(row.get("GNB FILE") or "").strip() or None,
        "designation": row["DESIGNATION"].strip().upper(),
        "status": row["STATUS"].strip().upper(),
        "lga": row["LGA"].strip(),
        "latitude": row["_latitude"],
        "longitude": row["_longitude"],
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--towns", type=Path, default=DEFAULT_TOWNS)
    parser.add_argument("--report", type=Path, default=DEFAULT_REPORT)
    parser.add_argument("--source-csv", type=Path, help="Use an already downloaded current GNR CSV")
    parser.add_argument(
        "--max-automatic-movement-km",
        type=float,
        default=25.0,
        help="Quarantine larger moves for manual locality/postcode review (default: 25)",
    )
    args = parser.parse_args()

    source_rows, metadata, cache_path = load_gnr(args.source_csv)
    index: dict[str, list[dict[str, Any]]] = defaultdict(list)
    eligible_designations: Counter[str] = Counter()
    for row in source_rows:
        designation = str(row.get("DESIGNATION", "")).strip().upper()
        status = str(row.get("STATUS", "")).strip().upper()
        if designation not in TYPE_PRIORITY or not any(status.startswith(value) for value in ACCEPTED_STATUS):
            continue
        try:
            latitude = float(str(row.get("GDA2020 LAT", "")).strip())
            longitude = float(str(row.get("GDA2020 LONG", "")).strip())
        except ValueError:
            continue
        prepared = dict(row)
        prepared["_latitude"] = round(latitude, 7)
        prepared["_longitude"] = round(longitude, 7)
        index[normalized_name(str(row.get("PLACENAME", "")))].append(prepared)
        eligible_designations[designation] += 1

    town_data = json.loads(args.towns.read_text(encoding="utf-8"))
    nsw_towns = [town for town in town_data["towns"] if town.get("state") == "NSW"]
    matched: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[str] = []

    for town in nsw_towns:
        candidates = index.get(normalized_name(str(town["name"])), [])
        if not candidates:
            unmatched.append(str(town["name"]))
            continue
        priority = min(TYPE_PRIORITY[row["DESIGNATION"].strip().upper()] for row in candidates)
        best = [row for row in candidates if TYPE_PRIORITY[row["DESIGNATION"].strip().upper()] == priority]
        points = {(row["_latitude"], row["_longitude"]) for row in best}
        if len(best) != 1 and len(points) != 1:
            ambiguous.append({
                "town": town["name"],
                "postcode": town.get("pc"),
                "reason": "multiple equal-priority official coordinates",
                "candidates": [candidate_summary(row) for row in best],
            })
            continue

        selected = sorted(
            best,
            key=lambda row: (
                str(row.get("GNB FILE") or ""),
                float(row["_latitude"]),
                float(row["_longitude"]),
            ),
        )[0]
        old_latitude = float(town["lat"])
        old_longitude = float(town["lng"])
        proposal = {
            "town": town["name"],
            "postcode": town.get("pc"),
            "current": {"latitude": old_latitude, "longitude": old_longitude},
            "official": candidate_summary(selected),
            "movement_km": round(haversine_km(old_latitude, old_longitude, selected["_latitude"], selected["_longitude"]), 3),
        }
        if proposal["movement_km"] > args.max_automatic_movement_km:
            ambiguous.append({
                "town": town["name"],
                "postcode": town.get("pc"),
                "reason": "official candidate is too far from the current seed for automatic acceptance",
                "movement_km": proposal["movement_km"],
                "current": proposal["current"],
                "candidates": [candidate_summary(row) for row in best],
            })
            continue
        matched.append(proposal)

    sydney_matches = [row for row in matched if normalized_name(str(row["town"])) == "sydney"]
    sydney_ambiguous = [row for row in ambiguous if normalized_name(str(row["town"])) == "sydney"]
    if len(sydney_matches) != 1 or sydney_ambiguous:
        raise RuntimeError("Sydney did not resolve uniquely to the official assigned CITY record")
    sydney = sydney_matches[0]
    if sydney["official"]["designation"] != "CITY" or sydney["official"]["gnb_file_reference"] != "2937":
        raise RuntimeError("Sydney control record was not the current GNR CITY record (file 2937)")

    report = {
        "generated_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "scope": "NSW town-coordinate verification; report-only, no seed mutations",
        "source": {
            "name": "Geographical Name Register of NSW",
            "publisher": "Geographical Names Board of NSW",
            "dataset_url": DATASET_URL,
            "current_export_url": CURRENT_EXPORT_URL,
            "licence": LICENCE,
            "licence_url": metadata.get("license_url", "http://www.opendefinition.org/licenses/cc-by"),
            "metadata_modified": metadata.get("metadata_modified"),
            "export_search_date": metadata.get("search_date"),
            "export_http_last_modified": metadata.get("export_http_last_modified"),
            "export_etag": metadata.get("export_etag"),
            "downloaded_file": cache_path.name,
            "downloaded_location": "system temporary directory" if args.source_csv is None else "caller-supplied file",
            "sha256": hashlib.sha256(cache_path.read_bytes()).hexdigest(),
            "source_records": len(source_rows),
        },
        "matching_policy": {
            "name": "exact after Unicode, apostrophe, case and whitespace normalisation",
            "accepted_status": sorted(ACCEPTED_STATUS),
            "designation_priority": TYPE_PRIORITY,
            "ambiguity": "quarantine equal-priority candidates with different coordinates",
            "maximum_automatic_movement_km": args.max_automatic_movement_km,
        },
        "summary": {
            "nsw_seed_towns": len(nsw_towns),
            "authoritative_proposals": len(matched),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched": len(unmatched),
            "eligible_official_records_by_designation": dict(sorted(eligible_designations.items())),
            "moves_over_1_km": sum(row["movement_km"] > 1 for row in matched),
            "moves_over_10_km": sum(row["movement_km"] > 10 for row in matched),
            "moves_over_50_km": sum(row["movement_km"] > 50 for row in matched),
        },
        "sydney_control": sydney,
        "proposals": sorted(matched, key=lambda row: (str(row["town"]).casefold(), str(row.get("postcode") or ""))),
        "ambiguous": sorted(ambiguous, key=lambda row: str(row["town"]).casefold()),
        "unmatched": sorted(unmatched, key=str.casefold),
    }
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps({"summary": report["summary"], "sydney_control": sydney}, indent=2))


if __name__ == "__main__":
    main()
