#!/usr/bin/env python3
"""Audit WA towns against GA's commercially reusable Composite Gazetteer.

This is an independent, report-only alternative to the Landgate audit. It
downloads WA-approved place records to OS TEMP and never edits the national
town seed.
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
TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
REPORT = ROOT / "database" / "seeds" / "wa_ga_coordinate_quality_report.json"
SNAPSHOT = Path(tempfile.gettempdir()) / "assist-platform-wa-ga-cga.json"
SERVICE = "https://services.ga.gov.au/gis/rest/services/Composite_Gazetteer_of_Australia/MapServer"
LAYER = f"{SERVICE}/0"
QUERY = f"{LAYER}/query"
LICENCE = "https://creativecommons.org/licenses/by/4.0/"
COPYRIGHT = "https://www.ga.gov.au/copyright"
PRIORITY = {"LOCALITY": 10, "SETTLEMENT": 20, "TOWN SITE": 30}
PAGE_SIZE = 2000


def normalise(value: str) -> str:
    return " ".join(unicodedata.normalize("NFC", value).strip().casefold().split())


def get_json(values: dict[str, str]) -> dict[str, Any]:
    request = urllib.request.Request(
        f"{QUERY}?{urllib.parse.urlencode(values)}",
        headers={"User-Agent": "AssistPlatformTownCoordinateAudit/1.0"},
    )
    with urllib.request.urlopen(request, timeout=120) as response:
        payload = json.load(response)
    if "error" in payload:
        raise RuntimeError(f"Composite Gazetteer query failed: {payload['error']}")
    return payload


def download(path: Path) -> dict[str, Any]:
    kinds = ",".join(f"'{name}'" for name in PRIORITY)
    rows: list[dict[str, Any]] = []
    offset = 0
    while True:
        payload = get_json({
            "f": "json",
            "where": f"authority='WA' AND feature IN ({kinds})",
            "outFields": "objectid,id,auth_id,name,feature,category,theme,latitude,longitude,authority,supply_date",
            "returnGeometry": "false",
            "orderByFields": "objectid",
            "resultOffset": str(offset),
            "resultRecordCount": str(PAGE_SIZE),
        })
        page = [feature["attributes"] for feature in payload.get("features", [])]
        rows.extend(page)
        if len(page) < PAGE_SIZE and not payload.get("exceededTransferLimit", False):
            break
        if not page:
            raise RuntimeError("Composite Gazetteer pagination stopped before completion")
        offset += len(page)
    result = {
        "downloaded_at": datetime.now(timezone.utc).isoformat(),
        "source_layer": LAYER,
        "record_count": len(rows),
        "records": rows,
    }
    path.write_text(json.dumps(result, separators=(",", ":")), encoding="utf-8")
    return result


def distance(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    dlat, dlng = math.radians(lat2 - lat1), math.radians(lng2 - lng1)
    value = math.sin(dlat / 2) ** 2 + math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(dlng / 2) ** 2
    return 12742.0176 * math.asin(math.sqrt(value))


def view(row: dict[str, Any]) -> dict[str, Any]:
    supplied = None
    if row.get("supply_date") is not None:
        supplied = (datetime(1970, 1, 1, tzinfo=timezone.utc) + timedelta(milliseconds=float(row["supply_date"]))).date().isoformat()
    return {
        "id": row["id"], "authority_reference": row["auth_id"],
        "feature": row["feature"], "authority": row["authority"],
        "supply_date": supplied, "lat": round(float(row["latitude"]), 8),
        "lng": round(float(row["longitude"]), 8),
    }


def resolve(rows: list[dict[str, Any]]) -> tuple[dict[str, Any] | None, list[dict[str, Any]], int | None]:
    if not rows:
        return None, [], None
    priority = min(PRIORITY[row["feature"]] for row in rows)
    best = [row for row in rows if PRIORITY[row["feature"]] == priority]
    points = {(round(float(row["latitude"]), 8), round(float(row["longitude"]), 8)) for row in best}
    return (min(best, key=lambda row: str(row["id"])) if len(points) == 1 else None), best, priority


def build(towns_path: Path, snapshot: dict[str, Any]) -> dict[str, Any]:
    data = json.loads(towns_path.read_text(encoding="utf-8"))
    towns = [town for town in data["towns"] if town.get("state") == "WA"]
    index: dict[str, list[dict[str, Any]]] = defaultdict(list)
    for row in snapshot["records"]:
        if row.get("name") and row.get("latitude") is not None and row.get("longitude") is not None:
            index[normalise(row["name"])].append(row)
    matches, ambiguous, unmatched = [], [], []
    for town in sorted(towns, key=lambda item: (item["name"].casefold(), item.get("pc", ""))):
        selected, best, priority = resolve(index.get(normalise(town["name"]), []))
        if not best:
            unmatched.append({"town": town["name"], "postcode": town.get("pc")})
            continue
        if selected is None:
            ambiguous.append({
                "town": town["name"], "postcode": town.get("pc"), "priority": priority,
                "reason": "equal-priority exact-name candidates have different coordinates",
                "candidates": [view(row) for row in best],
            })
            continue
        official = view(selected)
        shift = distance(float(town["lat"]), float(town["lng"]), official["lat"], official["lng"])
        matches.append({
            "town": town["name"], "postcode": town.get("pc"),
            "seed": {"lat": town["lat"], "lng": town["lng"]}, "official": official,
            "coincident_candidate_count": len(best), "coordinate_shift_km": round(shift, 3),
        })
    perth = next((item for item in matches if normalise(item["town"]) == "perth"), None)
    if perth is None or perth["official"]["feature"] != "LOCALITY":
        raise RuntimeError("Perth did not resolve to the official WA locality")
    return {
        "generated_at": datetime.now(timezone.utc).isoformat(), "state": "WA",
        "backlog_item": "DATA-001",
        "source": {
            "key": "ga-composite-gazetteer-wa", "name": "Composite Gazetteer of Australia - WA approved names",
            "authority": "Geoscience Australia / Western Australian naming authority",
            "service_url": SERVICE, "api_layer_url": LAYER,
            "source_snapshot": "operating-system TEMP only; not committed",
            "downloaded_at": snapshot["downloaded_at"],
            "limitations": "Point accuracy varies and must not be relied upon for navigation, precise positioning or safety-of-life applications.",
        },
        "licence": {
            "name": "Creative Commons Attribution 4.0 International (CC BY 4.0)",
            "url": LICENCE, "copyright_policy_url": COPYRIGHT,
            "commercial_use_permitted": True, "lawful_use_status": "PERMITTED_WITH_ATTRIBUTION",
            "licence_basis": "The layer identifies Geoscience Australia as copyright holder and states no alternative product licence; GA's published default is CC BY 4.0.",
            "required_attribution": "Based on Composite Gazetteer of Australia data by Geoscience Australia, © Commonwealth of Australia, provided under CC BY 4.0; WA names are supplied by the Western Australian naming authority.",
        },
        "matching_policy": {
            "source_scope": "approved WA place names", "name": "exact after Unicode NFC, case-folding and whitespace collapse only",
            "type_priority": PRIORITY, "existing_seed_proximity_used_to_break_ties": False,
            "ambiguity_rule": "unique coordinate at highest-priority type only; otherwise quarantine",
        },
        "counts": {
            "wa_seed_towns": len(towns), "official_relevant_approved_records": len(snapshot["records"]),
            "official_exact_matches": len(matches), "ambiguous_quarantined": len(ambiguous),
            "unmatched_held_for_review": len(unmatched),
            "matches_shifted_over_25_km": sum(1 for item in matches if item["coordinate_shift_km"] > 25),
        },
        "perth_verification": perth, "matches": matches, "ambiguous": ambiguous, "unmatched": unmatched,
    }


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--towns", type=Path, default=TOWNS)
    parser.add_argument("--report", type=Path, default=REPORT)
    parser.add_argument("--snapshot", type=Path, default=SNAPSHOT)
    parser.add_argument("--reuse-snapshot", action="store_true")
    args = parser.parse_args()
    snapshot = json.loads(args.snapshot.read_text(encoding="utf-8")) if args.reuse_snapshot else download(args.snapshot)
    report = build(args.towns, snapshot)
    args.report.write_text(json.dumps(report, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({**report["counts"], "perth": report["perth_verification"]}, indent=2))


if __name__ == "__main__":
    main()
