#!/usr/bin/env python3
"""Verify Queensland town coordinates against the official place gazetteer.

Only current, exact name matches are accepted. Population centres are preferred,
then suburbs, bounded localities and unbounded localities. Equal-priority
duplicates are reported as ambiguous and deliberately left unchanged.
"""

from __future__ import annotations

import argparse
import csv
import json
import math
from collections import defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
SOURCE_URL = "https://www.data.qld.gov.au/dataset/bd13d3a3-5470-437b-8d43-54d166907296/resource/414391b9-7943-4fc3-a237-a1ac57b75aab/download/queensland_place_names_gazetteer.csv"
TYPE_PRIORITY = {"POPL": 1, "SUB": 2, "LOCB": 3, "LOCU": 4}
REGION_CENTROIDS = {
    "seq": (-27.47, 153.02), "downs": (-27.56, 151.95),
    "widebay": (-24.87, 152.35), "cq": (-23.38, 150.51),
    "fitzroy": (-23.52, 148.16), "mackay": (-21.14, 149.19),
    "nq": (-19.26, 146.82), "fnq": (-16.92, 145.77),
    "outback": (-23.44, 144.25),
}


def nearest_region(latitude: float, longitude: float) -> str:
    def distance(point: tuple[float, float]) -> float:
        lat_delta = math.radians(point[0] - latitude)
        lng_delta = math.radians(point[1] - longitude)
        value = math.sin(lat_delta / 2) ** 2 + math.cos(math.radians(latitude)) * math.cos(math.radians(point[0])) * math.sin(lng_delta / 2) ** 2
        return 2 * 6371 * math.asin(math.sqrt(value))
    return min(REGION_CENTROIDS, key=lambda key: distance(REGION_CENTROIDS[key]))


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--csv", required=True)
    parser.add_argument("--towns", default=str(TOWNS))
    args = parser.parse_args()

    candidates: dict[str, list[dict[str, str]]] = defaultdict(list)
    with Path(args.csv).open(encoding="utf-8-sig", newline="") as source:
        for row in csv.DictReader(source):
            if row["Status"] != "Y" or row["Currency"] != "Y" or row["Type"] not in TYPE_PRIORITY:
                continue
            if not row["Latitude DD"] or not row["Longitude DD"]:
                continue
            candidates[row["Place name"].strip().casefold()].append(row)

    data_path = Path(args.towns)
    data = json.loads(data_path.read_text(encoding="utf-8"))
    matched = 0
    ambiguous: list[dict[str, object]] = []
    missing: list[str] = []
    for town in data["towns"]:
        if town["state"] != "QLD":
            continue
        rows = candidates.get(town["name"].casefold(), [])
        if not rows:
            missing.append(town["name"])
            continue
        best_priority = min(TYPE_PRIORITY[row["Type"]] for row in rows)
        best = [row for row in rows if TYPE_PRIORITY[row["Type"]] == best_priority]
        unique_points = {(row["Latitude DD"], row["Longitude DD"]) for row in best}
        if len(unique_points) != 1:
            ambiguous.append({
                "town": town["name"],
                "type": best[0]["Type"],
                "candidates": [
                    {"ref": row["Ref number"], "lga": row["LGA name"], "lat": row["Latitude DD"], "lng": row["Longitude DD"]}
                    for row in best
                ],
            })
            continue
        town["lat"] = round(float(best[0]["Latitude DD"]), 7)
        town["lng"] = round(float(best[0]["Longitude DD"]), 7)
        town["region"] = nearest_region(town["lat"], town["lng"])
        town["coordinate_source"] = "qld-place-names-gazetteer"
        town["coordinate_confidence"] = "authoritative"
        town["coordinate_reference"] = best[0]["Ref number"]
        matched += 1

    quality = data.setdefault("coordinate_quality", {})
    quality["qld_authoritative_matches"] = matched
    quality["qld_ambiguous"] = len(ambiguous)
    quality["qld_unmatched"] = len(missing)
    sources = data.setdefault("coordinate_sources", {})
    sources["qld-place-names-gazetteer"] = {
        "name": "Queensland Government Place Names Gazetteer",
        "url": SOURCE_URL,
        "licence": "CC BY 4.0",
        "source_updated": "2025-08-22",
    }
    data_path.write_text(json.dumps(data, separators=(",", ":")), encoding="utf-8")

    report = {
        "qld_towns": sum(1 for town in data["towns"] if town["state"] == "QLD"),
        "authoritative_matches": matched,
        "ambiguous": ambiguous,
        "unmatched": sorted(missing),
    }
    report_path = ROOT / "database" / "seeds" / "qld_coordinate_quality_report.json"
    report_path.write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps({key: value for key, value in report.items() if key not in {"ambiguous", "unmatched"}} | {
        "ambiguous": len(ambiguous), "unmatched": len(missing)
    }, indent=2))


if __name__ == "__main__":
    main()
