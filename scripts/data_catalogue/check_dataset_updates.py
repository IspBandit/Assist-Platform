#!/usr/bin/env python3
"""Check whether catalogued remote datasets report newer update tokens."""

from __future__ import annotations

import argparse
import json
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
if str(SCRIPT_DIR) not in sys.path:
    sys.path.insert(0, str(SCRIPT_DIR))

from data_catalogue_lib import catalogue_entries  # noqa: E402

ROOT = SCRIPT_DIR.parent.parent
CATALOGUE_DIR = ROOT / "data_catalogue"
USER_AGENT = "AssistRIC/0.7 (+https://vanassist.com.au; dataset-update-check)"


def utc_now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def http_json(url: str) -> dict:
    request = urllib.request.Request(url, headers={"User-Agent": USER_AGENT, "Accept": "application/json"})
    with urllib.request.urlopen(request, timeout=60) as response:
        return json.loads(response.read().decode("utf-8"))


def http_head(url: str) -> dict[str, str]:
    request = urllib.request.Request(url, method="HEAD", headers={"User-Agent": USER_AGENT})
    with urllib.request.urlopen(request, timeout=60) as response:
        return {k.lower(): v for k, v in response.headers.items()}


def check_toilet_map() -> dict:
    payload = http_json(
        "https://data.gov.au/data/api/3/action/resource_show?id=34076296-6692-4e30-b627-67b7c4eb1027"
    )
    result = payload.get("result") or {}
    local_meta = CATALOGUE_DIR / "raw" / "au_national_public_toilet_map" / "metadata.json"
    local_token = ""
    if local_meta.exists():
        local_token = str(json.loads(local_meta.read_text(encoding="utf-8")).get("last_updated_declared") or "")
    remote_token = str(result.get("last_modified") or "")
    return {
        "dataset_id": "au_national_public_toilet_map",
        "available": bool(result.get("url")),
        "remote_update_token": remote_token,
        "local_update_token": local_token,
        "update_available": bool(remote_token and remote_token != local_token),
        "download_url": result.get("url"),
    }


def check_geofabrik() -> dict:
    headers = http_head("https://download.geofabrik.de/australia-oceania/australia-latest.osm.pbf")
    local_meta = CATALOGUE_DIR / "raw" / "geofabrik_australia" / "metadata.json"
    local_token = ""
    if local_meta.exists():
        local_token = str(json.loads(local_meta.read_text(encoding="utf-8")).get("last_modified") or "")
    remote_token = headers.get("last-modified", "")
    return {
        "dataset_id": "portal_osm_australia",
        "available": True,
        "remote_update_token": remote_token,
        "local_update_token": local_token,
        "update_available": bool(remote_token and remote_token != local_token),
        "content_length": headers.get("content-length"),
    }


def check_direct(entry: dict) -> dict:
    dataset_id = entry["dataset_id"]
    url = str(entry.get("download_url") or "").strip()
    local_path = str(entry.get("local_raw_path") or "").strip()
    local_meta = CATALOGUE_DIR / Path(local_path).parent / "metadata.json" if local_path else None
    local_bytes = None
    if local_meta and local_meta.exists():
        local_bytes = json.loads(local_meta.read_text(encoding="utf-8")).get("bytes")
    if not url:
        return {
            "dataset_id": dataset_id,
            "available": False,
            "message": "No download_url",
            "update_available": False,
        }
    try:
        headers = http_head(url)
    except urllib.error.HTTPError as exc:
        return {
            "dataset_id": dataset_id,
            "available": False,
            "message": f"HTTP {exc.code}",
            "update_available": False,
        }
    except urllib.error.URLError as exc:
        return {
            "dataset_id": dataset_id,
            "available": False,
            "message": str(exc.reason),
            "update_available": False,
        }
    remote_len = headers.get("content-length")
    update = False
    if remote_len is not None and local_bytes is not None:
        try:
            update = int(remote_len) != int(local_bytes)
        except ValueError:
            update = False
    return {
        "dataset_id": dataset_id,
        "available": True,
        "remote_content_length": remote_len,
        "local_bytes": local_bytes,
        "last_modified": headers.get("last-modified"),
        "update_available": update,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--json-out", type=Path, default=CATALOGUE_DIR / "update_check.json")
    parser.add_argument("--offline", action="store_true", help="Do not call network; report local metadata only")
    args = parser.parse_args()

    results: list[dict] = []
    if args.offline:
        for entry in catalogue_entries():
            local = str(entry.get("local_raw_path") or "")
            path = CATALOGUE_DIR / local if local and not local.endswith("/") else None
            exists = bool(path and path.exists())
            results.append(
                {
                    "dataset_id": entry["dataset_id"],
                    "available": exists,
                    "update_available": False,
                    "mode": "offline",
                    "local_path": local,
                }
            )
    else:
        try:
            results.append(check_toilet_map())
            results.append(check_geofabrik())
            for dataset_id in (
                "nsw_rest_areas",
                "nsw_boat_ramps",
                "nsw_ev_charging_locations",
                "gold_coast_caravan_parks",
            ):
                entry = next(row for row in catalogue_entries() if row["dataset_id"] == dataset_id)
                results.append(check_direct(entry))
        except urllib.error.URLError as exc:
            print(f"Network check failed: {exc}", file=sys.stderr)
            return 2

    payload = {"checked_at_utc": utc_now(), "results": results}
    args.json_out.parent.mkdir(parents=True, exist_ok=True)
    args.json_out.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")
    updates = [row for row in results if row.get("update_available")]
    print(f"Checked {len(results)} sources; updates_available={len(updates)}")
    for row in updates:
        print(f"  UPDATE {row['dataset_id']}: remote={row.get('remote_update_token') or row.get('remote_content_length')}")
    print(f"Wrote {args.json_out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
