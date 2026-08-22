#!/usr/bin/env python3
"""Merge Queensland's mandatory fuel reporting sites into the provider pack."""

from __future__ import annotations

import argparse
import csv
import json
import math
import re
from collections import defaultdict
from datetime import datetime
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PACK = ROOT / "database" / "seeds" / "localtorque" / "providers-publishable.json"
SOURCE_URL = "https://www.data.qld.gov.au/dataset/fuel-price-reporting-2026/resource/82409c67-6333-4dce-a8f0-569735b45139"
CONTACT_OVERRIDES = {
    "61470427": {"website": "https://map.bp.com/en-AU/AU/gas-station/emerald/bp-emerald/1434"},
    "61451637": {
        "website": "https://find.shell.com/au/fuel/10111268-shell-reddy-express-emerald-qld/en_AU",
        "phone": "+61 7 4877 3969", "opening_hours": "Open 24 hours",
    },
}


def normalise(value: object) -> str:
    return re.sub(r"[^a-z0-9]+", " ", str(value or "").casefold()).strip()


def distance_km(a: dict[str, object], b: dict[str, object]) -> float:
    lat1, lng1, lat2, lng2 = map(float, (a["lat"], a["lng"], b["lat"], b["lng"]))
    dlat, dlng = math.radians(lat2 - lat1), math.radians(lng2 - lng1)
    value = math.sin(dlat / 2) ** 2 + math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(dlng / 2) ** 2
    return 2 * 6371 * math.asin(math.sqrt(value))


def fuel_key(value: str) -> str:
    keys = {
        "diesel": "diesel", "premium diesel": "premium_diesel", "e10": "e10",
        "e85": "e85", "unleaded 91": "octane_91", "unleaded 95": "octane_95",
        "unleaded 98": "octane_98", "unleaded": "octane_91",
        "pulp 95/96 ron": "octane_95", "pulp 98 ron": "octane_98", "lpg": "lpg",
    }
    return keys.get(value.casefold(), normalise(value).replace(" ", "_"))


def reported_at(row: dict[str, str]) -> datetime:
    return datetime.strptime(row["TransactionDateutc"], "%d/%m/%Y %H:%M")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--csv", required=True)
    parser.add_argument("--pack", default=str(PACK))
    args = parser.parse_args()

    grouped: dict[str, list[dict[str, str]]] = defaultdict(list)
    with Path(args.csv).open(encoding="utf-8-sig", newline="") as source:
        for row in csv.DictReader(source):
            if row["Site_State"].upper() == "QLD" and row["SiteId"]:
                grouped[row["SiteId"]].append(row)

    pack_path = Path(args.pack)
    existing = json.loads(pack_path.read_text(encoding="utf-8"))
    old_qld_fuel = [
        row for row in existing
        if row.get("state") == "QLD" and "fuel-station" in row.get("categories", []) and not row.get("claimed")
    ]
    legacy_qld_fuel = [row for row in old_qld_fuel if row.get("source") != "qld-fuel-reporting"]
    retained = [row for row in existing if row not in old_qld_fuel]
    enriched = 0
    official: list[dict[str, object]] = []

    for site_id, changes in grouped.items():
        latest = max(changes, key=reported_at)
        record: dict[str, object] = {
            "id": f"qld-fuel-{site_id}", "name": latest["Site_Name"].strip(),
            "trading_name": latest["Site_Brand"].strip() or None,
            "phone": None, "email": None, "website": None,
            "address": latest["Sites_Address_Line_1"].strip(), "town": latest["Site_Suburb"].strip(),
            "region": None, "state": "QLD", "postcode": latest["Site_Post_Code"].strip(),
            "lat": float(latest["Site_Latitude"]), "lng": float(latest["Site_Longitude"]),
            "modes": ["workshop"], "categories": ["fuel-station"], "va_cats": [],
            "brands": ["localtorque", "vanassist"],
            "services_note": "Current Queensland fuel reporting site. Confirm opening hours and facilities before travelling.",
            "opening_hours": None, "operator": latest["Site_Brand"].strip() or None,
            "fuel_types": sorted({fuel_key(row["Fuel_Type"]) for row in changes if row["Fuel_Type"]}),
            "operational_status": "OPERATIONAL", "source": "qld-fuel-reporting",
            "source_url": SOURCE_URL, "source_licence": "CC BY 4.0", "claimed": False,
            "verified": False, "confidence": 95, "publishable": True, "needs_review": False,
            "listing_note": "Identity, address, coordinates and reported fuel types sourced from Queensland Government fuel reporting. Contact details and hours are shown only when separately sourced.",
            "source_last_seen": reported_at(latest).isoformat(timespec="minutes") + "Z",
        }

        if site_id in CONTACT_OVERRIDES:
            for field, value in CONTACT_OVERRIDES[site_id].items():
                record[field] = value
            enriched += 1
        official.append(record)

    merged = retained + official
    merged.sort(key=lambda row: (str(row.get("state") or ""), str(row.get("town") or ""), str(row.get("name") or ""), str(row.get("id") or "")))
    pack_path.write_text(json.dumps(merged, separators=(",", ":")), encoding="utf-8")
    report = {
        "source": SOURCE_URL, "licence": "CC BY 4.0", "official_sites": len(official),
        "replacement_policy": "Current official QLD feed replaces unclaimed QLD fuel seeds",
        "contact_enrichments_retained": enriched,
        "towns": len({normalise(row["town"]) for row in official}),
        "emerald_sites": len([row for row in official if normalise(row["town"]) == "emerald"]),
    }
    (pack_path.parent / "qld-fuel-reporting.meta.json").write_text(json.dumps(report, indent=2), encoding="utf-8")
    print(json.dumps(report, indent=2))


if __name__ == "__main__":
    main()
