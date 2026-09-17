#!/usr/bin/env python3
"""
VanAssist national traveller-data source archive sync (DATA-012 / DATA-011A companion).

Repeatable workflow:
  1. Prefer hardlink/copy from Assist RIC raw packs when present
  2. Else download from official public URLs
  3. SHA256 checksum every stored artefact
  4. Write machine + human registries
  5. Never scrape login/paywalled content; never import AMBER into production

Usage:
  python tools/vanassist_sources/sync_archive.py
  python tools/vanassist_sources/sync_archive.py --refresh-green
  python tools/vanassist_sources/sync_archive.py --amber-only
"""
from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import ssl
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[2]
ARCHIVE = ROOT / "data" / "sources" / "vanassist"
REGISTRY_DIR = ARCHIVE / "registry"
RIC_RAW = Path(r"D:\Works _in_progress\assist-ric\data_catalogue\raw")
UA = "AssistPlatform-VanAssistSourceSync/1.0 (+https://vanassist.com.au; data-acquisition; contact=platform)"

REUSE_GREEN = "GREEN"
REUSE_AMBER = "AMBER_PERMISSION_REQUIRED"
REUSE_YELLOW = "YELLOW_SPECIAL_LICENCE"
REUSE_UNKNOWN = "UNKNOWN_LICENCE"
REUSE_SKIP = "SKIP"
REUSE_GRANTED = "PERMISSION_GRANTED"

