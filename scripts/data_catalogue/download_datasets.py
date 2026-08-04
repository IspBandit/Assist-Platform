#!/usr/bin/env python3
"""Download free reusable VanAssist datasets into data_catalogue/raw/.

Does not download Geofabrik PBF unless --include geofabrik_australia is set.
Never stores API keys. Paid sources are skipped.
"""

from __future__ import annotations

import argparse
import csv
import hashlib
import json
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from data_catalogue_lib import (  # noqa: E402
    CATALOGUE_SCHEMA_VERSION,
    catalogue_entries,
    validate_catalogue,
)

ROOT = SCRIPT_DIR.parent.parent
CATALOGUE_DIR = ROOT / "data_catalogue"
USER_AGENT = "AssistRIC/0.7 (+https://vanassist.com.au; dataset-acquisition)"


def utc_now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def export_catalogue(entries: list[dict]) -> None:
    CATALOGUE_DIR.mkdir(parents=True, exist_ok=True)
    errors = validate_catalogue(entries)
    if errors:
        raise SystemExit("Catalogue validation failed:\n- " + "\n- ".join(errors))
    payload = {
        "schema_version": CATALOGUE_SCHEMA_VERSION,
        "generated_at_utc": utc_now(),
        "source": "scripts/data_catalogue_lib.py",
        "count": len(entries),
        "datasets": entries,
    }
    json_path = CATALOGUE_DIR / "catalogue.json"
    json_path.write_text(json.dumps(payload, indent=2, sort_keys=False) + "\n", encoding="utf-8")
    fieldnames = list(entries[0].keys())
    csv_path = CATALOGUE_DIR / "catalogue.csv"
    with csv_path.open("w", encoding="utf-8", newline="") as handle:
        writer = csv.DictWriter(handle, fieldnames=fieldnames)
        writer.writeheader()
        for row in entries:
            flat = {
                key: json.dumps(value) if isinstance(value, (dict, list)) else value
                for key, value in row.items()
            }
            writer.writerow(flat)
    print(f"Wrote {json_path} ({len(entries)} sources)")
    print(f"Wrote {csv_path}")


def write_sidecar(folder: Path, *, entry: dict, source_url: str, bytes_written: int, checksum: str) -> None:
    folder.mkdir(parents=True, exist_ok=True)
    metadata = {
        "dataset_id": entry["dataset_id"],
        "name": entry["name"],
        "source_url": entry.get("source_url"),
        "download_url": source_url,
        "api_url": entry.get("api_url"),
        "licence": entry.get("licence"),
        "attribution": entry.get("attribution_requirement"),
        "publisher": entry.get("publisher"),
        "format": entry.get("format"),
        "downloaded_at_utc": utc_now(),
        "bytes": bytes_written,
        "sha256": checksum,
        "last_updated_declared": entry.get("last_updated") or "",
        "current_status": entry.get("current_status"),
    }
    (folder / "metadata.json").write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")
    (folder / "licence.txt").write_text(str(entry.get("licence") or "") + "\n", encoding="utf-8")
    (folder / "attribution.txt").write_text(
        str(entry.get("attribution_requirement") or "") + "\n", encoding="utf-8"
    )
    (folder / "checksum.sha256").write_text(f"{checksum}  source_file\n", encoding="utf-8")


def http_download(url: str, destination: Path, *, timeout: float = 180.0) -> int:
    destination.parent.mkdir(parents=True, exist_ok=True)
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(request, timeout=timeout) as response:
        data = response.read()
    destination.write_bytes(data)
    return len(data)


