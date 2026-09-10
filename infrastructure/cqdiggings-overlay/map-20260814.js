const REGION = [-22.5, 147.9],
  QLD_BBOX = "138,-29.25,153.7,-9.0",
  BBOX = QLD_BBOX;
let commodity = "All",
  commodityGroup = "ALL",
  gemTypeFilter = "all_gems";
const MINES =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/GeoscientificInformation/MiningResources/MapServer";
const TENURE =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Economy/MinesPermitsCurrent/MapServer";
const ADMIN =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Boundaries/MiningAdministrativeAreas/MapServer";
const PROTECTED =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Environment/ParksTerrestrialProtectedAreas/MapServer";
const PARCEL =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/PlanningCadastre/LandParcelPropertyFramework/MapServer/4";
const map = L.map("map", {
  preferCanvas: true,
  touchZoom: true,
  dragging: true,
}).setView(REGION, 6);
window.__leafletMap = map;
const streetBasemap = L.tileLayer("https://tile.openstreetmap.org/{z}/{x}/{y}.png", {
  maxZoom: 18,
  attribution: "© OpenStreetMap contributors",
});
const satelliteBasemap = L.tileLayer(
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
  {
    maxZoom: 19,
    attribution: "Imagery © Esri and contributors",
  },
);
streetBasemap.addTo(map);
document.querySelectorAll("[data-gold-flow-research]").forEach((button) => {
  button.addEventListener("click", () => {
    ["allWatercourses", "historicalGoldAlluvial", "prospectivity"].forEach((key) => {
      const input = document.querySelector(`[data-layer="${key}"]`);
      if (input && !input.checked) { input.checked = true; input.dispatchEvent(new Event("change", { bubbles: true })); }
    });
    openDetail(`<p class="eyebrow dark">Experimental gold-flow research</p><h2>Possible source-to-drainage pathways are visible</h2><p>This combines current mapped watercourses, documented historical alluvial evidence and experimental corridors traced from recorded reef, lode, vein or quartz evidence.</p><div class="warning"><strong>This is a hypothesis, not a find or access map.</strong><br>Current drainage may differ from historical channels. Check source records, tenure, permission and ground conditions independently.</div>`);
  });
});
let satelliteActive = false;
function syncFloatingBasemapButton() {
  const button = document.querySelector("#mapBasemapToggle");
  if (!button) return;
  const label = button.querySelector("span");
  if (label) {
    label.textContent = satelliteActive ? "Map" : "Satellite";
  }
  button.setAttribute(
    "aria-label",
    satelliteActive ? "Switch to map background" : "Switch to satellite background",
  );
  button.setAttribute("aria-pressed", String(satelliteActive));
}
function setBasemap(satellite) {
  satelliteActive = satellite;
  if (satellite) {
    map.removeLayer(streetBasemap);
    satelliteBasemap.addTo(map);
  } else {
    map.removeLayer(satelliteBasemap);
    streetBasemap.addTo(map);
  }
  document.querySelectorAll("[data-basemap]").forEach((choice) => {
    const selected = choice.dataset.basemap === (satellite ? "satellite" : "map");
    choice.classList.toggle("active", selected);
    choice.setAttribute("aria-pressed", String(selected));
  });
  syncFloatingBasemapButton();
}
document.querySelectorAll("[data-basemap]").forEach((button) => {
  button.addEventListener("click", () => setBasemap(button.dataset.basemap === "satellite"));
});
syncFloatingBasemapButton();
function syncFloatingControlPosition() {
  const header = document.querySelector(".topbar");
  const controls = document.querySelector(".map-floating-controls");
  const panel = document.querySelector("#mapLayersPanel");
  if (!header || !controls) return;
  const headerBottom = Math.max(0, header.getBoundingClientRect().bottom);
  const controlsTop = Math.ceil(headerBottom + 8);
  controls.style.top = `${controlsTop}px`;
  if (panel) {
    panel.style.top = `${controlsTop + controls.getBoundingClientRect().height + 8}px`;
  }
}
window.addEventListener("resize", syncFloatingControlPosition);
window.addEventListener("scroll", syncFloatingControlPosition, { passive: true });
window.requestAnimationFrame(syncFloatingControlPosition);
const mapPageHeader = document.querySelector(".topbar");
if (mapPageHeader && "ResizeObserver" in window) {
  new ResizeObserver(syncFloatingControlPosition).observe(mapPageHeader);
}
L.control.scale({ metric: true, imperial: false, position: "bottomleft" }).addTo(map);
const northControl = L.control({ position: "topright" });
northControl.onAdd = () => {
  const north = L.DomUtil.create("div", "field-north");
  north.innerHTML = '<span aria-hidden="true">▲</span><strong>N</strong>';
  north.setAttribute("aria-label", "North is towards the top of the map");
  return north;
};
northControl.addTo(map);
const legendControl = L.control({ position: "bottomright" });
let legendEl = null;
function refreshMapLegend() {
  if (!legendEl) return;
  const rows = [];
  if (commodityGroup !== "GEMS") {
    const goldVisible =
      map.hasLayer(groups.historical) ||
      map.hasLayer(groups.occurrences) ||
      map.hasLayer(groups.archive) ||
      map.hasLayer(groups.historicalGoldSources) ||
      map.hasLayer(groups.historicalGoldAlluvial) ||
      map.hasLayer(groups.historicalMaps) ||
      map.hasLayer(groups.allWatercourses) ||
      map.hasLayer(groups.prospectivity) ||
      map.hasLayer(groups.prioritiesResearch);
    if (goldVisible) {
      rows.push(
        '<span class="legend-block"><strong>Gold</strong></span>' +
          '<span><i class="swatch" style="background:#c89132"></i>Gold record</span>' +
          '<span><i class="swatch line potential"></i>Experimental gold pathway corridor</span>' +
          '<span><i class="swatch density gold"></i>Gold record density</span>',
      );
    }
  }
  if (commodityGroup !== "GOLD") {
    const gemVisible =
      map.hasLayer(groups.historicalGemEvidence) ||
      map.hasLayer(groups.gemOccurrences) ||
      map.hasLayer(groups.gemLocalities) ||
      map.hasLayer(groups.gemFieldObservations) ||
      map.hasLayer(groups.gemFossicking) ||
      map.hasLayer(groups.gemKnownWash) ||
      map.hasLayer(groups.gemGeology) ||
      map.hasLayer(groups.gemOccurrenceDensity) ||
      map.hasLayer(groups.gemWorkingsDensity);
    if (gemVisible) {
      rows.push(
        '<span class="legend-block"><strong>Gems</strong></span>' +
          '<span><i class="swatch" style="background:#356f9a"></i>Gem occurrence</span>' +
          '<span><i class="swatch" style="background:#6b4f8a"></i>Gemfield locality</span>' +
          '<span><i class="swatch" style="background:#c9772b"></i>Confirmed field recovery</span>' +
          '<span><i class="swatch" style="background:#1f6f58"></i>Fossicking boundary</span>' +
          '<span><i class="swatch" style="background:#2a9aaa"></i>Known wash (official)</span>' +
          '<span><i class="swatch density gem-blue"></i>Gem occurrence density</span>' +
          '<span><i class="swatch density gem-cyan"></i>Gem workings density</span>',
      );
    }
  }
  const tenureVisible =
    map.hasLayer(groups.grantedTenure) ||
    map.hasLayer(groups.applicationTenure) ||
    map.hasLayer(groups.fossickingLand) ||
    map.hasLayer(groups.constraints);
  if (tenureVisible) {
    rows.push(
      '<span class="legend-block"><strong>Tenure / constraints</strong></span>' +
        '<span><i class="swatch line tenure"></i>Resource tenure and constraints</span>',
    );
  }
  const densityVisible =
    map.hasLayer(groups.recordDensity) ||
    map.hasLayer(groups.workingsDensity) ||
    map.hasLayer(groups.occurrenceDensity) ||
    map.hasLayer(groups.gemOccurrenceDensity) ||
    map.hasLayer(groups.gemWorkingsDensity);
  if (!rows.length) {
    rows.push('<span>Zoom in to reveal detailed map families.</span>');
  } else if (densityVisible) {
    rows.push('<span class="legend-block"><strong>Density</strong></span>');
  }
  legendEl.innerHTML = `<strong>Legend</strong>${rows.join("")}`;
}
legendControl.onAdd = () => {
  legendEl = L.DomUtil.create("div", "legend map-legend");
  legendEl.setAttribute("aria-label", "Map legend");
  legendEl.innerHTML = "<strong>Legend</strong><span>Loading visible map layers...</span>";
  L.DomEvent.disableClickPropagation(legendEl);
  return legendEl;
};
legendControl.addTo(map);
function refreshMapLayout() {
  window.requestAnimationFrame(() => {
    map.invalidateSize({ animate: false });
  });
}
window.addEventListener("load", refreshMapLayout);
window.addEventListener("resize", refreshMapLayout);
if (document.fonts?.ready) document.fonts.ready.then(refreshMapLayout);
const clusterOpts = {
  showCoverageOnHover: false,
  spiderfyOnMaxZoom: true,
  removeOutsideVisibleBounds: true,
  chunkedLoading: true,
  maxClusterRadius: 56,
};
map.createPane("evidencePoints");
map.getPane("evidencePoints").style.zIndex = 650;
map.getPane("evidencePoints").style.pointerEvents = "auto";
const evidenceRenderer = L.svg({ pane: "evidencePoints" });
map.createPane("potentialCorridors");
map.getPane("potentialCorridors").style.zIndex = 445;
const potentialRenderer = L.svg({ pane: "potentialCorridors", padding: 0.3 });
const officialWatercourses = L.tileLayer.wms(
  "https://spatial-gis.information.qld.gov.au/arcgis/services/InlandWaters/WaterCoursesAndBodies/MapServer/WMSServer",
  {
    layers: "33",
    format: "image/png",
    transparent: true,
    opacity: 0.72,
    attribution: "© State of Queensland watercourse mapping",
  },
);
const mountLarcomOverlay = L.imageOverlay(
  "assets/maps/mount-larcom-23-special.webp",
  [
    [-23.8208333333, 150.96875],
    [-23.8041666667, 150.984375],
  ],
  { opacity: 0.58, interactive: true },
);
mountLarcomOverlay.on("click", () =>
  openDetail(
    `<p class="eyebrow dark">Verified historical overlay</p><h2>Mount Larcom 23 (Special), 1983</h2><p>Official 1:2500 cadastral map, sheet 9050-21123. Positioned from all four printed graticule corners.</p><div class="warning"><strong>Historical, not current cadastral information.</strong><br>Allow at least 10 metres when comparing features and verify all current boundaries and access rights officially.</div><p><a href="data/georeferencing-mount-larcom-23-special.json" target="_blank" rel="noopener">Open georeferencing record ↗</a><br><a href="downloads/maps/0126-mount-larcom-23-special.pdf" target="_blank" rel="noopener">Open source PDF ↗</a></p>`,
  ),
);
const groups = {
  historical: L.markerClusterGroup(clusterOpts),
  occurrences: L.markerClusterGroup(clusterOpts),
  archive: L.layerGroup(),
  recordDensity: L.layerGroup(),
  workingsDensity: L.layerGroup(),
  occurrenceDensity: L.layerGroup(),
  prioritiesResearch: L.layerGroup(),
  prospectivity: L.layerGroup(),
  allWatercourses: L.layerGroup([officialWatercourses]),
  historicalMaps: L.layerGroup([mountLarcomOverlay]),
  historicalMapExtents: L.layerGroup(),
  grantedTenure: L.layerGroup(),
  applicationTenure: L.layerGroup(),
  fossickingLand: L.layerGroup(),
  constraints: L.layerGroup(),
  legalGold: L.layerGroup().addTo(map),
  protectedAreas: L.layerGroup(),
  gemFossicking: L.layerGroup(),
  gemOccurrences: L.markerClusterGroup(clusterOpts),
  gemLocalities: L.markerClusterGroup({ ...clusterOpts, maxClusterRadius: 36 }),
  gemFieldObservations: L.markerClusterGroup({ ...clusterOpts, maxClusterRadius: 40 }),
  gemOccurrenceDensity: L.layerGroup(),
  gemWorkingsDensity: L.layerGroup(),
  gemKnownWash: L.layerGroup(),
  gemGeology: L.layerGroup(),
  historicalGoldSources: L.markerClusterGroup({ ...clusterOpts, maxClusterRadius: 50 }),
  historicalGoldAlluvial: L.markerClusterGroup({ ...clusterOpts, maxClusterRadius: 45 }),
  historicalGemEvidence: L.markerClusterGroup({ ...clusterOpts, maxClusterRadius: 45 }),
};
window.__leafletGroups = groups;
refreshMapLegend();
fetch("data/evidence-intelligence/historical-map-footprints.geojson")
  .then((response) => response.json())
  .then((data) => {
    const layer = L.geoJSON(data, {
      style: { color: "#805d2c", weight: 1.6, dashArray: "7 5", fillColor: "#d5a84b", fillOpacity: 0.08 },
      onEachFeature(feature, featureLayer) {
        const p = feature.properties || {};
        featureLayer.bindTooltip(`${p.title || p.map_id} · historical map coverage`, { sticky: true });
        featureLayer.on("click", () => openDetail(`<p class="eyebrow dark">Historical map coverage</p><h2>${esc(p.title || p.map_id)}</h2><dl><dt>Map ID</dt><dd>${esc(p.map_id)}</dd><dt>Coverage status</dt><dd>${esc(p.coverage_status)}</dd><dt>Extent method</dt><dd>${esc(String(p.extent_method || "Not recorded").replaceAll("_", " "))}</dd><dt>Practical uncertainty</dt><dd>${Number.isFinite(Number(p.practical_uncertainty_m)) ? `About ${Number(p.practical_uncertainty_m)} m` : "Not recorded"}</dd></dl><p>This outline shows the checked broad coverage of the historical map. It does not place every feature printed inside the map and is not current boundary or access information.</p><p><a href="${esc(p.source)}" target="_blank" rel="noopener">Open official map catalogue record ↗</a></p>`));
      },
    });
    groups.historicalMapExtents.addLayer(layer);
    const count = document.querySelector("#historicalMapExtentCount");
    if (count) count.textContent = `(${(data.features || []).length})`;
    applyViewportLayerVisibility();
  })
  .catch(() => {
    const count = document.querySelector("#historicalMapExtentCount");
    if (count) count.textContent = "(unavailable)";
  });
fetch("analytics/cms-public.php?kind=overlays", { cache: "no-store" })
  .then((response) => (response.ok ? response.json() : null))
  .then((registry) => {
    (registry?.overlays || []).forEach((overlay) => {
      if (!overlay?.image || overlay.status === "retired") return;
      const points = Object.values(overlay.corners || {}).filter(
        (point) => Number.isFinite(Number(point?.lat)) && Number.isFinite(Number(point?.lng)),
      );
      if (points.length < 2) return;
      const bounds = L.latLngBounds(points.map((point) => [Number(point.lat), Number(point.lng)]));
      if (overlay.id === "mr010860") {
        mountLarcomOverlay.setUrl(overlay.image);
        mountLarcomOverlay.setBounds(bounds);
        mountLarcomOverlay.setOpacity(Number(overlay.opacity) || 0.58);
        const applyRotation = () => {
          const element = mountLarcomOverlay.getElement();
          if (element) element.style.rotate = `${Number(overlay.rotation) || 0}deg`;
        };
        mountLarcomOverlay.on("add", applyRotation);
        applyRotation();
        return;
      }
      const layer = L.imageOverlay(overlay.image, bounds, {
        opacity: Number(overlay.opacity) || 0.55,
        interactive: true,
        className: `historical-overlay historical-overlay-${String(overlay.status || "unknown")}`,
      });
      layer.on("add", () => {
        const element = layer.getElement();
        if (element) element.style.rotate = `${Number(overlay.rotation) || 0}deg`;
      });
      layer.bindPopup(`<strong>${esc(overlay.title || overlay.id)}</strong><br>${esc(overlay.status || "unknown")} · ${esc(overlay.confidence || "unknown confidence")}<br>${esc(overlay.notes || "Position requires review.")}`);
      groups.historicalMaps.addLayer(layer);
    });
  })
  .catch(() => {});
