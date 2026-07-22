// Build a nationwide VanAssist stays seed from OpenStreetMap (ODbL).
// Community records are never marked council/operator verified. Council data
// must be imported separately with an authoritative source URL.
"use strict";
const fs = require("fs");
const path = require("path");
const allStates = ["QLD","NSW","VIC","SA","WA","TAS","NT","ACT"];
const statesArg = process.argv.indexOf("--states");
const states = statesArg >= 0 && process.argv[statesArg + 1]
  ? process.argv[statesArg + 1].split(",").map(s=>s.trim().toUpperCase()).filter(s=>allStates.includes(s))
  : allStates;
const root = path.join(__dirname, "..");
const towns = JSON.parse(fs.readFileSync(path.join(root,"database","seeds","towns_national.json"),"utf8")).towns;
const endpoint = process.env.OVERPASS_ENDPOINT || "https://overpass-api.de/api/interpreter";
const cacheDir = path.join(__dirname,".osm-stays-cache");
const output = path.join(root,"database","seeds","stays_osm.json");
fs.mkdirSync(cacheDir,{recursive:true});

const rad = v => v * Math.PI / 180;
function km(a,b,c,d){const x=rad(c-a),y=rad(d-b),q=Math.sin(x/2)**2+Math.cos(rad(a))*Math.cos(rad(c))*Math.sin(y/2)**2;return 12742*Math.asin(Math.sqrt(q));}
function nearest(lat,lng,state){let best=null,dist=Infinity;for(const t of towns){if(t.state!==state||!Number.isFinite(+t.lat)||!Number.isFinite(+t.lng))continue;const d=km(lat,lng,+t.lat,+t.lng);if(d<dist){dist=d;best=t;}}return dist<=100?best:null;}
function yes(v){return /^(yes|designated|permissive)$/i.test(String(v||""))?true:/^(no|private)$/i.test(String(v||""))?false:null;}
function url(tags){return tags.website||tags["contact:website"]||"";}
async function fetchState(state){
  const cache=path.join(cacheDir,state+".json");
  if(fs.existsSync(cache) && !process.argv.includes("--refresh")) return JSON.parse(fs.readFileSync(cache,"utf8")).elements||[];
  if(process.argv.includes("--from-cache")) return JSON.parse(fs.readFileSync(cache,"utf8")).elements||[];
  const q=`[out:json][timeout:300];area["ISO3166-2"="AU-${state}"]->.a;(nwr["tourism"="caravan_site"](area.a);nwr["tourism"="camp_site"](area.a);nwr["amenity"="caravan_site"](area.a););out center tags;`;
  let error;
  for(let attempt=1;attempt<=4;attempt++){
    try{const r=await fetch(endpoint,{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded","User-Agent":"VanAssist-Stays/1.0 (hello@vanassist.com.au)"},body:"data="+encodeURIComponent(q)});if(!r.ok)throw new Error(`HTTP ${r.status}`);const j=await r.json();fs.writeFileSync(cache,JSON.stringify(j));return j.elements||[];}catch(e){error=e;await new Promise(ok=>setTimeout(ok,attempt*8000));}
  }
  throw error;
}
(async()=>{
  const stays=[];
  for(const state of states){
    process.stdout.write(`Fetching ${state}... `);
    const elements=await fetchState(state); console.log(elements.length);
    for(const el of elements){
      const t=el.tags||{},lat=+(el.lat??el.center?.lat),lng=+(el.lon??el.center?.lon);
      if(!t.name||!Number.isFinite(lat)||!Number.isFinite(lng))continue;
      const town=nearest(lat,lng,state); if(!town)continue;
      const fee=String(t.fee||"").toLowerCase();
      const free=fee==="no"||/free camp/i.test(t.name);
      stays.push({
        external_id:`osm-${el.type[0]}${el.id}`,name:t.name,address:[t["addr:housenumber"],t["addr:street"]].filter(Boolean).join(" "),town:town.name,state,
        latitude:lat,longitude:lng,stay_type:free?"free_camp":(t.tourism==="camp_site"?"campground":"caravan_park"),price_type:free?"free":(fee==="yes"?"paid":"unknown"),
        phone:t.phone||t["contact:phone"]||"",email:t.email||t["contact:email"]||"",website:url(t),booking_url:t["contact:booking"]||t.booking||"",
        powered_sites:yes(t.power_supply),unpowered_sites:null,toilets:yes(t.toilets),showers:yes(t.shower),potable_water:yes(t.drinking_water),dump_point:yes(t.sanitary_dump_station),pets_allowed:yes(t.dog),
        source_type:"openstreetmap",source_url:`https://www.openstreetmap.org/${el.type}/${el.id}`,verification_type:"community"
      });
    }
  }
  const unique=[...new Map(stays.map(s=>[s.external_id,s])).values()];
  fs.writeFileSync(output,JSON.stringify({generated_at:new Date().toISOString(),source:"OpenStreetMap contributors (ODbL)",count:unique.length,stays:unique},null,2)+"\n");
  console.log(`Wrote ${unique.length} stays to ${output}`);
})().catch(e=>{console.error(e);process.exit(1);});