def download_ckan_toilet_map(entry: dict, force: bool) -> Path:
    folder = CATALOGUE_DIR / "raw" / "au_national_public_toilet_map"
    target = folder / "Toiletmap.csv"
    if target.exists() and not force:
        print(f"skip existing {target}")
        checksum = sha256_file(target)
        write_sidecar(folder, entry=entry, source_url=entry["download_url"], bytes_written=target.stat().st_size, checksum=checksum)
        return target
    api = "https://data.gov.au/data/api/3/action/resource_show?id=34076296-6692-4e30-b627-67b7c4eb1027"
    request = urllib.request.Request(api, headers={"User-Agent": USER_AGENT, "Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=60) as response:
        payload = json.loads(response.read().decode("utf-8"))
    url = payload["result"]["url"]
    entry = dict(entry)
    entry["last_updated"] = str(payload["result"].get("last_modified") or "")
    print(f"download toilet map <- {url}")
    nbytes = http_download(url, target, timeout=300)
    checksum = sha256_file(target)
    write_sidecar(folder, entry=entry, source_url=url, bytes_written=nbytes, checksum=checksum)
    print(f"  wrote {nbytes} bytes sha256={checksum[:12]}...")
    return target


def download_direct(entry: dict, force: bool) -> Path | None:
    local = str(entry.get("local_raw_path") or "").strip()
    url = str(entry.get("download_url") or "").strip()
    if not local or not url:
        print(f"skip {entry['dataset_id']}: missing download_url/local_raw_path")
        return None
    if entry.get("cost_type") == "paid":
        print(f"skip paid {entry['dataset_id']}")
        return None
    if entry["dataset_id"] == "portal_osm_australia" or entry["dataset_id"] == "geofabrik_australia":
        return None
    target = CATALOGUE_DIR / local
    folder = target.parent
    if target.exists() and not force:
        print(f"skip existing {target}")
        checksum = sha256_file(target)
        write_sidecar(folder, entry=entry, source_url=url, bytes_written=target.stat().st_size, checksum=checksum)
        return target
    print(f"download {entry['dataset_id']} <- {url}")
    try:
        nbytes = http_download(url, target, timeout=300)
    except urllib.error.HTTPError as exc:
        print(f"  FAIL HTTP {exc.code}")
        return None
    except urllib.error.URLError as exc:
        print(f"  FAIL network {exc.reason}")
        return None
    # Reject HTML landing pages masquerading as data
    head = target.read_bytes()[:64].lstrip().lower()
    if head.startswith(b"<!doctype html") or head.startswith(b"<html"):
        target.unlink(missing_ok=True)
        print("  FAIL: received HTML landing page, not bulk data")
        return None
    checksum = sha256_file(target)
    write_sidecar(folder, entry=entry, source_url=url, bytes_written=nbytes, checksum=checksum)
    print(f"  wrote {nbytes} bytes sha256={checksum[:12]}...")
    return target


def write_geofabrik_metadata(entry: dict, download_pbf: bool, force: bool) -> None:
    folder = CATALOGUE_DIR / "raw" / "geofabrik_australia"
    folder.mkdir(parents=True, exist_ok=True)
    url = entry["download_url"]
    request = urllib.request.Request(url, method="HEAD", headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(request, timeout=60) as response:
        headers = {k.lower(): v for k, v in response.headers.items()}
    meta = {
        "dataset_id": "portal_osm_australia",
        "source_url": entry["source_url"],
        "download_url": url,
        "content_length": headers.get("content-length"),
        "last_modified": headers.get("last-modified"),
        "checked_at_utc": utc_now(),
        "note": "PBF not committed. Use --include geofabrik_australia to download locally.",
    }
    (folder / "metadata.json").write_text(json.dumps(meta, indent=2) + "\n", encoding="utf-8")
    (folder / "licence.txt").write_text("ODbL 1.0 — https://www.openstreetmap.org/copyright\n", encoding="utf-8")
    (folder / "attribution.txt").write_text("© OpenStreetMap contributors\n", encoding="utf-8")
    print(f"wrote Geofabrik metadata last-modified={meta['last_modified']} size={meta['content_length']}")
    if not download_pbf:
        return
    target = folder / "australia-latest.osm.pbf"
    if target.exists() and not force:
        print(f"skip existing {target}")
        return
    print(f"download large Geofabrik PBF <- {url}")
    nbytes = http_download(url, target, timeout=3600)
    checksum = sha256_file(target)
    (folder / "checksum.sha256").write_text(f"{checksum}  australia-latest.osm.pbf\n", encoding="utf-8")
    meta.update({"bytes": nbytes, "sha256": checksum, "downloaded_at_utc": utc_now()})
    (folder / "metadata.json").write_text(json.dumps(meta, indent=2) + "\n", encoding="utf-8")
    print(f"  wrote {nbytes} bytes")


def write_batehaven_sample() -> None:
    """Write a normalised sample around Batehaven from the Toilet Map raw CSV if present."""
    raw = CATALOGUE_DIR / "raw" / "au_national_public_toilet_map" / "Toiletmap.csv"
    if not raw.exists():
        print("Batehaven sample skipped: Toilet Map raw CSV missing")
        return
    import math

    bate_lat, bate_lng = -35.7325, 150.1985

    def hav(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
        r = 6371.0
        p1, p2 = math.radians(lat1), math.radians(lat2)
        dphi = math.radians(lat2 - lat1)
        dl = math.radians(lon2 - lon1)
        a = math.sin(dphi / 2) ** 2 + math.cos(p1) * math.cos(p2) * math.sin(dl / 2) ** 2
        return 2 * r * math.asin(math.sqrt(a))

    sample_rows = []
    with raw.open(encoding="utf-8-sig", newline="") as handle:
        for row in csv.DictReader(handle):
            try:
                lat = float(row.get("Latitude") or "")
                lng = float(row.get("Longitude") or "")
            except ValueError:
                continue
            distance = hav(bate_lat, bate_lng, lat, lng)
            if distance <= 20:
                dump = str(row.get("DumpPoint", "")).strip().lower() in {"1", "true", "yes", "y", "t"}
                sample_rows.append(
                    {
                        "facility_id": row.get("FacilityID"),
                        "name": row.get("Name"),
                        "town": row.get("Town"),
                        "state": row.get("State"),
                        "latitude": lat,
                        "longitude": lng,
                        "dump_point": dump,
                        "distance_km": round(distance, 3),
                        "licence": "CC BY 3.0 AU",
                        "attribution": (
                            "© Commonwealth of Australia — National Public Toilet Map (data.gov.au)"
                        ),
                        "source_dataset_id": "au_national_public_toilet_map",
                    }
                )
    sample_rows.sort(key=lambda item: item["distance_km"])
    out_dir = CATALOGUE_DIR / "samples"
    out_dir.mkdir(parents=True, exist_ok=True)
    out = out_dir / "batehaven_toilet_map_20km.json"
    payload = {
        "centre": {"name": "Batehaven NSW", "latitude": bate_lat, "longitude": bate_lng},
        "radius_km": 20,
        "generated_at_utc": utc_now(),
        "count": len(sample_rows),
        "dump_point_count": sum(1 for row in sample_rows if row["dump_point"]),
        "records": sample_rows,
    }
    out.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    print(
        f"Wrote {out} toilets={payload['count']} dump_points={payload['dump_point_count']}"
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--export-only", action="store_true", help="Only write catalogue.json/csv")
    parser.add_argument("--force", action="store_true", help="Re-download existing raw files")
    parser.add_argument(
        "--include",
        action="append",
        default=[],
        help="Optional extras: geofabrik_australia",
    )
    parser.add_argument(
        "--only",
        action="append",
        default=[],
        help="Limit downloads to dataset_id values",
    )
    args = parser.parse_args()
    entries = catalogue_entries()
    export_catalogue(entries)
    if args.export_only:
        return 0

    only = set(args.only)
    by_id = {row["dataset_id"]: row for row in entries}
    download_ids = [
        "au_national_public_toilet_map",
        "nsw_rest_areas",
        "nsw_boat_ramps",
        "nsw_ev_charging_locations",
        "gold_coast_caravan_parks",
    ]
    if only:
        download_ids = [dataset_id for dataset_id in download_ids if dataset_id in only]

    for dataset_id in download_ids:
        entry = by_id[dataset_id]
        if dataset_id == "au_national_public_toilet_map":
            download_ckan_toilet_map(entry, force=args.force)
        else:
            download_direct(entry, force=args.force)

    if not only or "portal_osm_australia" in only or "geofabrik_australia" in args.include:
        write_geofabrik_metadata(
            by_id["portal_osm_australia"],
            download_pbf="geofabrik_australia" in args.include,
            force=args.force,
        )

    write_batehaven_sample()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
