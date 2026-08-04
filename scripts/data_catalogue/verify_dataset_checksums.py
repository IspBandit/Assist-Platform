#!/usr/bin/env python3
"""Verify SHA-256 checksums for downloaded data_catalogue/raw datasets."""

from __future__ import annotations

import argparse
import hashlib
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
CATALOGUE_DIR = ROOT / "data_catalogue" / "raw"


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def verify_folder(folder: Path) -> dict:
    checksum_file = folder / "checksum.sha256"
    metadata_file = folder / "metadata.json"
    if not checksum_file.exists():
        return {"folder": str(folder), "ok": False, "error": "missing checksum.sha256"}
    line = checksum_file.read_text(encoding="utf-8").strip().splitlines()[0]
    expected, _, name = line.partition("  ")
    expected = expected.strip()
    name = name.strip() or "source_file"
    # Prefer explicit source filenames used by this pack
    candidates = [
        folder / name,
        folder / "Toiletmap.csv",
        folder / "rest-areas-csv-format.csv",
        folder / "boating_ramps.csv",
        folder / "ev_charging_locations.csv",
        folder / "caravan_parks.geojson",
        folder / "australia-latest.osm.pbf",
    ]
    target = next((path for path in candidates if path.exists() and path.is_file()), None)
    if target is None:
        # Fallback: largest non-sidecar file
        files = [
            path
            for path in folder.iterdir()
            if path.is_file()
            and path.name not in {"checksum.sha256", "metadata.json", "licence.txt", "attribution.txt"}
        ]
        if not files:
            return {"folder": str(folder), "ok": False, "error": "no source file found"}
        target = max(files, key=lambda path: path.stat().st_size)
    actual = sha256_file(target)
    ok = actual.lower() == expected.lower()
    result = {
        "folder": str(folder),
        "file": target.name,
        "expected": expected,
        "actual": actual,
        "ok": ok,
    }
    if metadata_file.exists():
        meta = json.loads(metadata_file.read_text(encoding="utf-8"))
        if meta.get("sha256") and str(meta["sha256"]).lower() != actual.lower():
            result["ok"] = False
            result["error"] = "metadata.json sha256 mismatch"
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--raw-dir", type=Path, default=CATALOGUE_DIR)
    args = parser.parse_args()
    if not args.raw_dir.exists():
        print(f"No raw directory at {args.raw_dir}", file=sys.stderr)
        return 1
    results = []
    for folder in sorted(path for path in args.raw_dir.iterdir() if path.is_dir()):
        if not (folder / "checksum.sha256").exists():
            # Geofabrik metadata-only folder is acceptable without PBF checksum
            if (folder / "metadata.json").exists() and not any(
                child.suffix.lower() in {".csv", ".json", ".geojson", ".pbf", ".zip"}
                for child in folder.iterdir()
                if child.is_file() and child.name != "metadata.json"
            ):
                results.append({"folder": str(folder), "ok": True, "note": "metadata-only"})
                continue
            results.append({"folder": str(folder), "ok": False, "error": "missing checksum.sha256"})
            continue
        results.append(verify_folder(folder))
    failed = [row for row in results if not row.get("ok")]
    for row in results:
        status = "OK" if row.get("ok") else "FAIL"
        print(f"{status} {row.get('folder')} {row.get('error') or row.get('note') or row.get('file')}")
    if failed:
        print(f"{len(failed)} checksum verification failure(s)", file=sys.stderr)
        return 1
    print(f"Verified {len(results)} source folder(s)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
