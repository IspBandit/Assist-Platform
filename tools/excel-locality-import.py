#!/usr/bin/env python3
"""
VanAssist locality-provider Excel → JSON converter.

Reads the three VanAssist_Locality_Providers_*.xlsx research workbooks (one row
per locality, six core service roles per row) and writes split seed files under
database/seeds/ for NationalImportSeeder::seedLocality() (keeps memory low on
shared hosting — no single 16 MB JSON blob).

Usage:
  python tools/excel-locality-import.py
  python tools/excel-locality-import.py --file "F:/VanAssist_Locality_Providers_1_NSW_ACT.xlsx" ...

Requires: pandas, openpyxl  (pip install pandas openpyxl)
"""

from __future__ import annotations

import argparse
import json
import math
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

import pandas as pd

ROOT = Path(__file__).resolve().parent.parent
OUT_DIR = ROOT / "database" / "seeds"
META_FILE = OUT_DIR / "businesses_locality.meta.json"
BIZ_FILE = OUT_DIR / "businesses_locality_businesses.json"
COV_FILE = OUT_DIR / "businesses_locality_coverage.jsonl"

DEFAULT_FILES = [
    Path(r"F:/VanAssist_Locality_Providers_1_NSW_ACT.xlsx"),
    Path(r"F:/VanAssist_Locality_Providers_2_VIC_TAS_SA.xlsx"),
    Path(r"F:/VanAssist_Locality_Providers_3_QLD_NT_WA_OT.xlsx"),
]

ROLE_MAP = {
    "Caravan / trailer / RV support": "caravan",
    "Mechanical / brakes / suspension": "mechanical",
    "Tyres / wheels / punctures": "mechanical",
    "Electrical / battery / solar": "autoelec",
    "Certification / inspection": "roadworthy",
    "Towing / roadside / recovery": "roadside",
}

REGION_CENTROIDS = {
    "seq": (-27.47, 153.02), "downs": (-27.56, 151.95), "widebay": (-24.87, 152.35),
    "cq": (-23.38, 150.51), "fitzroy": (-23.52, 148.16), "mackay": (-21.14, 149.19),
    "nq": (-19.26, 146.82), "fnq": (-16.92, 145.77), "outback": (-23.44, 144.25),
    "nsw-sydney": (-33.87, 151.21), "nsw-hunter": (-32.93, 151.78),
    "nsw-north-coast": (-30.30, 153.12), "nsw-northern-inland": (-31.09, 150.93),
    "nsw-central-west": (-33.28, 149.10), "nsw-riverina": (-35.12, 147.37),
    "nsw-south-coast": (-34.88, 150.60), "nsw-far-west": (-31.95, 141.47),
    "vic-melb": (-37.81, 144.96), "vic-geelong": (-38.15, 144.36),
    "vic-ballarat": (-37.56, 143.86), "vic-bendigo": (-36.76, 144.28),
    "vic-gippsland": (-38.20, 146.54), "vic-goulburn": (-36.38, 145.40),
    "vic-westvic": (-36.71, 142.20), "vic-murray": (-34.19, 142.16),
    "sa-adelaide": (-34.93, 138.60), "sa-fleurieu": (-35.55, 138.62),
    "sa-barossa": (-34.48, 138.99), "sa-riverland": (-34.28, 140.60),
    "sa-yorke-eyre": (-34.00, 136.20), "sa-southeast": (-37.50, 140.40),
    "sa-outback": (-30.50, 135.50),
    "wa-perth": (-31.95, 115.86), "wa-southwest": (-33.33, 115.64),
    "wa-greatsouthern": (-35.03, 117.88), "wa-wheatbelt": (-31.65, 116.67),
    "wa-midwest": (-28.77, 114.61), "wa-goldfields": (-30.75, 121.47),
    "wa-gascoyne": (-24.88, 113.66), "wa-pilbara": (-20.74, 116.85),
    "wa-kimberley": (-17.96, 122.24),
    "tas-hobart": (-42.88, 147.33), "tas-launceston": (-41.43, 147.14),
    "tas-northwest": (-41.05, 145.91), "tas-east": (-41.65, 148.10),
    "nt-darwin": (-12.46, 130.84), "nt-katherine": (-14.46, 132.26),
    "nt-tennant": (-19.65, 134.19), "nt-alice": (-23.70, 133.88),
    "nt-eastarnhem": (-12.20, 136.78),
    "act-canberra": (-35.28, 149.13),
}

