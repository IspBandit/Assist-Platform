#!/usr/bin/env python3
"""Audit WA town coordinates against Landgate's official GEONOMA register.

This script is intentionally read-only with respect to ``towns_national.json``.
It downloads the current official source into the operating-system temporary
directory and writes an audit report containing proposed matches, quarantined
ambiguities and unmatched seed names.

Landgate's public feed is subject to a personal/non-commercial licence unless
Landgate grants other terms in writing. The report therefore MUST NOT be used
to publish GEONOMA-derived coordinates in this commercial product until a
suitable licence has been obtained and recorded.
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
DEFAULT_REPORT = ROOT / "database" / "seeds" / "wa_coordinate_quality_report.json"
DEFAULT_SNAPSHOT = Path(tempfile.gettempdir()) / "assist-platform-wa-geonoma.json"

CATALOGUE_URL = "https://catalogue.data.wa.gov.au/dataset/geographic-names-geonoma"
LAYER_URL = (
    "https://token.slip.wa.gov.au/public/rest/services/SLIP_Public_Services/"
    "Places_and_Addresses_WFS/MapServer/0"
)
QUERY_URL = f"{LAYER_URL}/query"
LICENCE_URL = (
    "https://www.landgate.wa.gov.au/siteassets/documents/location-data-and-services/"
    "licensing/slip-transaction-personal-use-licence-300523.pdf"
)

# Addressable locality/suburb concepts take precedence over historic townsites
# and broad place-name points. Equal-priority records at different coordinates
# are quarantined rather than resolved by proximity to the existing seed.
TYPE_PRIORITY = {
    "LOCB": 10,  # Locality (Bounded)
    "SUB": 20,   # Suburb
    "TNST": 30,  # Townsite
    "COMM": 40,  # Community
    "LGAT": 50,  # Local Government Town
    "MC": 60,    # Mining Centre
    "PLNA": 70,  # Place Name
}
SOURCE_KEY = "wa-landgate-geonoma"
PAGE_SIZE = 2_000


def normalise_name(value: str) -> str:
    """Apply only Unicode, case and whitespace normalisation."""

    return " ".join(unicodedata.normalize("NFC", value).strip().casefold().split())


def post_json(url: str, values: dict[str, str]) -> dict[str, Any]:
    request = urllib.request.Request(
        url,
        data=urllib.parse.urlencode(values).encode("ascii"),
        headers={"User-Agent": "AssistPlatformTownCoordinateAudit/1.0"},
        method="POST",
    )
    with urllib.request.urlopen(request, timeout=120) as response:
        payload = json.load(response)
    if "error" in payload:
        raise RuntimeError(f"Landgate query failed: {payload['error']}")
    return payload


def download_snapshot(path: Path) -> dict[str, Any]:
    relevant = ",".join(f"'{value}'" for value in TYPE_PRIORITY)
    records: list[dict[str, Any]] = []
    offset = 0

    while True:
        payload = post_json(
            QUERY_URL,
            {
                "f": "json",
                "where": f"feature_class IN ({relevant})",
                "outFields": (
                    "objectid,feature_number,full_name,feature_class,"
                    "feature_class_description,date_approved,latitude,longitude,lga_names"
                ),
                "returnGeometry": "false",
                "orderByFields": "objectid",
                "resultOffset": str(offset),
                "resultRecordCount": str(PAGE_SIZE),
            },
        )
        page = [feature["attributes"] for feature in payload.get("features", [])]
        records.extend(page)
        if len(page) < PAGE_SIZE and not payload.get("exceededTransferLimit", False):
            break
        if not page:
            raise RuntimeError("Landgate pagination stopped without reaching the final page")
        offset += len(page)

    snapshot = {
        "downloaded_at": datetime.now(timezone.utc).isoformat(),
        "source_layer": LAYER_URL,
        "filter_classes": list(TYPE_PRIORITY),
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


def candidate_view(record: dict[str, Any]) -> dict[str, Any]:
    approved_at = record.get("date_approved")
    approved_date = None
    if approved_at is not None:
        try:
            approved_date = (
                datetime(1970, 1, 1, tzinfo=timezone.utc)
                + timedelta(milliseconds=float(approved_at))
            ).date().isoformat()
        except (OverflowError, ValueError):
            approved_date = None
    return {
        "objectid": record["objectid"],
        "feature_number": record["feature_number"],
        "feature_class": record["feature_class"],
        "feature_class_description": record["feature_class_description"],
        "date_approved": approved_date,
        "lga_names": record.get("lga_names"),
        "lat": round(float(record["latitude"]), 8),
        "lng": round(float(record["longitude"]), 8),
    }


def build_report(towns_path: Path, snapshot: dict[str, Any]) -> dict[str, Any]:
    seed = json.loads(towns_path.read_text(encoding="utf-8"))
    wa_towns = [town for town in seed["towns"] if town.get("state") == "WA"]
    by_name: dict[str, list[dict[str, Any]]] = defaultdict(list)

    for record in snapshot["records"]:
        if (
            record.get("feature_class") not in TYPE_PRIORITY
            or not record.get("full_name")
            or record.get("latitude") is None
            or record.get("longitude") is None
        ):
            continue
        by_name[normalise_name(str(record["full_name"]))].append(record)

    matches: list[dict[str, Any]] = []
    ambiguous: list[dict[str, Any]] = []
    unmatched: list[dict[str, Any]] = []

    for town in sorted(wa_towns, key=lambda item: (item["name"].casefold(), item.get("pc", ""))):
        candidates = by_name.get(normalise_name(town["name"]), [])
        if not candidates:
            unmatched.append({"town": town["name"], "postcode": town.get("pc")})
            continue

        best_priority = min(TYPE_PRIORITY[item["feature_class"]] for item in candidates)
        best = [item for item in candidates if TYPE_PRIORITY[item["feature_class"]] == best_priority]
        points = {
            (round(float(item["latitude"]), 8), round(float(item["longitude"]), 8))
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

        selected = min(best, key=lambda item: int(item["objectid"]))
        official_lat = float(selected["latitude"])
        official_lng = float(selected["longitude"])
        seed_lat = float(town["lat"])
        seed_lng = float(town["lng"])
        matches.append(
            {
                "town": town["name"],
                "postcode": town.get("pc"),
                "seed": {"lat": seed_lat, "lng": seed_lng},
                "official": candidate_view(selected),
                "coincident_candidate_count": len(best),
                "coordinate_shift_km": round(
                    haversine_km(seed_lat, seed_lng, official_lat, official_lng), 3
                ),
            }
        )

    perth = next((item for item in matches if normalise_name(item["town"]) == "perth"), None)
    if perth is None:
        raise RuntimeError("Perth did not resolve to a unique authoritative candidate")

    shifts = [item["coordinate_shift_km"] for item in matches]
    return {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "state": "WA",
        "backlog_item": "DATA-001",
        "source": {
            "key": SOURCE_KEY,
            "name": "Landgate Geographic Names (GEONOMA) (LGATE-013)",
            "authority": "Western Australian Land Information Authority (Landgate)",
            "catalogue_url": CATALOGUE_URL,
            "api_layer_url": LAYER_URL,
            "source_snapshot": "operating-system TEMP only; not committed",
            "downloaded_at": snapshot["downloaded_at"],
            "update_frequency": "weekly",
            "copyright": "Western Australian Land Information Authority (Landgate)",
            "limitations": (
                "The public WFS view does not expose GEONOMA name_type, feature_status or "
                "archived fields. An exact result verifies the register position and spelling, "
                "but does not by itself prove current approved status for legal purposes."
            ),
        },
        "licence": {
            "name": "SLIP Transaction - Personal Use Licence",
            "url": LICENCE_URL,
            "commercial_use_permitted_by_default": False,
            "status": "BLOCKED_PENDING_LANDGATE_COMMERCIAL_LICENCE",
            "instruction": (
                "Use this report for internal verification only. Do not copy GEONOMA-derived "
                "coordinates into public or production data unless Landgate grants suitable terms in writing."
            ),
        },
        "matching_policy": {
            "name": "exact after Unicode NFC, case-folding and whitespace collapse only",
            "type_priority": TYPE_PRIORITY,
            "ambiguity_rule": (
                "choose the unique coordinate at the highest-priority class; quarantine "
                "equal-priority candidates at different coordinates"
            ),
            "existing_seed_proximity_used_to_break_ties": False,
        },
        "counts": {
            "wa_seed_towns": len(wa_towns),
            "official_relevant_place_records": len(snapshot["records"]),
            "official_register_exact_matches": len(matches),
            "ambiguous_quarantined": len(ambiguous),
            "unmatched_held_for_review": len(unmatched),
            "matches_shifted_over_25_km": sum(1 for shift in shifts if shift > 25),
        },
        "perth_verification": perth,
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
        help="Reuse --snapshot instead of downloading the current Landgate feed.",
    )
    args = parser.parse_args()

    if args.reuse_snapshot:
        snapshot = json.loads(args.snapshot.read_text(encoding="utf-8"))
    else:
        snapshot = download_snapshot(args.snapshot)
    report = build_report(args.towns, snapshot)
    args.report.parent.mkdir(parents=True, exist_ok=True)
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({**report["counts"], "perth": report["perth_verification"]}, indent=2))


if __name__ == "__main__":
    main()