# Official GREEN (and related) sources. download_url may be None when only RIC copy or API login.
SOURCES: list[dict[str, Any]] = [
    # --- AUSTRALIA ---
    {
        "source_id": "au_national_public_toilet_map",
        "source_name": "National Public Toilet Map",
        "publisher": "Australian Government / Department of Health, Disability and Ageing",
        "jurisdiction": "AU",
        "landing_page": "https://data.gov.au/data/dataset/national-public-toilet-map",
        "download_url": "https://data.gov.au/data/dataset/553b3049-2b8b-46a2-95e6-640d7986a8c1/resource/34076296-6692-4e30-b627-67b7c4eb1027/download/toiletmapexport_260901_074429.csv",
        "ckan_resource_id": "34076296-6692-4e30-b627-67b7c4eb1027",
        "file_format": "CSV",
        "filename": "Toiletmap.csv",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "© Commonwealth of Australia — National Public Toilet Map (data.gov.au)",
        "reuse_status": REUSE_GREEN,
        "category": "public_toilet,dump_point,drinking_water,public_shower",
        "import_status": "importable",
        "subdir": "australia",
        "ric_key": "au_national_public_toilet_map",
        "notes": "Highest priority. Monthly publish on data.gov.au. CKAN resource id stable; download filename rotates.",
        "refresh_method": "ckan_resource + archive checksum",
        "refresh_frequency": "monthly",
    },
    {
        "source_id": "au_wikidata_enrichment",
        "source_name": "Wikidata (targeted enrichment only)",
        "publisher": "Wikimedia Foundation / Wikidata community",
        "jurisdiction": "AU",
        "landing_page": "https://www.wikidata.org/",
        "download_url": None,
        "file_format": "SPARQL",
        "filename": None,
        "licence": "CC0",
        "attribution": "Wikidata contributors",
        "reuse_status": REUSE_GREEN,
        "category": "enrichment",
        "import_status": "deferred_targeted_only",
        "subdir": "australia",
        "notes": "Do not dump entire database. Use only for identity/enrichment matching with separable attribution.",
        "refresh_method": "SPARQL as needed",
        "refresh_frequency": "on demand",
    },
    {
        "source_id": "au_national_formal_rest_areas",
        "source_name": "National Formal Rest Areas",
        "publisher": "Australian Government (NFDH / data.gov.au)",
        "jurisdiction": "AU",
        "landing_page": "https://data.gov.au/data/dataset/national-formal-rest-areas",
        "download_url": None,
        "file_format": "CSV",
        "filename": "national-formal-rest-areas.csv",
        "licence": "Unspecified on portal — do not treat as reusable without confirmation",
        "attribution": "National Formal Rest Areas — attribution as published if licence clarified",
        "reuse_status": REUSE_UNKNOWN,
        "category": "rest_area",
        "import_status": "archived_not_imported",
        "subdir": "australia",
        "ric_key": "au_national_formal_rest_areas",
        "notes": "Archive only until licence explicitly permits reuse. Do not present as recently verified.",
        "refresh_method": "manual licence check then download",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "portal_osm_australia",
        "source_name": "OpenStreetMap Australia extract",
        "publisher": "OpenStreetMap contributors / Geofabrik",
        "jurisdiction": "AU",
        "landing_page": "https://www.openstreetmap.org/copyright",
        "download_url": "https://download.geofabrik.de/australia-oceania/australia-latest.osm.pbf",
        "file_format": "PBF",
        "filename": "australia-latest.osm.pbf",
        "licence": "ODbL 1.0",
        "attribution": "© OpenStreetMap contributors",
        "reuse_status": REUSE_YELLOW,
        "category": "enrichment",
        "import_status": "not_mixed_into_proprietary_db",
        "subdir": "australia",
        "notes": "YELLOW_SPECIAL_LICENCE. Do not mix ODbL-derived data into proprietary VanAssist DB without separate approved implementation.",
        "refresh_method": "Geofabrik extract (opt-in)",
        "refresh_frequency": "daily upstream; local opt-in",
        "skip_download_default": True,
    },
    # --- QLD ---
    {
        "source_id": "qld_roadside_amenities",
        "source_name": "Roadside amenities - Queensland",
        "publisher": "Queensland Government — Transport and Main Roads",
        "jurisdiction": "QLD",
        "landing_page": "https://www.data.qld.gov.au/dataset/roadside-amenities-queensland",
        "download_url": "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Transportation/StateRoadInformation/MapServer/17/query?where=1%3D1&outFields=*&f=geojson&resultRecordCount=2000",
        "file_format": "GeoJSON",
        "filename": "qld_roadside_amenities.geojson",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "© State of Queensland (Transport and Main Roads) — Roadside amenities",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area,dump_point,roadside_facility",
        "import_status": "importable",
        "subdir": "qld",
        "ric_key": "qld_roadside_amenities",
        "arcgis_paginate": True,
        "notes": "Includes roadside amenities, facilities and effluent dump sites. Prefer live ArcGIS GeoJSON over stale portal HTML mirror.",
        "refresh_method": "ArcGIS MapServer query paginated",
        "refresh_frequency": "quarterly / as published",
    },
    {
        "source_id": "qld_operational_boat_facilities",
        "source_name": "QLD Operational Boat Facilities",
        "publisher": "Queensland Government — Transport and Main Roads",
        "jurisdiction": "QLD",
        "landing_page": "https://www.data.qld.gov.au/",
        "download_url": "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Transportation/StateRoadInformation/MapServer/55/query?where=1%3D1&outFields=*&f=geojson&resultRecordCount=2000",
        "file_format": "GeoJSON",
        "filename": "qld_operational_boat_facilities.geojson",
        "licence": "Creative Commons Attribution 3.0 Australia (confirm on portal)",
        "attribution": "© State of Queensland (Transport and Main Roads) — Operational boat facilities",
        "reuse_status": REUSE_GREEN,
        "category": "boat_ramp",
        "import_status": "importable",
        "subdir": "qld",
        "ric_key": "qld_operational_boat_facilities",
        "arcgis_paginate": True,
        "notes": "Boat ramps / operational boat facilities.",
        "refresh_method": "ArcGIS MapServer query",
        "refresh_frequency": "as published",
    },
    # --- NSW ---
    {
        "source_id": "nsw_rest_areas",
        "source_name": "NSW Rest Areas",
        "publisher": "Transport for NSW",
        "jurisdiction": "NSW",
        "landing_page": "https://opendata.transport.nsw.gov.au/dataset/nsw-rest-areas",
        "download_url": None,
        "file_format": "CSV",
        "filename": "rest-areas-csv-format.csv",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "Transport for NSW — NSW Rest Areas",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable",
        "subdir": "nsw",
        "ric_key": "nsw_rest_areas",
        "notes": "Use CURRENT CSV/API resource. Do NOT use 'API Generated CSV - NO LONGER UPDATED'. TfNSW hub may require login; prefer RIC-synced or data.gov.au mirror when available.",
        "refresh_method": "TfNSW Open Data Hub / RIC sync",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "nsw_rest_area_temporary_closures",
        "source_name": "NSW Rest Area Temporary Closures",
        "publisher": "Transport for NSW",
        "jurisdiction": "NSW",
        "landing_page": "https://opendata.transport.nsw.gov.au/dataset/nsw-rest-areas",
        "download_url": None,
        "file_format": "CSV",
        "filename": "nsw-rest-area-temporary-closures.csv",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "Transport for NSW — NSW Rest Area Temporary Closures",
        "reuse_status": REUSE_GREEN,
        "category": "operational_status",
        "import_status": "operational_overlay_not_facility",
        "subdir": "nsw",
        "notes": "Operational/status overlay only — not a separate facility provider catalogue.",
        "refresh_method": "TfNSW Open Data Hub",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "nsw_boat_ramps",
        "source_name": "NSW Boat Ramps",
        "publisher": "Transport for NSW",
        "jurisdiction": "NSW",
        "landing_page": "https://opendata.transport.nsw.gov.au/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "nsw_boat_ramps.geojson",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "Transport for NSW — Boat Ramps",
        "reuse_status": REUSE_GREEN,
        "category": "boat_ramp",
        "import_status": "importable",
        "subdir": "nsw",
        "ric_key": "nsw_boat_ramps",
        "notes": "Synced from Assist RIC ready pack when local hub login is required.",
        "refresh_method": "RIC / TfNSW",
        "refresh_frequency": "as published",
    },
    # --- VIC ---
    {
        "source_id": "vic_recreation_sites",
        "source_name": "Recreation Sites (State Forest)",
        "publisher": "Victorian Government / DataVic",
        "jurisdiction": "VIC",
        "landing_page": "https://discover.data.vic.gov.au/dataset/recreation-sites",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "recreation_sites.geojson",
        "licence": "Creative Commons Attribution 4.0 International",
        "attribution": "© State of Victoria — Recreation Sites (DataVic)",
        "reuse_status": REUSE_GREEN,
        "category": "campground,picnic_area,rest_area",
        "import_status": "importable",
        "subdir": "vic",
        "ric_key": "vic_recreation_sites",
        "notes": "State forest recreation sites. Join to recreation assets where possible.",
        "refresh_method": "DataVic WFS / RIC",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "vic_recreation_assets",
        "source_name": "Recreation Assets",
        "publisher": "Victorian Government / DataVic",
        "jurisdiction": "VIC",
        "landing_page": "https://discover.data.vic.gov.au/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "recreation_assets.geojson",
        "licence": "Creative Commons Attribution 4.0 International",
        "attribution": "© State of Victoria — Recreation Assets (DataVic)",
        "reuse_status": REUSE_GREEN,
        "category": "public_toilet,picnic_area,other_essential",
        "import_status": "importable_join_to_sites",
        "subdir": "vic",
        "notes": "Toilets, picnic shelters, viewing facilities linked to recreation sites. Discover current DataVic resource on refresh.",
        "refresh_method": "DataVic search + download",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "parks_victoria_campgrounds",
        "source_name": "Parks Victoria Campgrounds",
        "publisher": "Parks Victoria / DataVic",
        "jurisdiction": "VIC",
        "landing_page": "https://discover.data.vic.gov.au/",
        "download_url": None,
        "file_format": "SHP/ZIP",
        "filename": "parks_victoria_campgrounds.zip",
        "licence": "Creative Commons Attribution 4.0 International",
        "attribution": "© Parks Victoria — Campgrounds",
        "reuse_status": REUSE_GREEN,
        "category": "campground",
        "import_status": "importable_as_stays",
        "subdir": "vic",
        "ric_key": "parks_victoria_campgrounds",
        "notes": "Campgrounds belong in stays (caravan_parks), not traveller_facilities.",
        "refresh_method": "DataVic / RIC",
        "refresh_frequency": "as published",
    },
    # --- SA ---
    {
        "source_id": "sa_rest_areas_state_maintained",
        "source_name": "Rest Areas - State Maintained (SA)",
        "publisher": "Location SA / Department for Infrastructure and Transport",
        "jurisdiction": "SA",
        "landing_page": "https://data.sa.gov.au/",
        "download_url": None,
        "file_format": "SHP/ZIP",
        "filename": "StateMaintainedRestAreas.zip",
        "licence": "AusGOAL Creative Commons Attribution",
        "attribution": "© Government of South Australia — Rest Areas State Maintained",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable_with_age_warning",
        "subdir": "sa",
        "ric_key": "sa_rest_areas_state_maintained",
        "notes": "Dataset may be old. Store source update date prominently. Do not represent as recently field-verified.",
        "refresh_method": "Location SA / data.gov.au mirror / RIC SHP",
        "refresh_frequency": "as published (may be stale)",
    },
    # --- WA ---
    {
        "source_id": "wa_heavy_vehicle_rest_areas",
        "source_name": "Main Roads WA Heavy Vehicle Rest Areas",
        "publisher": "Main Roads Western Australia",
        "jurisdiction": "WA",
        "landing_page": "https://catalogue.data.wa.gov.au/dataset/mrwa-heavy-vehicle-rest-area",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "heavy_vehicle_rest_area.geojson",
        "licence": "Creative Commons Attribution 4.0 International",
        "attribution": "© Main Roads Western Australia — Heavy Vehicle Rest Areas",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable_as_hv_rest_area",
        "subdir": "wa",
        "ric_key": "wa_heavy_vehicle_rest_areas",
        "notes": "Primarily heavy-vehicle rest areas. Do NOT auto-label as caravan camping or overnight camping.",
        "refresh_method": "Main Roads ArcGIS / data.wa.gov.au / RIC",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "wa_major_rest_areas",
        "source_name": "Main Roads WA Major Rest Areas",
        "publisher": "Main Roads Western Australia",
        "jurisdiction": "WA",
        "landing_page": "https://portal-mainroads.opendata.arcgis.com/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "major_rest_areas.geojson",
        "licence": "Creative Commons Attribution 4.0 / 3.0 AU as published",
        "attribution": "© Main Roads Western Australia — Major Rest Areas",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable_as_rest_area",
        "subdir": "wa",
        "ric_key": "wa_major_rest_areas",
        "notes": "Classify as rest_area, not caravan park.",
        "refresh_method": "Main Roads ArcGIS / RIC",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "wa_minor_rest_areas",
        "source_name": "Main Roads WA Minor Rest Areas",
        "publisher": "Main Roads Western Australia",
        "jurisdiction": "WA",
        "landing_page": "https://data.gov.au/data/dataset/mrwa-minor-rest-area",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "minor_rest_areas.geojson",
        "licence": "Creative Commons Attribution 3.0 Australia",
        "attribution": "© Main Roads Western Australia — Minor Rest Areas",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable_as_rest_area",
        "subdir": "wa",
        "ric_key": "wa_minor_rest_areas",
        "notes": "Classify as rest_area.",
        "refresh_method": "Main Roads ArcGIS / RIC",
        "refresh_frequency": "as published",
    },
    # --- TAS ---
    {
        "source_id": "tas_roadside_stops",
        "source_name": "Tasmania Roadside Stops",
        "publisher": "Department of State Growth / LIST",
        "jurisdiction": "TAS",
        "landing_page": "https://www.thelist.tas.gov.au/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "tas_roadside_stops.geojson",
        "licence": "Cite Tasmania / confirm LIST layer licence",
        "attribution": "State of Tasmania — Roadside Stops",
        "reuse_status": REUSE_GREEN,
        "category": "rest_area",
        "import_status": "importable",
        "subdir": "tas",
        "ric_key": "tas_roadside_stops",
        "notes": "Verify exact LIST layer licence on each refresh.",
        "refresh_method": "LIST ArcGIS / RIC",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "tas_boat_ramps",
        "source_name": "Tasmania Boat Ramps (LIST)",
        "publisher": "LIST Tasmania",
        "jurisdiction": "TAS",
        "landing_page": "https://www.thelist.tas.gov.au/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "tas_boat_ramps.geojson",
        "licence": "Cite the LIST — verify exact layer licence",
        "attribution": "theLIST © State of Tasmania",
        "reuse_status": REUSE_GREEN,
        "category": "boat_ramp",
        "import_status": "importable",
        "subdir": "tas",
        "ric_key": "tas_boat_ramps",
        "notes": "Do not assume all LIST layers share identical licensing.",
        "refresh_method": "LIST ArcGIS / RIC",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "tas_list_camping_caravan_layers",
        "source_name": "Tasmania LIST camping / caravan / dump layers",
        "publisher": "LIST Tasmania",
        "jurisdiction": "TAS",
        "landing_page": "https://www.thelist.tas.gov.au/",
        "download_url": None,
        "file_format": "various",
        "filename": None,
        "licence": "Per-layer — verify before import",
        "attribution": "theLIST © State of Tasmania",
        "reuse_status": REUSE_UNKNOWN,
        "category": "campground,caravan_park,dump_point",
        "import_status": "licence_check_required",
        "subdir": "tas",
        "notes": "Campground/Site, Camping Site, Camping Area, Caravan Park, Caravan Dump Point — verify each layer licence. UNKNOWN until confirmed.",
        "refresh_method": "LIST catalogue search",
        "refresh_frequency": "on demand",
    },
    # --- NT ---
    {
        "source_id": "nt_campground_web_pages",
        "source_name": "NT Government campground web pages",
        "publisher": "Northern Territory Government",
        "jurisdiction": "NT",
        "landing_page": "https://nt.gov.au/",
        "download_url": None,
        "file_format": "HTML",
        "filename": None,
        "licence": "No bulk reuse licence identified for page content",
        "attribution": "Northern Territory Government",
        "reuse_status": REUSE_AMBER,
        "category": "campground",
        "import_status": "not_imported",
        "subdir": "nt",
        "notes": "Useful fields exist on pages but do not bulk scrape unless explicit licence allows.",
        "refresh_method": "permission request / open-data search",
        "refresh_frequency": "n/a",
    },
    # --- ACT ---
    {
        "source_id": "act_public_toilet_assets",
        "source_name": "ACT Public Toilet Assets",
        "publisher": "ACT Government",
        "jurisdiction": "ACT",
        "landing_page": "https://www.data.act.gov.au/",
        "download_url": None,
        "file_format": "GeoJSON",
        "filename": "act_public_toilet_assets.geojson",
        "licence": "ACT open data terms as published",
        "attribution": "© Australian Capital Territory",
        "reuse_status": REUSE_GREEN,
        "category": "public_toilet",
        "import_status": "importable",
        "subdir": "act",
        "ric_key": "act_public_toilet_assets",
        "notes": "Discovered via Assist RIC ready packs / ACT FeatureServer.",
        "refresh_method": "ACT FeatureServer / RIC",
        "refresh_frequency": "as published",
    },
    # --- CPAQ (authorised) ---
    {
        "source_id": "cpaq_explore_qld_2026",
        "source_name": "Explore Queensland Caravan Parks Directory 2026",
        "publisher": "Caravan Parks Association of Queensland Ltd (CPAQ) / Caravanning Queensland",
        "jurisdiction": "QLD",
        "landing_page": "https://www.caravanqld.com.au/queensland-caravan-park-directory/",
        "download_url": None,
        "file_format": "PDF + extracted JSON",
        "filename": "cpaq-2026-directory.pdf",
        "licence": "Permission granted directly to Glen by Vee (CPAQ) for parks and services reuse",
        "attribution": "Explore Queensland Caravan Parks Directory 2026 — Caravan Parks Association of Queensland Ltd. Used with permission.",
        "reuse_status": REUSE_GRANTED,
        "category": "caravan_park,provider_services",
        "import_status": "importable_authorised",
        "subdir": "industry",
        "local_pdf_candidates": [
            str(ROOT / "storage/imports/cpaq-2026-directory.pdf"),
        ],
        "extracted_json_dir": str(ROOT / "database/seeds/cpaq-2026"),
        "notes": "AUTHORISED. Permission supplied verbally by Vee at CPAQ to Glen. Prefer local supplied PDF. Extraction via tools/cpaq/extract_cpaq_directory.py.",
        "refresh_method": "annual directory edition + re-extract",
        "refresh_frequency": "annual",
        "permission_note": "Verbal permission from Vee (CPAQ) to Glen for parks and services use.",
    },
    # --- Industry AMBER guides ---
    {
        "source_id": "industry_nsw_ccia_holiday_guide_2026",
        "source_name": "2026 NSW Caravan & Camping Parks & Products Holiday Guide",
        "publisher": "Caravan & Camping Industry Association NSW",
        "jurisdiction": "NSW",
        "landing_page": "https://www.cciansw.asn.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "nsw-ccia-holiday-guide-2026.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravan & Camping Industry Association NSW",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Archive/reference only. Do not import contents without written permission.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_wa_caravan_camping_guide_2026",
        "source_name": "Caravan & Camping WA Guide 2026",
        "publisher": "Caravan Industry Association Western Australia",
        "jurisdiction": "WA",
        "landing_page": "https://www.caravanwa.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "caravan-camping-wa-guide-2026.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravan Industry Association Western Australia",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Archive only.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_tas_caravan_guide_2026",
        "source_name": "Caravan Tasmania 2026 Guide",
        "publisher": "Caravanning Tasmania",
        "jurisdiction": "TAS",
        "landing_page": "https://www.caravaningtasmania.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "caravan-tasmania-2026-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravanning Tasmania",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Public PDF may exist; archive only until permission.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_sa_caravan_camping_guide",
        "source_name": "SA Parks / Caravan & Camping SA guide",
        "publisher": "Caravan & Camping SA / SA Parks",
        "jurisdiction": "SA",
        "landing_page": "https://www.caravanandcampingsa.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "sa-caravan-camping-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravan & Camping SA",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "~150 member parks expected in current edition. Archive only.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_nt_caravanning_guide",
        "source_name": "Caravanning NT parks map / visitor guide",
        "publisher": "Caravanning NT",
        "jurisdiction": "NT",
        "landing_page": "https://www.caravannt.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "caravanning-nt-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravanning NT",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Archive only.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_vic_caravan_residential_guide",
        "source_name": "Caravan & Residential Parks Victoria accommodation guide",
        "publisher": "Caravan Industry Victoria",
        "jurisdiction": "VIC",
        "landing_page": "https://www.caravanvictoria.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "vic-caravan-residential-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Caravan Industry Victoria",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Archive only.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_big4_holiday_guide_2026",
        "source_name": "BIG4 Holiday Guide 2026",
        "publisher": "BIG4 Holiday Parks of Australia",
        "jurisdiction": "AU",
        "landing_page": "https://www.big4.com.au/",
        "download_url": "https://assets.big4.com.au/media/jduhbi2u/big4-holiday-guide-2026-flipbook.pdf",
        "file_format": "PDF",
        "filename": "big4-holiday-guide-2026.pdf",
        "licence": "Copyright — permission required",
        "attribution": "BIG4 Holiday Parks of Australia",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Publicly downloadable PDF archived. Do NOT scrape BIG4 park data into production.",
        "refresh_method": "public PDF URL",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_gday_parks_guide",
        "source_name": "G'day Parks National/Digital Guide",
        "publisher": "G'day Group",
        "jurisdiction": "AU",
        "landing_page": "https://www.gdayparks.com.au/",
        "download_url": None,
        "file_format": "PDF/web",
        "filename": "gday-parks-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "G'day Parks",
        "reuse_status": REUSE_AMBER,
        "category": "industry_guide",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Do not scrape website. Archive public guide only if freely downloadable.",
        "refresh_method": "public download if available",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "industry_ciaa_services_listing",
        "source_name": "Caravan Industry Association of Australia — Services Listing",
        "publisher": "Caravan Industry Association of Australia",
        "jurisdiction": "AU",
        "landing_page": "https://www.caravanindustry.com.au/",
        "download_url": None,
        "file_format": "web",
        "filename": None,
        "licence": "Copyright — permission required",
        "attribution": "Caravan Industry Association of Australia",
        "reuse_status": REUSE_AMBER,
        "category": "industry_directory",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Record public national Services Listing categories; do not scrape into production yet.",
        "refresh_method": "permission request",
        "refresh_frequency": "n/a",
    },
    {
        "source_id": "industry_national_b2b_directory",
        "source_name": "National caravan industry B2B directory",
        "publisher": "Industry association / commercial directory",
        "jurisdiction": "AU",
        "landing_page": "https://www.caravanindustry.com.au/",
        "download_url": None,
        "file_format": "web",
        "filename": None,
        "licence": "Copyright — permission required",
        "attribution": "As published by directory owner",
        "reuse_status": REUSE_AMBER,
        "category": "industry_directory",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Record existence only until permission.",
        "refresh_method": "permission request",
        "refresh_frequency": "n/a",
    },
    {
        "source_id": "industry_cmca_dump_points",
        "source_name": "CMCA dump-point list/map",
        "publisher": "Campervan & Motorhome Club of Australia",
        "jurisdiction": "AU",
        "landing_page": "https://www.cmca.net.au/",
        "download_url": None,
        "file_format": "PDF/web",
        "filename": "cmca-dump-points.pdf",
        "licence": "Copyright / membership terms — permission required unless terms explicitly permit",
        "attribution": "Campervan & Motorhome Club of Australia",
        "reuse_status": REUSE_AMBER,
        "category": "dump_point",
        "import_status": "archive_only",
        "subdir": "industry",
        "notes": "Archive/reference only unless current terms explicitly permit reuse.",
        "refresh_method": "public download if available + licence review",
        "refresh_frequency": "as published",
    },
    # --- Regional guides ---
    {
        "source_id": "regional_drive_queensland_2026",
        "source_name": "Drive Queensland Drive Guide 2025/2026",
        "publisher": "Drive Queensland",
        "jurisdiction": "QLD",
        "landing_page": "https://drivequeensland.com/drive-guide/",
        "download_url": "https://drivequeensland.b-cdn.net/wp-content/uploads/2025/07/Drive-Queensland-Magazine-2026.pdf",
        "file_format": "PDF",
        "filename": "drive-queensland-guide-2026.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Drive Queensland",
        "reuse_status": REUSE_AMBER,
        "category": "regional_guide",
        "import_status": "archive_only",
        "subdir": "regional-guides",
        "notes": "Official tourism drive guide. PDF from Drive Queensland CDN.",
        "refresh_method": "public PDF URL",
        "refresh_frequency": "annual",
    },
    {
        "source_id": "regional_barcoo_visitor_guide",
        "source_name": "Visit Barcoo Visitor Guide",
        "publisher": "Barcoo Shire Council",
        "jurisdiction": "QLD",
        "landing_page": "https://www.barcoo.qld.gov.au/council-services/visitor-information-centres",
        "download_url": "https://www.barcoo.qld.gov.au/images/explore-barcoo/17051%20-%20Visit%20Barcoo%202025%20-%20V9.pdf",
        "file_format": "PDF",
        "filename": "barcoo-visitor-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Barcoo Shire Council",
        "reuse_status": REUSE_AMBER,
        "category": "regional_guide",
        "import_status": "archive_only",
        "subdir": "regional-guides",
        "notes": "Official council visitor guide. Archive only.",
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
        "licence": "Creative Commons Attribution 4.0 International",
        "attribution": "© State of Victoria — Coastal Places of Interest",
        "reuse_status": REUSE_GREEN,
        "category": "campground,caravan_park,boat_ramp",
        "import_status": "importable_filtered",
        "subdir": "vic",
        "notes": "Import only camping grounds / caravan parks / traveller facilities — not unrelated coastal POIs.",
        "refresh_method": "DataVic SHP download",
        "refresh_frequency": "as published",
    },
    {
        "source_id": "regional_scenic_rim_visitor_guide",
        "source_name": "Scenic Rim Visitor Guide",
        "publisher": "Scenic Rim Regional Council / tourism",
        "jurisdiction": "QLD",
        "landing_page": "https://www.visitscenicrim.com.au/",
        "download_url": None,
        "file_format": "PDF",
        "filename": "scenic-rim-visitor-guide.pdf",
        "licence": "Copyright — permission required",
        "attribution": "Scenic Rim tourism / council as published",
        "reuse_status": REUSE_AMBER,
        "category": "regional_guide",
        "import_status": "archive_only",
        "subdir": "regional-guides",
        "notes": "Archive if publicly downloadable.",
        "refresh_method": "official tourism PDF search",
        "refresh_frequency": "annual",
    },
]