STATE_REGIONS = {
    "QLD": ["seq", "downs", "widebay", "cq", "fitzroy", "mackay", "nq", "fnq", "outback"],
    "NSW": ["nsw-sydney", "nsw-hunter", "nsw-north-coast", "nsw-northern-inland", "nsw-central-west", "nsw-riverina", "nsw-south-coast", "nsw-far-west"],
    "VIC": ["vic-melb", "vic-geelong", "vic-ballarat", "vic-bendigo", "vic-gippsland", "vic-goulburn", "vic-westvic", "vic-murray"],
    "SA": ["sa-adelaide", "sa-fleurieu", "sa-barossa", "sa-riverland", "sa-yorke-eyre", "sa-southeast", "sa-outback"],
    "WA": ["wa-perth", "wa-southwest", "wa-greatsouthern", "wa-wheatbelt", "wa-midwest", "wa-goldfields", "wa-gascoyne", "wa-pilbara", "wa-kimberley"],
    "TAS": ["tas-hobart", "tas-launceston", "tas-northwest", "tas-east"],
    "NT": ["nt-darwin", "nt-katherine", "nt-tennant", "nt-alice", "nt-eastarnhem"],
    "ACT": ["act-canberra"],
}


def dist_km(lat1: float, lng1: float, lat2: float, lng2: float) -> float:
    r = 6371.0
    p1, p2 = math.radians(lat1), math.radians(lat2)
    dlat = math.radians(lat2 - lat1)
    dlng = math.radians(lng2 - lng1)
    a = math.sin(dlat / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dlng / 2) ** 2
    return 2 * r * math.asin(math.sqrt(a))


def nearest_region(state: str, lat: float, lng: float) -> str | None:
    best, best_d = None, float("inf")
    for slug in STATE_REGIONS.get(state, []):
        c = REGION_CENTROIDS.get(slug)
        if not c:
            continue
        d = dist_km(lat, lng, c[0], c[1])
        if d < best_d:
            best_d, best = d, slug
    return best


def slugify(text: str) -> str:
    text = text.lower().strip()
    text = re.sub(r"[^a-z0-9]+", "-", text)
    return text.strip("-")[:190]


def clean_str(val) -> str:
    if val is None or (isinstance(val, float) and math.isnan(val)):
        return ""
    return str(val).strip()


def clean_phone(val) -> str:
    s = clean_str(val)
    return s if s and s.lower() != "nan" else ""


def business_id(name: str, phone: str, website: str) -> str:
    base = slugify(name)
    digits = re.sub(r"\D", "", phone)[:10]
    if digits:
        return f"{base}-{digits}"[:190]
    host = re.sub(r"^https?://(www\.)?", "", website.lower()).split("/")[0]
    if host:
        return f"{base}-{slugify(host)}"[:190]
    return base or "provider"


def infer_modes(band: str, base: str, town: str) -> list[str]:
    b = band.lower()
    if "statewide" in b:
        return ["mobile"]
    if base and town and base.lower() != town.lower():
        return ["mobile"]
    if "workshop" in b or "local" in b:
        return ["workshop"]
    return ["mobile"]


def is_statewide(band: str) -> bool:
    return "statewide" in band.lower()