const viewportLayerRules = {
  historical: { minZoom: 7 },
  occurrences: { minZoom: 7 },
  archive: { minZoom: 7 },
  historicalGoldSources: { minZoom: 9 },
  historicalGoldAlluvial: { minZoom: 10 },
  historicalGemEvidence: { minZoom: 9 },
  recordDensity: { minZoom: 6 },
  workingsDensity: { minZoom: 6 },
  occurrenceDensity: { minZoom: 6 },
  prioritiesResearch: { minZoom: 6 },
  prospectivity: { minZoom: 7 },
  allWatercourses: { minZoom: 6 },
  historicalMaps: { minZoom: 6 },
  historicalMapExtents: { minZoom: 6 },
  gemOccurrences: { minZoom: 8 },
  gemLocalities: { minZoom: 8 },
  gemFieldObservations: { minZoom: 8 },
  gemOccurrenceDensity: { minZoom: 6 },
  gemWorkingsDensity: { minZoom: 6 },
  gemKnownWash: { minZoom: 9 },
  gemGeology: { minZoom: 10 },
  gemFossicking: { minZoom: 6 },
  grantedTenure: { minZoom: 9, deEmphasis: true },
  applicationTenure: { minZoom: 10, deEmphasis: true },
  fossickingLand: { minZoom: 7, deEmphasis: true },
  constraints: { minZoom: 8, deEmphasis: true },
  protectedAreas: { minZoom: 6 },
};
const goldOnlyLayers = new Set([
  "historical",
  "occurrences",
  "archive",
  "recordDensity",
  "workingsDensity",
  "occurrenceDensity",
  "prioritiesResearch",
  "prospectivity",
  "historicalGoldSources",
  "historicalGoldAlluvial",
  "allWatercourses",
  "grantedTenure",
  "applicationTenure",
  "constraints",
  "historicalMaps",
]);
const gemOnlyLayers = new Set([
  "gemFossicking",
  "gemOccurrences",
  "gemLocalities",
  "gemFieldObservations",
  "gemOccurrenceDensity",
  "gemWorkingsDensity",
  "gemKnownWash",
  "gemGeology",
  "historicalGemEvidence",
]);
function getLayerChecked(layer) {
  const checkbox = document.querySelector(`[data-layer="${layer}"]`);
  return checkbox ? checkbox.checked : false;
}
function isLayerAllowedForMode(layer) {
  if (goldOnlyLayers.has(layer)) return commodityGroup !== "GEMS";
  if (gemOnlyLayers.has(layer)) return commodityGroup !== "GOLD";
  return true;
}
function getLayerStyleBase(layer, isPointStyle = false) {
  if (!layer?.options) return null;
  if (isPointStyle) {
    return {
      fillColor: layer.options.fillColor,
      fillOpacity: layer.options.fillOpacity,
      color: layer.options.color,
      weight: layer.options.weight,
      opacity: layer.options.opacity,
      radius: layer.options.radius,
    };
  }
  return {
    color: layer.options.color,
    opacity: layer.options.opacity,
    fillColor: layer.options.fillColor,
    fillOpacity: layer.options.fillOpacity,
    weight: layer.options.weight,
    dashArray: layer.options.dashArray,
  };
}
function applyLayerStyleMultiplier(layer, multiplier, pointStyle = false) {
  if (!layer?.eachLayer) return;
  layer.eachLayer((featureLayer) => {
    if (typeof featureLayer.setStyle !== "function") return;
    if (!featureLayer.__cqLayerStyleBase) {
      featureLayer.__cqLayerStyleBase = getLayerStyleBase(featureLayer, pointStyle);
    }
    const base = featureLayer.__cqLayerStyleBase;
    if (!base) return;
    const opacityScale = pointStyle ? 1 : multiplier;
    const next = {
      ...base,
      opacity: Math.max(0, (base.opacity ?? 1) * opacityScale),
      fillOpacity: Math.max(0, (base.fillOpacity ?? 0) * opacityScale),
      weight: Math.max(0.45, (base.weight ?? 1) * Math.max(opacityScale, 0.4)),
    };
    featureLayer.setStyle(next);
  });
}
function visibilityRulesMultiplier(zoom, deEmphasis) {
  if (!deEmphasis) return 1;
  if (zoom <= 8) return 0;
  if (zoom <= 10) return 0.42;
  if (zoom <= 11) return 0.68;
  return 1;
}
function applyViewportLayerVisibility() {
  const zoom = map.getZoom();
  Object.entries(viewportLayerRules).forEach(([key, rule]) => {
    const group = groups[key];
    if (!group) return;
    const checked = getLayerChecked(key);
    const allowed = isLayerAllowedForMode(key);
    const visibleForZoom = zoom >= rule.minZoom;
    const shouldShow = checked && allowed && visibleForZoom;
    if (shouldShow) {
      group.addTo(map);
    } else {
      map.removeLayer(group);
    }
    if (shouldShow && rule.deEmphasis) {
      const scale = visibilityRulesMultiplier(zoom, true);
      applyLayerStyleMultiplier(group, scale, false);
    } else if (rule.deEmphasis && map.hasLayer(group)) {
      applyLayerStyleMultiplier(group, 1, false);
    }
  });
}
function refreshClusterRadius() {
  const zoom = map.getZoom();
  const radius = zoom <= 6 ? 72 : zoom <= 8 ? 56 : 48;
  [
    groups.historical,
    groups.occurrences,
    groups.gemOccurrences,
    groups.gemLocalities,
    groups.gemFieldObservations,
  ].forEach((group) => {
    if (group?.options) group.options.maxClusterRadius = radius;
  });
}
map.on("zoomend", refreshClusterRadius);
map.on("zoomend", applyViewportLayerVisibility);
refreshClusterRadius();
const colours = {
  Gold: "#c89132",
  Copper: "#b45e38",
  Sapphire: "#356f9a",
  Silver: "#8a8e91",
  Coal: "#33332f",
  Manganese: "#795548",
  Iron: "#a84632",
  Limestone: "#8c9a91",
  "Lead / Zinc": "#65748a",
  Other: "#6f7c68",
};
let permanentPages = {};
let reportMatches = {};
fetch("data/gladstone-gold-occurrences.json")
  .then((r) => r.json())
  .then(
    (j) =>
      (permanentPages = Object.fromEntries(
        j.records.map((x) => [String(x.site_no), x.page]),
      )),
  )
  .catch(() => {});
fetch("data/occurrence-report-matches.json")
  .then((r) => r.json())
  .then((j) => {
    reportMatches = j.matches || {};
  })
  .catch(() => {});
const aliases = {
  Gold: ["GOLD", "AU"],
  Copper: ["COPPER", "CU"],
  Sapphire: ["SAPPHIRE", "CORUNDUM"],
  Silver: ["SILVER", "AG"],
  Coal: ["COAL"],
  Manganese: ["MANGANESE", "MN"],
  Iron: ["IRON", "MAGNETITE"],
  Limestone: ["LIMESTONE"],
  "Lead / Zinc": ["LEAD", "ZINC", "PB", "ZN"],
};
let features = [],
  gemRecords = [],
  gemLocalities = [],
  gemFieldObservations = [],
  gemKnownWashFeatures = [],
  gemWashDepthMax = 20;
