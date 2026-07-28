#!/usr/bin/env python3
"""Audit SA town coordinates against the official State Gazetteer.

The national seed is read but never modified. The current Gazetteer archive is
downloaded into the operating-system temporary directory and an exact-match
quality report is written for review. Only current ``GEOG`` records released
for public use are eligible; historical, variant, local and unapproved names
are deliberately excluded.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import math
import tempfile
import unicodedata
import urllib.request
import zipfile
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
DEFAULT_REPORT = ROOT / "database" / "seeds" / "sa_coordinate_quality_report.json"
DEFAULT_ARCHIVE = Path(tempfile.gettempdir()) / "assist-platform-sa-gazetteer.zip"

CATALOGUE_URL = "https://data.sa.gov.au/data/dataset/gazetteer"
METADATA_API_URL = "https://data.sa.gov.au/data/api/3/action/package_show?id=gazetteer"
DOWNLOAD_URL = "https://www.dptiapps.com.au/dataportal/Gazetteer_geojson.zip"
ARCHIVE_MEMBER = "GazetteerSites_GDA2020.geojson"
LICENCE_MEMBER = "License.txt"
LICENCE_URL = "https://creativecommons.org/licenses/by/3.0/au/"
EXPECTED_LICENCE_TEXT = "Creative Commons Attribution 3.0 Australia"
SOURCE_KEY = "sa-state-gazetteer"

# The official classification guide defines GEOG as a current geographical
# name under the Geographical Names Act. Population centres and bounded
# localities are preferred over less precise current geographic records.
TYPE_PRIORITY = {
    "POPL": 10,  # Population Centre
    "LOCB": 20,  # Bounded Locality
    "SUB": 30,   # Suburb/locality
    "GTWN": 40,  # Town Site
    "LOCU": 50,  # Unbounded Locality
    "LOCA": 60,  # Locality/Neighbourhood
    "SUBD": 70,  # Neighbourhood
}


def normalise_name(value: str) -> str:
    """Apply only Unicode, case and whitespace normalisation."""

    return " ".join(unicodedata.normalize("NFC", value).strip().casefold().split())


def download_archive(path: Path) -> dict[str, Any]:
    request = urllib.request.Request(
        DOWNLOAD_URL,
        headers={"User-Agent": "AssistPlatformTownCoordinateAudit/1.0"},
    )
    path.parent.mkdir(parents=True, exist_ok=True)
    digest = hashlib.sha256()
    with urllib.request.urlopen(request, timeout=300) as response, path.open("wb") as target:
        while chunk := response.read(1024 * 1024):
            digest.update(chunk)
            target.write(chunk)
        headers = response.headers
    return {
        "downloaded_at": datetime.now(timezone.utc).isoformat(),
        "content_length": path.stat().st_size,
        "last_modified": headers.get("Last-Modified"),
        "sha256": digest.hexdigest(),
    }


def existing_archive_metadata(path: Path) -> dict[str, Any]:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        while chunk := source.read(1024 * 1024):
            digest.update(chunk)
    return {
        "downloaded_at": None,
        "content_length": path.stat().st_size,
        "last_modified": None,
        "sha256": digest.hexdigest(),
    }


def load_source(path: Path) -> tuple[str, list[dict[str, Any]]]:
    with zipfile.ZipFile(path) as archive:
        licence_text = archive.read(LICENCE_MEMBER).decode("utf-8-sig").strip()
        if EXPECTED_LICENCE_TEXT not in licence_text:
            raise RuntimeError(
                "The Gazetteer archive licence is missing or changed; production audit stopped"
            )
        with archive.open(ARCHIVE_MEMBER) as source:
            payload = json.load(source)
    return licence_text, payload["features"]


def haversine_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    radius = 6_371.0088
    lat_delta = math.radians(lat2 - lat1)
    lng_delta = math.radians(lng2 - lng1)
    value = (
        math.sin(lat_delta / 2) ** 2
        + math.cos(math.radians(lat1))
        * math.cos(math.radians(lat2))
        * math.sin(lng_delta / 2) ** 2
    )
    return radius * 2 * math.asin(math.sqrt(value))


def candidate_view(feature: dict[str, Any]) -> dict[str, Any]:
    properties = feature["properties"]
    longitude, latitude = feature["geometry"]["coordinates"][:2]
    return {
        "reference": properties["recno"],
        "feature_code": properties["f_code"],
        "feature_type": properties.get("feature_type"),
        "feature_sub_type": properties.get("feature_sub_type"),
        "classification": properties.get("class"),
        "gazette_date": properties.get("gazdate"),
        "postcode": properties.get("postcode"),
        "lga": properties.get("lga"),
        "lat": round(float(latitude), 8),
        "lng": round(float(longitude), 8),
    }


def is_eligible(feature: dict[str, Any]) -> bool:
    properties = feature.get("properties", {})
    geometry = feature.get("geometry") or {}
    coordinates = geometry.get("coordinates") or []
    return (
        properties.get("class") == "GEOG"
        and properties.get("public_rel") == "Y"
        and properties.get("f_code") in TYPE_PRIORITY
        and bool(properties.get("name"))
        and geometry.get("type") == "Point"
        and len(coordinates) >= 2
        and coordinates[0] is not None
        and coordinates[1] is not None
    )


def build_report(
    towns_path: Path,
    features: list[dict[str, Any]],
    archive_metadata: dict[str, Any],
    licence_text: str,
) -> dict[str, Any]:
    seed = json.loads(towns_path.read_text(encoding="utf-8"))
    sa_towns = [town for town in seed["towns"] if town.get("state") == "SA"]
    eligible = [feature for feature in features if is_eligible(feature)]
    by_name: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for feature in eligible:
        by_name[normalise_name(str(feature["properties"]["name"]))].append(feature)

    matches: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[dict[str, Any]] = []

    for town in sorted(sa_towns, key=lambda item: (item["name"].casefold(), item.get("pc", ""))):
        candidates = by_name.get(normalise_name(town["name"]), [])
        if not candidates:
            unmatched.append({"town": town["name"], "postcode": town.get("pc")})
            continue
        best_priority = min(TYPE_PRIORITY[item["properties"]["f_code"]] for item in candidates)
        best = [
            item
            for item in candidates
            if TYPE_PRIORITY[item["properties"]["f_code"]] == best_priority
        ]
        points = {
            (
                round(float(item["geometry"]["coordinates"][1]), 8),
                round(float(item["geometry"]["coordinates"][0]), 8),
            )
            for item in best
        }
        if len(points) != 1:
            ambiguous.append(
                {
                    "town": town["name"],
                    "postcode": town.get("pc"),
                    "priority": best_priority,
                    "reason": "equal-priority exact-name candidates have different coordinates",
                    "candidates": [candidate_view(item) for item in best],
                }
            )
            continue

        selected = min(best, key=lambda item: str(item["properties"]["recno"]))
        official = candidate_view(selected)
        seed_lat = float(town["lat"])
        seed_lng = float(town["lng"])
        matches.append(
            {
                "town": town["name"],
                "postcode": town.get("pc"),
                "seed": {"lat": seed_lat, "lng": seed_lng},
                "official": official,
                "coincident_candidate_count": len(best),
                "coordinate_shift_km": round(
                    haversine_km(seed_lat, seed_lng, official["lat"], official["lng"]), 3
                ),
            }
        )

    adelaide = next((item for item in matches if normalise_name(item["town"]) == "adelaide"), None)
    if adelaide is None or adelaide["official"]["feature_code"] != "POPL":
        raise RuntimeError("Adelaide did not resolve to the official population-centre record")

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "state": "SA",
        "backlog_item": "DATA-001",
        "source": {
            "key": SOURCE_KEY,
            "name": "South Australian Place names (State Gazetteer)",
            "authority": "Government of South Australia, Department for Housing and Urban Development",
            "catalogue_url": CATALOGUE_URL,
            "metadata_api_url": METADATA_API_URL,
            "download_url": DOWNLOAD_URL,
            "archive_member": ARCHIVE_MEMBER,
            "source_archive": "operating-system TEMP only; not committed",
            **archive_metadata,
            "total_site_records": len(features),
            "limitations": (
                "Coordinates are Gazetteer point representations, not locality polygons or legal "
                "survey evidence. Exact matching does not infer matches for postal facilities or aliases."
            ),
        },
        "licence": {
            "name": "Creative Commons Attribution 3.0 Australia (CC BY 3.0 AU)",
            "url": LICENCE_URL,
            "bundled_notice": licence_text,
            "commercial_use_permitted": True,
            "lawful_use_status": "PERMITTED_WITH_ATTRIBUTION",
            "required_attribution": (
                "Contains South Australian Place names (State Gazetteer) data, "
                "© Government of South Australia, licensed under CC BY 3.0 AU."
            ),
        },
        "matching_policy": {
            "eligible_classification": "GEOG only (current geographical name under the Act)",
            "excluded_classifications": ["GEOH", "DEPT", "DEPH", "RECD", "RECH", "ABNA", "LOCL", "LOCH", "VART", "UNAP"],
            "name": "exact after Unicode NFC, case-folding and whitespace collapse only",
            "type_priority": TYPE_PRIORITY,
            "ambiguity_rule": (
                "choose the unique coordinate at the highest-priority type; quarantine "
                "equal-priority candidates at different coordinates"
            ),
            "existing_seed_proximity_used_to_break_ties": False,
        },
        "counts": {
            "sa_seed_towns": len(sa_towns),
            "official_total_site_records": len(features),
            "eligible_current_geographic_records": len(eligible),
            "official_exact_matches": len(matches),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched_held_for_review": len(unmatched),
            "matches_shifted_over_25_km": sum(
                1 for item in matches if item["coordinate_shift_km"] > 25
            ),
        },
        "adelaide_verification": adelaide,
        "matches": matches,
        "ambiguous": ambiguous,
        "unmatched": unmatched,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--towns", type=Path, default=DEFAULT_TOWNS)
    parser.add_argument("--report", type=Path, default=DEFAULT_REPORT)
    parser.add_argument("--archive", type=Path, default=DEFAULT_ARCHIVE)
    parser.add_argument(
        "--reuse-download",
        action="store_true",
        help="Reuse --archive rather than downloading the current Gazetteer archive.",
    )
    args = parser.parse_args()

    archive_metadata = (
        existing_archive_metadata(args.archive)
        if args.reuse_download
        else download_archive(args.archive)
    )
    licence_text, features = load_source(args.archive)
    report = build_report(args.towns, features, archive_metadata, licence_text)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({**report["counts"], "adelaide": report["adelaide_verification"]}, indent=2))


if __name__ == "__main__":
    main()
