#!/usr/bin/env python3
"""Apply accepted state-gazetteer proposals to the national town seed."""

from __future__ import annotations

import json
import math
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
TOWNS = ROOT / "database" / "seeds" / "towns_national.json"
REGION_CENTROIDS = {
    "NSW": {"nsw-sydney": (-33.87,151.21),"nsw-hunter":(-32.93,151.78),"nsw-north-coast":(-30.30,153.12),"nsw-northern-inland":(-31.09,150.93),"nsw-central-west":(-33.28,149.10),"nsw-riverina":(-35.12,147.37),"nsw-south-coast":(-34.88,150.60),"nsw-far-west":(-31.95,141.47)},
    "VIC": {"vic-melb":(-37.81,144.96),"vic-geelong":(-38.15,144.36),"vic-ballarat":(-37.56,143.86),"vic-bendigo":(-36.76,144.28),"vic-gippsland":(-38.20,146.54),"vic-goulburn":(-36.38,145.40),"vic-westvic":(-36.71,142.20),"vic-murray":(-34.19,142.16)},
    "SA": {"sa-adelaide":(-34.93,138.60),"sa-fleurieu":(-35.55,138.62),"sa-barossa":(-34.48,138.99),"sa-riverland":(-34.28,140.60),"sa-yorke-eyre":(-34.00,136.20),"sa-southeast":(-37.50,140.40),"sa-outback":(-30.50,135.50)},
    "WA": {"wa-perth":(-31.95,115.86),"wa-southwest":(-33.33,115.64),"wa-greatsouthern":(-35.03,117.88),"wa-wheatbelt":(-31.65,116.67),"wa-midwest":(-28.77,114.61),"wa-goldfields":(-30.75,121.47),"wa-gascoyne":(-24.88,113.66),"wa-pilbara":(-20.74,116.85),"wa-kimberley":(-17.96,122.24)},
    "TAS": {"tas-hobart":(-42.88,147.33),"tas-launceston":(-41.43,147.14),"tas-northwest":(-41.05,145.91),"tas-east":(-41.65,148.10)},
    "NT": {"nt-darwin":(-12.46,130.84),"nt-katherine":(-14.46,132.26),"nt-tennant":(-19.65,134.19),"nt-alice":(-23.70,133.88),"nt-eastarnhem":(-12.20,136.78)},
    "ACT": {"act-canberra":(-35.28,149.13)},
}


def nearest_region(state: str, latitude: float, longitude: float) -> str:
    def distance(point: tuple[float, float]) -> float:
        dlat, dlng = math.radians(point[0]-latitude), math.radians(point[1]-longitude)
        value = math.sin(dlat/2)**2 + math.cos(math.radians(latitude))*math.cos(math.radians(point[0]))*math.sin(dlng/2)**2
        return 2*6371*math.asin(math.sqrt(value))
    return min(REGION_CENTROIDS[state], key=lambda key: distance(REGION_CENTROIDS[state][key]))


def load(name: str) -> dict:
    return json.loads((ROOT / "database" / "seeds" / name).read_text(encoding="utf-8"))


def accepted() -> list[dict]:
    rows: list[dict] = []
    nsw = load("nsw_coordinate_quality_report.json")
    rows += [{"state":"NSW","town":r["town"],"lat":r["official"]["latitude"],"lng":r["official"]["longitude"],"ref":r["official"]["gnb_file_reference"],"source":"nsw-geographical-name-register"} for r in nsw["proposals"]]
    vic = load("vic_coordinate_quality_report.json")
    rows += [{"state":"VIC","town":r["town"],"lat":r["lat"],"lng":r["lng"],"ref":str(r["ufi"]),"source":"vic-vicnames"} for r in vic["verified"]]
    sa = load("sa_coordinate_quality_report.json")
    rows += [{"state":"SA","town":r["town"],"lat":r["official"]["lat"],"lng":r["official"]["lng"],"ref":r["official"]["reference"],"source":"sa-state-gazetteer"} for r in sa["matches"]]
    tas = load("tas_coordinate_quality_report.json")
    rows += [{"state":"TAS","town":r["town"],"lat":r["lat"],"lng":r["lng"],"ref":r["nom_reg_no"],"source":"tas-list-place-names"} for r in tas["verified"]]
    wa = load("wa_ga_coordinate_quality_report.json")
    rows += [{"state":"WA","town":r["town"],"lat":r["official"]["lat"],"lng":r["official"]["lng"],"ref":r["official"]["id"],"source":"ga-composite-gazetteer-wa"} for r in wa["matches"]]
    for state, filename, source_key in (("NT","nt_coordinate_quality_report.json","nt-place-names-register"),("ACT","act_coordinate_quality_report.json","act-place-names-register")):
        path = ROOT / "database" / "seeds" / filename
        if not path.exists():
            continue
        report = json.loads(path.read_text(encoding="utf-8"))
        for row in report.get("verified", report.get("matches", report.get("proposals", []))):
            official = row.get("official", row)
            rows.append({"state":state,"town":row["town"],"lat":official.get("lat",official.get("latitude")),"lng":official.get("lng",official.get("longitude")),"ref":str(official.get("reference",official.get("id",official.get("object_id","")))),"source":source_key})
        if state == "NT" and report.get("darwin_verification", {}).get("seed_status") == "MISSING_CANONICAL_DARWIN_SEED":
            official = report["darwin_verification"]["official"]
            rows.append({"state":"NT","town":"Darwin","lat":official["lat"],"lng":official["lng"],"ref":official["id"],"source":source_key})
    return rows