const {
  matchesCommodityGroup,
  gemRecordDetailUrl,
  gemMapUrl,
  gemResultTypeLabel,
  normalizeGemType,
  matchesGemType,
  gemMarkerStyle,
  gemTypeLabel,
  GEM_TYPE_LABELS,
} = window.CQResearch || {};
const archive = [
  [
    "Clermont and Blair Athol district",
    -22.83,
    147.64,
    "Gold",
    "research-regions.html",
  ],
  [
    "Springsure and Emerald surrounds",
    -23.86,
    148.09,
    "Gold",
    "research-regions.html",
  ],
  ["Canoona goldfield", -23.04, 150.27, "Gold", "rockhampton-context.html"],
  [
    "Rockhampton and Bouldercombe district",
    -23.56,
    150.51,
    "Gold",
    "rockhampton-context.html",
  ],
  [
    "Mount Morgan and Dee River",
    -23.65,
    150.39,
    "Gold; Copper",
    "research-regions.html",
  ],
  ["Mount Larcom mine-map series", -23.81, 150.98, "Gold", "mount-larcom.html"],
  ["Calliope Goldfield 1885 plan", -24.01, 151.2, "Gold", "calliope.html"],
  ["Raglan Goldfield", -23.72, 150.75, "Gold", "raglan.html"],
  ["Norton Goldfield", -24.35, 151.32, "Gold", "boyne-valley-norton.html"],
  ["Many Peaks", -24.55, 151.37, "Copper; Gold; Silver", "many-peaks.html"],
  ["Glassford Creek", -24.53, 151.35, "Copper; Gold", "glassford-creek.html"],
  ["Diglum", -24.65, 151.2, "Iron; Copper; Gold", "diglum.html"],
].map((x) => ({
  name: x[0],
  lat: x[1],
  lng: x[2],
  commodity: x[3],
  url: x[4],
  kind: "CQDiggings source",
  detail:
    "Curated locality anchor. Open the dossier for primary evidence and limitations.",
}));
const priorities = [
  {
    name: "Mount Larcom evidence-gap study area · score 72/100",
    lat: -23.82,
    lng: 150.92,
    radius: 17000,
    basis:
      "Known evidence 23/25; host context 17/25; structures 13/20; drainage 10/15; verified sampling coverage 9/15.",
  },
  {
    name: "Boyne Valley–Many Peaks evidence corridor · score 76/100",
    lat: -24.45,
    lng: 151.25,
    radius: 24000,
    basis:
      "Known evidence 25/25; host context 18/25; structures 14/20; drainage 9/15; verified sampling coverage 10/15.",
  },
  {
    name: "Raglan records gap · score 65/100",
    lat: -23.75,
    lng: 150.65,
    radius: 18000,
    basis:
      "Known evidence 22/25; host context 14/25; structures 11/20; drainage 10/15; verified sampling coverage 8/15.",
  },
];
const tenureLayers = [
  { id: 3, name: "EPM granted", group: "grantedTenure" },
  { id: 25, name: "MDL granted", group: "grantedTenure" },
  {
    id: 36,
    name: "Mining claim granted",
    group: "grantedTenure",
    blocking: true,
  },
  {
    id: 44,
    name: "Mining lease granted",
    group: "grantedTenure",
    blocking: true,
  },
  { id: 2, name: "EPM application", group: "applicationTenure" },
  { id: 22, name: "MDL application", group: "applicationTenure" },
  { id: 33, name: "Mining claim application", group: "applicationTenure" },
  {
    id: 40,
    name: "Mining lease application",
    group: "applicationTenure",
    blocking: true,
  },
];
const adminLayers = [
  { id: 11, name: "Fossicking area", group: "fossickingLand", declared: true },
  {
    id: 15,
    name: "Designated fossicking land",
    group: "fossickingLand",
    declared: true,
  },
  {
    id: 20,
    name: "General permission area",
    group: "fossickingLand",
    declared: true,
  },
  { id: 13, name: "Restricted area", group: "constraints", restricted: true },
  {
    id: 9,
    name: "Native title indication",
    group: "constraints",
    native: true,
  },
];
function esc(x) {
  return String(x ?? "Not recorded").replace(
    /[&<>"']/g,
    (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        m
      ],
  );
}
function commodityText(d) {
  return [
    d.commodity,
    d.main_commodity,
    d.main_commodity_code,
    d.all_commodities,
  ]
    .filter(Boolean)
    .join(";")
    .toUpperCase();
}
function matches(d, w) {
  if (w === "All") return true;
  const t = commodityText(d);
  return (aliases[w] || [w.toUpperCase()]).some((a) =>
    a.length <= 2
      ? new RegExp(`(^|[^A-Z])${a}([^A-Z]|$)`).test(t)
      : t.includes(a),
  );
}
function display(d) {
  return Object.keys(aliases).find((k) => matches(d, k)) || "Other";
}
function recordPage(d) {
  return permanentPages[String(d.site_no)] || "";
}
function openDetail(html) {
  const e = document.querySelector("#detail");
  e.innerHTML = `<button id="closeDetail" class="no-print" aria-label="Close">×</button>${html}<p class="no-print"><button class="button ghost" id="printDossier" type="button">Print or save this dossier</button></p>`;
  e.classList.add("open");
  e.querySelector("#closeDetail").onclick = () => e.classList.remove("open");
  e.querySelector("#printDossier").onclick = () => window.print();
}
function recordDetailLink(d) {
  const site = String(d.site_no || d.mino_no || "");
  if (site && permanentPages[site]) return permanentPages[site];
  if (site) return `record.html?site=${encodeURIComponent(site)}`;
  return "";
}
function locationConfidenceLabel(d) {
  const accuracy = Number(d.loc_accuracy);
  if (!Number.isFinite(accuracy)) return "See source";
  if (accuracy <= 25) return `Precise · ±${accuracy} m`;
  if (accuracy <= 100) return `Approximate · ±${accuracy} m`;
  return `Locality-scale · ±${accuracy} m`;
}
function passesGemTypeFilter(d) {
  if (commodityGroup === "GOLD") return false;
  if (commodityGroup !== "GEMS") return true;
  return typeof matchesGemType === "function"
    ? matchesGemType(d, gemTypeFilter)
    : true;
}
function isGoldFeature(d) {
  if (d.commodity_group === "gems") return false;
  if (typeof matchesCommodityGroup === "function") {
    return matchesCommodityGroup(d, "GOLD");
  }
  return true;
}
function fieldObsBestCarats(d) {
  return d.best_sapphire_carats_approx ?? d.recovery_carats_approx;
}
function fieldObservationDossier(d) {
  const detailUrl =
    (typeof gemRecordDetailUrl === "function" ? gemRecordDetailUrl(d) : "") ||
    `record.html?gem=${encodeURIComponent(d.id || "")}`;
  const colourLabel = Array.isArray(d.colours)
    ? d.colours.map((c) => c.charAt(0).toUpperCase() + c.slice(1)).join(" / ")
    : esc(d.colours || "Not recorded");
  const dateLabel = d.date
    ? new Date(`${d.date}T12:00:00`).toLocaleDateString("en-AU", {
        day: "numeric",
        month: "long",
        year: "numeric",
      })
    : "Not recorded";
  const zirconNote = (d.associated_minerals || []).includes("zircon")
    ? `<dt>Associated mineral</dt><dd>Small zircons</dd>`
    : "";
  openDetail(
    `<p class="eyebrow dark">Confirmed field recovery</p><h2>${esc(d.locality || d.name)}</h2><div class="field-access-status"><strong>Confirmed worked patch</strong><span>Ground worked over approximately one week across about 10 m², partially excavated. GPS marks the worked patch ; not a surveyed wash boundary.</span></div><dl><dt>Best sapphire</dt><dd>~${esc(fieldObsBestCarats(d))} ct</dd><dt>Colours</dt><dd>${colourLabel}</dd><dt>Wash depth</dt><dd>~${esc(d.wash_depth_m_approx)} m</dd><dt>Worked area</dt><dd>~10 m², partially excavated</dd>${zirconNote}<dt>Date</dt><dd>${esc(dateLabel)}</dd><dt>Evidence</dt><dd>${esc(d.evidence_type || "GPS-tagged field observation")}</dd><dt>Coordinates</dt><dd>${Number(d.lat).toFixed(6)}, ${Number(d.lng).toFixed(6)}</dd><dt>Location confidence</dt><dd>${esc(d.location_confidence || "High")}</dd><dt>Source</dt><dd>${esc(d.source)}</dd></dl><p class="warning">Field observation only ; GPS marks the worked patch, not the extent of sapphire-bearing wash. Surrounding ground is not inferred to be productive.</p><div class="actions">${detailUrl ? `<a class="button primary" href="${esc(detailUrl)}">Open field observation record</a>` : ""}<a class="button ghost" href="https://www.qld.gov.au/recreation/activities/areas-facilities/fossicking">Queensland fossicking rules ↗</a></div>`,
  );
}
function gemDossier(d) {
  const hasPoint =
    Number.isFinite(Number(d.lat)) && Number.isFinite(Number(d.lng));
  const detailUrl =
    (typeof gemRecordDetailUrl === "function" ? gemRecordDetailUrl(d) : "") ||
    (d.site_no ? `record.html?site=${encodeURIComponent(d.site_no)}` : "");
  openDetail(
    `<p class="eyebrow dark">${esc(d.kind || d.record_type || "Gem record")}</p><h2>${esc(d.name || d.occur_name)}</h2><div class="field-access-status"><strong>Fossicking licence and area conditions still apply</strong><span>This authoritative record does not grant entry. Verify tenure, exclusions and every required permission.</span></div><p>${esc(d.detail || d.description || "Queensland Government gemfield record.")}</p><dl><dt>Gem type</dt><dd>${esc(gemTypeLabel ? gemTypeLabel(normalizeGemType ? normalizeGemType(d) : d.gem_type || "other_gem") : d.gem_type || d.commodity || "Not recorded")}</dt>${d.gem_subtype ? `<dt>Subtype</dt><dd>${esc(d.gem_subtype)}</dd>` : ""}<dt>Commodity</dt><dd>${esc(d.commodity || "Not recorded")}</dd><dt>Locality</dt><dd>${esc(d.locality || d.site_locality || d.basis)}</dd><dt>Record type</dt><dd>${esc(d.record_type || d.kind)}</dd>${Number.isFinite(Number(d.wash_depth_m)) ? `<dt>Recorded wash depth</dt><dd>${esc(d.wash_depth_m)} m (official text only)</dd>` : ""}<dt>Confidence</dt><dd>${esc(d.confidence || "Official source")}</dd><dt>Access / legal</dt><dd>${esc(d.access_notes || "Check current fossicking licence and area conditions.")}</dd>${d.site_no ? `<dt>Site number</dt><dd>MINOCC ${esc(d.site_no)}</dd>` : ""}${hasPoint ? `<dt>Coordinates</dt><dd>${Number(d.lat).toFixed(6)}, ${Number(d.lng).toFixed(6)}</dd>` : ""}<dt>Source</dt><dd>${esc(d.source || "Queensland Government")}</dd></dl><div class="actions">${detailUrl ? `<a class="button primary" href="${esc(detailUrl)}">Open full gem record</a>` : ""}${d.source_url ? `<a class="button ghost" href="${esc(d.source_url)}" target="_blank" rel="noopener">Open government source ↗</a>` : ""}<a class="button ghost" href="https://www.qld.gov.au/recreation/activities/areas-facilities/fossicking">Queensland fossicking rules ↗</a></div>`,
  );
}
function pointFieldObservationMarker(d) {
  return L.circleMarker([d.lat, d.lng], {
    radius: 8,
    color: "#fff",
    weight: 2,
    fillColor: "#c9772b",
    fillOpacity: 0.95,
    pane: "evidencePoints",
    renderer: evidenceRenderer,
  })
    .on("click", (e) => {
      L.DomEvent.stopPropagation(e);
      fieldObservationDossier(d);
    })
    .bindTooltip(
      `<strong>${esc(d.locality || d.name)}</strong><br>Confirmed sapphire recovery<br>Best sapphire: ~${esc(fieldObsBestCarats(d))} ct`,
      { direction: "top", className: "map-label" },
    )
    .addTo(groups.gemFieldObservations);
}
function pointGemMarker(d, g) {
  const gt = normalizeGemType ? normalizeGemType(d) : d.gem_type || "sapphire";
  const style = gemMarkerStyle ? gemMarkerStyle(gt) : { fill: "#356f9a", radius: 5 };
  const colour = g === "gemLocalities" ? "#6b4f8a" : style.fill;
  const radius = g === "gemLocalities" ? 7 : style.radius;
  return L.circleMarker([d.lat, d.lng], {
    radius,
    color: "#fff",
    weight: 1.5,
    fillColor: colour,
    fillOpacity: 0.92,
    pane: "evidencePoints",
    renderer: evidenceRenderer,
  })
    .on("click", (e) => {
      L.DomEvent.stopPropagation(e);
      gemDossier(d);
    })
    .addTo(groups[g]);
}
function redrawGems() {
  groups.gemOccurrences.clearLayers();
  groups.gemLocalities.clearLayers();
  groups.gemFieldObservations.clearLayers();
  if (commodityGroup === "GOLD") return;
  gemRecords.filter(passesGemTypeFilter).forEach((d) => pointGemMarker(d, "gemOccurrences"));
  gemLocalities.filter(passesGemTypeFilter).forEach((d) => pointGemMarker(d, "gemLocalities"));
  gemFieldObservations.filter(passesGemTypeFilter).forEach((d) => pointFieldObservationMarker(d));
  const visibleGems = gemRecords.filter(passesGemTypeFilter).length;
  const fossCount = document.querySelector("#gemFossickingCount");
  const occCount = document.querySelector("#gemOccurrenceCount");
  const locCount = document.querySelector("#gemLocalityCount");
  const fieldCount = document.querySelector("#gemFieldObservationCount");
  if (fossCount) fossCount.textContent = `(${groups.gemFossicking.getLayers().length})`;
  if (occCount) occCount.textContent = `(${visibleGems})`;
  if (locCount) locCount.textContent = `(${gemLocalities.length})`;
  if (fieldCount) fieldCount.textContent = `(${gemFieldObservations.length})`;
  applyViewportLayerVisibility();
}
function buildGemDensityLayers() {
  groups.gemOccurrenceDensity.clearLayers();
  groups.gemWorkingsDensity.clearLayers();
  const sapphireHeat = {
    radius: 22,
    blur: 18,
    minOpacity: 0.14,
    maxZoom: 13,
    gradient: {
      0.2: "rgba(210, 228, 245, 0.35)",
      0.45: "#8eb4d9",
      0.7: "#3d7aad",
      1: "#173f63",
    },
  };
  const workingsHeat = {
    radius: 20,
    blur: 16,
    minOpacity: 0.14,
    maxZoom: 13,
    gradient: {
      0.2: "rgba(210, 240, 245, 0.35)",
      0.45: "#7ec8d4",
      0.7: "#2a9aaa",
      1: "#0d5f6e",
    },
  };
  const occurrencePoints = [];
  const workingPoints = [];
  gemRecords.forEach((record) => {
    if (normalizeGemType && normalizeGemType(record) !== "sapphire") return;
    const point = [record.lat, record.lng, 0.65];
    if (record.record_type === "historical_working") workingPoints.push(point);
    else occurrencePoints.push(point);
  });
  if (occurrencePoints.length) {
    L.heatLayer(occurrencePoints, sapphireHeat).addTo(groups.gemOccurrenceDensity);
  }
  if (workingPoints.length) {
    L.heatLayer(workingPoints, workingsHeat).addTo(groups.gemWorkingsDensity);
  }
  const occCount = document.querySelector("#gemOccurrenceHeatCount");
  const workCount = document.querySelector("#gemWorkingsHeatCount");
  if (occCount) occCount.textContent = `(${occurrencePoints.length})`;
  if (workCount) workCount.textContent = `(${workingPoints.length})`;
  ["gemOccurrenceDensity", "gemWorkingsDensity"].forEach((layer) => {
    const input = document.querySelector(`input[data-layer="${layer}"]`);
    if (input?.checked && commodityGroup !== "GOLD") groups[layer].addTo(map);
    else map.removeLayer(groups[layer]);
  });
  applyViewportLayerVisibility();
}
function redrawKnownWash() {
  groups.gemKnownWash.clearLayers();
  if (commodityGroup === "GOLD") return;
  const depthFilter = document.querySelector("#gemWashDepthFilter");
  const depthInput = document.querySelector("#gemWashDepthMax");
  const depthOutput = document.querySelector("#gemWashDepthValue");
  const withDepth = gemKnownWashFeatures.filter((f) =>
    Number.isFinite(Number(f.properties?.wash_depth_m)),
  );
  if (depthFilter) {
    depthFilter.classList.toggle("hidden", !withDepth.length);
  }
  gemKnownWashFeatures.forEach((feature) => {
    const depth = Number(feature.properties?.wash_depth_m);
    if (
      withDepth.length &&
      Number.isFinite(depth) &&
      depth > gemWashDepthMax
    ) {
      return;
    }
    L.geoJSON(feature, {
      pointToLayer: (_f, latlng) =>
        L.circleMarker(latlng, {
          radius: 6,
          color: "#fff",
          weight: 1.5,
          fillColor: "#2a9aaa",
          fillOpacity: 0.92,
          pane: "evidencePoints",
          renderer: evidenceRenderer,
        }),
      onEachFeature: (f, layer) =>
        layer.on("click", (e) => {
          L.DomEvent.stopPropagation(e);
          const p = f.properties;
          const [lng, lat] = f.geometry.coordinates;
          gemDossier({
            id: p.id,
            name: p.name,
            locality: p.locality,
            record_type: "known_wash",
            kind: "Known wash (official alluvial MINOCC)",
            lat,
            lng,
            detail: [p.exposure_type, p.work_extent, p.provenance]
              .filter(Boolean)
              .join(" · "),
            access_notes:
              "Official alluvial record only. Verify licence, tenure and area conditions.",
            source: p.source,
            source_url: p.source_url,
            wash_depth_m: p.wash_depth_m,
          });
        }),
    }).addTo(groups.gemKnownWash);
  });
  const washCount = document.querySelector("#gemKnownWashCount");
  if (washCount) {
    washCount.textContent = `(${groups.gemKnownWash.getLayers().length})`;
  }
  if (depthInput && depthOutput && withDepth.length) {
    const maxDepth = Math.max(...withDepth.map((f) => Number(f.properties.wash_depth_m)));
    depthInput.max = String(Math.ceil(maxDepth));
    depthInput.value = String(gemWashDepthMax);
    depthOutput.textContent =
      gemWashDepthMax >= maxDepth ? "Any depth" : `≤ ${gemWashDepthMax} m recorded`;
  }
  applyViewportLayerVisibility();
}
function applyCommodityGroupMode() {
  const goldOnly = commodityGroup === "GEMS";
  const gemOnly = commodityGroup === "GOLD";
  document.querySelectorAll(".gold-only-layer").forEach((el) => {
    el.hidden = goldOnly;
  });
  document.querySelectorAll(".gem-evidence-section").forEach((el) => {
    el.classList.toggle("hidden", gemOnly);
  });
  const gemTypeSection = document.querySelector("#gemTypeFilterSection");
  if (gemTypeSection) {
    gemTypeSection.hidden = gemOnly || commodityGroup === "GOLD";
  }
  document.querySelector(".map-sidebar section:has(#commodityFilters)")?.classList.toggle(
    "hidden",
    goldOnly,
  );
  const disabledByMode = {
    GEMS: goldOnlyLayers,
    GOLD: gemOnlyLayers,
  };
  document.querySelectorAll("[data-layer]").forEach((input) => {
    const layer = input.dataset.layer;
    if (!disabledByMode[commodityGroup]?.has(layer)) {
      input.disabled = false;
      return;
    }
    input.checked = false;
    input.disabled = true;
    map.removeLayer(groups[layer]);
  });
  document.querySelectorAll(".map-sidebar [data-layer]").forEach((sourceInput) => {
    const panelInput = document
      .querySelector("#mapLayerChoices")
      ?.querySelector(`input[data-layer="${sourceInput.dataset.layer}"]`);
    if (!panelInput) return;
    panelInput.checked = sourceInput.checked;
    panelInput.disabled = sourceInput.disabled;
  });
  if (groups.statewideFossicking) {
    if (commodityGroup === "GOLD") {
      map.removeLayer(groups.statewideFossicking);
    } else {
      groups.statewideFossicking.addTo(map);
    }
  }
  applyViewportLayerVisibility();
  refreshMapLegend();
  redrawMinerals();
  if (window.CQFieldNotes) window.CQFieldNotes.commodityModeChanged();
  redrawGems();
  redrawKnownWash();
  const status = document.querySelector("#mapStatus");
  if (status) {
    const modeLabel =
      commodityGroup === "GOLD"
        ? "Gold mode"
        : commodityGroup === "GEMS"
          ? "Gems mode"
          : "All commodities";
    status.textContent = `${modeLabel} · ${features.filter((d) => matches(d, commodity) && isGoldFeature(d)).length} gold records · ${gemRecords.length} gem records loaded`;
  }
}
async function loadGemData() {
  try {
    const [fossicking, gemData, knownWash, fieldObservations, geologyManifest] = await Promise.all([
      fetch("data/gems/fossicking-areas.geojson").then((r) => r.json()),
      fetch("data/gems/gem-occurrences.json").then((r) => r.json()),
      fetch("data/gems/known-wash.geojson").then((r) => r.json()),
      fetch("data/gems/field-observations.json").then((r) => r.json()),
      fetch("data/gems/gem-geology-manifest.json").then((r) => r.json()),
    ]);
    L.geoJSON(fossicking, {
      style: {
        color: "#1f6f58",
        weight: 2,
        fillColor: "#4aa47d",
        fillOpacity: 0.14,
      },
      onEachFeature: (f, layer) =>
        layer.on("click", (e) => {
          L.DomEvent.stopPropagation(e);
          const p = f.properties;
          const centre = layer.getBounds().getCenter();
          gemDossier({
            ...p,
            kind: p.record_type === "designated_fossicking_land"
              ? "Designated fossicking land"
              : "Fossicking area",
            lat: centre.lat,
            lng: centre.lng,
            detail: p.description,
            access_notes: p.access_notes,
          });
        }),
    }).addTo(groups.gemFossicking);
    gemRecords = (gemData.records || [])
      .filter((r) => Number.isFinite(Number(r.latitude)) && Number.isFinite(Number(r.longitude)))
      .map((r) => ({
        ...r,
        lat: Number(r.latitude),
        lng: Number(r.longitude),
        kind: r.record_type === "historical_working" ? "Gem historical working" : "Gem occurrence",
        detail: r.description,
      }));
    gemLocalities = (gemData.localities || [])
      .filter((r) => Number.isFinite(Number(r.latitude)) && Number.isFinite(Number(r.longitude)))
      .map((r) => ({
        ...r,
        lat: Number(r.latitude),
        lng: Number(r.longitude),
        kind: "Gemfield locality",
        detail: r.description,
      }));
    gemKnownWashFeatures = knownWash.features || [];
    gemFieldObservations = (fieldObservations.records || [])
      .filter(
        (r) =>
          r.public_display !== false &&
          Number.isFinite(Number(r.latitude)) &&
          Number.isFinite(Number(r.longitude)),
      )
      .map((r) => ({
        ...r,
        lat: Number(r.latitude),
        lng: Number(r.longitude),
        kind: "Confirmed field recovery",
        detail: r.public_notes,
      }));
    const geologyWms = L.tileLayer.wms(geologyManifest.source.wms, {
      layers: geologyManifest.source.layer,
      format: "image/png",
      transparent: true,
      opacity: 0.55,
      attribution: "© State of Queensland geology mapping",
    });
    groups.gemGeology.clearLayers();
    geologyWms.addTo(groups.gemGeology);
    buildGemDensityLayers();
    redrawKnownWash();
    redrawGems();
    applyCommodityGroupMode();
  } catch (_) {
    document.querySelector("#mapStatus").textContent +=
      " · gem dataset unavailable";
  }
}
loadGemData();

function historicalMarkerStyle(props) {
  const approx = props.spatial_precision === "APPROXIMATE_POINT";
  const isGem = props.commodity_group === "gems";
  const isSource = /REEF|LODE|VEIN|SHAFT|HARD_ROCK|QUARTZ|BATTERY|CRUSHING/.test(props.evidence_type || "");
  return {
    radius: approx ? 7 : 6,
    color: isGem ? "#4a2d6b" : isSource ? "#6b4a12" : "#9a7b3c",
    fillColor: isGem ? "#8b5fbf" : isSource ? "#b8924a" : "#c4a35a",
    fillOpacity: approx ? 0.55 : 0.78,
    weight: approx ? 2 : 1.5,
    dashArray: approx ? "4 3" : null,
  };
}

function historicalEvidencePopup(props) {
  const unc = props.location_uncertainty_m
    ? `<p><strong>Location uncertainty:</strong> ~${Math.round(props.location_uncertainty_m)} m (${esc(props.spatial_precision)})</p>`
    : `<p><strong>Location:</strong> ${esc(props.spatial_precision || "unknown")}</p>`;
  return `<p class="eyebrow dark">Historical evidence</p><h2>${esc(props.name || "Historical record")}</h2><p>${esc(props.evidence_type)} · ${esc(props.commodity || "")}</p>${unc}<p>${esc(props.description || "").slice(0, 280)}</p><p class="method">${esc(props.provenance?.authority || "")}</p><p><a href="historical-record.html?id=${encodeURIComponent(props.record_id)}">Open historical record ↗</a></p>`;
}

async function loadHistoricalEvidence() {
  const sources = [
    { url: "data/historical-research/gold/historical-gold-sources.geojson", group: groups.historicalGoldSources, countId: "histGoldSrcCount" },
    { url: "data/historical-research/gold/historical-alluvial-evidence.geojson", group: groups.historicalGoldAlluvial, countId: "histGoldAllCount" },
    { url: "data/historical-research/gems/historical-gem-evidence.geojson", group: groups.historicalGemEvidence, countId: "histGemCount" },
  ];
  let total = 0;
  for (const { url, group, countId } of sources) {
    try {
      const data = await (await fetch(url)).json();
      group.clearLayers();
      let n = 0;
      for (const feat of data.features || []) {
        const props = feat.properties || {};
        if (!props.map_display || !feat.geometry || feat.geometry.type !== "Point") continue;
        const [lng, lat] = feat.geometry.coordinates;
        L.circleMarker([lat, lng], historicalMarkerStyle(props))
          .bindPopup(historicalEvidencePopup(props))
          .addTo(group);
        n += 1;
      }
      total += n;
      const el = document.getElementById(countId);
      if (el) el.textContent = `(${n})`;
    } catch (_) {
      /* historical layers optional until deployed */
    }
  }
  if (document.querySelector('[data-layer="historicalGoldAlluvial"]')?.checked) {
    groups.historicalGoldAlluvial.addTo(map);
  }
  if (document.querySelector('[data-layer="historicalGemEvidence"]')?.checked) {
    groups.historicalGemEvidence.addTo(map);
  }
}
loadHistoricalEvidence();
let stateRegionFilter = "";
const statewideGoldLoaded = new Set();
const statewideGemLoaded = new Set();
let statewideMoveTimer = null;

async function mergeStatewideGold(regionIds) {
  if (!window.CQStatewide) return;
  const ids =
    regionIds ||
    (stateRegionFilter
      ? [stateRegionFilter]
      : (
          await window.CQStatewide.loadRegionsMeta().then((meta) =>
            window.CQStatewide.regionsForViewport(map.getBounds(), meta).map(
              (region) => region.id,
            ),
          )
        ));
  for (const regionId of ids) {
    if (statewideGoldLoaded.has(regionId)) continue;
    const records = await window.CQStatewide.loadGoldRegion(regionId);
    statewideGoldLoaded.add(regionId);
    for (const record of records) {
      if (
        features.some(
          (existing) => String(existing.site_no) === String(record.site_no),
        )
      ) {
        continue;
      }
      features.push(record);
    }
  }
  redrawMinerals();
}

async function mergeStatewideGems(regionIds) {
  if (!window.CQStatewide) return;
  const ids =
    regionIds ||
    (stateRegionFilter
      ? [stateRegionFilter]
      : (
          await window.CQStatewide.loadRegionsMeta().then((meta) =>
            window.CQStatewide.regionsForViewport(map.getBounds(), meta).map(
              (region) => region.id,
            ),
          )
        ));
  for (const regionId of ids) {
    if (statewideGemLoaded.has(regionId)) continue;
    const records = await window.CQStatewide.loadGemRegion(regionId);
    statewideGemLoaded.add(regionId);
    for (const record of records) {
      if (
        gemRecords.some(
          (existing) => String(existing.site_no || existing.id) === String(record.site_no || record.id),
        )
      ) {
        continue;
      }
      gemRecords.push(record);
    }
  }
  buildGemDensityLayers();
  redrawGems();
  applyCommodityGroupMode();
}

async function loadStatewideFossicking() {
  if (!window.CQStatewide || groups.statewideFossicking) return;
  groups.statewideFossicking = L.layerGroup();
  const fossicking = await window.CQStatewide.loadFossicking();
  L.geoJSON(fossicking, {
    style: {
      color: "#1f6f58",
      weight: 2,
      fillColor: "#4aa47d",
      fillOpacity: 0.1,
    },
    onEachFeature: (f, layer) =>
      layer.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
        const p = f.properties;
        const centre = layer.getBounds().getCenter();
        gemDossier({
          ...p,
          kind: p.record_type === "designated_fossicking_land"
            ? "Designated fossicking land"
            : "Fossicking area",
          lat: centre.lat,
          lng: centre.lng,
          detail: p.description,
          access_notes: p.access_notes,
        });
      }),
  }).addTo(groups.statewideFossicking);
  if (commodityGroup !== "GOLD") groups.statewideFossicking.addTo(map);
}

