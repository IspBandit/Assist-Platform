#!/usr/bin/env python3
"""Replace postcode centroids with ABS Suburbs and Localities centroids.

Source: ABS ASGS Edition 3, Suburbs and Localities 2021 (GDA2020).
Install the lightweight reader with ``python -m pip install pyshp`` and run:

  python tools/enrich-town-coordinates-abs.py --shapefile C:/path/SAL_2021_AUST_GDA2020

Exact state/name matches only. Government gazetteer overrides are applied last.
"""

from __future__ import annotations

import argparse
import json
from pathlib import Path

import shapefile

ROOT = Path(__file__).resolve().parents[1]
TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
OVERRIDES = ROOT / "database" / "seeds" / "town_coordinate_overrides.json"
STATE_ABBR = {
    "New South Wales": "NSW", "Victoria": "VIC", "Queensland": "QLD",
    "South Australia": "SA", "Western Australia": "WA", "Tasmania": "TAS",
    "Northern Territory": "NT", "Australian Capital Territory": "ACT",
}


def polygon_centroid(shape: shapefile.Shape) -> tuple[float, float]:
    """Area-weighted ring centroid, with bbox fallback for degenerate geometry."""
    points = shape.points
    parts = list(shape.parts) + [len(points)]
    area_sum = x_sum = y_sum = 0.0
    for start, end in zip(parts, parts[1:]):
        ring = points[start:end]
        if len(ring) < 3:
            continue
        for index, first in enumerate(ring):
            second = ring[(index + 1) % len(ring)]
            cross = first[0] * second[1] - second[0] * first[1]
            area_sum += cross
            x_sum += (first[0] + second[0]) * cross
            y_sum += (first[1] + second[1]) * cross
    if abs(area_sum) > 1e-12:
        return x_sum / (3 * area_sum), y_sum / (3 * area_sum)
    xmin, ymin, xmax, ymax = shape.bbox
    return (xmin + xmax) / 2, (ymin + ymax) / 2


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--shapefile", required=True, help="Path without .shp suffix")
    parser.add_argument("--towns", default=str(TOWNS))
    args = parser.parse_args()

    path = Path(args.towns)
    data = json.loads(path.read_text(encoding="utf-8"))
    reader = shapefile.Reader(args.shapefile)
    matches: dict[tuple[str, str], tuple[float, float]] = {}
    for record, shape in zip(reader.iterRecords(), reader.iterShapes()):
        if not getattr(shape, "points", None):
            continue
        state = STATE_ABBR.get(str(record["STE_NAME21"]))
        name = str(record["SAL_NAME21"]).strip().casefold()
        if state and name and not name.startswith("migratory"):
            longitude, latitude = polygon_centroid(shape)
            matches[(state, name)] = (round(latitude, 7), round(longitude, 7))

    matched = 0
    for town in data["towns"]:
        coordinates = matches.get((town["state"], town["name"].casefold()))
        if coordinates:
            town["lat"], town["lng"] = coordinates
            town["coordinate_source"] = "abs-asgs-sal-2021"
            town["coordinate_confidence"] = "statistical"
            matched += 1
        else:
            town["coordinate_source"] = "australian-postcodes"
            town["coordinate_confidence"] = "unverified"

    overrides = json.loads(OVERRIDES.read_text(encoding="utf-8"))
    by_key = {(town["state"], town["name"].casefold()): town for town in data["towns"]}
    for override in overrides["towns"]:
        town = by_key[(override["state"], override["name"].casefold())]
        town["lat"], town["lng"] = override["lat"], override["lng"]
        town["coordinate_source"] = overrides["source_key"]
        town["coordinate_confidence"] = "authoritative"

    data["coordinate_sources"] = {
        "abs-asgs-sal-2021": {
            "name": "ABS ASGS Edition 3 Suburbs and Localities 2021",
            "url": "https://www.abs.gov.au/statistics/standards/australian-statistical-geography-standard-asgs/edition-3-july-2021-june-2026/access-and-downloads/digital-boundary-files",
            "note": "Statistical approximations of state and territory locality boundaries; centroid calculated by Assist.",
        },
        overrides["source_key"]: overrides,
        "australian-postcodes": {
            "name": "australian_postcodes",
            "url": "https://github.com/matthewproctor/australianpostcodes",
            "note": "Postcode-derived seed only; not trusted for precise distance claims.",
        },
    }
    data["coordinate_quality"] = {
        "matched_abs_localities": matched,
        "unverified": sum(1 for town in data["towns"] if town["coordinate_confidence"] == "unverified"),
    }
    path.write_text(json.dumps(data, separators=(",", ":")), encoding="utf-8")
    print(json.dumps(data["coordinate_quality"], indent=2))


if __name__ == "__main__":
    main()