def main() -> None:
    data = json.loads(TOWNS.read_text(encoding="utf-8"))
    by_key = {(town["state"], town["name"].casefold()): town for town in data["towns"]}
    if ("NT", "darwin") not in by_key:
        darwin = {"name":"Darwin","state":"NT","region":"nt-darwin","pc":"0800","lat":-12.4615,"lng":130.8425}
        data["towns"].append(darwin)
        by_key[("NT", "darwin")] = darwin
    counts: dict[str, int] = {}
    for record in accepted():
        town = by_key[(record["state"], record["town"].casefold())]
        town["lat"], town["lng"] = round(float(record["lat"]),7), round(float(record["lng"]),7)
        town["region"] = nearest_region(record["state"], town["lat"], town["lng"])
        town["coordinate_source"] = record["source"]
        town["coordinate_confidence"] = "authoritative"
        town["coordinate_reference"] = record["ref"]
        counts[record["state"]] = counts.get(record["state"], 0) + 1
    data.setdefault("coordinate_quality", {})["state_authoritative_matches"] = counts
    data["coordinate_quality"]["confidence_counts"] = {
        confidence: sum(1 for town in data["towns"] if town.get("coordinate_confidence") == confidence)
        for confidence in ("authoritative", "statistical", "unverified")
    }
    data["coordinate_quality"]["unverified"] = data["coordinate_quality"]["confidence_counts"]["unverified"]
    data["count"] = len(data["towns"])
    data.setdefault("coordinate_sources", {}).update({
        "nsw-geographical-name-register":{"name":"Geographical Name Register of NSW","url":"https://data.nsw.gov.au/data/dataset/geographical-name-register-of-nsw","licence":"CC BY"},
        "vic-vicnames":{"name":"VICNAMES Place Name Register","url":"https://discover.data.vic.gov.au/dataset/vicmap-features-vicnames-place-name-register-point","licence":"CC BY 4.0"},
        "sa-state-gazetteer":{"name":"South Australian State Gazetteer","url":"https://data.sa.gov.au/data/dataset/gazetteer","licence":"CC BY 3.0 AU"},
        "tas-list-place-names":{"name":"Tasmania LIST Place Names","url":"https://www.thelist.tas.gov.au/app/content/data/geo-meta-data-record?detailRecordUID=d193cd7a-d93a-4ca8-a0a3-670929ad247a","licence":"CC BY 3.0 AU"},
        "ga-composite-gazetteer-wa":{"name":"Composite Gazetteer of Australia — WA authority records","url":"https://services.ga.gov.au/gis/rest/services/Composite_Gazetteer_of_Australia/MapServer/0","licence":"CC BY 4.0"},
        "nt-place-names-register":{"name":"Composite Gazetteer of Australia — NT authority records","url":"https://services.ga.gov.au/gis/rest/services/Composite_Gazetteer_of_Australia/MapServer/0","licence":"CC BY 4.0"},
        "act-place-names-register":{"name":"ACT Government Divisions and Composite Gazetteer ACT records","url":"https://www.arcgis.com/home/item.html?id=fab46ae9304f407499f104bb63c9eb77","licence":"CC BY 4.0"},
    })
    TOWNS.write_text(json.dumps(data,separators=(",",":")),encoding="utf-8")
    print(json.dumps(counts,indent=2))


if __name__ == "__main__":
    main()