function queueStatewideSync() {
  clearTimeout(statewideMoveTimer);
  statewideMoveTimer = setTimeout(async () => {
    try {
      if (commodityGroup !== "GEMS") await mergeStatewideGold();
      if (commodityGroup !== "GOLD") await mergeStatewideGems();
    } catch (_) {
      const status = document.querySelector("#mapStatus");
      if (status) status.textContent += " · statewide subset unavailable";
    }
  }, 350);
}

async function initStatewideFilters() {
  if (!window.CQStatewide) return;
  const [index, regionsMeta] = await Promise.all([
    window.CQStatewide.loadIndex(),
    window.CQStatewide.loadRegionsMeta(),
  ]);
  const select = document.querySelector("#stateRegionFilter");
  if (!select) return;
  for (const region of regionsMeta.state_regions) {
    const goldCount = index.gold_regions.find((entry) => entry.id === region.id)?.record_count || 0;
    const gemCount = index.gem_regions.find((entry) => entry.id === region.id)?.record_count || 0;
    const labelCount = goldCount + gemCount;
    select.insertAdjacentHTML(
      "beforeend",
      `<option value="${esc(region.id)}">${esc(region.name)} (${labelCount.toLocaleString()} records)</option>`,
    );
  }
  select.onchange = async () => {
    stateRegionFilter = select.value;
    if (stateRegionFilter) {
      await mergeStatewideGold([stateRegionFilter]);
      await mergeStatewideGems([stateRegionFilter]);
      const region = regionsMeta.state_regions.find((entry) => entry.id === stateRegionFilter);
      if (region?.centre) map.setView(region.centre, 7);
    } else {
      queueStatewideSync();
    }
  };
  loadStatewideFossicking().catch(() => {});
  queueStatewideSync();
}