def utc_now() -> str:
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def sha256_file(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def count_records(path: Path, fmt: str) -> int | None:
    try:
        if fmt.upper() == "CSV":
            with path.open("r", encoding="utf-8", errors="replace") as f:
                # subtract header
                n = sum(1 for _ in f)
            return max(0, n - 1)
        if fmt.upper() == "GEOJSON":
            data = json.loads(path.read_text(encoding="utf-8", errors="replace"))
            feats = data.get("features")
            if isinstance(feats, list):
                return len(feats)
        if fmt.upper() in {"JSON", "JSONL"} and path.suffix.lower() == ".json":
            data = json.loads(path.read_text(encoding="utf-8", errors="replace"))
            if isinstance(data, list):
                return len(data)
    except Exception:
        return None
    return None


def ensure_dirs() -> None:
    for name in (
        "registry", "australia", "qld", "nsw", "vic", "tas", "sa", "wa", "nt", "act",
        "industry", "regional-guides", "raw", "normalized", "reports",
    ):
        (ARCHIVE / name).mkdir(parents=True, exist_ok=True)


def http_get(url: str, dest: Path, timeout: int = 180) -> None:
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "*/*"})
    ctx = ssl.create_default_context()
    with urllib.request.urlopen(req, timeout=timeout, context=ctx) as resp:
        dest.parent.mkdir(parents=True, exist_ok=True)
        tmp = dest.with_suffix(dest.suffix + ".part")
        with tmp.open("wb") as out:
            shutil.copyfileobj(resp, out)
        tmp.replace(dest)


