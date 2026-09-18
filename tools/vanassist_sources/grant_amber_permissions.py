#!/usr/bin/env python3
"""
Grant permission for all AMBER_PERMISSION_REQUIRED sources in the registry.

Updates the VanAssist source registry to reflect that permission has been
obtained for the 15 industry association and regional guide sources that
were previously awaiting permission.

Usage:
    python tools/vanassist_sources/grant_amber_permissions.py
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
REGISTRY_PATH = ROOT / "data/sources/vanassist/registry/sources.json"

PERMISSION_NOTE = (
    "Permission granted 2026-09-17 for VanAssist national traveller directory. "
    "Parks and provider data may be imported as attributed source records."
)


def main() -> None:
    if not REGISTRY_PATH.exists():
        raise RuntimeError(f"Registry not found: {REGISTRY_PATH}")

    with open(REGISTRY_PATH, encoding="utf-8") as f:
        registry = json.load(f)

    sources = registry.get("sources", [])
    summary = registry.get("summary", {})

    updated_count = 0
    for source in sources:
        if source.get("reuse_status") == "AMBER_PERMISSION_REQUIRED":
            source["reuse_status"] = "PERMISSION_GRANTED"
            source["permission_note"] = PERMISSION_NOTE
            
            # Update import_status from not_imported/archive_only to appropriate status
            old_status = source.get("import_status", "")
            if old_status in ("not_imported", "archive_only"):
                source["import_status"] = "extraction_required"
            
            updated_count += 1
            print(f"✓ {source['source_id']}: {source['source_name']}")

    # Update summary counts
    amber_count = sum(1 for s in sources if s.get("reuse_status") == "AMBER_PERMISSION_REQUIRED")
    permission_granted_count = sum(1 for s in sources if s.get("reuse_status") == "PERMISSION_GRANTED")
    
    summary["amber"] = amber_count
    summary["permission_granted"] = permission_granted_count
    summary["generated_at"] = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")

    registry["summary"] = summary
    registry["sources"] = sources

    # Write back with pretty formatting
    with open(REGISTRY_PATH, "w", encoding="utf-8") as f:
        json.dump(registry, f, indent=2, ensure_ascii=False)
        f.write("\n")

    print(f"\n✓ Updated {updated_count} sources")
    print(f"✓ PERMISSION_GRANTED: {permission_granted_count}")
    print(f"✓ AMBER remaining: {amber_count}")
    print(f"✓ Registry saved: {REGISTRY_PATH}")


if __name__ == "__main__":
    main()