map.on("moveend", () => {
  queueStatewideSync();
  applyViewportLayerVisibility();
});
initStatewideFilters();
function dossier(d) {
  const permanent = recordPage(d) || recordDetailLink(d) || d.url;
  const hasPoint =
    Number.isFinite(Number(d.lat)) && Number.isFinite(Number(d.lng));
  const areaLink = hasPoint
    ? `map.html?commodity=${encodeURIComponent(display(d))}&site=${encodeURIComponent(d.site_no || "")}&lat=${Number(d.lat).toFixed(6)}&lng=${Number(d.lng).toFixed(6)}&z=16`
    : "";
  const externalMap = hasPoint
    ? `https://www.google.com/maps/@?api=1&map_action=map&center=${Number(d.lat).toFixed(6)}%2C${Number(d.lng).toFixed(6)}&zoom=16&basemap=satellite`
    : "";
  const isGeoResGlobe =
    permanent === "https://georesglobe.information.qld.gov.au/";
  const navigation = hasPoint
    ? `https://www.google.com/maps/dir/?api=1&destination=${Number(d.lat).toFixed(6)}%2C${Number(d.lng).toFixed(6)}`
    : "";
  const proximity = hasPoint && userLocation
    ? `<div class="field-proximity"><strong>${distanceKm(userLocation, { lat: Number(d.lat), lng: Number(d.lng) }).toFixed(2)} km · ${bearingLabel(userLocation, { lat: Number(d.lat), lng: Number(d.lng) })}</strong><span>straight-line distance and direction from your last device location</span></div>`
    : "";
  const noteKey = `cqdiggings-field-note:${d.site_no || d.mino_no || d.name || d.occur_name || `${d.lat},${d.lng}`}`;
  let savedNote = "";
  try { savedNote = localStorage.getItem(noteKey) || ""; } catch (_) {}
  const siteNo = String(d.site_no || d.mino_no || "");
  const linkedReports = reportMatches[siteNo] || [];
  const reportBlock = linkedReports.length
    ? `<dt>Linked reports</dt><dd>${linkedReports.length} matched in local GSQ archive text</dd>`
    : `<dt>Linked reports</dt><dd>None matched in extracted text yet</dd>`;
  const links = `${hasPoint ? `<button id="assessMapRecord" class="button primary" type="button">Can I fossick here?</button><button id="saveMapRecord" class="button primary" type="button">Save place to My Diggings</button>` : ""}${permanent ? `<a class="button primary" href="${esc(permanent)}">${permanent.startsWith("occurrences/") || permanent.startsWith("record.html") ? "Open full CQDiggings record →" : isGeoResGlobe ? "Open GeoResGlobe viewer ↗" : "Open permanent record ↗"}</a>` : ""}${areaLink ? `<a class="button ghost" href="${esc(areaLink)}">Keep this area on the map</a>` : ""}${externalMap ? `<a class="button ghost" href="${esc(externalMap)}" target="_blank" rel="noopener">Open centred satellite map ↗</a>` : ""}${navigation ? `<a class="button ghost" href="${esc(navigation)}" target="_blank" rel="noopener">Open navigation ↗</a>` : ""}<a class="button ghost" href="maps-library.html">Browse historical maps</a>`;
  openDetail(
    `<p class="eyebrow dark">${esc(d.kind || d.site_type || d.record_type || "Official record")}</p><h2>${esc(d.name || d.occur_name)}</h2>${proximity}<div class="field-access-status"><strong>Access not established</strong><span>This geological record does not grant entry or fossicking permission. Check the parcel, occupier, tenure and exclusions separately.</span></div><p>${esc(d.detail || d.work_extent_comments || "Queensland Government mineral record.")}</p><dl><dt>Commodity</dt><dd>${esc(d.commodity || d.main_commodity || d.all_commodities)}</dd><dt>All commodities</dt><dd>${esc(d.all_commodities)}</dd><dt>Record type</dt><dd>${esc(d.record_type || d.site_type || d.kind)}</dd><dt>Site number</dt><dd>${esc(d.site_no || d.mino_no)}</dd><dt>Status / extent</dt><dd>${esc(d.mine_status || d.work_extent)}</dd><dt>Exposure</dt><dd>${esc(d.exposure_type || "Not recorded")}</dd><dt>Locality</dt><dd>${esc(d.site_locality || d.basis)}</dd><dt>Location confidence</dt><dd>${esc(locationConfidenceLabel(d))}</dd>${hasPoint ? `<dt>Coordinates</dt><dd>${Number(d.lat).toFixed(6)}, ${Number(d.lng).toFixed(6)}</dd>` : ""}${reportBlock}<dt>Source</dt><dd>${esc(d.source || "Queensland Government")}</dd></dl>${linkedReports.length ? `<div class="records">${linkedReports.slice(0, 3).map((report) => `<article class="record"><h3>${esc(report.title)}</h3><p>${esc(report.locality || "")}</p><a href="${esc(report.directUrl || report.officialUrl)}" target="_blank" rel="noopener">Open matched report ↗</a></article>`).join("")}${linkedReports.length > 3 ? `<p><small>${linkedReports.length - 3} more linked report(s) on the full record page.</small></p>` : ""}</div>` : ""}<div class="actions">${links}</div><p class="navigation-warning"><strong>Navigation is orientation only.</strong> A suggested route may cross private land, closed roads or restricted areas and does not provide access rights.</p><details class="field-note"><summary>Private field note</summary><label for="fieldNoteText">Saved only in this browser</label><textarea id="fieldNoteText" rows="4">${esc(savedNote)}</textarea><button id="saveFieldNote" type="button">Save note</button><span id="fieldNoteStatus"></span></details><p><a href="contact.html">Report an incorrect location, duplicate or access concern</a></p>${isGeoResGlobe ? "<p><small>GeoResGlobe does not publish a supported coordinate deep-link format. Use the centred CQDiggings or satellite link above, then search the authority number manually in GeoResGlobe.</small></p>" : ""}`,
  );
  const assessRecord = document.querySelector("#assessMapRecord");
  if (assessRecord) assessRecord.onclick = () =>
    assess({ lat: Number(d.lat), lng: Number(d.lng) });
  const saveNote = document.querySelector("#saveFieldNote");
  if (saveNote) saveNote.onclick = () => {
    try {
      localStorage.setItem(noteKey, document.querySelector("#fieldNoteText").value);
      document.querySelector("#fieldNoteStatus").textContent = "Saved on this device";
    } catch (_) {
      document.querySelector("#fieldNoteStatus").textContent = "This browser could not save the note";
    }
  };
  const saveRecord = document.querySelector("#saveMapRecord");
  if (saveRecord) saveRecord.onclick = () => {
    const key = "cqdiggings:saved-records";
    try {
      const existing = JSON.parse(localStorage.getItem(key) || "[]");
      const id = String(d.site_no || d.mino_no || `${d.lat},${d.lng}`);
      const record = {
        id,
        title: d.name || d.occur_name || "Mapped record",
    locality: d.site_locality || d.basis || "Queensland",
        note: [d.commodity || d.main_commodity, d.mine_status || d.work_extent].filter(Boolean).join(" · "),
        url: areaLink,
        saved: new Date().toISOString(),
      };
      localStorage.setItem(key, JSON.stringify([record, ...existing.filter((item) => item.id !== id)].slice(0, 100)));
      saveRecord.textContent = "Saved in My Diggings";
      saveRecord.disabled = true;
    } catch (_) {
      saveRecord.textContent = "This browser could not save it";
    }
  };
}
function pointMarker(d, g) {
  const title = d.name || d.occur_name || "Recorded site";
  const commodityLabel = d.commodity || d.main_commodity || d.all_commodities;
  const locality = d.site_locality || d.basis || d.detail;
  const preview = `<div class="map-hover-preview"><strong>${esc(title)}</strong><span>${esc(d.kind || d.site_type || (g === "archive" ? "CQDiggings source" : "Official mineral record"))}</span><dl><dt>Commodity</dt><dd>${esc(commodityLabel)}</dd><dt>Locality</dt><dd>${esc(locality)}</dd>${d.site_no ? `<dt>Site</dt><dd>${esc(d.site_no)}</dd>` : ""}${d.loc_accuracy ? `<dt>Accuracy</dt><dd>±${esc(d.loc_accuracy)} m</dd>` : ""}</dl><small>Click for the full dossier</small></div>`;
  return L.circleMarker([d.lat, d.lng], {
    pane: "evidencePoints",
    renderer: evidenceRenderer,
    radius: g === "archive" ? 8 : 5,
    color: "#fff",
    weight: 1,
    fillColor: colours[display(d)],
    fillOpacity: 0.88,
  })
    .bindTooltip(preview, {
      direction: "top",
      className: "map-hover-card",
      offset: [0, -7],
    })
    .on("click", (e) => {
      L.DomEvent.stopPropagation(e);
      dossier(d);
    })
    .addTo(groups[g]);
}
archive.forEach((d) => pointMarker(d, "archive"));
async function loadDensityLayers() {
  const response = await fetch("data/queensland-gold-occurrences.json");
  if (!response.ok) throw new Error("Regional evidence file unavailable");
  const data = await response.json();
  const heatOptions = {
    radius: 24,
    blur: 20,
    minOpacity: 0.12,
    maxZoom: 12,
    gradient: {
      0.2: "rgba(224, 239, 218, 0.35)",
      0.45: "#b8d9ad",
      0.7: "#62a76d",
      1: "#17633a",
    },
  };
  const allPoints = [];
  const workingPoints = [];
  const occurrencePoints = [];
  data.records.forEach((record) => {
    if (
      !Number.isFinite(Number(record.latitude)) ||
      !Number.isFinite(Number(record.longitude))
    ) {
      return;
    }
    const point = [
      Number(record.latitude),
      Number(record.longitude),
      0.65,
    ];
    allPoints.push(point);
    const type = String(record.record_type || record.site_type || "").toLowerCase();
    if (type.includes("historical")) workingPoints.push(point);
    else if (type.includes("occurrence")) occurrencePoints.push(point);
  });
  L.heatLayer(allPoints, heatOptions).addTo(groups.recordDensity);
  L.heatLayer(workingPoints, { ...heatOptions, radius: 22 }).addTo(
    groups.workingsDensity,
  );
  L.heatLayer(occurrencePoints, {
    ...heatOptions,
    radius: 20,
    gradient: {
      0.2: "rgba(230, 236, 245, 0.35)",
      0.45: "#b8c9e0",
      0.7: "#6280a7",
      1: "#173f63",
    },
  }).addTo(groups.occurrenceDensity);
  const setCount = (selector, points) => {
    const count = document.querySelector(selector);
    if (count) count.textContent = `(${points.length.toLocaleString()})`;
  };
  setCount("#evidenceHeatCount", allPoints);
  setCount("#workingsHeatCount", workingPoints);
  setCount("#occurrenceHeatCount", occurrencePoints);
  ["recordDensity", "workingsDensity", "occurrenceDensity"].forEach((layer) => {
    const input = document.querySelector(`input[data-layer="${layer}"]`);
    if (input?.checked) groups[layer].addTo(map);
    else map.removeLayer(groups[layer]);
  });
  applyViewportLayerVisibility();
}
loadDensityLayers().catch(() => {
  ["#evidenceHeatCount", "#workingsHeatCount", "#occurrenceHeatCount"].forEach(
    (selector) => {
      const count = document.querySelector(selector);
      if (count) count.textContent = "(unavailable)";
    },
  );
});
async function loadGoldPotential() {
  const response = await fetch("data/gold-path-model.json");
  if (!response.ok) throw new Error("Gold-potential model unavailable");
  const model = await response.json();
  const mobileCorridorScale = window.matchMedia("(max-width: 800px)").matches ? 0.6 : 1;
  const potentialColour = (feature) => {
    const score = Number(feature.properties.model_weight || 0);
    if (score >= 0.92) return "#17633a";
    if (score >= 0.8) return "#4c8f5d";
    return "#9bc792";
  };
  // Nested translucent lines create a continuous graduated corridor. The old
  // point heat layer made the model look like unrelated glowing dots.
  L.geoJSON(model.corridors, {
    pane: "potentialCorridors",
    renderer: potentialRenderer,
    interactive: false,
    style: () => ({
      className: "potential-halo-outer",
      color: "#cce5c5",
      weight: 16 * mobileCorridorScale,
      opacity: 0.3,
      lineCap: "round",
      lineJoin: "round",
    }),
  }).addTo(groups.prospectivity);
  L.geoJSON(model.corridors, {
    pane: "potentialCorridors",
    renderer: potentialRenderer,
    interactive: false,
    style: (feature) => ({
      className: "potential-halo-inner",
      color: potentialColour(feature),
      weight: (11 + Number(feature.properties.model_weight || 0) * 7) * mobileCorridorScale,
      opacity: 0.34,
      lineCap: "round",
      lineJoin: "round",
    }),
  }).addTo(groups.prospectivity);
  L.geoJSON(model.corridors, {
    pane: "potentialCorridors",
    renderer: potentialRenderer,
    style: (feature) => ({
      color: potentialColour(feature),
      weight: Math.max(1.4, (2 + Number(feature.properties.model_weight || 0) * 2) * Math.max(0.55, mobileCorridorScale)),
      opacity: feature.properties.confidence === "supported" ? 0.82 : 0.58,
      dashArray: feature.properties.confidence === "supported" ? null : "5 5",
      lineCap: "round",
      lineJoin: "round",
    }),
    onEachFeature: (feature, layer) => {
      const properties = feature.properties;
      const sources = properties.source_site_numbers || [];
      const alluvial = properties.supporting_alluvial_site_numbers || [];
      const watercourse = properties.name || properties.alternate_name || "Unnamed mapped watercourse";
      layer.bindTooltip(
        `<strong>${esc(watercourse)}</strong><br>${esc(properties.confidence)} model corridor · ${sources.length} source ${sources.length === 1 ? "record" : "records"}${alluvial.length ? ` · ${alluvial.length} nearby alluvial ${alluvial.length === 1 ? "record" : "records"}` : ""}<br><small>Click for full evidence and limits</small>`,
        { direction: "top", className: "map-cluster-hover" },
      );
      layer.on("click", (event) => {
        L.DomEvent.stopPropagation(event);
        openDetail(`<p class="eyebrow dark">Experimental gold pathway research</p>
          <h2>${esc(watercourse)}</h2>
          <p>This line follows an official, flow-directed Queensland watercourse downstream from record-derived reef, lode, vein or quartz source evidence. It is experimental research context ; not proof of gold and not a recommendation.</p>
          <dl>
            <dt>Model confidence</dt><dd>${esc(properties.confidence)}${properties.confidence === "supported" ? " ; one or more alluvial records lie near this modelled corridor" : " ; source and drainage connection only; no nearby alluvial record has yet been matched"}</dd>
            <dt>Relative model score</dt><dd>${Math.round(Number(properties.model_weight || 0) * 100)} / 100</dd>
            <dt>Source record numbers</dt><dd>${esc(sources.join(", ") || "None recorded")}</dd>
            <dt>Nearby alluvial record numbers</dt><dd>${esc(alluvial.join(", ") || "None matched")}</dd>
            <dt>Stream order</dt><dd>${esc(properties.stream_order || "Not supplied")}</dd>
            <dt>Drainage basin</dt><dd>${esc(properties.drainage_basin || "Not supplied")}</dd>
            <dt>Watercourse mapping source</dt><dd>${esc(properties.feature_source || "Queensland Government watercourse service")}</dd>
          </dl>
          <div class="warning"><strong>Do not treat this as a find or access map.</strong><br>Current drainage may differ from the channel that transported historical alluvial gold. The model does not yet include a terrain model, mapped geological structures or verified palaeochannels. Check the individual records, ground, tenure and permission.</div>
          <div class="actions"><a class="button primary" href="heatmap-method.html">Read the complete method</a><a class="button ghost" href="data/gold-path-model.json" target="_blank">Open model data ↗</a></div>`);
      });
    },
  }).addTo(groups.prospectivity);
  const count = document.querySelector("#potentialCount");
  if (count) count.textContent = `(${model.corridors.features.length.toLocaleString()} corridors)`;
}
loadGoldPotential().catch(() => {
  const count = document.querySelector("#potentialCount");
  if (count) count.textContent = "(unavailable)";
});
priorities.forEach((d) =>
  L.circle([d.lat, d.lng], {
    radius: d.radius,
    color: "#9c4e2b",
    weight: 2,
    dashArray: "7 6",
    fillColor: "#c99642",
    fillOpacity: 0.14,
  })
    .bindTooltip(`${esc(d.name)} · click for scoring basis`, {
      direction: "top",
    })
    .on("click", (e) => {
      L.DomEvent.stopPropagation(e);
      dossier({
        ...d,
        kind: "Evidence-ranked research area",
        commodity: "Multiple evidence factors",
        detail:
          "Research-priority intensity only. This score ranks documentary follow-up; it is not a gold probability, discovery or access statement.",
        basis: d.basis,
      });
    })
    .addTo(groups.prioritiesResearch),
);
const prioritiesCount = document.querySelector("#prioritiesCount");
if (prioritiesCount)
  prioritiesCount.textContent = `(${priorities.length} study areas)`;
[groups.historical, groups.occurrences].forEach((group) => {
  group.on("clustermouseover", (event) => {
    const count = event.layer.getChildCount();
    event.layer
      .bindTooltip(
        `<strong>${count.toLocaleString()} records in this cluster</strong><br><span>Click to zoom in and hover over individual points.</span>`,
        { direction: "top", className: "map-cluster-hover" },
      )
      .openTooltip();
  });
});
async function loadClermontLegalGold() {
  const response = await fetch("data/clermont-legal-gold-prospectivity.geojson?v=20260826a");
  if (!response.ok) throw new Error("Clermont prospectivity layer unavailable");
  const data = await response.json();
  let targetCount = 0;
  L.geoJSON(data, {
    style: (feature) => {
      const p = feature.properties || {};
      const excluded = p.priority === "exclusion" || /exclusion/i.test(p.name || "");
      if (!excluded) targetCount += 1;
      return excluded
        ? { color: "#2d004b", weight: 2, fillColor: "#653a80", fillOpacity: 0.58 }
        : { color: p.stroke || "#8e2d1e", weight: 2, fillColor: p.fill || "#d95f35", fillOpacity: Number(p.fill_opacity) || 0.48 };
    },
    onEachFeature: (feature, layer) => {
      const p = feature.properties || {};
      const excluded = p.priority === "exclusion" || /exclusion/i.test(p.name || "");
      const drains = (p.nearby_named_drainage || []).join(", ") || "No named watercourse within 1 km";
      layer.bindPopup(`<strong>${esc(p.name)}</strong><br>${esc(p.basis)}<br><small>Confidence: ${esc(p.confidence)} · ${esc(p.area_ha || "")} ha<br>Drainage: ${esc(drains)}</small><p><strong>${excluded ? "Current tenure exclusion." : "Potentially accessible, not automatically legal."}</strong><br>${esc(p.legal_status)}</p><a href="clermont-gold-investigation.html">Open the full investigation</a>`);
    },
  }).addTo(groups.legalGold);
  const count = document.querySelector("#legalGoldCount");
  if (count) count.textContent = `(${targetCount})`;
}
loadClermontLegalGold().catch(() => {
  const count = document.querySelector("#legalGoldCount");
  if (count) count.textContent = "(unavailable)";
});

async function loadClermontValidationPoints() {
  const response = await fetch("data/clermont-field-validation-points.geojson?v=20260826b");
  if (!response.ok) throw new Error("Clermont validation points unavailable");
  const data = await response.json();
  L.geoJSON(data, {
    pointToLayer: (_feature, latlng) =>
      L.circleMarker(latlng, {
        radius: 6,
        color: "#4f3a73",
        weight: 2,
        fillColor: "#f2c14e",
        fillOpacity: 0.9,
      }),
    onEachFeature: (feature, layer) => {
      const p = feature.properties || {};
      const drains = (p.nearby_named_drainage || []).join(", ") || "No named watercourse recorded nearby";
      layer.bindPopup(`<strong>${esc(p.validation_id)} · ${esc(p.target_name)}</strong><p><strong>Desktop planning point only — not a dig-here coordinate or access guarantee.</strong></p><p>${esc(p.field_task)}</p><small>Confidence: ${esc(p.confidence)} · approximate target-edge clearance: ${esc(p.edge_clearance_m_approx)} m<br>Drainage: ${esc(drains)}</small><p>${esc(p.legal_gate)}</p><a href="clermont-gold-investigation.html">Open the full investigation</a>`);
    },
  }).addTo(groups.legalGold);
}
loadClermontValidationPoints().catch(() => {});

async function loadMinerals(id, g) {
  let offset = 0;
  for (;;) {
    const u = `${MINES}/${id}/query?where=1%3D1&geometry=${BBOX}&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&outSR=4326&resultOffset=${offset}&resultRecordCount=2000&orderByFields=objectid&f=geojson`,
      j = await (await fetch(u)).json();
    if (j.error) throw Error(j.error.message);
    j.features.forEach((f) => {
      const [lng, lat] = f.geometry.coordinates;
      features.push({ ...f.properties, lat, lng, group: g });
    });
    if (j.features.length < 2000) break;
    offset += j.features.length;
  }
}
function redrawMinerals() {
  groups.historical.clearLayers();
  groups.occurrences.clearLayers();
  if (commodityGroup === "GEMS") return;
  const shown = features.filter((d) => matches(d, commodity) && isGoldFeature(d));
  shown.forEach((d) => pointMarker(d, d.group));
  const h = shown.filter((x) => x.group === "historical").length,
    o = shown.length - h;
  document.querySelector("#historicalCount").textContent = `(${h})`;
  document.querySelector("#occurrenceCount").textContent = `(${o})`;
  if (commodityGroup !== "GEMS") {
    document.querySelector("#mapStatus").textContent =
      `Showing ${shown.length} of ${features.length} official mineral records · markers cluster when zoomed out`;
  }
  applyViewportLayerVisibility();
}
function tenureTitle(p, fallback) {
  return p.displayname || p.permitnumber || p.permit_no || fallback;
}
function tenureHolder(p) {
  return (
    p.authorisedholdername || p.holdername || p.holder || "Holder not returned"
  );
}
async function loadTenureLayer(config) {
  const u = `${TENURE}/${config.id}/query?where=1%3D1&geometry=${BBOX}&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&outSR=4326&f=geojson`,
    j = await (await fetch(u)).json();
  if (j.error) throw Error(j.error.message);
  L.geoJSON(j, {
    style: {
      color: config.group === "grantedTenure" ? "#742f25" : "#a96b27",
      weight: 1.5,
      dashArray: config.group === "applicationTenure" ? "5 4" : null,
      fillColor: config.group === "grantedTenure" ? "#9b493b" : "#d69c45",
      fillOpacity: 0.12,
    },
    onEachFeature: (f, l) =>
      l.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
        const p = f.properties;
        dossier({
          kind: config.name,
          name: tenureTitle(p, config.name),
          detail: `${p.permitstatus || p.permitstate || ""}. Holder/applicant: ${tenureHolder(p)}.`,
          basis: p.permitpurpose || p.permitminerals,
          source:
            "Queensland Government current mining and exploration tenure service",
          url: "https://georesglobe.information.qld.gov.au/",
          lat: e.latlng.lat,
          lng: e.latlng.lng,
        });
      }),
  }).addTo(groups[config.group]);
  return j.features.length;
}
async function queryLayerAt(config, p) {
  const u = `${TENURE}/${config.id}/query?where=1%3D1&geometry=${p.lng},${p.lat}&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=false&f=json`,
    j = await (await fetch(u)).json();
  return (j.features || []).map((f) => ({ config, properties: f.attributes }));
}
async function loadAdminLayer(config) {
  const u = `${ADMIN}/${config.id}/query?where=1%3D1&geometry=${BBOX}&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&outSR=4326&f=geojson`,
    j = await (await fetch(u)).json();
  if (j.error) throw Error(j.error.message);
  L.geoJSON(j, {
    style: {
      color: config.declared ? "#1f6f58" : "#704f83",
      weight: 2,
      dashArray: config.native ? "3 5" : null,
      fillColor: config.declared ? "#4aa47d" : "#8d70a1",
      fillOpacity: config.declared ? 0.14 : 0.08,
    },
    onEachFeature: (f, l) =>
      l.on("click", (e) => {
        L.DomEvent.stopPropagation(e);
        const p = f.properties;
        dossier({
          kind: config.name,
          name: p.name || p.displayname || p.label || config.name,
          detail: config.declared
            ? "An official administrative fossicking boundary was returned. A licence and every current area condition still apply."
            : "Constraint indication only. Verify the current legal effect in GeoResGlobe and official records.",
          source: "Queensland Government Mining Administrative Areas",
          url: "https://georesglobe.information.qld.gov.au/",
          lat: e.latlng.lat,
          lng: e.latlng.lng,
        });
      }),
  }).addTo(groups[config.group]);
  return j.features.length;
}
async function queryAdminAt(config, p) {
  const u = `${ADMIN}/${config.id}/query?where=1%3D1&geometry=${p.lng},${p.lat}&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=false&f=json`,
    j = await (await fetch(u)).json();
  return (j.features || []).map((f) => ({ config, properties: f.attributes }));
}
const protectedLayers = [
  { id: 10, name: "Protected areas and forests", estate: true },
  { id: 15, name: "Special wildlife reserve", wildlife: true },
];
const protectedEstateLabels = {
  NP: "National Park",
  NS: "National Park (Scientific)",
  NY: "National Park (CYPAL)",
  NA: "National Park (Aboriginal Land)",
  CP: "Conservation Park",
  RR: "Resources Reserve",
  FR: "Forest Reserve",
  SF: "State Forest",
  TR: "Timber Reserve",
};
const prohibitedProtectedTypes = new Set(["NP", "NS", "NY", "NA", "CP"]);
const forestExceptionTypes = new Set(["SF", "TR"]);