def resolve_ckan_download(resource_id: str) -> str | None:
    api = f"https://data.gov.au/data/api/3/action/resource_show?id={resource_id}"
    req = urllib.request.Request(api, headers={"User-Agent": UA})
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            payload = json.loads(resp.read().decode("utf-8"))
        return (payload.get("result") or {}).get("url")
    except Exception as exc:
        print(f"  CKAN resolve failed: {exc}", file=sys.stderr)
        return None


def arcgis_download_all(base_query_url: str, dest: Path) -> None:
    """Paginate ArcGIS GeoJSON export into one FeatureCollection."""
    features: list[Any] = []
    offset = 0
    page_size = 2000
    while True:
        sep = "&" if "?" in base_query_url else "?"
        url = f"{base_query_url}{sep}resultOffset={offset}"
        if "resultRecordCount=" not in url:
            url += f"&resultRecordCount={page_size}"
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        with urllib.request.urlopen(req, timeout=180) as resp:
            page = json.loads(resp.read().decode("utf-8"))
        batch = page.get("features") or []
        features.extend(batch)
        print(f"  ArcGIS page offset={offset} got={len(batch)} total={len(features)}")
        if len(batch) < page_size or page.get("exceededTransferLimit") is not True:
            if len(batch) == 0:
                break
            if len(batch) < page_size:
                break
        offset += page_size
        if offset > 100_000:
            break
        time.sleep(0.3)
    dest.parent.mkdir(parents=True, exist_ok=True)
    dest.write_text(
        json.dumps({"type": "FeatureCollection", "features": features}, ensure_ascii=False),
        encoding="utf-8",
    )


