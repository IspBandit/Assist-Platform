"""VanAssist / Assist RIC portable dataset acquisition catalogue definition.

This module is the portable pack authoring source for data_catalogue/catalogue.json
and catalogue.csv. RIC SQLite still seeds from catalogue_seed.py; Platform SoR
remains government_datasets (ADR 0033).
"""

from __future__ import annotations

from copy import deepcopy
from typing import Any

CATALOGUE_SCHEMA_VERSION = "1.0.0"

REQUIRED_FIELDS: tuple[str, ...] = (
    "dataset_id",
    "name",
    "description",
    "category",
    "publisher",
    "jurisdiction",
    "geographic_coverage",
    "source_url",
    "api_url",
    "download_url",
    "portal_type",
    "format",
    "licence",
    "attribution_requirement",
    "signup_required",
    "api_key_required",
    "pricing_free_allowance",
    "update_frequency",
    "last_updated",
    "bulk_download_available",
    "automated_access_allowed",
    "recommended_ric_integration_method",
    "priority",
    "expected_user_value",
    "import_difficulty",
    "trust_policy",
    "enabled_state",
    "notes",
    "current_status",
    "cost_type",
    "connector_key",
    "local_raw_path",
)


def _row(**kwargs: Any) -> dict[str, Any]:
    base: dict[str, Any] = {
        "dataset_id": "",
        "name": "",
        "description": "",
        "category": "",
        "publisher": "",
        "jurisdiction": "",
        "geographic_coverage": "",
        "source_url": "",
        "api_url": "",
        "download_url": "",
        "portal_type": "",
        "format": "",
        "licence": "",
        "attribution_requirement": "",
        "signup_required": False,
        "api_key_required": False,
        "pricing_free_allowance": "free open data where licence permits",
        "update_frequency": "as published",
        "last_updated": "",
        "bulk_download_available": False,
        "automated_access_allowed": True,
        "recommended_ric_integration_method": "connector",
        "priority": "medium",
        "expected_user_value": "medium",
        "import_difficulty": "medium",
        "trust_policy": "trusted_review",
        "enabled_state": False,
        "notes": "",
        "current_status": "verified",
        "cost_type": "free",
        "connector_key": "",
        "local_raw_path": "",
        "paging_method": "",
        "geographic_query_support": False,
        "rate_limits": "",
        "daily_cap": 0,
        "monthly_cap": 0,
        "monthly_budget_aud": 0.0,
    }
    base.update(kwargs)
    return base