function protectedName(record) {
  const p = record.properties || {};
  return p.estatename || p.name || p.altname || record.config.name;
}
function protectedType(record) {
  if (record.config.wildlife) return "Special Wildlife Reserve";
  const code = String(record.properties?.esttype || "").toUpperCase();
  return protectedEstateLabels[code] || code || record.config.name;
}
async function queryProtectedAt(config, p) {
  const url = `${PROTECTED}/${config.id}/query?where=1%3D1&geometry=${p.lng},${p.lat}&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=false&f=json`;
  const payload = await (await fetch(url)).json();
  if (payload.error) throw Error(payload.error.message);
  return (payload.features || []).map((feature) => ({
    config,
    properties: feature.attributes || {},
  }));
}
async function loadProtectedLayer(config) {
  const url = `${PROTECTED}/${config.id}/query?where=1%3D1&geometry=${BBOX}&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&outSR=4326&f=geojson`;
  const payload = await (await fetch(url)).json();
  if (payload.error) throw Error(payload.error.message);
  L.geoJSON(payload, {
    style: (feature) => {
      const type = String(feature.properties?.esttype || "").toUpperCase();
      const forest = forestExceptionTypes.has(type) || type === "FR";
      return {
        color: forest ? "#8b5a2b" : "#426b35",
        weight: 1.5,
        dashArray: forest ? "6 4" : null,
        fillColor: forest ? "#c49a6c" : "#6f9b5b",
        fillOpacity: 0.1,
      };
    },
    onEachFeature: (feature, layer) =>
      layer.on("click", (event) => {
        L.DomEvent.stopPropagation(event);
        const record = { config, properties: feature.properties || {} };
        dossier({
          kind: protectedType(record),
          name: protectedName(record),
          detail: config.wildlife
            ? "Special wildlife reserve returned. Fossicking is prohibited in wildlife reserves."
            : "Current Queensland protected-area or forest estate returned. Use the point access assessment to apply the official fossicking rules and any GPA exception.",
          source: "Queensland Government Parks Terrestrial Protected Areas",
          url: `${PROTECTED}/${config.id}`,
          lat: event.latlng.lat,
          lng: event.latlng.lng,
        });
      }),
  }).addTo(groups.protectedAreas);
  return (payload.features || []).length;
}

async function assess(p) {
  openDetail(
    '<p class="eyebrow dark">Access assessment</p><h2>Checking current services…</h2><p>This may take a few seconds.</p>',
  );
  try {
    const parcelUrl = `${PARCEL}/query?where=1%3D1&geometry=${p.lng},${p.lat}&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=lotplan,tenure,lot_area,locality,shire_name&returnGeometry=false&f=json`;
    const all = await Promise.all([
      fetch(parcelUrl).then((response) => response.json()),
      ...tenureLayers.map((config) => queryLayerAt(config, p)),
      ...adminLayers.map((config) => queryAdminAt(config, p)),
      ...protectedLayers.map((config) => queryProtectedAt(config, p)),
    ]);
    const tenureStart = 1;
    const adminStart = tenureStart + tenureLayers.length;
    const protectedStart = adminStart + adminLayers.length;
    const parcel = all[0].features?.[0]?.attributes;
    const tenures = all.slice(tenureStart, adminStart).flat();
    const admin = all.slice(adminStart, protectedStart).flat();
    const protectedRecords = all.slice(protectedStart).flat();
    const blocks = tenures.filter((record) => record.config.blocking);
    const declared = admin.filter((record) => record.config.declared);
    const restricted = admin.filter((record) => record.config.restricted);
    const native = admin.filter((record) => record.config.native);
    const protectedProhibited = protectedRecords.filter((record) =>
      record.config.wildlife ||
      prohibitedProtectedTypes.has(String(record.properties?.esttype || "").toUpperCase()),
    );
    const forests = protectedRecords.filter((record) =>
      forestExceptionTypes.has(String(record.properties?.esttype || "").toUpperCase()),
    );
    const forestWithoutGpa = forests.length > 0 && declared.length === 0;

    let classification = "CHECKS INCOMPLETE";
    let status = "No automatic public access confirmed";
    let explanation =
      "Current landholder permission may still be required. Cultural-heritage, temporary-closure, fire, road and weather checks remain outstanding.";

    if (protectedProhibited.length || restricted.length) {
      classification = "PROHIBITED";
      status = protectedProhibited.length
        ? "Protected land ; fossicking prohibited"
        : "Restricted area ; do not fossick";
      explanation = protectedProhibited.length
        ? "A national park, conservation park or special wildlife reserve intersects this point. Queensland rules prohibit fossicking in these protected areas."
        : "A restricted-area boundary intersects this point. Do not fossick unless current official advice explicitly confirms it is lawful.";
    } else if (forestWithoutGpa) {
      classification = "PROHIBITED";
      status = "State forest or timber reserve without a GPA";
      explanation =
        "Queensland permits fossicking in state forests and timber reserves only where a general permission area applies. No intersecting GPA was returned.";
    } else if (blocks.length) {
      classification = "PERMISSION REQUIRED";
      status = "Written resource-holder permission required";
      explanation =
        "A mining claim, mining lease or mining-lease application intersects this point. Obtain every required written permission before entry or fossicking.";
    } else if (declared.length) {
      classification = "OFFICIAL FOSSICKING AREA";
      status = forests.length
        ? "Official GPA within forest estate"
        : "Official fossicking boundary returned";
      explanation =
        "An official fossicking boundary intersects this point. A current licence, the exact area map, exclusions and all special conditions still apply.";
    } else if (parcel) {
      classification = "PERMISSION REQUIRED";
      status = "Landholder permission required unless an official exception applies";
      explanation =
        "No official fossicking boundary was returned. Obtain current written landholder consent and verify every other applicable restriction.";
    }

    const stop = classification === "PROHIBITED";
    const resourceList = tenures.length
      ? `<ul class="tenure-list">${tenures.map((record) => `<li><strong>${esc(record.config.name)} ${esc(tenureTitle(record.properties, ""))}</strong><br>${esc(tenureHolder(record.properties))}</li>`).join("")}</ul>`
      : "<p>No queried mineral permit, claim or lease intersected this point.</p>";
    const adminList = admin.length
      ? `<ul class="tenure-list">${admin.map((record) => `<li><strong>${esc(record.config.name)}</strong><br>${esc(record.properties.name || record.properties.displayname || record.properties.label || "Boundary returned")}</li>`).join("")}</ul>`
      : "<p>No declared fossicking, restricted-area or native-title-indication boundary was returned.</p>";
    const protectedList = protectedRecords.length
      ? `<ul class="tenure-list">${protectedRecords.map((record) => `<li><strong>${esc(protectedType(record))}</strong><br>${esc(protectedName(record))}</li>`).join("")}</ul>`
      : "<p>No national park, conservation park, state forest, timber reserve or special wildlife reserve intersected this point in the queried service.</p>";

    openDetail(
      `<p class="eyebrow dark">Can I fossick here?</p><h2>${parcel ? esc(parcel.lotplan) : "Exact point assessment"}</h2><div class="assessment-result ${stop ? "stop" : ""}"><strong>${classification}: ${status}</strong>${explanation}</div><dl><dt>Coordinates</dt><dd>${p.lat.toFixed(6)}, ${p.lng.toFixed(6)}</dd><dt>Parcel</dt><dd>${esc(parcel?.lotplan)}</dd><dt>Land tenure</dt><dd>${esc(parcel?.tenure)}</dd><dt>Locality / council</dt><dd>${esc(parcel?.locality)} · ${esc(parcel?.shire_name)}</dd></dl><h3>Protected areas and forests</h3>${protectedList}<h3>Resource tenure</h3>${resourceList}<h3>Administrative constraints</h3>${adminList}${native.length ? "<p><strong>Native title indication returned:</strong> this is a guide only. Determine whether exclusive rights or another permission requirement applies.</p>" : ""}<h3>Checks still required</h3><ul><li>Cultural heritage and any site-specific restrictions.</li><li>Current title and the identity and authority of whoever must give permission.</li><li>Temporary closures, fire, road, weather and site conditions.</li></ul><div class="actions"><a class="button primary" href="https://georesglobe.information.qld.gov.au/" target="_blank" rel="noopener">Verify in GeoResGlobe ↗</a><a class="button ghost" href="https://www.qld.gov.au/recreation/activities/areas-facilities/fossicking/rules/designated-areas" target="_blank" rel="noopener">Official access rules ↗</a><a class="button ghost" href="https://www.qld.gov.au/recreation/activities/areas-facilities/fossicking/licences-permits" target="_blank" rel="noopener">Licence and permits ↗</a><a class="button ghost" href="permission-request.html">Permission template</a></div><p><small>Live parcel, mining-tenure, mining-administration and protected-area services were queried. Absence of a returned constraint is not proof of permission. Automated guidance only; it is not permission, a title search or legal advice. Assessment run ${new Date().toLocaleDateString("en-AU")}.</small></p>`,
    );
  } catch {
    openDetail(
      '<p class="eyebrow dark">Can I fossick here?</p><h2>Official check unavailable</h2><div class="assessment-result stop"><strong>CHECKS INCOMPLETE: do not rely on this result</strong>The government services did not return a complete assessment. Check GeoResGlobe and current titles manually.</div>',
    );
  }
}

Promise.all([loadMinerals(16, "historical"), loadMinerals(17, "occurrences")])
  .then(() => {
    redrawMinerals();
    applyCommodityGroupMode();
  })
  .catch(async () => {
    try {
      const offline = await (await fetch("data/queensland-gold-occurrences.json")).json();
      features = (offline.records || []).map((record) => ({
        ...record,
        lat: Number(record.latitude),
        lng: Number(record.longitude),
        group: String(record.record_type || "").toLowerCase().includes("historical") ? "historical" : "occurrences",
      }));
      redrawMinerals();
      document.querySelector("#mapStatus").textContent =
        `Offline register: ${features.length.toLocaleString()} cached gold records. Live government layers are unavailable.`;
    } catch (_) {
      document.querySelector("#mapStatus").textContent =
        "Mineral record service and offline register unavailable; curated markers remain.";
    }
  });
const remoteLayerLoads = {
  tenure: null,
  admin: null,
  protected: null,
};

function updateLayerCount(selector, text) {
  const count = document.querySelector(selector);
  if (count) count.textContent = text;
}

function finishRemoteLayerLoad() {
  applyViewportLayerVisibility();
  refreshMapLegend();
}

function loadTenureLayersOnce() {
  if (remoteLayerLoads.tenure) return remoteLayerLoads.tenure;
  updateLayerCount("#grantedCount", "(loading…)");
  updateLayerCount("#applicationCount", "(loading…)");
  remoteLayerLoads.tenure = Promise.all(tenureLayers.map(loadTenureLayer))
    .then((counts) => {
      updateLayerCount(
        "#grantedCount",
        `(${counts.slice(0, 4).reduce((a, b) => a + b, 0)})`,
      );
      updateLayerCount(
        "#applicationCount",
        `(${counts.slice(4).reduce((a, b) => a + b, 0)})`,
      );
      finishRemoteLayerLoad();
      return counts;
    })
    .catch((error) => {
      remoteLayerLoads.tenure = null;
      updateLayerCount("#grantedCount", "(unavailable)");
      updateLayerCount("#applicationCount", "(unavailable)");
      document.querySelector("#mapStatus").textContent +=
        " · tenure overlay unavailable";
      throw error;
    });
  return remoteLayerLoads.tenure;
}

function loadAdminLayersOnce() {
  if (remoteLayerLoads.admin) return remoteLayerLoads.admin;
  updateLayerCount("#fossickingCount", "(loading…)");
  updateLayerCount("#constraintCount", "(loading…)");
  remoteLayerLoads.admin = Promise.all(adminLayers.map(loadAdminLayer))
    .then((counts) => {
      updateLayerCount(
        "#fossickingCount",
        `(${counts.slice(0, 3).reduce((a, b) => a + b, 0)})`,
      );
      updateLayerCount(
        "#constraintCount",
        `(${counts.slice(3).reduce((a, b) => a + b, 0)})`,
      );
      finishRemoteLayerLoad();
      return counts;
    })
    .catch((error) => {
      remoteLayerLoads.admin = null;
      updateLayerCount("#fossickingCount", "(unavailable)");
      updateLayerCount("#constraintCount", "(unavailable)");
      document.querySelector("#mapStatus").textContent +=
        " · administrative constraints unavailable";
      throw error;
    });
  return remoteLayerLoads.admin;
}

function loadProtectedLayersOnce() {
  if (remoteLayerLoads.protected) return remoteLayerLoads.protected;
  updateLayerCount("#protectedCount", "(loading…)");
  remoteLayerLoads.protected = Promise.all(protectedLayers.map(loadProtectedLayer))
    .then((counts) => {
      updateLayerCount(
        "#protectedCount",
        `(${counts.reduce((sum, value) => sum + value, 0)})`,
      );
      finishRemoteLayerLoad();
      return counts;
    })
    .catch((error) => {
      remoteLayerLoads.protected = null;
      updateLayerCount("#protectedCount", "(unavailable)");
      document.querySelector("#mapStatus").textContent +=
        " · protected-area overlay unavailable";
      throw error;
    });
  return remoteLayerLoads.protected;
}

const lazyRemoteLayerLoaders = {
  grantedTenure: loadTenureLayersOnce,
  applicationTenure: loadTenureLayersOnce,
  fossickingLand: loadAdminLayersOnce,
  constraints: loadAdminLayersOnce,
  protectedAreas: loadProtectedLayersOnce,
};