def find_ric_file(ric_key: str, preferred_name: str | None) -> Path | None:
    folder = RIC_RAW / ric_key
    if not folder.is_dir():
        # alternate keys sometimes differ
        for alt in RIC_RAW.iterdir() if RIC_RAW.is_dir() else []:
            if alt.is_dir() and (alt.name == ric_key or alt.name.replace("-", "_") == ric_key):
                folder = alt
                break
        else:
            return None
    if preferred_name:
        candidate = folder / preferred_name
        if candidate.is_file():
            return candidate
    # prefer geojson/csv over sidecars
    for pattern in ("*.geojson", "*.csv", "*.json", "*.zip", "*.shp", "*.pdf"):
        matches = sorted(folder.glob(pattern), key=lambda p: p.stat().st_mtime, reverse=True)
        matches = [m for m in matches if m.name.lower() not in {"metadata.json"}]
        if matches:
            return matches[0]
    return None


def copy_or_link(src: Path, dest: Path) -> str:
    dest.parent.mkdir(parents=True, exist_ok=True)
    if dest.exists():
        dest.unlink()
    try:
        os.link(src, dest)
        return "hardlink"
    except OSError:
        shutil.copy2(src, dest)
        return "copy"


def sync_source(src: dict[str, Any], refresh: bool, amber_only: bool, green_only: bool) -> dict[str, Any]:
    status = src.get("reuse_status")
    if amber_only and status not in {REUSE_AMBER, REUSE_GRANTED}:
        return finalize(
            src,
            utc_now(),
            None,
            None,
            None,
            "skipped_filter",
            ["Skipped by --amber-only filter"],
        )
    if green_only and status not in {REUSE_GREEN, REUSE_GRANTED}:
        # Still emit full registry metadata; do not live-download non-green.
        ric_key = src.get("ric_key")
        filename = src.get("filename")
        dest = (ARCHIVE / str(src["subdir"]) / filename) if filename else None
        local_path = None
        sha = None
        record_count = None
        action = "registered_metadata"
        notes = ["Non-green: metadata registered; live refresh skipped"]
        if ric_key and dest is not None:
            ric_file = find_ric_file(str(ric_key), filename)
            if ric_file and ric_file.is_file():
                if not dest.exists():
                    action = copy_or_link(ric_file, dest)
                else:
                    action = "existing"
                local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
                sha = sha256_file(dest)
                record_count = count_records(dest, str(src.get("file_format") or ""))
                notes = [f"Archived from RIC without green refresh ({action})"]
        # CPAQ / local PDF still allowed
        if src["source_id"] == "cpaq_explore_qld_2026":
            return sync_source({**src, "reuse_status": REUSE_GRANTED}, False, False, False)
        return finalize(src, utc_now(), sha, local_path, record_count, action, notes)
    # Never auto-download special-licence / huge opt-in sources (e.g. OSM PBF).
    if src.get("skip_download_default"):
        return {
            **src,
            "retrieved_at": None,
            "sha256": None,
            "local_path": None,
            "record_count": None,
            "sync_action": "registered_only",
            "sync_notes": "Opt-in only — not downloaded by default (large and/or special licence).",
        }

    subdir = ARCHIVE / str(src["subdir"])
    filename = src.get("filename")
    dest = subdir / filename if filename else None
    retrieved_at = utc_now()
    action = "metadata_only"
    sync_notes: list[str] = []
    record_count = None
    sha = None
    local_path = None

    # CPAQ PDF from local candidates
    if src["source_id"] == "cpaq_explore_qld_2026":
        for cand in src.get("local_pdf_candidates") or []:
            p = Path(cand)
            if p.is_file():
                dest = subdir / "cpaq-2026-directory.pdf"
                action = copy_or_link(p, dest)
                local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
                sha = sha256_file(dest)
                # also mirror extraction report counts
                report = Path(src["extracted_json_dir"]) / "extraction-report.json"
                if report.is_file():
                    rep = json.loads(report.read_text(encoding="utf-8"))
                    record_count = int(rep.get("parks_total_merged") or 0) + int(rep.get("trade_extracted") or 0)
                sync_notes.append(f"Local PDF via {action} from {p}")
                break
        else:
            sync_notes.append("CPAQ PDF not found in local candidates")
        return finalize(src, retrieved_at, sha, local_path, record_count, action, sync_notes)

    # Prefer RIC raw
    ric_key = src.get("ric_key")
    if ric_key and dest is not None:
        ric_file = find_ric_file(str(ric_key), filename)
        if ric_file and ric_file.is_file():
            if not dest.exists() or refresh or sha256_file(dest) != sha256_file(ric_file):
                action = copy_or_link(ric_file, dest)
            else:
                action = "unchanged"
            local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
            sha = sha256_file(dest)
            record_count = count_records(dest, str(src.get("file_format") or ""))
            sync_notes.append(f"Synced from Assist RIC raw/{ric_key} ({action})")
            # still try live refresh for Toilet Map / ArcGIS when refresh requested
            if not refresh:
                return finalize(src, retrieved_at, sha, local_path, record_count, action, sync_notes)

    # Live download paths
    if dest is not None and (src.get("download_url") or src.get("ckan_resource_id") or src.get("arcgis_paginate")):
        try:
            if src.get("ckan_resource_id") and (refresh or not dest.exists()):
                url = resolve_ckan_download(str(src["ckan_resource_id"])) or src.get("download_url")
                if url:
                    print(f"  Downloading CKAN {src['source_id']} …")
                    http_get(str(url), dest)
                    action = "downloaded_ckan"
                    sync_notes.append(f"Downloaded from {url}")
            elif src.get("arcgis_paginate") and src.get("download_url") and (refresh or not dest.exists()):
                print(f"  Downloading ArcGIS {src['source_id']} …")
                # strip existing resultOffset from template
                base = re.sub(r"[&?]resultOffset=\d+", "", str(src["download_url"]))
                arcgis_download_all(base, dest)
                action = "downloaded_arcgis"
                sync_notes.append("Paginated ArcGIS GeoJSON download")
            elif src.get("download_url") and (refresh or not dest.exists()):
                print(f"  Downloading {src['source_id']} …")
                http_get(str(src["download_url"]), dest)
                action = "downloaded"
                sync_notes.append(f"Downloaded from {src['download_url']}")
            if dest.exists():
                local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
                sha = sha256_file(dest)
                record_count = count_records(dest, str(src.get("file_format") or ""))
        except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, OSError, json.JSONDecodeError) as exc:
            sync_notes.append(f"Download failed: {exc}")
            if dest.exists():
                local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
                sha = sha256_file(dest)
                record_count = count_records(dest, str(src.get("file_format") or ""))
                action = action if action != "metadata_only" else "ric_or_partial"

    if local_path is None and dest is not None and dest.exists():
        local_path = str(dest.relative_to(ROOT)).replace("\\", "/")
        sha = sha256_file(dest)
        record_count = count_records(dest, str(src.get("file_format") or ""))
        action = "existing"

    if local_path is None:
        sync_notes.append("No file archived (register metadata only / permission or login required)")

    return finalize(src, retrieved_at, sha, local_path, record_count, action, sync_notes)


