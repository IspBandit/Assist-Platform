#!/usr/bin/env python3
"""Audit NT towns against the official Composite Gazetteer of Australia.

The Composite Gazetteer is the national repository of approved place names and
contains data supplied by the Northern Territory naming authority. This script
downloads the relevant approved NT records into the operating-system temporary
directory and writes a review report. It never changes the national town seed.
"""

from __future__ import annotations

import argparse
import json
import math
import tempfile
import unicodedata
import urllib.parse
import urllib.request
from collections import defaultdict
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
DEFAULT_REPORT = ROOT / "database" / "seeds" / "nt_coordinate_quality_report.json"
DEFAULT_SNAPSHOT = Path(tempfile.gettempdir()) / "assist-platform-nt-cga.json"

SERVICE_URL = "https://services.ga.gov.au/gis/rest/services/Composite_Gazetteer_of_Australia/MapServer"
LAYER_URL = f"{SERVICE_URL}/0"
QUERY_URL = f"{LAYER_URL}/query"
PLACE_NAMES_URL = "https://placenames.fsdf.org.au/"
NT_REGISTER_URL = "https://environment.nt.gov.au/boards-committees/place-names-committee/search-place-names"
LICENCE_URL = "https://creativecommons.org/licenses/by/4.0/"
COPYRIGHT_URL = "https://www.ga.gov.au/copyright"
SOURCE_KEY = "ga-composite-gazetteer-nt"
PAGE_SIZE = 2_000

TYPE_PRIORITY = {
    "POPULATION CENTRE": 10,
    "LOCALITY": 20,
    "TOWN SITE": 30,
    "OUTCAMP": 40,
}


def normalise_name(value: str) -> str:
    """Apply only Unicode, case and whitespace normalisation."""

    return " ".join(unicodedata.normalize("NFC", value).strip().casefold().split())


def get_json(url: str, values: dict[str, str]) -> dict[str, Any]:
    request = urllib.request.Request(
        f"{url}?{urllib.parse.urlencode(values)}",
        headers={"User-Agent": "AssistPlatformTownCoordinateAudit/1.0"},
    )
    with urllib.request.urlopen(request, timeout=120) as response:
        payload = json.load(response)
    if "error" in payload:
        raise RuntimeError(f"Composite Gazetteer query failed: {payload['error']}")
    return payload


def download_snapshot(path: Path) -> dict[str, Any]:
    features = ",".join(f"'{value}'" for value in TYPE_PRIORITY)
    records: list[dict[str, Any]] = []
    offset = 0
    while True:
        payload = get_json(
            QUERY_URL,
            {
                "f": "json",
                "where": f"authority='NT' AND feature IN ({features})",
                "outFields": (
                    "objectid,id,auth_id,name,feature,category,theme,latitude,"
                    "longitude,authority,supply_date"
                ),
                "returnGeometry": "false",
                "orderByFields": "objectid",
                "resultOffset": str(offset),
                "resultRecordCount": str(PAGE_SIZE),
            },
        )
        page = [item["attributes"] for item in payload.get("features", [])]
        records.extend(page)
        if len(page) < PAGE_SIZE and not payload.get("exceededTransferLimit", False):
            break
        if not page:
            raise RuntimeError("Composite Gazetteer pagination stopped before completion")
        offset += len(page)

    snapshot = {
        "downloaded_at": datetime.now(timezone.utc).isoformat(),
        "source_layer": LAYER_URL,
        "authority": "NT",
        "features": list(TYPE_PRIORITY),
        "record_count": len(records),
        "records": records,
    }
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(json.dumps(snapshot, separators=(",", ":")), encoding="utf-8")
    return snapshot


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


def supply_date(record: dict[str, Any]) -> str | None:
    value = record.get("supply_date")
    if value is None:
        return None
    try:
        return (
            datetime(1970, 1, 1, tzinfo=timezone.utc)
            + timedelta(milliseconds=float(value))
        ).date().isoformat()
    except (OverflowError, ValueError):
        return None


def candidate_view(record: dict[str, Any]) -> dict[str, Any]:
    return {
        "id": record["id"],
        "authority_reference": record["auth_id"],
        "feature": record["feature"],
        "category": record["category"],
        "theme": record["theme"],
        "authority": record["authority"],
        "supply_date": supply_date(record),
        "lat": round(float(record["latitude"]), 8),
        "lng": round(float(record["longitude"]), 8),
    }


def resolve_candidates(
    name: str, candidates: list[dict[str, Any]]
) -> tuple[dict[str, Any] | None, list[dict[str, Any]], int | None]:
    if not candidates:
        return None, [], None
    priority = min(TYPE_PRIORITY[item["feature"]] for item in candidates)
    best = [item for item in candidates if TYPE_PRIORITY[item["feature"]] == priority]
    points = {
        (round(float(item["latitude"]), 8), round(float(item["longitude"]), 8))
        for item in best
    }
    if len(points) != 1:
        return None, best, priority
    return min(best, key=lambda item: str(item["id"])), best, priority