updateLayerCount("#grantedCount", "(loads when selected)");
updateLayerCount("#applicationCount", "(loads when selected)");
updateLayerCount("#fossickingCount", "(loads when selected)");
updateLayerCount("#constraintCount", "(loads when selected)");
updateLayerCount("#protectedCount", "(loads when selected)");
const requested = new URLSearchParams(location.search).get("commodity");
if (aliases[requested]) commodity = requested;
const requestedMode = new URLSearchParams(location.search).get("mode");
if (requestedMode && ["ALL", "GOLD", "GEMS"].includes(requestedMode.toUpperCase())) {
  commodityGroup = requestedMode.toUpperCase();
}
document.querySelectorAll("[data-commodity-group]").forEach((button) => {
  const selected = button.dataset.commodityGroup === commodityGroup;
  button.classList.toggle("active", selected);
  button.setAttribute("aria-pressed", String(selected));
  button.onclick = () => {
    commodityGroup = button.dataset.commodityGroup;
    document.querySelectorAll("[data-commodity-group]").forEach((item) => {
      const active = item.dataset.commodityGroup === commodityGroup;
      item.classList.toggle("active", active);
      item.setAttribute("aria-pressed", String(active));
    });
    applyCommodityGroupMode();
    const u = new URL(location.href);
    u.searchParams.set("mode", commodityGroup);
    history.replaceState(null, "", u);
  };
});
if (requestedMode && ["ALL", "GOLD", "GEMS"].includes(requestedMode.toUpperCase())) {
  applyCommodityGroupMode();
}
[
  "All",
  "Gold",
  "Copper",
  "Silver",
  "Manganese",
  "Iron",
  "Limestone",
  "Lead / Zinc",
  "Coal",
].forEach((x) => {
  const b = document.createElement("button");
  b.className = "chip" + (x === commodity ? " active" : "");
  b.textContent = x;
  b.onclick = () => {
    commodity = x;
    document
      .querySelectorAll(".chip")
      .forEach((y) => y.classList.toggle("active", y === b));
    redrawMinerals();
  };
  document.querySelector("#commodityFilters").appendChild(b);
});
document
  .querySelectorAll("[data-layer]")
  .forEach((x) => {
    x.onchange = () => {
      if (!isLayerAllowedForMode(x.dataset.layer)) {
        x.checked = false;
      }
      const lazyLoader = lazyRemoteLayerLoaders[x.dataset.layer];
      if (x.checked && lazyLoader) {
        const label = x.closest("label");
        label?.setAttribute("aria-busy", "true");
        lazyLoader()
          .catch(() => {})
          .finally(() => label?.removeAttribute("aria-busy"));
      }
      applyViewportLayerVisibility();
      refreshMapLegend();
      if (window.CQFieldNotes) window.CQFieldNotes.layerVisibilityChanged?.();
    };
  });

const mapLayerChoices = document.querySelector("#mapLayerChoices");
if (mapLayerChoices) {
  const layerGuidance = window.CQ_MAP_LAYER_GUIDANCE || {};
  const choiceGroups = new Map();
  document.querySelectorAll(".map-sidebar [data-layer]").forEach((sourceInput) => {
    const sourceLabel = sourceInput.closest("label");
    if (!sourceLabel) return;
    const layerId = sourceInput.dataset.layer;
    if (!layerId) return;

    const guidance = layerGuidance[layerId] || { group: "More layers", title: sourceLabel.textContent.trim(), shows: "Additional mapped research context.", source: "See the linked record or official service.", use: "Compare it with other evidence in the same place.", limits: "Check the source, scale and access position before relying on it." };
    const { group: groupTitle, title: displayTitle } = guidance;
    let choiceGroup = choiceGroups.get(groupTitle);
    if (!choiceGroup) {
      choiceGroup = document.createElement("fieldset");
      choiceGroup.className = "map-layer-group";
      const legend = document.createElement("legend");
      legend.textContent = groupTitle;
      choiceGroup.appendChild(legend);
      choiceGroups.set(groupTitle, choiceGroup);
      mapLayerChoices.appendChild(choiceGroup);
    }

    const row = document.createElement("label");
    row.className = "map-layer-row";
    const panelInput = document.createElement("input");
    panelInput.type = "checkbox";
    panelInput.dataset.layer = layerId;
    panelInput.checked = sourceInput.checked;
    panelInput.disabled = sourceInput.disabled;

    panelInput.onchange = () => {
      sourceInput.checked = panelInput.checked;
      sourceInput.dispatchEvent(new Event("change", { bubbles: true }));
    };
    sourceInput.addEventListener("change", () => {
      panelInput.checked = sourceInput.checked;
      panelInput.disabled = sourceInput.disabled;
    });

    const copy = document.createElement("span");
    copy.className = "map-layer-copy";
    copy.innerHTML = `<strong>${displayTitle}</strong>${window.CQMapLayerExplanation(guidance)}`;
    row.append(panelInput, copy);
    choiceGroup.appendChild(row);
  });
}
function setMapLayersPanelOpen(active) {
  const panel = document.querySelector("#mapLayersPanel");
  const trigger = document.querySelector("#mapLayersToggle");
  if (!panel) return;
  panel.classList.toggle("open", active);
  panel.setAttribute("aria-hidden", String(!active));
  if (trigger) trigger.setAttribute("aria-expanded", String(active));
}
function isMapLayersPanelOpen() {
  const panel = document.querySelector("#mapLayersPanel");
  return !!panel?.classList.contains("open");
}
const closeMapLayers = document.querySelector("#closeMapLayers");
if (closeMapLayers) {
  closeMapLayers.onclick = () => setMapLayersPanelOpen(false);
}
function revealSidebarGroup(selector) {
  const group = document.querySelector(selector);
  if (!group) return;
  if (group.tagName === "DETAILS") group.open = true;
  window.setTimeout(() => group.scrollIntoView({ block: "start" }), 80);
}
document.querySelectorAll("[data-map-quick]").forEach((button) => {
  button.addEventListener("click", () => {
    const action = button.dataset.mapQuick;
    if (action === "help") {
      document.querySelector("#terrainGuide")?.showModal();
      return;
    }
    if (action === "find") {
      setMobileFilterOpen(true);
      revealSidebarGroup(".map-toolbox");
      const search = document.querySelector("#mapSearch");
      window.setTimeout(() => search?.focus(), 180);
      return;
    }
    if (action === "layers") {
      setMapLayersPanelOpen(!isMapLayersPanelOpen());
      return;
    }
    if (action === "satellite") {
      setBasemap(true);
      return;
    }
    if (action === "map") {
      setBasemap(false);
      return;
    }
    if (action === "terrain") {
      const mapTool = document.querySelector("#fieldToolsToggle");
      if (mapTool) mapTool.click();
      return;
    }
  });
});
const mapBasemapToggle = document.querySelector("#mapBasemapToggle");
if (mapBasemapToggle) {
  mapBasemapToggle.onclick = () => setBasemap(!satelliteActive);
}
const gemWashDepthSlider = document.querySelector("#gemWashDepthMax");
if (gemWashDepthSlider) {
  gemWashDepthSlider.oninput = (event) => {
    gemWashDepthMax = Number(event.target.value);
    redrawKnownWash();
  };
}
document.querySelector("#fit").onclick = () =>
  map.setView(stateRegionFilter ? map.getCenter() : REGION, stateRegionFilter ? 7 : 6);
document.querySelector("#closeDetail").onclick = () =>
  document.querySelector("#detail").classList.remove("open");
map.on("click", (e) => {
  if (!document.querySelector("#assessmentMode").checked) return;
  if (map.getZoom() < 11)
    return openDetail(
      '<p class="eyebrow dark">Can I fossick here?</p><h2>Zoom in further</h2><p>Assessment mode starts at zoom level 11 so the intended point is reasonably specific.</p>',
    );
  assess(e.latlng);
});
document.querySelector("#historicalOpacity").oninput = (e) =>
  mountLarcomOverlay.setOpacity(Number(e.target.value));
const toolbox = document.createElement("section");
toolbox.className = "map-toolbox";
toolbox.innerHTML =
  '<h2>Find and share</h2><label for="mapSearch">Mine, locality or site number</label><input id="mapSearch" type="search" placeholder="Start typing…"><div id="mapSearchResults"></div><div class="actions"><button id="nearMe" type="button">Near me</button><button id="shareMap" type="button">Copy map link</button><button id="exportMap" type="button">Export visible CSV</button></div><small>Location remains in your browser and is not sent to CQDiggings.</small>';
document.querySelector(".map-intro").after(toolbox);
function ensureMobileMapControls() {
  const mapSidebarEl = document.querySelector(".map-sidebar");
  if (!mapSidebarEl) return;
  if (!mapSidebarEl.id) mapSidebarEl.id = "map-sidebar";

  const mapShell = document.querySelector(".map-shell") || document.body;
  const mapEl = document.querySelector("#map");
  const mapControlShell = mapShell || document.body;

  let mapBasemapSwitch = document.querySelector("#mobileBasemapSwitch");
  if (!mapBasemapSwitch) {
    mapBasemapSwitch = document.createElement("div");
    mapBasemapSwitch.id = "mobileBasemapSwitch";
    mapBasemapSwitch.className = "mobile-basemap-switch";
    mapBasemapSwitch.setAttribute("role", "group");
    mapBasemapSwitch.setAttribute("aria-label", "Mobile map background");
    mapBasemapSwitch.innerHTML =
      '<button type="button" data-basemap="map" aria-pressed="true">Map</button>' +
      '<button type="button" data-basemap="satellite" aria-pressed="false">Satellite</button>';
    if (mapEl) mapShell.insertBefore(mapBasemapSwitch, mapEl);
    else mapControlShell.appendChild(mapBasemapSwitch);
  }
  mapBasemapSwitch.style.top = window.innerWidth <= 430 ? "150px" : "";

  let mobileControls = document.querySelector(".field-map-controls");
  if (!mobileControls) {
    mobileControls = document.createElement("div");
    mobileControls.className = "field-map-controls";
    mobileControls.setAttribute("aria-label", "Mobile field map controls");
    if (mapEl) mapShell.insertBefore(mobileControls, mapEl.nextSibling);
    else mapControlShell.appendChild(mobileControls);
  }

  let filterToggle = document.querySelector("#mobileFilterToggle");
  if (!filterToggle) {
    filterToggle = document.createElement("button");
    filterToggle.id = "mobileFilterToggle";
    filterToggle.type = "button";
    filterToggle.textContent = "Filters";
    filterToggle.setAttribute("aria-pressed", "false");
    filterToggle.setAttribute("aria-expanded", "false");
    filterToggle.setAttribute("aria-controls", "map-sidebar");
    mobileControls.appendChild(filterToggle);
  }

  let fieldMode = document.querySelector("#fieldModeToggle");
  if (!fieldMode) {
    fieldMode = document.createElement("button");
    fieldMode.id = "fieldModeToggle";
    fieldMode.type = "button";
    fieldMode.textContent = "Full screen";
    fieldMode.setAttribute("aria-pressed", "false");
    mobileControls.appendChild(fieldMode);
  }

  let fieldLocate = document.querySelector("#fieldLocate");
  if (!fieldLocate) {
    fieldLocate = document.createElement("button");
    fieldLocate.id = "fieldLocate";
    fieldLocate.type = "button";
    fieldLocate.textContent = "Near me";
    mobileControls.appendChild(fieldLocate);
  }

  let toolsToggle = document.querySelector("#fieldToolsToggle");
  if (!toolsToggle) {
    toolsToggle = document.createElement("button");
    toolsToggle.id = "fieldToolsToggle";
    toolsToggle.type = "button";
    toolsToggle.textContent = "Tools";
    toolsToggle.setAttribute("aria-expanded", "false");
    toolsToggle.setAttribute("aria-controls", "fieldTools");
    mobileControls.appendChild(toolsToggle);
  }

  let closeFilter = document.querySelector("#mobileFilterClose");
  if (!closeFilter) {
    closeFilter = document.createElement("button");
    closeFilter.id = "mobileFilterClose";
    closeFilter.type = "button";
    closeFilter.className = "mobile-filter-close";
    closeFilter.setAttribute("aria-label", "Close filter panel");
    closeFilter.textContent = "×";
    mapSidebarEl.appendChild(closeFilter);
  }

}
ensureMobileMapControls();
function applyShortViewportMobileControlFix() {
  const width = window.innerWidth;
  const height = window.innerHeight;
  const isShortMobile = width <= 390 && height <= 700;
  const controls = document.querySelector(".field-map-controls");
  const legend = document.querySelector(".map-legend");
  const attribution = document.querySelector(".leaflet-control-attribution");
  const mapBasemapSwitch = document.querySelector(".mobile-basemap-switch");
  const isCompactMobile = width <= 430;
  if (controls) {
    controls.style.bottom = isShortMobile
      ? "max(118px, calc(env(safe-area-inset-bottom) + 82px))"
      : "";
  }
  // live cache refresh marker: 20260820a
  if (legend) {
    legend.style.display = isShortMobile ? "none" : "";
  }
  if (attribution) {
    attribution.style.display = isShortMobile ? "none" : "";
  }
  if (mapBasemapSwitch) {
    mapBasemapSwitch.style.top = isCompactMobile ? "150px" : "";
  }
}
applyShortViewportMobileControlFix();
window.addEventListener("resize", applyShortViewportMobileControlFix);
const mapSearch = document.querySelector("#mapSearch"),
  mapSearchResults = document.querySelector("#mapSearchResults");
let userLocation = null;
let userLocationMarker = null;
let userAccuracyCircle = null;
function distanceKm(a, b) {
  const r = 6371,
    p = Math.PI / 180,
    dlat = (b.lat - a.lat) * p,
    dlng = (b.lng - a.lng) * p;
  const z =
    Math.sin(dlat / 2) ** 2 +
    Math.cos(a.lat * p) * Math.cos(b.lat * p) * Math.sin(dlng / 2) ** 2;
  return 2 * r * Math.asin(Math.sqrt(z));
}
function bearingLabel(a, b) {
  const p = Math.PI / 180;
  const y = Math.sin((b.lng - a.lng) * p) * Math.cos(b.lat * p);
  const x = Math.cos(a.lat * p) * Math.sin(b.lat * p) - Math.sin(a.lat * p) * Math.cos(b.lat * p) * Math.cos((b.lng - a.lng) * p);
  const degrees = (Math.atan2(y, x) / p + 360) % 360;
  const points = ["north", "north-east", "east", "south-east", "south", "south-west", "west", "north-west"];
  return points[Math.round(degrees / 45) % 8];
}
function searchMap() {
  const q = mapSearch.value.trim().toLowerCase();
  if (q.length < 2) {
    mapSearchResults.innerHTML = "";
    return;
  }
  const out = [
    ...(commodityGroup === "GEMS"
      ? []
      : features.filter((x) => isGoldFeature(x))),
    ...(commodityGroup === "GOLD" ? [] : gemRecords),
    ...(commodityGroup === "GOLD" ? [] : gemLocalities),
    ...(commodityGroup === "GOLD" ? [] : gemFieldObservations),
    ...archive,
  ]
    .filter((x) =>
      [x.name, x.occur_name, x.site_locality, x.site_no, x.locality]
        .join(" ")
        .toLowerCase()
        .includes(q),
    )
    .slice(0, 8);
  mapSearchResults.innerHTML =
    out
      .map(
        (x, i) =>
          `<button type="button" data-result="${i}">${esc(x.name || x.occur_name)}${userLocation ? ` · ${distanceKm(userLocation, { lat: x.lat, lng: x.lng }).toFixed(1)} km` : ""}</button>`,
      )
      .join("") || "<small>No matching loaded records.</small>";
  mapSearchResults.querySelectorAll("button").forEach(
    (b, i) =>
      (b.onclick = () => {
        const x = out[i];
        map.setView([x.lat, x.lng], 14);
        if (x.commodity_group === "gems" || x.record_type?.includes("fossick") || x.kind?.includes("Gem")) {
          if (x.record_type === "field_observation") fieldObservationDossier(x);
          else gemDossier(x);
        } else {
          dossier(x);
        }
        history.replaceState(
          null,
          "",
          `?mode=${encodeURIComponent(commodityGroup)}&commodity=${encodeURIComponent(commodity)}&site=${encodeURIComponent(x.site_no || x.id || "")}&lat=${x.lat.toFixed(6)}&lng=${x.lng.toFixed(6)}&z=14`,
        );
      }),
  );
}
mapSearch.addEventListener("input", searchMap);
function locateUser() {
  const buttons = [document.querySelector("#nearMe"), document.querySelector("#fieldLocate"), document.querySelector("#fieldLocateSheet")].filter(Boolean);
  buttons.forEach((button) => {
    button.disabled = true;
    button.textContent = "Finding location…";
  });
  const resetButtons = () => buttons.forEach((button) => {
    button.disabled = false;
    button.textContent = button.id === "fieldLocate" ? "Near me" : button.id === "fieldLocateSheet" ? "Update location" : "Near me";
  });
  return navigator.geolocation
    ? navigator.geolocation.getCurrentPosition(
        (p) => {
          userLocation = { lat: p.coords.latitude, lng: p.coords.longitude };
          if (userLocationMarker) map.removeLayer(userLocationMarker);
          if (userAccuracyCircle) map.removeLayer(userAccuracyCircle);
          userAccuracyCircle = L.circle(userLocation, {
            radius: Math.max(Number(p.coords.accuracy || 0), 5),
            color: "#1d5f78",
            weight: 1,
            fillColor: "#5ca8c4",
            fillOpacity: 0.12,
            interactive: false,
          }).addTo(map);
          userLocationMarker = L.circleMarker(userLocation, {
            radius: 8,
            color: "#1d5f78",
            weight: 3,
            fillColor: "#fff",
            fillOpacity: 0.8,
          })
            .addTo(map)
            .bindPopup(`Your device location<br><small>Accuracy approximately ${Math.round(p.coords.accuracy || 0)} metres</small>`)
            .openPopup();
          map.setView(userLocation, Math.max(map.getZoom(), 15));
          const accuracy = Math.round(p.coords.accuracy || 0);
          const accuracyAdvice = accuracy > 50 ? " ; too imprecise for boundary decisions" : " ; still verify boundaries officially";
          document.querySelector("#fieldCoordinates").innerHTML = `<strong>${userLocation.lat.toFixed(6)}, ${userLocation.lng.toFixed(6)}</strong><br>Accuracy approximately ${accuracy} metres${accuracyAdvice}`;
          document.querySelector("#copyCoordinates").disabled = false;
          showNearby(5);
          searchMap();
          resetButtons();
        },
        (error) => {
          resetButtons();
          openDetail(
            `<h2>Location unavailable</h2><p>${error.code === 1 ? "Location permission was not granted. Allow location access in the browser and try again." : "Your device could not provide a reliable location. You can still pan, zoom and search by name or site number."}</p>`,
          );
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 10000 },
      )
    : (resetButtons(), openDetail(
        "<h2>Location unsupported</h2><p>This browser does not provide location access.</p>",
      ));
}
[
  "#nearMe",
  "#fieldLocate",
  "#fieldLocateSheet",
].forEach((id) => {
  const el = document.querySelector(id);
  if (el) el.onclick = locateUser;
});