def catalogue_entries() -> list[dict[str, Any]]:
    """Return the verified portable acquisition catalogue."""
    entries: list[dict[str, Any]] = []

    # --- Priority verified bulk / API sources ---
    entries.append(
        _row(
            dataset_id="au_national_public_toilet_map",
            name="National Public Toilet Map",
            description=(
                "National public toilet facilities including accessibility attributes; "
                "DumpPoint column identifies dump-point capable facilities."
            ),
            category="public_toilets",
            publisher="Department of Health, Disability and Ageing (via data.gov.au)",
            jurisdiction="AU",
            geographic_coverage="Australia",
            source_url="https://data.gov.au/data/dataset/national-public-toilet-map",
            api_url="https://data.gov.au/data/api/3/action",
            download_url=(
                "https://data.gov.au/data/dataset/"
                "553b3049-2b8b-46a2-95e6-640d7986a8c1/resource/"
                "34076296-6692-4e30-b627-67b7c4eb1027/download/"
            ),
            portal_type="ckan",
            format="csv",
            licence="CC BY 3.0 AU",
            attribution_requirement=(
                "© Commonwealth of Australia — National Public Toilet Map (data.gov.au)"
            ),
            signup_required=False,
            bulk_download_available=True,
            recommended_ric_integration_method="national_public_toilet_map",
            priority="critical",
            expected_user_value="critical",
            import_difficulty="low",
            enabled_state=True,
            current_status="download_ready",
            connector_key="national_public_toilet_map",
            local_raw_path="raw/au_national_public_toilet_map/Toiletmap.csv",
            update_frequency="monthly",
            last_updated="2026-08-02T01:02:14.556347",
            notes=(
                "Stable CKAN resource_id 34076296-6692-4e30-b627-67b7c4eb1027. "
                "Validated Batehaven NSW toilets + Corrigans Beach dump point in live CSV."
            ),
            paging_method="single CSV resource; optional datastore SQL if enabled",
            geographic_query_support=False,
        )
    )
    entries.append(
        _row(
            dataset_id="portal_osm_australia",
            name="OpenStreetMap Australia (Geofabrik extract)",
            description=(
                "Geofabrik Australia OSM PBF extract for offline enrichment of parks, "
                "campgrounds, toilets, dump points, fuel and EV features."
            ),
            category="openstreetmap",
            publisher="OpenStreetMap contributors / Geofabrik",
            jurisdiction="AU",
            geographic_coverage="Australia",
            source_url="https://download.geofabrik.de/australia-oceania/australia.html",
            api_url="",
            download_url="https://download.geofabrik.de/australia-oceania/australia-latest.osm.pbf",
            portal_type="geofabrik",
            format="osm.pbf",
            licence="ODbL 1.0",
            attribution_requirement="© OpenStreetMap contributors",
            bulk_download_available=True,
            recommended_ric_integration_method="osm_australia_extract",
            priority="critical",
            expected_user_value="critical",
            import_difficulty="high",
            enabled_state=True,
            trust_policy="community_review",
            current_status="download_ready",
            connector_key="osm_australia_extract",
            local_raw_path="raw/geofabrik_australia/",
            update_frequency="daily extract",
            notes=(
                "~900MB+ PBF. Metadata and reproducible download script only by default; "
                "do not commit the PBF. Prefer local pack / fixture for demos."
            ),
        )
    )
    entries.append(
        _row(
            dataset_id="nsw_rest_areas",
            name="NSW Rest Areas",
            description="Transport for NSW rest areas including amenities attributes and coordinates.",
            category="rest_areas",
            publisher="Transport for NSW",
            jurisdiction="NSW",
            geographic_coverage="New South Wales",
            source_url="https://data.gov.au/data/dataset/nsw-2-nsw-rest-areas",
            api_url="https://opendata.transport.nsw.gov.au/",
            download_url=(
                "https://opendata.transport.nsw.gov.au/data/dataset/"
                "37a56f96-feb7-49c4-9a7d-06957ccf7368/resource/"
                "6f2bcede-9d55-4315-b609-dffa30510894/download/rest-areas-csv-format.csv"
            ),
            portal_type="ckan",
            format="csv",
            licence="CC BY 3.0 AU",
            attribution_requirement="© Transport for NSW — NSW Rest Areas",
            bulk_download_available=True,
            recommended_ric_integration_method="offline_pack",
            priority="critical",
            expected_user_value="high",
            import_difficulty="low",
            current_status="download_ready",
            connector_key="gov_open_data_pack",
            local_raw_path="raw/nsw_rest_areas/rest-areas-csv-format.csv",
            notes="Also listed on data.gov.au as nsw-2-nsw-rest-areas. CSV download verified 2026-08-04.",
        )
    )
    entries.append(
        _row(
            dataset_id="nsw_boat_ramps",
            name="Maritime NSW Boat Ramps",
            description="NSW boat ramp locations and amenity attributes.",
            category="boat_ramps",
            publisher="Transport for NSW",
            jurisdiction="NSW",
            geographic_coverage="New South Wales",
            source_url="https://data.gov.au/data/dataset/nsw-2-maritime-nsw-boat-ramps",
            api_url="https://opendata.transport.nsw.gov.au/",
            download_url=(
                "https://opendata.transport.nsw.gov.au/data/dataset/"
                "912cd680-15ed-4be5-9d09-1a9d0f15e6ee/resource/"
                "e7537c27-d2d1-4666-b058-b586e8732236/download/boating_ramps.csv"
            ),
            portal_type="ckan",
            format="csv",
            licence="CC BY 3.0 AU",
            attribution_requirement="© Transport for NSW — Maritime NSW Boat Ramps",
            bulk_download_available=True,
            recommended_ric_integration_method="offline_pack",
            priority="medium",
            expected_user_value="medium",
            import_difficulty="low",
            current_status="download_ready",
            connector_key="gov_open_data_pack",
            local_raw_path="raw/nsw_boat_ramps/boating_ramps.csv",
        )
    )
    entries.append(
        _row(
            dataset_id="nsw_ev_charging_locations",
            name="EV Charging Locations (NSW)",
            description="Public EV charging locations published by Transport for NSW.",
            category="ev_charging",
            publisher="Transport for NSW",
            jurisdiction="NSW",
            geographic_coverage="New South Wales",
            source_url="https://data.gov.au/data/dataset/nsw-2-ev-charging-locations",
            api_url="https://opendata.transport.nsw.gov.au/",
            download_url=(
                "https://opendata.transport.nsw.gov.au/data/dataset/"
                "be1c4de4-4517-4bd0-8a09-2965ddfc7179/resource/"
                "7bbb6461-e52d-4fe7-ace4-a15c30198de0/download/ev_20251216.csv"
            ),
            portal_type="ckan",
            format="csv",
            licence="CC BY 3.0 AU",
            attribution_requirement="© Transport for NSW — EV Charging Locations",
            bulk_download_available=True,
            recommended_ric_integration_method="offline_pack",
            priority="medium",
            expected_user_value="medium",
            import_difficulty="low",
            current_status="download_ready",
            connector_key="gov_open_data_pack",
            local_raw_path="raw/nsw_ev_charging_locations/ev_charging_locations.csv",
        )
    )
    entries.append(
        _row(
            dataset_id="gold_coast_caravan_parks",
            name="City of Gold Coast Caravan Parks",
            description="Council-published caravan park locations (GeoJSON WFS).",
            category="caravan_parks",
            publisher="City of Gold Coast",
            jurisdiction="QLD",
            geographic_coverage="City of Gold Coast",
            source_url="https://data.gov.au/data/dataset/caravan-parks",
            api_url=(
                "https://data.gov.au/geoserver/caravan-parks/wfs"
                "?request=GetFeature&typeName=ckan_191d8a90_f617_481c_84fa_8c2f4b92a172"
                "&outputFormat=json"
            ),
            download_url=(
                "https://data.gov.au/geoserver/caravan-parks/wfs"
                "?request=GetFeature&typeName=ckan_191d8a90_f617_481c_84fa_8c2f4b92a172"
                "&outputFormat=json"
            ),
            portal_type="wfs",
            format="geojson",
            licence="CC BY 3.0 AU",
            attribution_requirement="© City of Gold Coast — Caravan Parks",
            bulk_download_available=True,
            recommended_ric_integration_method="offline_pack",
            priority="high",
            expected_user_value="high",
            import_difficulty="low",
            current_status="download_ready",
            connector_key="gov_open_data_pack",
            local_raw_path="raw/gold_coast_caravan_parks/caravan_parks.geojson",
            notes="Council-scale sample for caravan park coverage; not national.",
        )
    )
    entries.append(
        _row(
            dataset_id="sa_rest_areas_state_maintained",
            name="SA Rest Areas — State Maintained",
            description="South Australia Department for Infrastructure and Transport rest areas.",
            category="rest_areas",
            publisher="Department for Infrastructure and Transport (SA)",
            jurisdiction="SA",
            geographic_coverage="South Australia",
            source_url="https://data.gov.au/data/dataset/rest-areas-state-maintained",
            api_url="",
            download_url="https://www.dptiapps.com.au/dataportal/StateMaintainedRestAreas_shp.zip",
            portal_type="open_data",
            format="shp.zip",
            licence="CC BY 3.0 AU",
            attribution_requirement="© Government of South Australia — Rest Areas State Maintained",
            bulk_download_available=True,
            recommended_ric_integration_method="offline_pack",
            priority="high",
            expected_user_value="high",
            import_difficulty="medium",
            current_status="api_ready",
            connector_key="gov_open_data_pack",
            notes="SHP ZIP download published on data.gov.au; verify current URL before enable.",
        )
    )
    entries.append(
        _row(
            dataset_id="wa_major_rest_areas",
            name="WA Major Rest Areas",
            description="Main Roads Western Australia major rest areas / road stopping places.",
            category="rest_areas",
            publisher="Main Roads Western Australia",
            jurisdiction="WA",
            geographic_coverage="Western Australia",
            source_url="https://data.gov.au/data/dataset/mrwa-major-rest-area",
            api_url="https://portal-mainroads.opendata.arcgis.com/datasets/mainroads::major-rest-area",
            download_url="",
            portal_type="arcgis_hub",
            format="arcgis",
            licence="CC BY 3.0 AU",
            attribution_requirement="© Main Roads Western Australia — Major Rest Area",
            bulk_download_available=False,
            recommended_ric_integration_method="arcgis_feature",
            priority="high",
            expected_user_value="high",
            import_difficulty="medium",
            current_status="manual_review",
            connector_key="gov_open_data_pack",
            notes=(
                "Hub landing URL is HTML, not a bulk file. Need FeatureServer layer URL "
                "before automated download."
            ),
        )
    )

    # --- Discovery portals ---
    portals = [
        ("portal_data_gov_au", "data.gov.au", "https://data.gov.au/", "https://data.gov.au/data/api/3/action", "AU", "federal_portal"),
        ("portal_qld_open_data", "Queensland Open Data", "https://www.data.qld.gov.au/", "https://www.data.qld.gov.au/api/3/action", "QLD", "state_portal"),
        ("portal_nsw_open_data", "NSW Open Data", "https://data.nsw.gov.au/", "https://data.nsw.gov.au/data/api/3/action", "NSW", "state_portal"),
        ("portal_transport_nsw", "Transport for NSW Open Data", "https://opendata.transport.nsw.gov.au/", "https://opendata.transport.nsw.gov.au/api/3/action", "NSW", "state_transport"),
        ("portal_datavic", "DataVic", "https://www.data.vic.gov.au/", "https://discover.data.vic.gov.au/api/3/action", "VIC", "state_portal"),
        ("portal_wa_open_data", "WA Open Data", "https://catalogue.data.wa.gov.au/", "https://catalogue.data.wa.gov.au/api/3/action", "WA", "state_portal"),
        ("portal_sa_data_directory", "SA Data Directory", "https://data.sa.gov.au/", "https://data.sa.gov.au/data/api/3/action", "SA", "state_portal"),
        ("portal_tas_open_data", "Tasmania Open Data", "https://www.data.tas.gov.au/", "", "TAS", "state_portal"),
        ("portal_act_open_data", "ACT Open Data", "https://www.data.act.gov.au/", "", "ACT", "state_portal"),
        ("portal_nt_open_data", "NT Open Data", "https://data.nt.gov.au/", "", "NT", "state_portal"),
        ("theme_council_open_data_portals", "Council open-data portal discovery", "https://data.gov.au/", "https://data.gov.au/data/api/3/action", "AU", "council_portals"),
    ]
    for dataset_id, name, source_url, api_url, jurisdiction, category in portals:
        entries.append(
            _row(
                dataset_id=dataset_id,
                name=name,
                description=f"Discovery portal for VanAssist-relevant open datasets ({name}).",
                category=category,
                publisher=name,
                jurisdiction=jurisdiction,
                geographic_coverage=jurisdiction,
                source_url=source_url,
                api_url=api_url,
                portal_type="ckan" if api_url else "open_data",
                format="portal",
                licence="Portal-specific — verify per child dataset",
                attribution_requirement=f"Preserve attribution required by each {name} dataset",
                bulk_download_available=False,
                recommended_ric_integration_method="portal_discovery",
                priority="high",
                expected_user_value="high",
                import_difficulty="medium",
                current_status="verified",
                connector_key="gov_open_data_pack",
                notes="Portal only. Enable child datasets after licence citation.",
            )
        )

    themes = [
        ("theme_dump_points", "Dump points", "dump_points", "critical", "critical"),
        ("theme_drinking_water", "Drinking water", "drinking_water", "high", "high"),
        ("theme_rest_areas", "Rest areas", "rest_areas", "high", "high"),
        ("theme_visitor_information_centres", "Visitor Information Centres", "visitor_information", "high", "high"),
        ("theme_caravan_parks", "Caravan parks", "caravan_parks", "critical", "critical"),
        ("theme_campgrounds", "Campgrounds", "campgrounds", "critical", "critical"),
        ("theme_public_showers", "Public showers", "public_showers", "medium", "medium"),
        ("theme_laundries", "Laundries", "laundries", "medium", "medium"),
        ("theme_fuel_stations", "Fuel stations", "fuel_stations", "high", "high"),
        ("theme_lpg", "LPG refill or exchange", "lpg", "medium", "medium"),
        ("theme_hospitals", "Hospitals", "hospitals", "high", "high"),
        ("theme_medical_centres", "Medical centres", "medical_centres", "medium", "medium"),
        ("theme_pharmacies", "Pharmacies", "pharmacies", "medium", "medium"),
        ("theme_emergency_services", "Emergency services", "emergency_services", "high", "high"),
        ("theme_boat_ramps", "Boat ramps", "boat_ramps", "medium", "medium"),
        ("theme_picnic_areas", "Picnic areas", "picnic_areas", "low", "low"),
        ("theme_barbecues", "Barbecues", "barbecues", "low", "low"),
        ("theme_waste_disposal", "Waste disposal", "waste_disposal", "medium", "medium"),
        ("theme_ev_charging", "EV charging", "ev_charging", "medium", "medium"),
        ("theme_weighbridges", "Weighbridges", "weighbridges", "low", "low"),
        ("theme_roadside_stopping", "Roadside stopping places", "roadside_stopping", "medium", "medium"),
        ("theme_national_parks", "National parks and reserves", "national_parks", "high", "high"),
        ("theme_rv_repair", "Caravan and RV repair providers", "rv_repair", "high", "high"),
        ("theme_auto_electricians", "Auto electricians", "auto_electricians", "medium", "medium"),
        ("theme_mobile_mechanics", "Mobile mechanics", "mobile_mechanics", "medium", "medium"),
        ("theme_tyre_services", "Tyre services", "tyre_services", "medium", "medium"),
        ("theme_towing_recovery", "Towing and recovery", "towing_recovery", "medium", "medium"),
        ("theme_caravan_parts", "Caravan parts and accessories", "caravan_parts", "medium", "medium"),
    ]
    for dataset_id, name, category, priority, value in themes:
        entries.append(
            _row(
                dataset_id=dataset_id,
                name=name,
                description=f"Multi-source theme programme for {name.lower()} across government and open data.",
                category=category,
                publisher="Multi-source theme (government / open / tourism)",
                jurisdiction="AU",
                geographic_coverage="Australia — assembled from verified open sources",
                source_url="https://data.gov.au/",
                api_url="https://data.gov.au/data/api/3/action",
                portal_type="theme",
                format="mixed",
                licence="Per contributing dataset",
                attribution_requirement="Preserve source attribution per contributing dataset",
                bulk_download_available=False,
                recommended_ric_integration_method="theme_aggregator",
                priority=priority,
                expected_user_value=value,
                import_difficulty="medium",
                trust_policy="community_review"
                if category in {"picnic_areas", "barbecues", "laundries", "caravan_parts"}
                else "trusted_review",
                current_status="verified",
                connector_key=dataset_id
                if dataset_id
                in {
                    "theme_dump_points",
                    "theme_drinking_water",
                    "theme_rest_areas",
                    "theme_visitor_information_centres",
                    "theme_caravan_parks",
                    "theme_campgrounds",
                }
                else "gov_open_data_pack",
                notes="Prefer specific verified child datasets before paid research APIs.",
            )
        )

    # Dump-point path via Toilet Map is already covered; explicit child for clarity
    entries.append(
        _row(
            dataset_id="au_toilet_map_dump_points",
            name="National Public Toilet Map — dump-point attributes",
            description=(
                "Dump-point coverage derived from National Public Toilet Map DumpPoint "
                "attribute (not a separate federal dump-point register)."
            ),
            category="dump_points",
            publisher="Department of Health, Disability and Ageing (via data.gov.au)",
            jurisdiction="AU",
            geographic_coverage="Australia",
            source_url="https://data.gov.au/data/dataset/national-public-toilet-map",
            api_url="https://data.gov.au/data/api/3/action",
            download_url="",
            portal_type="ckan",
            format="csv",
            licence="CC BY 3.0 AU",
            attribution_requirement=(
                "© Commonwealth of Australia — National Public Toilet Map (data.gov.au)"
            ),
            bulk_download_available=True,
            recommended_ric_integration_method="national_public_toilet_map",
            priority="critical",
            expected_user_value="critical",
            import_difficulty="low",
            current_status="download_ready",
            connector_key="national_public_toilet_map",
            local_raw_path="raw/au_national_public_toilet_map/Toiletmap.csv",
            notes="Filter DumpPoint=true from Toiletmap.csv. Batehaven: Corrigans Beach (FacilityID 9277).",
        )
    )

    paid = [
        (
            "paid_google_places",
            "Google Places API",
            "https://developers.google.com/maps/documentation/places/web-service",
            "google_places_research",
            100,
            1000,
            25.0,
            "Confirm current Places SKU pricing on Google Cloud before enablement.",
        ),
        (
            "paid_brave_search",
            "Brave Search API",
            "https://brave.com/search/api/",
            "gov_open_data_pack",
            100,
            1000,
            20.0,
            "Confirm Brave Search API free/paid tiers on vendor site.",
        ),
        (
            "paid_here_places",
            "HERE Places / Search",
            "https://www.here.com/docs/bundle/places-api-developer-guide",
            "gov_open_data_pack",
            50,
            500,
            20.0,
            "Confirm HERE developer plan pricing before enablement.",
        ),
        (
            "paid_tomtom_search",
            "TomTom Search API",
            "https://developer.tomtom.com/search-api",
            "gov_open_data_pack",
            50,
            500,
            20.0,
            "Confirm TomTom Search pricing before enablement.",
        ),
        (
            "paid_mapbox_search",
            "Mapbox Search",
            "https://docs.mapbox.com/api/search/",
            "gov_open_data_pack",
            50,
            500,
            20.0,
            "Confirm Mapbox Search pricing before enablement.",
        ),
        (
            "paid_openchargemap",
            "OpenChargeMap API",
            "https://openchargemap.org/site/develop",
            "gov_open_data_pack",
            100,
            1000,
            10.0,
            "Confirm current free-tier/API key terms before enablement.",
        ),
    ]
    for dataset_id, name, url, connector, daily, monthly, budget, note in paid:
        entries.append(
            _row(
                dataset_id=dataset_id,
                name=name,
                description=f"Connector-ready paid/research API stub for {name}.",
                category="paid_research",
                publisher=name,
                jurisdiction="AU",
                geographic_coverage="Configurable; vendor coverage varies",
                source_url=url,
                api_url=url,
                portal_type="paid_api",
                format="json",
                licence="Vendor terms — verify on vendor portal before enablement",
                attribution_requirement="Vendor attribution required when results are used",
                signup_required=True,
                api_key_required=True,
                pricing_free_allowance="Confirm on vendor portal; seed caps are guardrails only",
                bulk_download_available=False,
                automated_access_allowed=False,
                recommended_ric_integration_method=connector,
                priority="low",
                expected_user_value="medium",
                import_difficulty="high",
                trust_policy="web_research_review",
                enabled_state=False,
                current_status="api_ready",
                cost_type="paid",
                connector_key=connector,
                daily_cap=daily,
                monthly_cap=monthly,
                monthly_budget_aud=budget,
                rate_limits=f"seed daily_cap={daily}, monthly_cap={monthly}, budget_aud={budget}",
                geographic_query_support=True,
                paging_method="vendor-specific; see API docs",
                notes=(
                    f"Disabled by default. Owner signup + secure key storage required. {note}"
                ),
            )
        )

    # De-dupe by dataset_id preserving first occurrence
    seen: set[str] = set()
    unique: list[dict[str, Any]] = []
    for row in entries:
        dataset_id = str(row["dataset_id"])
        if dataset_id in seen:
            raise ValueError(f"Duplicate dataset_id in pack definition: {dataset_id}")
        seen.add(dataset_id)
        unique.append(row)
    return deepcopy(unique)