def build_report(towns_path: Path, snapshot: dict[str, Any]) -> dict[str, Any]:
    seed = json.loads(towns_path.read_text(encoding="utf-8"))
    nt_towns = [town for town in seed["towns"] if town.get("state") == "NT"]
    by_name: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for record in snapshot["records"]:
        if (
            record.get("name")
            and record.get("feature") in TYPE_PRIORITY
            and record.get("latitude") is not None
            and record.get("longitude") is not None
            and record.get("authority") == "NT"
        ):
            by_name[normalise_name(str(record["name"]))].append(record)

    matches: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[dict[str, Any]] = []
    for town in sorted(nt_towns, key=lambda item: (item["name"].casefold(), item.get("pc", ""))):
        selected, best, priority = resolve_candidates(
            town["name"], by_name.get(normalise_name(town["name"]), [])
        )
        if not best:
            unmatched.append({"town": town["name"], "postcode": town.get("pc")})
            continue
        if selected is None:
            ambiguous.append(
                {
                    "town": town["name"],
                    "postcode": town.get("pc"),
                    "priority": priority,
                    "reason": "equal-priority exact-name candidates have different coordinates",
                    "candidates": [candidate_view(item) for item in best],
                }
            )
            continue

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

    darwin_selected, darwin_best, darwin_priority = resolve_candidates(
        "Darwin", by_name.get("darwin", [])
    )
    if darwin_selected is None:
        raise RuntimeError(
            f"Darwin did not resolve uniquely at priority {darwin_priority}: {len(darwin_best)} candidates"
        )
    darwin_seed = [town for town in nt_towns if normalise_name(town["name"]) == "darwin"]
    darwin = {
        "town": "Darwin",
        "official": candidate_view(darwin_selected),
        "seed_matches": darwin_seed,
        "seed_status": "PRESENT" if darwin_seed else "MISSING_CANONICAL_DARWIN_SEED",
    }

    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "state": "NT",
        "backlog_item": "DATA-001",
        "source": {
            "key": SOURCE_KEY,
            "name": "Composite Gazetteer of Australia - Northern Territory approved names",
            "authority": "Geoscience Australia / NT place-naming authority",
            "service_url": SERVICE_URL,
            "api_layer_url": LAYER_URL,
            "place_names_url": PLACE_NAMES_URL,
            "nt_register_url": NT_REGISTER_URL,
            "source_snapshot": "operating-system TEMP only; not committed",
            "downloaded_at": snapshot["downloaded_at"],
            "limitations": (
                "The official service states that positional accuracy varies and points must not "
                "be relied upon for navigation, precise positioning or safety-of-life applications."
            ),
        },
        "licence": {
            "name": "Creative Commons Attribution 4.0 International (CC BY 4.0)",
            "url": LICENCE_URL,
            "copyright_policy_url": COPYRIGHT_URL,
            "commercial_use_permitted": True,
            "lawful_use_status": "PERMITTED_WITH_ATTRIBUTION",
            "licence_basis": (
                "The ArcGIS layer identifies its copyright holder as Geoscience Australia and "
                "does not state an alternative product licence; Geoscience Australia's published "
                "default for its website material is CC BY 4.0."
            ),
            "required_attribution": (
                "Based on Composite Gazetteer of Australia data by Geoscience Australia, "
                "© Commonwealth of Australia, provided under CC BY 4.0; NT names are "
                "supplied by the Northern Territory naming authority."
            ),
        },
        "matching_policy": {
            "source_scope": "approved NT place names only",
            "name": "exact after Unicode NFC, case-folding and whitespace collapse only",
            "type_priority": TYPE_PRIORITY,
            "ambiguity_rule": (
                "choose the unique coordinate at the highest-priority feature type; quarantine "
                "equal-priority candidates at different coordinates"
            ),
            "postal_facility_aliases_inferred": False,
            "existing_seed_proximity_used_to_break_ties": False,
        },
        "counts": {
            "nt_seed_towns": len(nt_towns),
            "official_relevant_approved_records": len(snapshot["records"]),
            "official_exact_matches": len(matches),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched_held_for_review": len(unmatched),
            "matches_shifted_over_25_km": sum(
                1 for item in matches if item["coordinate_shift_km"] > 25
            ),
            "canonical_darwin_seed_records": len(darwin_seed),
        },
        "darwin_verification": darwin,
        "matches": matches,
        "ambiguous": ambiguous,
        "unmatched": unmatched,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--towns", type=Path, default=DEFAULT_TOWNS)
    parser.add_argument("--report", type=Path, default=DEFAULT_REPORT)
    parser.add_argument("--snapshot", type=Path, default=DEFAULT_SNAPSHOT)
    parser.add_argument(
        "--reuse-snapshot",
        action="store_true",
        help="Reuse --snapshot rather than downloading the current official records.",
    )
    args = parser.parse_args()

    snapshot = (
        json.loads(args.snapshot.read_text(encoding="utf-8"))
        if args.reuse_snapshot
        else download_snapshot(args.snapshot)
    )
    report = build_report(args.towns, snapshot)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({**report["counts"], "darwin": report["darwin_verification"]}, indent=2))


if __name__ == "__main__":
    main()