def process_workbook(path: Path, businesses: dict, coverage: list) -> tuple[int, int]:
    xl = pd.ExcelFile(path)
    data_sheets = [s for s in xl.sheet_names if s not in ("Dashboard", "Methodology")]
    rows_seen = 0
    assignments = 0

    for sheet in data_sheets:
        df = pd.read_excel(path, sheet_name=sheet)
        for _, row in df.iterrows():
            rows_seen += 1
            state = clean_str(row.get("State")) or sheet
            if state == "OT":
                continue
            town = clean_str(row.get("Locality / Suburb"))
            if not town:
                continue
            try:
                postcode = str(int(row.get("Postcode"))) if pd.notna(row.get("Postcode")) else ""
            except (ValueError, TypeError):
                postcode = clean_str(row.get("Postcode"))
            lat = float(row["Latitude"]) if pd.notna(row.get("Latitude")) else None
            lng = float(row["Longitude"]) if pd.notna(row.get("Longitude")) else None
            region = nearest_region(state, lat, lng) if lat is not None and lng is not None else None

            for role_label, cat in ROLE_MAP.items():
                name = clean_str(row.get(f"{role_label} Provider"))
                if not name:
                    continue
                phone = clean_phone(row.get(f"{role_label} Phone"))
                email = clean_str(row.get(f"{role_label} Email"))
                website = clean_str(row.get(f"{role_label} Website"))
                source_url = clean_str(row.get(f"{role_label} Source URL"))
                base = clean_str(row.get(f"{role_label} Provider Base")) or town
                band = clean_str(row.get(f"{role_label} Coverage Band")) or "Local"

                bid = business_id(name, phone, website)
                if bid not in businesses:
                    businesses[bid] = {
                        "id": bid,
                        "name": name,
                        "base": base,
                        "state": state,
                        "phone": phone,
                        "email": email or None,
                        "website": website or None,
                        "source_url": source_url or None,
                        "modes": infer_modes(band, base, town),
                        "cats": [],
                    }
                if cat not in businesses[bid]["cats"]:
                    businesses[bid]["cats"].append(cat)
                # Prefer richer contact detail when the same business appears again.
                b = businesses[bid]
                if phone and not b.get("phone"):
                    b["phone"] = phone
                if email and not b.get("email"):
                    b["email"] = email
                if website and not b.get("website"):
                    b["website"] = website

                coverage.append({
                    "bid": bid,
                    "state": state,
                    "town": town,
                    "postcode": postcode or None,
                    "lat": lat,
                    "lng": lng,
                    "region": region,
                    "cat": cat,
                    "band": band,
                })
                assignments += 1

    return rows_seen, assignments


def main() -> int:
    parser = argparse.ArgumentParser(description="Convert VanAssist locality-provider Excel workbooks to JSON.")
    parser.add_argument("--file", action="append", dest="files", help="Path to an .xlsx workbook (repeatable)")
    parser.add_argument("--out-dir", default=str(OUT_DIR), help="Output directory for seed files")
    parser.add_argument("--dry-run", action="store_true", help="Report counts only; do not write")
    args = parser.parse_args()

    files = [Path(p) for p in args.files] if args.files else DEFAULT_FILES
    missing = [str(p) for p in files if not p.is_file()]
    if missing:
        print("Missing workbook(s):", ", ".join(missing), file=sys.stderr)
        print("Pass paths with --file or copy the workbooks to the default F:\\ locations.", file=sys.stderr)
        return 1

    businesses: dict[str, dict] = {}
    coverage: list[dict] = []
    total_rows = 0

    for path in files:
        rows, assigns = process_workbook(path, businesses, coverage)
        print(f"{path.name}: {rows} locality rows -> {assigns} assignments")
        total_rows += rows

    print(f"Total: {total_rows} locality rows, {len(businesses)} unique businesses, {len(coverage)} coverage assignments")

    if args.dry_run:
        return 0

    out_dir = Path(args.out_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    meta_path = out_dir / META_FILE.name
    biz_path = out_dir / BIZ_FILE.name
    cov_path = out_dir / COV_FILE.name

    meta = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "source": "VanAssist locality-provider Excel research",
        "source_files": [p.name for p in files],
        "business_count": len(businesses),
        "coverage_count": len(coverage),
    }
    with meta_path.open("w", encoding="utf-8") as fh:
        json.dump(meta, fh, ensure_ascii=False, separators=(",", ":"))

    with biz_path.open("w", encoding="utf-8") as fh:
        json.dump(businesses, fh, ensure_ascii=False, separators=(",", ":"))

    with cov_path.open("w", encoding="utf-8") as fh:
        for row in coverage:
            fh.write(json.dumps(row, ensure_ascii=False, separators=(",", ":")))
            fh.write("\n")

    biz_mb = biz_path.stat().st_size / (1024 * 1024)
    cov_mb = cov_path.stat().st_size / (1024 * 1024)
    print(f"Wrote {meta_path.name}, {biz_path.name} ({biz_mb:.1f} MB), {cov_path.name} ({cov_mb:.1f} MB)")
    print("Next: deploy, then Admin -> Maintenance -> Import locality-provider research")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
