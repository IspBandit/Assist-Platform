#!/usr/bin/env python3
"""Patch registry with discovery-pass extras and refresh checksums from disk."""
from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REG = ROOT / "data/sources/vanassist/registry/sources.json"


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def count_records(path: Path, fmt: str | None) -> int | None:
    if not path.is_file() or not fmt:
        return None
    try:
        if fmt.upper() == "CSV":
            with path.open("r", encoding="utf-8", errors="replace") as f:
                return max(0, sum(1 for _ in f) - 1)
        if fmt.upper() == "GEOJSON":
            data = json.loads(path.read_text(encoding="utf-8", errors="replace"))
            feats = data.get("features")
            return len(feats) if isinstance(feats, list) else None
    except Exception:
        return None
    return None


def main() -> None:
    data = json.loads(REG.read_text(encoding="utf-8"))
    sources = {s["source_id"]: s for s in data["sources"]}
    now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    extras = [
        {
            "source_id": "regional_barcoo_visitor_guide",
            "source_name": "Visit Barcoo Visitor Guide",
            "publisher": "Barcoo Shire Council",
            "jurisdiction": "QLD",
            "landing_page": "https://www.barcoo.qld.gov.au/council-services/visitor-information-centres",
            "download_url": "https://www.barcoo.qld.gov.au/images/explore-barcoo/17051%20-%20Visit%20Barcoo%202025%20-%20V9.pdf",
            "file_format": "PDF",
            "filename": "barcoo-visitor-guide.pdf",
            "local_path": "data/sources/vanassist/regional-guides/barcoo-visitor-guide.pdf",
            "licence": "Copyright — permission required",
            "attribution": "Barcoo Shire Council",
            "reuse_status": "AMBER_PERMISSION_REQUIRED",
            "category": "regional_guide",
            "import_status": "archive_only",
            "notes": "Official council visitor guide PDF archived. Do not extract into production without permission.",
            "refresh_method": "public PDF URL",
            "refresh_frequency": "annual",
        },
        {
            "source_id": "regional_drive_queensland_2026",
            "source_name": "Drive Queensland Drive Guide 2026",
            "publisher": "Drive Queensland",
            "jurisdiction": "QLD",
            "landing_page": "https://drivequeensland.com/drive-guide/",
            "download_url": "https://drivequeensland.b-cdn.net/wp-content/uploads/2025/07/Drive-Queensland-Magazine-2026.pdf",
            "file_format": "PDF",
            "filename": "drive-queensland-guide-2026.pdf",
            "local_path": "data/sources/vanassist/regional-guides/drive-queensland-guide-2026.pdf",
            "licence": "Copyright — permission required",
            "attribution": "Drive Queensland",
            "reuse_status": "AMBER_PERMISSION_REQUIRED",
            "category": "regional_guide",
            "import_status": "archive_only",
            "notes": "2026 Drive Guide PDF archived from official CDN.",
            "refresh_method": "public PDF URL",
            "refresh_frequency": "annual",
        },
        {
            "source_id": "vic_coastal_places_of_interest",
            "source_name": "Coastal Places of Interest",
            "publisher": "Victorian Government / DEECA / DataVic",
            "jurisdiction": "VIC",
            "landing_page": "https://discover.data.vic.gov.au/dataset/coastal-places-of-interest",
            "download_url": None,
            "file_format": "SHP",
            "filename": None,
            "local_path": None,
            "licence": "Creative Commons Attribution 4.0 International",
            "attribution": "© State of Victoria — Coastal Places of Interest",
            "reuse_status": "GREEN",
            "category": "campground,caravan_park,boat_ramp",
            "import_status": "importable_filtered",
            "notes": "Import only camping grounds / caravan parks / traveller facilities — not unrelated coastal POIs.",
            "refresh_method": "DataVic SHP download",
            "refresh_frequency": "as published",
        },
    ]

    for e in extras:
        local = e.get("local_path")
        if local:
            p = ROOT / local
            if p.is_file():
                e["sha256"] = sha256(p)
                e["retrieved_at"] = now
                e["record_count"] = None
                e["sync_action"] = "downloaded"
        sources[e["source_id"]] = {**sources.get(e["source_id"], {}), **e}

    sa = ROOT / "data/sources/vanassist/sa/StateMaintainedRestAreas_GDA2020.shp"
    if sa.is_file() and "sa_rest_areas_state_maintained" in sources:
        sources["sa_rest_areas_state_maintained"].update(
            {
                "local_path": "data/sources/vanassist/sa/StateMaintainedRestAreas_GDA2020.shp",
                "filename": "StateMaintainedRestAreas_GDA2020.shp",
                "file_format": "SHP",
                "sha256": sha256(sa),
                "retrieved_at": now,
                "sync_action": "copy",
                "sync_notes": "Copied from Assist RIC StateMaintainedRestAreas_shp (age warning applies)",
            }
        )

    entries = list(sources.values())
    raw = 0
    for e in entries:
        if e.get("record_count") is not None:
            raw += int(e["record_count"])
            continue
        local = e.get("local_path")
        if not local:
            continue
        c = count_records(ROOT / local, e.get("file_format"))
        if c is not None:
            e["record_count"] = c
            raw += c

    summary = data.get("summary") or {}
    summary.update(
        {
            "generated_at": now,
            "sources_registered": len(entries),
            "datasets_archived": sum(1 for e in entries if e.get("local_path")),
            "pdfs_archived": sum(
                1
                for e in entries
                if e.get("local_path") and str(e.get("file_format") or "").upper().startswith("PDF")
            ),
            "green": sum(1 for e in entries if e.get("reuse_status") == "GREEN"),
            "permission_granted": sum(1 for e in entries if e.get("reuse_status") == "PERMISSION_GRANTED"),
            "amber": sum(1 for e in entries if e.get("reuse_status") == "AMBER_PERMISSION_REQUIRED"),
            "yellow": sum(1 for e in entries if e.get("reuse_status") == "YELLOW_SPECIAL_LICENCE"),
            "unknown": sum(1 for e in entries if e.get("reuse_status") == "UNKNOWN_LICENCE"),
            "skip": sum(1 for e in entries if e.get("reuse_status") == "SKIP"),
            "raw_records_measurable": raw,
        }
    )
    REG.write_text(json.dumps({"summary": summary, "sources": entries}, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    checks = [f"{e['sha256']}  {e['local_path']}" for e in entries if e.get("sha256") and e.get("local_path")]
    (ROOT / "data/sources/vanassist/registry/checksums.sha256").write_text("\n".join(checks) + "\n", encoding="utf-8")

    # Refresh human register via sync_archive writer by importing function
    from sync_archive import write_markdown  # type: ignore

    write_markdown(entries, summary)
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