const fieldModeToggle = document.querySelector("#fieldModeToggle");
const mobileFilterToggle = document.querySelector("#mobileFilterToggle");
const mobileFilterClose = document.querySelector("#mobileFilterClose");
const mapSidebar = document.querySelector(".map-sidebar");
const fieldToolsToggle = document.querySelector("#fieldToolsToggle");
function setMobileFilterOpen(active) {
  if (!mapSidebar) return;
  mapSidebar.classList.toggle("open", active);
  document.body.classList.toggle("map-filters-open", active);
  if (mobileFilterToggle) mobileFilterToggle.setAttribute("aria-expanded", String(active));
  if (mobileFilterToggle) mobileFilterToggle.setAttribute("aria-pressed", String(active));
}
function isMobileFilterControlLayout() {
  return window.matchMedia("(max-width: 900px)").matches;
}
if (fieldModeToggle) {
  fieldModeToggle.onclick = () => {
    const active = document.body.classList.toggle("field-map-mode");
    fieldModeToggle.setAttribute("aria-pressed", String(active));
    fieldModeToggle.textContent = active ? "Exit" : "Full screen";
    window.setTimeout(refreshMapLayout, 120);
  };
}
const fieldTools = document.querySelector("#fieldTools");
function setFieldTools(open) {
  if (!fieldTools || !fieldToolsToggle) return;
  fieldTools.classList.toggle("open", open);
  fieldTools.setAttribute("aria-hidden", String(!open));
  fieldToolsToggle.setAttribute("aria-expanded", String(open));
}
if (fieldToolsToggle) {
  fieldToolsToggle.onclick = () => setFieldTools(!fieldTools?.classList.contains("open"));
}
const closeFieldTools = document.querySelector("#closeFieldTools");
if (closeFieldTools) {
  closeFieldTools.onclick = () => setFieldTools(false);
}
if (mobileFilterToggle) {
  mobileFilterToggle.onclick = () => {
    setMobileFilterOpen(!mapSidebar?.classList.contains("open"));
  };
}
if (mobileFilterClose) {
  mobileFilterClose.onclick = () => setMobileFilterOpen(false);
}
if (fieldToolsToggle) {
  fieldToolsToggle.onclick = () => setFieldTools(!fieldTools.classList.contains("open"));
}
if (fieldToolsToggle) {
  window.addEventListener("resize", () => {
    if (!isMobileFilterControlLayout() && mapSidebar?.classList.contains("open")) {
      setMobileFilterOpen(false);
    }
  });
}
document.querySelector("#copyCoordinates").onclick = async () => {
  if (!userLocation) return;
  try {
    await navigator.clipboard.writeText(`${userLocation.lat.toFixed(6)}, ${userLocation.lng.toFixed(6)}`);
    document.querySelector("#copyCoordinates").textContent = "Copied";
    window.setTimeout(() => document.querySelector("#copyCoordinates").textContent = "Copy coordinates", 1500);
  } catch (_) {
    document.querySelector("#copyCoordinates").textContent = "Copy unavailable";
  }
};
function showNearby(radius) {
  const target = document.querySelector("#nearbyFieldResults");
  document.querySelectorAll("[data-nearby]").forEach((button) => button.classList.toggle("active", Number(button.dataset.nearby) === radius));
  if (!userLocation) {
    target.innerHTML = "<small>Tap “Locate me” before searching nearby.</small>";
    return;
  }
  const nearby = [
    ...(commodityGroup === "GEMS" ? [] : features.filter(isGoldFeature)),
    ...(commodityGroup === "GOLD" ? [] : gemRecords),
    ...(commodityGroup === "GOLD" ? [] : gemLocalities),
    ...(commodityGroup === "GOLD" ? [] : gemFieldObservations),
  ]
    .filter((record) => Number.isFinite(Number(record.lat)) && Number.isFinite(Number(record.lng)))
    .map((record) => ({ record, distance: distanceKm(userLocation, { lat: Number(record.lat), lng: Number(record.lng) }) }))
    .filter((item) => item.distance <= radius)
    .sort((a, b) => a.distance - b.distance);
  const shown = nearby.slice(0, 30);
  target.innerHTML = shown.length
    ? `<p><strong>${nearby.length} record${nearby.length === 1 ? "" : "s"} within ${radius} km</strong>${nearby.length > shown.length ? ` · nearest ${shown.length} shown` : ""}</p>${shown.map((item, index) => `<button type="button" data-nearby-result="${index}"><strong>${esc(item.record.name || item.record.occur_name || "Recorded site")}</strong><span>${item.distance.toFixed(2)} km · ${bearingLabel(userLocation, { lat: Number(item.record.lat), lng: Number(item.record.lng) })}</span></button>`).join("")}`
    : `<p>No loaded records were found within ${radius} km. This does not mean the ground is accessible or geologically unprospective.</p>`;
  target.querySelectorAll("[data-nearby-result]").forEach((button) => {
    button.onclick = () => {
      const item = shown[Number(button.dataset.nearbyResult)];
      setFieldTools(false);
      map.setView([item.record.lat, item.record.lng], 16);
      if (
        item.record.commodity_group === "gems" ||
        item.record.record_type?.includes("fossick") ||
        item.record.kind?.includes("Gem")
      ) {
        if (item.record.record_type === "field_observation") fieldObservationDossier(item.record);
        else gemDossier(item.record);
      } else {
        dossier(item.record);
      }
    };
  });
}
document.querySelectorAll("[data-nearby]").forEach((button) => button.onclick = () => showNearby(Number(button.dataset.nearby)));
const fieldLayerChoices = document.querySelector("#fieldLayerChoices");
const fieldChoiceGroups = new Map();
document.querySelectorAll(".map-sidebar [data-layer]").forEach((source) => {
  const guidance = window.CQ_MAP_LAYER_GUIDANCE?.[source.dataset.layer] || { group: "More layers", title: source.parentElement.textContent.trim(), shows: "Additional mapped research context.", source: "See the linked record or official service.", use: "Compare it with other evidence in the same place.", limits: "Check the source, scale and access position before relying on it." };
  const { group: groupTitle, title: displayTitle } = guidance;
  let group = fieldChoiceGroups.get(groupTitle);
  if (!group) {
    group = document.createElement("fieldset");
    group.className = "field-layer-group";
    const legend = document.createElement("legend");
    legend.textContent = groupTitle;
    group.appendChild(legend);
    fieldChoiceGroups.set(groupTitle, group);
    fieldLayerChoices.appendChild(group);
  }
  const row = document.createElement("label");
  row.className = "field-layer-row";
  const input = document.createElement("input");
  input.type = "checkbox";
  input.checked = source.checked;
  input.dataset.fieldLayer = source.dataset.layer;
  const copy = document.createElement("span");
  copy.className = "map-layer-copy";
  copy.innerHTML = `<strong>${displayTitle}</strong>${window.CQMapLayerExplanation(guidance)}`;
  row.append(input, copy);
  input.onchange = () => {
    source.checked = input.checked;
    source.dispatchEvent(new Event("change"));
  };
  source.addEventListener("change", () => {
    input.checked = source.checked;
    input.disabled = source.disabled;
  });
  group.appendChild(row);
});
function updateOnlineStatus() {
  const status = document.querySelector("#fieldOnlineStatus");
  status.textContent = navigator.onLine ? "Online ; live layers can load" : "Offline ; imagery and live layers may be unavailable";
  status.classList.toggle("offline", !navigator.onLine);
}
window.addEventListener("online", updateOnlineStatus);
window.addEventListener("offline", updateOnlineStatus);
updateOnlineStatus();
document.querySelector("#shareMap").onclick = async () => {
  const c = map.getCenter(),
    u = new URL(location.href);
  u.searchParams.set("lat", c.lat.toFixed(6));
  u.searchParams.set("lng", c.lng.toFixed(6));
  u.searchParams.set("z", map.getZoom());
  u.searchParams.set("mode", commodityGroup);
  u.searchParams.set("commodity", commodity);
  try {
    await navigator.clipboard.writeText(u.href);
    document.querySelector("#shareMap").textContent = "Link copied";
  } catch {
    prompt("Copy this map link", u.href);
  }
};
document.querySelector("#exportMap").onclick = () => {
  const b = map.getBounds(),
    rows = features.filter(
      (x) => b.contains([x.lat, x.lng]) && matches(x, commodity),
    );
  const csv = [
    "site_no,name,locality,commodity,latitude,longitude,record_type",
    ...rows.map((x) =>
      [
        x.site_no,
        x.occur_name,
        x.site_locality,
        x.all_commodities,
        x.lat,
        x.lng,
        x.group,
      ]
        .map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`)
        .join(","),
    ),
  ].join("\r\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
  a.download = `cqdiggings-${commodity.toLowerCase().replace(/\W+/g, "-")}-visible.csv`;
  a.click();
  URL.revokeObjectURL(a.href);
};
const deep = new URLSearchParams(location.search);
if (deep.has("lat") && deep.has("lng"))
  map.setView(
    [Number(deep.get("lat")), Number(deep.get("lng"))],
    Number(deep.get("z")) || 13,
  );
const requestedSite = deep.get("site");
const requestedGem = deep.get("gem");
if (requestedGem) {
  const revealGem = setInterval(() => {
    const x = [...gemRecords, ...gemLocalities, ...gemFieldObservations].find(
      (y) => String(y.id) === requestedGem,
    );
    if (x) {
      clearInterval(revealGem);
      commodityGroup = "GEMS";
      applyCommodityGroupMode();
      map.setView([x.lat, x.lng], 14);
      if (x.record_type === "field_observation") fieldObservationDossier(x);
      else gemDossier(x);
    }
  }, 500);
  setTimeout(() => clearInterval(revealGem), 20000);
}
if (requestedSite) {
  const reveal = setInterval(() => {
    const x = features.find((y) => String(y.site_no) === requestedSite);
    if (x) {
      clearInterval(reveal);
      map.setView([x.lat, x.lng], 14);
      dossier(x);
    }
  }, 500);
  setTimeout(() => clearInterval(reveal), 20000);
}

if (window.CQFieldNotes) {
  window.CQFieldNotes.initMap({
    map,
    getCommodityGroupFn: () => commodityGroup,
    getGemTypeFn: () => gemTypeFilter,
    visible: document.querySelector("#showPrivateFieldNotes")?.checked !== false,
  });
  window.CQFieldNotes.wireButtons({
    addButtonIds: ["#addPrivateFieldNote", "#addPrivateFieldNoteMobile"],
    atLocationButtonIds: ["#addFieldNoteAtLocation", "#addFieldNoteAtLocationMobile"],
    listButtonIds: ["#openPrivateFieldNotesList", "#openPrivateFieldNotesListMobile"],
    layerCheckboxId: "#showPrivateFieldNotes",
  });
  window.CQFieldNotes.onStoreChange(async () => {
    const usage = await window.CQFieldNotesStore.estimateStorageUsage();
    const el = document.querySelector("#fieldNotesStorageSidebar");
    if (el) {
      el.textContent = `Field Notes storage: ${usage.formatted} · ${usage.observation_count} on this device`;
    }
  });
  window.CQFieldNotesStore.estimateStorageUsage().then((usage) => {
    const el = document.querySelector("#fieldNotesStorageSidebar");
    if (el) {
      el.textContent = `Field Notes storage: ${usage.formatted} · ${usage.observation_count} on this device`;
    }
  });
}

if (window.CQFieldValidation) {
  window.CQFieldValidation.initMap({
    map,
    getCommodityGroupFn: () => commodityGroup,
    visible: document.querySelector("#showFieldValidation")?.checked === true,
  });
  window.CQFieldValidation.wireControls({
    layerCheckboxId: "#showFieldValidation",
    filterSelectId: "#fieldValidationFilter",
    nextButtonId: "#nextValidationPoint",
  });
  window.CQFieldNotes?.onStoreChange?.(() => window.CQFieldValidation.onNotesChanged());
}

async function initGemTypeFilter() {
  const sel = document.querySelector("#gemTypeFilter");
  if (!sel) return;
  try {
    const summary = await fetch("data/statewide/gems/gem-type-summary.json?v=20260818phaseD").then((r) => r.json());
    const counts = summary.records_by_gem_type || {};
    sel.innerHTML = `<option value="all_gems">All Gems</option>`;
    for (const [type, count] of Object.entries(counts).sort((a, b) => b[1] - a[1])) {
      if (!count) continue;
      const label = GEM_TYPE_LABELS?.[type] || type.replace(/_/g, " ");
      sel.innerHTML += `<option value="${type}">${label} (${count})</option>`;
    }
  } catch (_) {
    /* summary optional offline */
  }
  sel.value = gemTypeFilter;
  sel.addEventListener("change", () => {
    gemTypeFilter = sel.value || "all_gems";
    redrawGems();
    refreshMapLegend();
    const status = document.querySelector("#mapStatus");
    if (status && commodityGroup === "GEMS") {
      status.textContent = `Gems mode · ${gemTypeLabel ? gemTypeLabel(gemTypeFilter) : gemTypeFilter} · ${gemRecords.filter(passesGemTypeFilter).length} gem records visible`;
    }
  });
}
initGemTypeFilter();