def finalize(
    src: dict[str, Any],
    retrieved_at: str,
    sha: str | None,
    local_path: str | None,
    record_count: int | None,
    action: str,
    sync_notes: list[str],
) -> dict[str, Any]:
    out = {
        "source_id": src["source_id"],
        "source_name": src["source_name"],
        "publisher": src["publisher"],
        "jurisdiction": src["jurisdiction"],
        "landing_page": src["landing_page"],
        "download_url": src.get("download_url"),
        "file_format": src.get("file_format"),
        "filename": src.get("filename"),
        "retrieved_at": retrieved_at if local_path else None,
        "published_or_updated": src.get("published_or_updated"),
        "licence": src["licence"],
        "attribution": src["attribution"],
        "reuse_status": src["reuse_status"],
        "category": src["category"],
        "import_status": src["import_status"],
        "sha256": sha,
        "local_path": local_path,
        "record_count": record_count,
        "refresh_method": src.get("refresh_method"),
        "refresh_frequency": src.get("refresh_frequency"),
        "notes": src.get("notes"),
        "permission_note": src.get("permission_note"),
        "sync_action": action,
        "sync_notes": "; ".join(sync_notes) if sync_notes else None,
        "ric_key": src.get("ric_key"),
    }
    return out


def write_markdown(entries: list[dict[str, Any]], summary: dict[str, Any]) -> None:
    docs = ROOT / "docs" / "data"
    docs.mkdir(parents=True, exist_ok=True)
    path = docs / "VANASSIST_DATA_SOURCE_REGISTER.md"
    lines = [
        "# VanAssist data source register",
        "",
        f"**Generated:** {summary['generated_at']}  ",
        "**Backlog:** DATA-012, DATA-011A, VAN-001  ",
        "**Architecture:** Assist RIC acquires; Platform `government_datasets` is SoR (ADR 0033).  ",
        "**Raw archive:** `data/sources/vanassist/` (large binaries gitignored; registry + checksums committed).",
        "",
        "## Summary",
        "",
        f"- Sources registered: **{summary['sources_registered']}**",
        f"- Datasets with archived files: **{summary['datasets_archived']}**",
        f"- PDFs/guides archived: **{summary['pdfs_archived']}**",
        f"- GREEN: **{summary['green']}**",
        f"- PERMISSION_GRANTED: **{summary['permission_granted']}**",
        f"- AMBER_PERMISSION_REQUIRED: **{summary['amber']}**",
        f"- YELLOW_SPECIAL_LICENCE: **{summary['yellow']}**",
        f"- UNKNOWN_LICENCE: **{summary['unknown']}**",
        f"- SKIP: **{summary['skip']}**",
        f"- Raw records measurable: **{summary['raw_records_measurable']}**",
        "",
        "## Reuse rules",
        "",
        "| Status | Meaning |",
        "| --- | --- |",
        "| GREEN | Explicit reusable open licence — may import |",
        "| PERMISSION_GRANTED | Owner permission recorded — may import |",
        "| AMBER_PERMISSION_REQUIRED | Useful; archive only until permission |",
        "| YELLOW_SPECIAL_LICENCE | Special obligations (e.g. ODbL) — do not mix |",
        "| UNKNOWN_LICENCE | Archive; do not import |",
        "| SKIP | Intentionally excluded |",
        "",
        "## Sources",
        "",
        "| ID | Source | Org | Juris. | Format | Reuse | Import | Records | SHA256 | Path |",
        "| --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |",
    ]
    for e in entries:
        sha = (e.get("sha256") or "")[:12]
        path_s = e.get("local_path") or "—"
        rec = e.get("record_count")
        rec_s = str(rec) if rec is not None else "—"
        lines.append(
            f"| `{e['source_id']}` | {e['source_name']} | {e['publisher']} | {e['jurisdiction']} | "
            f"{e.get('file_format') or '—'} | {e['reuse_status']} | {e['import_status']} | {rec_s} | `{sha}` | `{path_s}` |"
        )
    lines.extend(
        [
            "",
            "## Permission queue (AMBER)",
            "",
        ]
    )
    for e in entries:
        if e["reuse_status"] == REUSE_AMBER:
            lines.append(f"- **{e['source_name']}** ({e['publisher']}) — {e.get('landing_page')}")
    lines.extend(
        [
            "",
            "## Refresh",
            "",
            "```bash",
            "python tools/vanassist_sources/sync_archive.py --refresh-green",
            "```",
            "",
            "Assist RIC remains the production acquisition engine (ADR 0033).",
            "This archive is the Platform-side immutable source vault + provenance register.",
            "",
        ]
    )
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--refresh-green", action="store_true", help="Re-download GREEN sources where URLs exist")
    parser.add_argument("--amber-only", action="store_true")
    parser.add_argument("--green-only", action="store_true")
    args = parser.parse_args()
    # --refresh-green implies green/granted filter for live downloads
    green_only = args.green_only or args.refresh_green

    ensure_dirs()
    entries: list[dict[str, Any]] = []
    print(f"Syncing {len(SOURCES)} registered sources into {ARCHIVE}")
    for src in SOURCES:
        print(f"- {src['source_id']} [{src['reuse_status']}]")
        entries.append(
            sync_source(
                src,
                refresh=args.refresh_green,
                amber_only=args.amber_only,
                green_only=green_only,
            )
        )

    summary = {
        "generated_at": utc_now(),
        "sources_registered": len(entries),
        "datasets_archived": sum(1 for e in entries if e.get("local_path")),
        "pdfs_archived": sum(
            1
            for e in entries
            if e.get("local_path") and str(e.get("file_format") or "").upper().startswith("PDF")
        ),
        "green": sum(1 for e in entries if e["reuse_status"] == REUSE_GREEN),
        "permission_granted": sum(1 for e in entries if e["reuse_status"] == REUSE_GRANTED),
        "amber": sum(1 for e in entries if e["reuse_status"] == REUSE_AMBER),
        "yellow": sum(1 for e in entries if e["reuse_status"] == REUSE_YELLOW),
        "unknown": sum(1 for e in entries if e["reuse_status"] == REUSE_UNKNOWN),
        "skip": sum(1 for e in entries if e["reuse_status"] == REUSE_SKIP),
        "raw_records_measurable": sum(int(e["record_count"]) for e in entries if e.get("record_count")),
        "ric_raw_root": str(RIC_RAW),
        "archive_root": str(ARCHIVE),
    }

    REGISTRY_DIR.mkdir(parents=True, exist_ok=True)
    (REGISTRY_DIR / "sources.json").write_text(
        json.dumps({"summary": summary, "sources": entries}, indent=2, ensure_ascii=False) + "\n",
        encoding="utf-8",
    )
    (ARCHIVE / "reports" / f"sync-report-{datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}.json").write_text(
        json.dumps(summary, indent=2) + "\n",
        encoding="utf-8",
    )
    # checksums sidecar for archived files
    checksum_lines = []
    for e in entries:
        if e.get("sha256") and e.get("local_path"):
            checksum_lines.append(f"{e['sha256']}  {e['local_path']}")
    (REGISTRY_DIR / "checksums.sha256").write_text("\n".join(checksum_lines) + ("\n" if checksum_lines else ""), encoding="utf-8")
    write_markdown(entries, summary)
    print(json.dumps(summary, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