def validate_catalogue(entries: list[dict[str, Any]]) -> list[str]:
    """Return validation errors for the portable catalogue."""
    errors: list[str] = []
    seen: set[str] = set()
    for index, row in enumerate(entries):
        for field in REQUIRED_FIELDS:
            if field not in row:
                errors.append(f"[{index}] missing field {field}")
        dataset_id = str(row.get("dataset_id") or "")
        if not dataset_id:
            errors.append(f"[{index}] empty dataset_id")
        elif dataset_id in seen:
            errors.append(f"duplicate dataset_id {dataset_id}")
        seen.add(dataset_id)
        licence = str(row.get("licence") or "").strip()
        attribution = str(row.get("attribution_requirement") or "").strip()
        if not licence:
            errors.append(f"{dataset_id}: licence required")
        if not attribution:
            errors.append(f"{dataset_id}: attribution_requirement required")
        if not str(row.get("source_url") or "").strip() and not str(row.get("api_url") or "").strip():
            errors.append(f"{dataset_id}: source_url or api_url required")
        if row.get("cost_type") == "paid" and bool(row.get("enabled_state")):
            errors.append(f"{dataset_id}: paid sources must be disabled by default")
        status = str(row.get("current_status") or "")
        if status not in {
            "verified",
            "download_ready",
            "api_ready",
            "manual_review",
            "unavailable",
            "prohibited",
        }:
            errors.append(f"{dataset_id}: invalid current_status {status}")
    return errors
