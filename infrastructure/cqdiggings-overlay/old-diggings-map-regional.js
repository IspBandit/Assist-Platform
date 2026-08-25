const VIEWPORT_HELPER = window.CQFieldMapViewport;
const CQ_CENTRE = (VIEWPORT_HELPER?.DEFAULT_CENTRE || [-23.65, 149.15]).slice();
const DEFAULT_ZOOM = VIEWPORT_HELPER?.DEFAULT_ZOOM || 7;
const requestedParameters = new URLSearchParams(location.search);
const startupView =
  VIEWPORT_HELPER?.resolveStartupView?.({
    savedView: (() => {
      if (typeof localStorage === "undefined") return null;
      return VIEWPORT_HELPER.getSavedMapState?.();
    })(),
    defaultCentre: CQ_CENTRE,
    defaultZoom: DEFAULT_ZOOM,
  }) || {
    center: CQ_CENTRE,
    zoom: DEFAULT_ZOOM,
  };
const cqMap = L.map("diggingsMap", { preferCanvas: true }).setView(
  startupView.center,
  startupView.zoom,
);
const streetBase = L.tileLayer(
  "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
  {
    maxZoom: 18,
    attribution: "© OpenStreetMap contributors",
    errorTileUrl:
      "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==",
  },
).addTo(cqMap);
let fieldOsmErrors = 0;
let fieldOsmLogged = false;
streetBase.on("tileerror", () => {
  fieldOsmErrors += 1;
  if (fieldOsmLogged || fieldOsmErrors < 3) return;
  fieldOsmLogged = true;
  window.cqTrack?.("client_error", "external_tile_error openstreetmap.org");
});
const satelliteBase = L.tileLayer(
  "https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}",
  {
    maxZoom: 19,
    attribution:
      "Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics and the GIS User Community",
    errorTileUrl:
      "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==",
  },
);
const BasemapToggle = L.Control.extend({
  options: { position: "topright" },
  onAdd() {
    const box = L.DomUtil.create("div", "basemap-toggle");
    box.setAttribute("role", "group");
    box.setAttribute("aria-label", "Choose map background");
    box.innerHTML =
      '<button type="button" data-base="map" class="active" aria-pressed="true">Map</button><button type="button" data-base="satellite" aria-pressed="false">Satellite</button>';
    L.DomEvent.disableClickPropagation(box);
    box.querySelectorAll("button").forEach((button) => {
      button.addEventListener("click", () => {
        const satellite = button.dataset.base === "satellite";
        if (satellite) {
          cqMap.removeLayer(streetBase);
          satelliteBase.addTo(cqMap);
        } else {
          cqMap.removeLayer(satelliteBase);
          streetBase.addTo(cqMap);
        }
        box.querySelectorAll("button").forEach((item) => {
          const selected = item === button;
          item.classList.toggle("active", selected);
          item.setAttribute("aria-pressed", String(selected));
        });
      });
    });
    return box;
  },
});
new BasemapToggle().addTo(cqMap);
const MapLegend = L.Control.extend({
  options: { position: "bottomright" },
  onAdd() {
    const box = L.DomUtil.create("div", "map-legend-control");
    box.innerHTML = `<button class="map-legend-toggle" type="button" aria-expanded="false" aria-controls="mapLegendPanel">Legend</button><div class="map-legend-panel" id="mapLegendPanel" hidden><div class="map-legend-head"><strong>Map legend</strong><button type="button" aria-label="Close legend">×</button></div><p class="map-legend-subhead">Recorded sites</p><ul><li><i class="legend-symbol hard-rock"></i><span><strong>Reef / hard-rock working</strong>Historical excavation or mine record</span></li><li><i class="legend-symbol alluvial"></i><span><strong>Alluvial / detrital working</strong>Gold recorded in transported sediment</span></li><li><i class="legend-symbol surface"></i><span><strong>Surface prospect / outcrop</strong>Surface evidence or shallow prospect</span></li><li><i class="legend-symbol occurrence"></i><span><strong>Mineral occurrence</strong>Record only; workings not established</span></li></ul><p class="map-legend-subhead">Areas and lines</p><ul><li><i class="legend-area accuracy"></i><span><strong>Accuracy halo</strong>Stated coordinate uncertainty</span></li><li><i class="legend-area gpa"></i><span><strong>Green</strong>Verified general permission area</span></li><li><i class="legend-area excluded"></i><span><strong>Red</strong>Declared no-go polygon</span></li><li><i class="legend-area permission"></i><span><strong>Amber</strong>Private or leased parcel; permission required</span></li><li><i class="legend-area density"></i><span><strong>Gold shading</strong>Historical record density, not gold potential</span></li><li><i class="legend-area study"></i><span><strong>Dashed brown</strong>Broad documentary study area</span></li><li><i class="legend-line route"></i><span><strong>Blue dashed line</strong>Straight-line bearing, not an access route</span></li></ul><p class="map-legend-warning">No colour or symbol grants access. Check current tenure and conditions.</p></div>`;
    L.DomEvent.disableClickPropagation(box);
    L.DomEvent.disableScrollPropagation(box);
    const toggle = box.querySelector(".map-legend-toggle");
    const panel = box.querySelector(".map-legend-panel");
    const close = box.querySelector(".map-legend-head button");
    const setOpen = (open) => {
      panel.hidden = !open;
      toggle.hidden = open;
      toggle.setAttribute("aria-expanded", String(open));
      if (open) close.focus();
    };
    toggle.addEventListener("click", () => setOpen(true));
    close.addEventListener("click", () => {
      setOpen(false);
      toggle.focus();
    });
    return box;
  },
});
new MapLegend().addTo(cqMap);
const layers = {
  markers: L.markerClusterGroup({
    showCoverageOnHover: false,
    chunkedLoading: true,
    maxClusterRadius: 46,
  }).addTo(cqMap),
  halos: L.layerGroup(),
  density: L.layerGroup(),
  study: L.layerGroup(),
  legalGold: L.layerGroup().addTo(cqMap),
  beginnerAccess: L.layerGroup().addTo(cqMap),
  accessExclusions: L.layerGroup().addTo(cqMap),
  permissionParcels: L.layerGroup(),
  nearby: L.layerGroup().addTo(cqMap),
  gemFossicking: L.layerGroup(),
  gemMarkers: L.markerClusterGroup({
    showCoverageOnHover: false,
    chunkedLoading: true,
    maxClusterRadius: 40,
  }),
  gemFieldObservations: L.markerClusterGroup({
    showCoverageOnHover: false,
    chunkedLoading: true,
    maxClusterRadius: 36,
  }),
  gemKnownWash: L.layerGroup(),
  gemDensity: L.layerGroup(),
};
const GEMFIELDS_BOUNDS = [
  [-23.72, 147.35],
  [-23.38, 147.85],
];
let commodityGroup = "ALL",
  gemTypeFilter = "all_gems",
  gemRecords = [],
  gemLocalities = [],
  gemFieldObservations = [],
  gemKnownWashFeatures = [];
const {
  gemRecordDetailUrl,
  gemMapUrl,
  normalizeGemType,
  matchesGemType,
  gemTypeLabel,
  GEM_TYPE_LABELS,
} = window.CQResearch || {};
function passesGemTypeFilter(r) {
  if (commodityGroup === "GOLD") return false;
  if (commodityGroup !== "GEMS") return true;
  return typeof matchesGemType === "function" ? matchesGemType(r, gemTypeFilter) : true;
}
const ADMIN =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/Boundaries/MiningAdministrativeAreas/MapServer";
const PARCEL =
  "https://spatial-gis.information.qld.gov.au/arcgis/rest/services/PlanningCadastre/LandParcelPropertyFramework/MapServer/4";
let parcelRequest = 0,
  parcelTimer = null;
const enabledTypes = new Set([
  "hard-rock",
  "alluvial",
  "surface",
  "occurrence",
]);
let records = [],
  regions = [],
  stateRegions = [],
  production = {},
  matches = {},
  accessFeatures = [],
  visible = [],
  userLocation = null,
  nearbyKm = 5,
  selectedNearby = null;
const esc = (x) =>
  String(x ?? "Not recorded").replace(
    /[&<>"']/g,
    (m) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        m
      ],
  );
function evidenceType(r) {
  const g = (production[String(r.site_no)]?.gold_form || "").toLowerCase(),
    e = (r.exposure_type || "").toUpperCase();
  if (g.includes("alluvial") || e.includes("ALLUVIAL")) return "alluvial";
  if (
    ["OUTCROP", "FLOAT", "PIT", "PITS", "PROSPECT"].some((x) => e.includes(x))
  )
    return "surface";
  if (r.record_type === "Mineral occurrence") return "occurrence";
  return "hard-rock";
}
function typeName(t) {
  return {
    "hard-rock": "Reef / hard-rock working",
    alluvial: "Alluvial / detrital working",
    surface: "Surface prospect / outcrop",
    occurrence: "Mineral occurrence only",
  }[t];
}
function markerIcon(t) {
  return L.divIcon({
    className: "",
    html: `<i class="od-marker ${t}"></i>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
  });
}
function fieldObsBestCarats(r) {
  return r.best_sapphire_carats_approx ?? r.recovery_carats_approx;
}
function showFieldObservationDetail(r) {
  const detailUrl =
    (typeof gemRecordDetailUrl === "function" ? gemRecordDetailUrl(r) : "") ||
    `record.html?gem=${encodeURIComponent(r.id || "")}`;
  const mapLink =
    (typeof gemMapUrl === "function" ? gemMapUrl(r) : "") ||
    "map.html?mode=GEMS";
  const colourLabel = Array.isArray(r.colours)
    ? r.colours.map((c) => c.charAt(0).toUpperCase() + c.slice(1)).join(" / ")
    : esc(r.colours || "Not recorded");
  const dateLabel = r.date
    ? new Date(`${r.date}T12:00:00`).toLocaleDateString("en-AU", {
        day: "numeric",
        month: "long",
        year: "numeric",
      })
    : "Not recorded";
  const zirconNote = (r.associated_minerals || []).includes("zircon")
    ? `<dt>Associated mineral</dt><dd>Small zircons</dd>`
    : "";
  const el = document.querySelector("#diggingsDetail");
  el.innerHTML = `<button id="closeDiggingsDetail" aria-label="Close">x</button><p class="eyebrow dark">Confirmed field recovery</p><h2>${esc(r.locality || r.name)}</h2><p>${esc(r.region || "Central Queensland Gemfields")}</p><div><span class="evidence-badge">${esc(r.commodity || "sapphire")}</span></div><section class="access-dossier permitted"><p class="eyebrow dark">Confirmed worked patch</p><h3>Not a prospectivity claim</h3><p>Ground worked over approximately one week across about 10 m², partially excavated. GPS marks the worked patch — not a surveyed wash boundary.</p></section><dl><dt>Best sapphire</dt><dd>~${esc(fieldObsBestCarats(r))} ct</dd><dt>Colours</dt><dd>${colourLabel}</dd><dt>Wash depth</dt><dd>~${esc(r.wash_depth_m_approx)} m</dd><dt>Worked area</dt><dd>~10 m², partially excavated</dd>${zirconNote}<dt>Date</dt><dd>${esc(dateLabel)}</dd><dt>Evidence</dt><dd>${esc(r.evidence_type || "GPS-tagged field observation")}</dd><dt>Coordinates</dt><dd>${Number(r.latitude).toFixed(6)}, ${Number(r.longitude).toFixed(6)}</dd></dl><p class="warning">Field observation only — GPS marks the worked patch, not the extent of sapphire-bearing wash.</p><div class="actions"><a class="button primary" href="${esc(detailUrl)}">Open field observation record</a><a class="button ghost" href="${esc(mapLink)}">Open on research map</a></div>`;
  el.classList.add("open");
  document.querySelector("#closeDiggingsDetail").onclick = () =>
    el.classList.remove("open");
}
function gemMarkerIcon(kind) {
  const tone =
    kind === "locality"
      ? "surface"
      : kind === "field-observation"
        ? "field-observation"
        : "occurrence";
  return L.divIcon({
    className: "",
    html: `<i class="od-marker gem ${tone}"></i>`,
    iconSize: [18, 18],
    iconAnchor: [9, 9],
  });
}
function showGemDetail(r) {
  const detailUrl =
    (typeof gemRecordDetailUrl === "function" ? gemRecordDetailUrl(r) : "") ||
    `record.html?gem=${encodeURIComponent(r.id || "")}`;
  const mapLink =
    (typeof gemMapUrl === "function" ? gemMapUrl(r) : "") ||
    "map.html?mode=GEMS";
  const el = document.querySelector("#diggingsDetail");
  el.innerHTML = `<button id="closeDiggingsDetail" aria-label="Close">x</button><p class="eyebrow dark">${esc(r.record_type || "Gem record")}</p><h2>${esc(r.name)}</h2><p>${esc(r.locality || r.region)}</p><div><span class="evidence-badge">${esc(gemTypeLabel ? gemTypeLabel(normalizeGemType ? normalizeGemType(r) : r.gem_type || "other_gem") : r.commodity || "sapphire")}</span></div><section class="access-dossier permitted"><p class="eyebrow dark">Fossicking and access</p><h3>Licence and conditions still apply</h3><p>${esc(r.access_notes || "Verify current fossicking licence, area conditions and overlapping tenure before fieldwork.")}</p></section><dl><dt>Record type</dt><dd>${esc(r.record_type)}</dd><dt>Confidence</dt><dd>${esc(r.confidence || "Official source")}</dd><dt>Source</dt><dd>${esc(r.source)}</dd>${Number.isFinite(Number(r.latitude)) ? `<dt>Coordinates</dt><dd>${Number(r.latitude).toFixed(6)}, ${Number(r.longitude).toFixed(6)}</dd>` : ""}</dl><p>${esc(r.description || "")}</p><div class="actions"><a class="button primary" href="${esc(detailUrl)}">Open full gem record</a><a class="button ghost" href="${esc(mapLink)}">Open on research map</a>${r.source_url ? `<a class="button ghost" href="${esc(r.source_url)}" target="_blank" rel="noopener">Government source ↗</a>` : ""}</div><p><small>Authoritative government record only. No wash-depth or prospectivity claims.</small></p>`;
  el.classList.add("open");
  document.querySelector("#closeDiggingsDetail").onclick = () =>
    el.classList.remove("open");
}
function redrawGems() {
  layers.gemMarkers.clearLayers();
  layers.gemFieldObservations.clearLayers();
  if (commodityGroup === "GOLD") return;
  [...gemRecords, ...gemLocalities].filter(passesGemTypeFilter).forEach((r) => {
    const lat = Number(r.latitude),
      lng = Number(r.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    L.marker([lat, lng], {
      icon: gemMarkerIcon(r.record_type === "fossicking_locality" ? "locality" : "occurrence"),
      title: r.name,
    })
      .on("click", () => showGemDetail(r))
      .bindTooltip(
        `<strong>${esc(r.name)}</strong><br>${esc(r.record_type || "Gem record")}`,
        { direction: "top", className: "od-label" },
      )
      .addTo(layers.gemMarkers);
  });
  gemFieldObservations.filter(passesGemTypeFilter).forEach((r) => {
    const lat = Number(r.latitude),
      lng = Number(r.longitude);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
    L.marker([lat, lng], {
      icon: gemMarkerIcon("field-observation"),
      title: r.name,
    })
      .on("click", () => showFieldObservationDetail(r))
      .bindTooltip(
        `<strong>${esc(r.locality || r.name)}</strong><br>Confirmed sapphire recovery<br>Best sapphire: ~${esc(fieldObsBestCarats(r))} ct`,
        { direction: "top", className: "od-label" },
      )
      .addTo(layers.gemFieldObservations);
  });
}
function redrawKnownWashField() {
  layers.gemKnownWash.clearLayers();
  if (commodityGroup === "GOLD") return;
  if (!document.querySelector('[data-gem-layer="knownWash"]')?.checked) return;
  gemKnownWashFeatures.forEach((feature) => {
    L.geoJSON(feature, {
      pointToLayer: (_f, latlng) =>
        L.circleMarker(latlng, {
          radius: 6,
          color: "#fff",
          weight: 1.5,
          fillColor: "#2a9aaa",
          fillOpacity: 0.92,
        }),
      onEachFeature: (f, layer) =>
        layer.on("click", () => {
          const p = f.properties;
          showGemDetail({
            id: p.id,
            name: p.name,
            locality: p.locality,
            record_type: "known_wash",
            description: [p.exposure_type, p.work_extent, p.provenance].filter(Boolean).join(" · "),
            source: p.source,
            source_url: p.source_url,
            latitude: f.geometry.coordinates[1],
            longitude: f.geometry.coordinates[0],
          });
        }),
    }).addTo(layers.gemKnownWash);
  });
}
function drawGemDensity() {
  layers.gemDensity.clearLayers();
  if (commodityGroup === "GOLD") return;
  if (!document.querySelector('[data-gem-layer="density"]')?.checked) return;
  const points = gemRecords
    .filter((r) => Number.isFinite(Number(r.latitude)))
    .filter((r) => !normalizeGemType || normalizeGemType(r) === "sapphire")
    .map((r) => [Number(r.latitude), Number(r.longitude), 0.65]);
  if (!points.length || typeof L.heatLayer !== "function") return;
  L.heatLayer(points, {
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
  }).addTo(layers.gemDensity);
}
function applyGemFieldLayers() {
  const goldOnly = commodityGroup === "GOLD";
  document.querySelectorAll(".gem-field-section").forEach((section) => {
    section.hidden = goldOnly;
  });
  const gemTypeSection = document.querySelector("#gemTypeFilterSection");
  if (gemTypeSection) gemTypeSection.hidden = goldOnly || commodityGroup !== "GEMS";
  if (goldOnly) {
    cqMap.removeLayer(layers.gemFossicking);
    cqMap.removeLayer(layers.gemMarkers);
    cqMap.removeLayer(layers.gemFieldObservations);
    cqMap.removeLayer(layers.gemKnownWash);
    cqMap.removeLayer(layers.gemDensity);
    return;
  }
  const toggles = {
    fossicking: document.querySelector('[data-gem-layer="fossicking"]')?.checked,
    markers: document.querySelector('[data-gem-layer="markers"]')?.checked,
    fieldObservations: document.querySelector('[data-gem-layer="fieldObservations"]')?.checked,
    knownWash: document.querySelector('[data-gem-layer="knownWash"]')?.checked,
    density: document.querySelector('[data-gem-layer="density"]')?.checked,
  };
  toggles.fossicking
    ? layers.gemFossicking.addTo(cqMap)
    : cqMap.removeLayer(layers.gemFossicking);
  toggles.markers
    ? layers.gemMarkers.addTo(cqMap)
    : cqMap.removeLayer(layers.gemMarkers);
  toggles.fieldObservations
    ? layers.gemFieldObservations.addTo(cqMap)
    : cqMap.removeLayer(layers.gemFieldObservations);
  redrawKnownWashField();
  drawGemDensity();
  toggles.knownWash
    ? layers.gemKnownWash.addTo(cqMap)
    : cqMap.removeLayer(layers.gemKnownWash);
  toggles.density
    ? layers.gemDensity.addTo(cqMap)
    : cqMap.removeLayer(layers.gemDensity);
}
function applyFieldCommodityMode() {
  const gemOnly = commodityGroup === "GEMS";
  const goldOnly = commodityGroup === "GOLD";
  document
    .querySelectorAll(".explorer-controls section:not(.commodity-group-section)")
    .forEach((section) => {
      if (section.querySelector("#regionFilter")) return;
      section.hidden = gemOnly;
    });
  document.querySelector(".nearby-tool")?.classList.toggle("hidden", gemOnly);
  if (gemOnly) {
    cqMap.removeLayer(layers.markers);
    cqMap.removeLayer(layers.halos);
    cqMap.removeLayer(layers.density);
    cqMap.removeLayer(layers.study);
    cqMap.fitBounds(GEMFIELDS_BOUNDS, { padding: [24, 24] });
  } else {
    layers.markers.addTo(cqMap);
    redraw();
  }
  applyGemFieldLayers();
  redrawGems();
  if (window.CQFieldNotes) window.CQFieldNotes.commodityModeChanged();
  const status = document.querySelector("#diggingsStatus");
  if (status) {
    status.textContent =
      commodityGroup === "GEMS"
        ? `${gemRecords.length} authoritative gem records · ${layers.gemFossicking.getLayers().length} official fossicking boundaries`
        : commodityGroup === "GOLD"
          ? `Showing ${visible.length} of ${records.length} deduplicated CQ gold records`
          : `${visible.length} gold records · ${gemRecords.length} gem records · official fossicking boundaries available`;
  }
}
function loadClermontLegalGold() {
  return fetch("data/clermont-legal-gold-prospectivity.geojson?v=20260826a")
    .then((response) => {
      if (!response.ok) throw new Error("Clermont prospectivity layer unavailable");
      return response.json();
    })
    .then((data) =>
      L.geoJSON(data, {
        style: (feature) => {
          const p = feature.properties || {};
          const excluded = p.priority === "exclusion" || /exclusion/i.test(p.name || "");
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
      }).addTo(layers.legalGold),
    );
}
loadClermontLegalGold().catch(() => {});

async function loadGemFieldData() {
  const [fossicking, gemData, knownWash, fieldObservations] = await Promise.all([
    fetch("data/gems/fossicking-areas.geojson").then((r) => r.json()),
    fetch("data/gems/gem-occurrences.json").then((r) => r.json()),
    fetch("data/gems/known-wash.geojson").then((r) => r.json()),
    fetch("data/gems/field-observations.json").then((r) => r.json()),
  ]);
  L.geoJSON(fossicking, {
    style: {
      color: "#1f6f58",
      weight: 2,
      fillColor: "#4aa47d",
      fillOpacity: 0.12,
    },
    onEachFeature: (f, layer) =>
      layer.on("click", () =>
        showGemDetail({
          ...f.properties,
          description: f.properties.description,
          access_notes: f.properties.access_notes,
          latitude: layer.getBounds().getCenter().lat,
          longitude: layer.getBounds().getCenter().lng,
        }),
      ),
  }).addTo(layers.gemFossicking);
  gemRecords = gemData.records || [];
  gemLocalities = gemData.localities || [];
  gemFieldObservations = (fieldObservations.records || []).filter(
    (r) => r.public_display !== false,
  );
  gemKnownWashFeatures = knownWash.features || [];
  redrawGems();
  applyGemFieldLayers();
  applyFieldCommodityMode();
}
function markerTooltip(r, t, acc) {
  const evidence = evidenceAssessment(r);
  const access = accessAssessment(r);
  return `<div class="od-hover-preview"><strong>${esc(r.occur_name)}</strong><span>${esc(typeName(t))}</span><dl><dt>Locality</dt><dd>${esc(r.site_locality)}</dd><dt>Commodities</dt><dd>${esc(r.all_commodities)}</dd><dt>Evidence</dt><dd>${evidence.score}/7 · ${esc(evidence.grade)}</dd><dt>Access</dt><dd>${esc(access.title)}</dd><dt>Accuracy</dt><dd>±${acc} m</dd></dl><small>Click for the full evidence dossier</small></div>`;
}
function recordMatches(r) {
  return matches[String(r.site_no)] || [];
}
function evidenceAssessment(r) {
  const reports = recordMatches(r),
    acc = Number(r.loc_accuracy) || 9999;
  let score = r.record_type === "Historical working" ? 2 : 1;
  if (reports.length) score += 2;
  if (r.work_extent || r.work_extent_comments) score += 1;
  if (acc <= 100) score += 2;
  else if (acc <= 500) score += 1;
  return {
    score,
    grade:
      score >= 6
        ? "Strong documentary evidence"
        : score >= 4
          ? "Moderate documentary evidence"
          : "Limited documentary evidence",
    reasons: [
      r.record_type === "Historical working"
        ? "Recorded as a historical working"
        : "Recorded as a mineral occurrence",
      reports.length
        ? `${reports.length} preserved report-name connection${reports.length === 1 ? "" : "s"}`
        : "No preserved report-name connection yet",
      `Official coordinate accuracy stated as ±${acc} m`,
      r.work_extent || r.work_extent_comments
        ? "Working detail is recorded"
        : "Working detail is not recorded",
    ],
  };
}
function geologicalReasoning(r, t) {
  const model = production[String(r.site_no)]?.gold_form || typeName(t);
  if (t === "alluvial")
    return `<strong>Recorded basis:</strong> ${esc(model)}. <strong>Interpretation:</strong> detrital gold, if still present, may have moved from an upstream bedrock source and reconcentrated in basal gravel, bedrock cracks, inside bends, confluences or slope breaks. The record does not identify the active source or prove recoverable gold remains.`;
  if (t === "hard-rock")
    return `<strong>Recorded basis:</strong> ${esc(model)}. <strong>Interpretation:</strong> the relevant model is primary gold associated with a reef, vein or mineralised structure. Weathering may release smaller pieces downslope or into nearby drainage. The record does not establish grade, continuity or surviving mineralisation.`;
  if (t === "surface")
    return `<strong>Recorded basis:</strong> ${esc(model)} at a surface prospect, pit, float or outcrop. <strong>Interpretation:</strong> exposed or transported material can indicate nearby mineralisation, but float may have moved and an isolated surface record cannot define a source.`;
  return `<strong>Recorded basis:</strong> an official mineral occurrence containing gold among its commodities. <strong>Interpretation:</strong> gold was reported at or near the mapped coordinate; this is not evidence of a mine, recoverable gold or a particular deposit style.`;
}
function fieldTestGuidance(t) {
  if (t === "alluvial")
    return "Only after lawful access is confirmed: take equal-sized, labelled pan samples from separate trap positions and horizons; compare upstream, tributary and downstream results. A negative pan tests that sample—not the whole creek.";
  if (t === "hard-rock")
    return "Only after lawful access is confirmed: observe and photograph safe surface geology, quartz or ironstone, structures and downslope wash; use small labelled surface or drainage samples where permitted. Never enter or excavate old workings.";
  if (t === "surface")
    return "Only after lawful access is confirmed: determine whether material is in-place outcrop or transported float, record its distribution and compare permitted surface or drainage samples along and across slope. Do not enlarge old pits or disturb unstable faces.";
  return "First verify the occurrence description in its original source and determine the deposit style. If access is lawful, use low-impact reconnaissance and small labelled comparison samples appropriate to the confirmed setting.";
}
function pointInRing(point, ring) {
  let inside = false;
  for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
    const [xi, yi] = ring[i],
      [xj, yj] = ring[j];
    if (
      yi > point[1] !== yj > point[1] &&
      point[0] < ((xj - xi) * (point[1] - yi)) / (yj - yi) + xi
    )
      inside = !inside;
  }
  return inside;
}
function pointInGeometry(point, geometry) {
  const polygons =
    geometry?.type === "Polygon"
      ? [geometry.coordinates]
      : geometry?.type === "MultiPolygon"
        ? geometry.coordinates
        : [];
  return polygons.some(
    (poly) =>
      pointInRing(point, poly[0]) &&
      !poly.slice(1).some((hole) => pointInRing(point, hole)),
  );
}
function accessAssessment(r) {
  const hit = accessFeatures.find((f) =>
    pointInGeometry([Number(r.longitude), Number(r.latitude)], f.geometry),
  );
  if (!hit)
    return {
      kind: "unresolved",
      title: "Access unresolved — check before travelling",
      text: "This pin is not inside a verified gold fossicking GPA or mapped no-go polygon loaded by CQDiggings. That does not mean access is permitted. Check the exact parcel, tenure, resource authority, exclusions and written-permission requirements.",
    };
  const noGo = /no-go/i.test(hit.properties?.comments || "");
  return noGo
    ? {
        kind: "blocked",
        title: "Mapped exclusion — fossicking not permitted",
        text: `The official access layer places this coordinate inside ${hit.properties?.parcel_name || "a declared no-go polygon"}. Recheck the current boundary and conditions before relying on it.`,
      }
    : {
        kind: "permitted",
        title: "Inside a verified general permission area",
        text: `The official access layer places this coordinate inside ${hit.properties?.parcel_name || "a current GPA"}. A current fossicking licence, GPA conditions and any overlapping claim or lease permissions still apply.`,
      };
}
function selectedStateRegion() {
  return document.querySelector("#stateRegionFilter")?.value || "";
}
function selectedRegion() {
  return document.querySelector("#regionFilter").value;
}
function passes(r) {
  const region = selectedRegion();
  const stateRegion = selectedStateRegion();
  return (
    (!stateRegion || r.state_region === stateRegion) &&
    (region === "all" || (r.research_regions || []).includes(region)) &&
    enabledTypes.has(evidenceType(r)) &&
    (!document.querySelector("#conditionNotesOnly").checked ||
      r.work_extent_comments) &&
    (!document.querySelector("#reportMatchesOnly").checked ||
      recordMatches(r).length)
  );
}
function regionTags(r) {
  const cqTags = (r.research_regions || [])
    .map((id) => regions.find((x) => x.id === id)?.name || id)
    .map((x) => `<span>${esc(x)}</span>`);
  const stateTag = r.state_region
    ? [`<span>${esc(stateRegions.find((x) => x.id === r.state_region)?.name || r.state_region)}</span>`]
    : [];
  return [...stateTag, ...cqTags].join("");
}
function reportHtml(r) {
  const found = recordMatches(r);
  if (!found.length)
    return "<p>No locally preserved report-name match is currently attached. The wider regional report index is still being expanded.</p>";
  return found
    .slice(0, 5)
    .map(
      (x) =>
        `<div class="report-link"><a href="${esc(x.officialUrl)}" target="_blank" rel="noopener">${esc(x.reportId)} · ${esc(x.title)}</a><small>${esc(x.confidence)} name match; verify the passage in context.</small></div>`,
    )
    .join("");
}
function savedIds() {
  try {
    return JSON.parse(localStorage.getItem("cq_saved_diggings") || "[]");
  } catch {
    return [];
  }
}
function renderSaved() {
  const box = document.querySelector("#savedRecords");
  if (!box) return;
  const found = savedIds()
    .map((id) => records.find((r) => String(r.site_no) === String(id)))
    .filter(Boolean);
  box.innerHTML = found.length
    ? found
        .map(
          (r) =>
            `<button type="button"><strong>${esc(r.occur_name)}</strong><span>Site ${esc(r.site_no)} · ${esc(r.site_locality)}</span></button>`,
        )
        .join("")
    : '<p class="empty-nearby">No records saved.</p>';
  box.querySelectorAll("button").forEach(
    (b, i) =>
      (b.onclick = () => {
        const r = found[i];
        cqMap.setView([r.latitude, r.longitude], 16);
        showDetail(r);
      }),
  );
}
function toggleSaved(r) {
  let ids = savedIds(),
    id = String(r.site_no);
  ids = ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id];
  localStorage.setItem("cq_saved_diggings", JSON.stringify(ids));
  renderSaved();
  showDetail(r);
}
async function shareRecord(r) {
  const url = `${location.origin}${location.pathname}?site=${encodeURIComponent(r.site_no)}&lat=${r.latitude}&lng=${r.longitude}&z=16`;
  if (navigator.share)
    await navigator.share({
      title: `${r.occur_name} - CQDiggings`,
      text: "Official historical record. Access is not established.",
      url,
    });
  else {
    await navigator.clipboard.writeText(url);
    alert("Share link copied.");
  }
}
function showDetail(r) {
  const t = evidenceType(r),
    acc = Number(r.loc_accuracy) || 500,
    p = production[String(r.site_no)] || {},
    evidence = evidenceAssessment(r),
    access = accessAssessment(r),
    localPage = r.page
      ? `<a class="button primary" href="${esc(r.page)}">Open permanent CQDiggings record</a>`
      : "",
    distance = userLocation
      ? distanceKm(userLocation, {
          lat: Number(r.latitude),
          lng: Number(r.longitude),
        })
      : null,
    routePanel = userLocation
      ? `<section class="route-panel"><p class="eyebrow dark">Field mode</p><h3>${formatDistance(distance)} from your current position</h3><p>The dashed line is a straight-line bearing to the historical coordinate, not a track or lawful entrance.</p><div class="access-check neutral"><strong>Access not established</strong><br>Check the selected point against current tenure and every applicable exclusion before travelling.</div><div class="actions"><button class="button primary" id="openDirections" type="button">Acknowledge and open road directions</button><a class="button ghost" href="map.html?commodity=Gold&site=${encodeURIComponent(r.site_no)}&lat=${r.latitude}&lng=${r.longitude}&z=16">Check tenure first</a></div><p><small>Opening directions sends your current coordinates and this destination to Google Maps. CQDiggings does not store them.</small></p></section>`
      : "",
    el = document.querySelector("#diggingsDetail");
  el.innerHTML = `<button id="closeDiggingsDetail" aria-label="Close">x</button><p class="eyebrow dark">${esc(typeName(t))}</p><h2>${esc(r.occur_name)}</h2><div class="region-tags">${regionTags(r)}</div><p>${esc(r.site_locality)}</p><div><span class="accuracy-badge">± ${acc} m stated accuracy</span><span class="evidence-badge">Site ${esc(r.site_no)}</span></div>${routePanel}${r.work_extent_comments ? `<p class="hazard-note"><strong>Recorded working condition</strong><br>${esc(r.work_extent_comments)}</p>` : ""}<dl><dt>Exposure</dt><dd>${esc(r.exposure_type)}</dd><dt>Status</dt><dd>${esc(r.mine_status)}</dd><dt>Working extent</dt><dd>${esc(r.work_extent)}</dd><dt>Gold model</dt><dd>${esc(p.gold_form || typeName(t))}</dd><dt>Commodities</dt><dd>${esc(r.all_commodities)}</dd><dt>Deposit class</dt><dd>${esc(r.deposit_size)}</dd><dt>Location method</dt><dd>${esc(r.loc_method)}</dd><dt>Coordinates</dt><dd>${Number(r.latitude).toFixed(6)}, ${Number(r.longitude).toFixed(6)}</dd></dl><h3>Preserved report connections</h3>${reportHtml(r)}<div class="actions">${localPage}<a class="button ghost" href="map.html?commodity=Gold&site=${encodeURIComponent(r.site_no)}&lat=${r.latitude}&lng=${r.longitude}&z=14">Check evidence and tenure map</a></div><p><small>Source: Queensland Government Mining Resources service. The halo visualises stated coordinate accuracy, not every uncertainty in historical interpretation. Old workings may be collapsed, filled, destroyed or dangerous.</small></p>`;
  el.querySelector("dl").insertAdjacentHTML(
    "beforebegin",
    `<section class="evidence-panel"><div class="confidence-row"><span class="confidence-score">${evidence.score}/7</span><div><p class="eyebrow dark">Confidence</p><h3>${evidence.grade}</h3></div></div><ul>${evidence.reasons.map((x) => `<li>${esc(x)}</li>`).join("")}</ul><p><small>This grades documentary support and coordinate precision—not the amount of gold or chance of finding it.</small></p></section><section class="evidence-panel"><p class="eyebrow dark">Geological reasoning</p><p>${geologicalReasoning(r, t)}</p></section><section class="evidence-panel"><p class="eyebrow dark">Controlled field test</p><p>${esc(fieldTestGuidance(t))}</p></section><section class="access-dossier ${access.kind}"><p class="eyebrow dark">Access classification</p><h3>${esc(access.title)}</h3><p>${esc(access.text)}</p><a href="map.html?commodity=Gold&site=${encodeURIComponent(r.site_no)}&lat=${r.latitude}&lng=${r.longitude}&z=16">Check the live tenure map →</a></section><div class="actions compact"><button class="button ghost" id="saveRecord" type="button">${savedIds().includes(String(r.site_no)) ? "Remove saved record" : "Save on this device"}</button><button class="button ghost" id="shareRecord" type="button">Share record</button></div><p class="drawer-separator"><strong>Recorded source fields</strong></p>`,
  );
  el.classList.add("open");
  document.querySelector("#closeDiggingsDetail").onclick = () =>
    el.classList.remove("open");
  const directions = document.querySelector("#openDirections");
  document.querySelector("#saveRecord").onclick = () => toggleSaved(r);
  document.querySelector("#shareRecord").onclick = () =>
    shareRecord(r).catch(() => {});
  if (directions)
    directions.onclick = () => {
      if (
        confirm(
          "This route may end at private land, a locked gate, an exclusion or unsafe ground. It does not grant access. Continue to Google Maps?",
        )
      ) {
        window.cqTrack?.(
          "field_action",
          `road directions opened - site ${r.site_no}`,
        );
        window.open(
          `https://www.google.com/maps/dir/?api=1&origin=${userLocation.lat},${userLocation.lng}&destination=${r.latitude},${r.longitude}&travelmode=driving`,
          "_blank",
          "noopener",
        );
      }
    };
}
function drawDensity(items) {
  layers.density.clearLayers();
  const cells = new Map(),
    step = 0.1;
  items.forEach((r) => {
    const x = Math.floor(Number(r.longitude) / step),
      y = Math.floor(Number(r.latitude) / step),
      k = `${x}:${y}`;
    if (!cells.has(k)) cells.set(k, { x, y, n: 0 });
    cells.get(k).n++;
  });
  const max = Math.max(1, ...[...cells.values()].map((c) => c.n));
  cells.forEach((c) => {
    if (c.n < 3) return;
    L.rectangle(
      [
        [c.y * step, c.x * step],
        [(c.y + 1) * step, (c.x + 1) * step],
      ],
      {
        color: "#7b4a28",
        weight: 0.5,
        fillColor: "#c88e35",
        fillOpacity: 0.06 + (0.36 * c.n) / max,
      },
    )
      .bindTooltip(`${c.n} documented records in this evidence cell`, {
        className: "od-label",
      })
      .addTo(layers.density);
  });
}
function drawStudyAreas() {
  layers.study.clearLayers();
  regions.forEach((r) => {
    const b = r.bbox;
    L.rectangle(
      [
        [b[1], b[0]],
        [b[3], b[2]],
      ],
      {
        color: "#8b552e",
        weight: 1.5,
        dashArray: "7 6",
        fillColor: "#c99642",
        fillOpacity: 0.035,
      },
    )
      .bindPopup(
        `<strong>${esc(r.name)}</strong><br>${esc(r.scope)}<br><small>${r.record_count} intersecting records. Research collection extent, not a geological boundary or target area.</small>`,
      )
      .addTo(layers.study);
  });
}
function redraw() {
  layers.markers.clearLayers();
  layers.halos.clearLayers();
  if (commodityGroup === "GEMS") {
    redrawGems();
    return;
  }
  visible = records.filter(passes);
  visible.forEach((r) => {
    const t = evidenceType(r),
      lat = Number(r.latitude),
      lng = Number(r.longitude),
      acc = Number(r.loc_accuracy) || 500;
    L.marker([lat, lng], { icon: markerIcon(t), title: r.occur_name })
      .on("click", () => showDetail(r))
      .bindTooltip(markerTooltip(r, t, acc), {
        direction: "top",
        className: "od-label od-hover-card",
        offset: [0, -8],
      })
      .addTo(layers.markers);
    if (visible.length < 700)
      L.circle([lat, lng], {
        radius: acc,
        color:
          t === "alluvial"
            ? "#b17c25"
            : t === "hard-rock"
              ? "#934a2c"
              : t === "surface"
                ? "#3a6672"
                : "#705a79",
        weight: 0.8,
        fillOpacity: 0.035,
        interactive: false,
      }).addTo(layers.halos);
  });
  if (document.querySelector("#showAccuracy").checked && visible.length < 700)
    layers.halos.addTo(cqMap);
  else cqMap.removeLayer(layers.halos);
  if (commodityGroup !== "GEMS") {
    drawDensity(visible);
    document.querySelector("#showDensity").checked
      ? layers.density.addTo(cqMap)
      : cqMap.removeLayer(layers.density);
  } else {
    cqMap.removeLayer(layers.density);
  }
  const c = Object.fromEntries(
    ["hard-rock", "alluvial", "surface", "occurrence"].map((t) => [
      t,
      visible.filter((r) => evidenceType(r) === t).length,
    ]),
  );
  document.querySelector("#hardRockCount").textContent = `(${c["hard-rock"]})`;
  document.querySelector("#alluvialCount").textContent = `(${c.alluvial})`;
  document.querySelector("#surfaceCount").textContent = `(${c.surface})`;
  document.querySelector("#occurrenceOnlyCount").textContent =
    `(${c.occurrence})`;
  const haloNote =
    visible.length >= 700
      ? " · halos suppressed at all-district scale; choose one district"
      : "";
  document.querySelector("#diggingsStatus").textContent =
    `Showing ${visible.length} of ${records.length} authoritative gold records${haloNote}`;
}
async function loadStatewideRecords(regionId) {
  if (!window.CQStatewide) return;
  const ids = regionId
    ? [regionId]
    : stateRegions.map((region) => region.id).filter(Boolean);
  for (const id of ids) {
    const subset = await window.CQStatewide.loadGoldRegion(id);
    for (const record of subset) {
      if (records.some((existing) => String(existing.site_no) === String(record.site_no))) {
        continue;
      }
      records.push({
        ...record,
        research_regions: record.research_regions || [],
        data_source: "statewide",
      });
    }
  }
}
function changeRegion() {
  const id = selectedRegion(),
    r = regions.find((x) => x.id === id);
  if (r) {
    const b = r.bbox;
    cqMap.fitBounds(
      [
        [b[1], b[0]],
        [b[3], b[2]],
      ],
      { padding: [20, 20] },
    );
  } else if (!selectedStateRegion()) cqMap.setView(CQ_CENTRE, 6);
  redraw();
}
async function changeStateRegion() {
  const stateId = selectedStateRegion();
  if (stateId) {
    await loadStatewideRecords(stateId);
    const region = stateRegions.find((entry) => entry.id === stateId);
    if (region?.centre) cqMap.setView(region.centre, 7);
  } else {
    await loadStatewideRecords("");
    cqMap.setView(CQ_CENTRE, 6);
  }
  redraw();
}
function search() {
  const q = document
      .querySelector("#diggingsSearch")
      .value.trim()
      .toLowerCase(),
    box = document.querySelector("#diggingsSearchResults");
  if (q.length < 2) {
    box.innerHTML = "";
    return;
  }
  const found = records
    .filter((r) =>
      [r.occur_name, r.site_locality, r.site_no, r.all_commodities]
        .join(" ")
        .toLowerCase()
        .includes(q),
    )
    .slice(0, 10);
  box.innerHTML =
    found
      .map(
        (r) =>
          `<button type="button"><strong>${esc(r.occur_name)}</strong><br><small>${esc(r.site_locality)} · ±${esc(r.loc_accuracy)} m</small></button>`,
      )
      .join("") || "<small>No matching record.</small>";
  box.querySelectorAll("button").forEach(
    (b, i) =>
      (b.onclick = () => {
        const r = found[i];
        cqMap.setView([r.latitude, r.longitude], 15);
        showDetail(r);
      }),
  );
}
function distanceKm(a, b) {
  const rad = Math.PI / 180,
    r = 6371,
    dLat = (b.lat - a.lat) * rad,
    dLon = (b.lng - a.lng) * rad,
    z =
      Math.sin(dLat / 2) ** 2 +
      Math.cos(a.lat * rad) * Math.cos(b.lat * rad) * Math.sin(dLon / 2) ** 2;
  return 2 * r * Math.asin(Math.sqrt(z));
}
function formatDistance(km) {
  return km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(2)} km`;
}
function selectNearby(r) {
  selectedNearby = r;
  drawNearby(false);
  const target = [Number(r.latitude), Number(r.longitude)];
  L.polyline([[userLocation.lat, userLocation.lng], target], {
    color: "#b87820",
    weight: 4,
    dashArray: "10 8",
    opacity: 0.9,
  })
    .bindTooltip("Straight-line bearing only - not an access route", {
      className: "od-label",
    })
    .addTo(layers.nearby);
  L.circleMarker(target, {
    radius: 10,
    color: "#fff",
    weight: 3,
    fillColor: "#b87820",
    fillOpacity: 1,
  }).addTo(layers.nearby);
  cqMap.fitBounds([[userLocation.lat, userLocation.lng], target], {
    padding: [45, 45],
    maxZoom: 16,
  });
  showDetail(r);
}
function drawNearby(recentre = true) {
  if (!userLocation) return;
  layers.nearby.clearLayers();
  const radius = nearbyKm * 1000;
  L.circle(userLocation, {
    radius,
    color: "#176d91",
    weight: 2,
    fillColor: "#2e8db4",
    fillOpacity: 0.06,
    interactive: false,
  }).addTo(layers.nearby);
  L.marker(userLocation, {
    icon: L.divIcon({
      className: "",
      html: '<i class="user-location-marker"></i>',
      iconSize: [24, 24],
      iconAnchor: [12, 12],
    }),
  })
    .bindTooltip(
      `Your device location · reported accuracy ±${Math.round(userLocation.accuracy)} m`,
      { className: "od-label" },
    )
    .addTo(layers.nearby);
  const found = records
    .map((r) => ({
      r,
      d: distanceKm(userLocation, {
        lat: Number(r.latitude),
        lng: Number(r.longitude),
      }),
    }))
    .filter((x) => x.d <= nearbyKm)
    .sort((a, b) => a.d - b.d);
  const box = document.querySelector("#nearbyResults");
  box.innerHTML = found.length
    ? found
        .slice(0, 100)
        .map(
          (x, i) =>
            `<button type="button" data-nearby-index="${i}"><strong>${esc(x.r.occur_name)} · ${formatDistance(x.d)}</strong><span>${esc(typeName(evidenceType(x.r)))} · record accuracy ±${esc(x.r.loc_accuracy)} m</span></button>`,
        )
        .join("")
    : `<p class="empty-nearby">No documented old digging or gold occurrence has a recorded coordinate within ${nearbyKm} km.</p>`;
  box.querySelectorAll("button").forEach(
    (b, i) =>
      (b.onclick = () => {
        selectNearby(found[i].r);
      }),
  );
  document.querySelector("#nearbyStatus").textContent =
    `${found.length} documented record${found.length === 1 ? "" : "s"} within ${nearbyKm} km. Select one to show direction and distance. GPS accuracy reported by this device: ±${Math.round(userLocation.accuracy)} m.`;
  const resultBand =
    found.length === 0
      ? "0"
      : found.length <= 5
        ? "1-5"
        : found.length <= 20
          ? "6-20"
          : "20+";
  window.cqTrack?.(
    "field_action",
    `radius ${nearbyKm} km - results ${resultBand}`,
  );
  document
    .querySelectorAll("[data-nearby-km]")
    .forEach((b) =>
      b.classList.toggle("active", Number(b.dataset.nearbyKm) === nearbyKm),
    );
  if (recentre) {
    const zoom = nearbyKm === 1 ? 14 : nearbyKm === 5 ? 12 : 11;
    cqMap.setView([userLocation.lat, userLocation.lng], zoom);
  }
}
function locateDiggings() {
  const status = document.querySelector("#nearbyStatus");
  if (!navigator.geolocation) {
    status.textContent = "This browser does not provide GPS location access.";
    return;
  }
  status.textContent = "Requesting your current GPS position...";
  navigator.geolocation.getCurrentPosition(
    (p) => {
      userLocation = {
        lat: p.coords.latitude,
        lng: p.coords.longitude,
        accuracy: p.coords.accuracy || 0,
      };
      window.cqTrack?.("field_action", "gps permission granted");
      document
        .querySelectorAll("[data-nearby-km]")
        .forEach((b) => (b.disabled = false));
      drawNearby();
    },
    (e) => {
      window.cqTrack?.(
        "field_action",
        e.code === 1 ? "gps permission denied" : "gps unavailable",
      );
      const fallback = e.code === 1 ? "permission-denied" : "gps-unavailable";
      status.textContent =
        (VIEWPORT_HELPER?.locationErrorMessage &&
          VIEWPORT_HELPER.locationErrorMessage(e.code)) ||
        (e.code === 1
          ? "Location permission was not granted. Enable it in your browser if you want to use nearby search."
          : "A reliable GPS position could not be obtained. Try again outdoors or check your device location settings.");
      window.cqTrack?.("field_action", fallback);
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 },
  );
}
async function loadBeginnerAccess() {
  const url = `${ADMIN}/20/query?where=1%3D1&geometry=146%2C-25.6%2C152%2C-21.5&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=*&returnGeometry=true&outSR=4326&f=geojson`,
    data = await fetch(url).then((r) => r.json());
  if (data.error) throw Error(data.error.message);
  accessFeatures = (data.features || []).filter((f) => {
    const p = f.properties || {},
      district = String(p.lot_plan || "");
    return (
      p.status === "Current" &&
      (district.includes("Central gold") ||
        district.includes("Capricorn region"))
    );
  });
  let permitted = 0,
    excluded = 0;
  L.geoJSON(data, {
    filter: (f) => {
      const p = f.properties || {},
        district = String(p.lot_plan || "");
      return (
        p.status === "Current" &&
        (district.includes("Central gold") ||
          district.includes("Capricorn region"))
      );
    },
    style: (f) => {
      const noGo = /no-go/i.test(f.properties?.comments || "");
      return noGo
        ? {
            color: "#982f2f",
            weight: 2,
            fillColor: "#c84d42",
            fillOpacity: 0.32,
          }
        : {
            color: "#16664f",
            weight: 2.4,
            fillColor: "#3b9874",
            fillOpacity: 0.25,
          };
    },
    onEachFeature: (f, l) => {
      const p = f.properties || {},
        noGo = /no-go/i.test(p.comments || "");
      noGo ? excluded++ : permitted++;
      l.addTo(noGo ? layers.accessExclusions : layers.beginnerAccess);
      l.bindPopup(
        `<p class="eyebrow dark">${noGo ? "Mapped exclusion" : "Verified general permission area"}</p><h3>${esc(p.parcel_name)}</h3><p>${noGo ? "Fossicking is not permitted in this mapped polygon." : "The official layer records general landholder permission for fossicking in this polygon."}</p><p><strong>District:</strong> ${esc(p.lot_plan)}<br><strong>Status:</strong> ${esc(p.status)}</p><p>A current fossicking licence, special conditions and any overlapping mining-claim or lease permission still apply.</p><a href="beginner-access.html">Open conditions and beginner guidance →</a>`,
      );
    },
  });
  document.querySelector("#beginnerAccessCount").textContent = `(${permitted})`;
  document.querySelector("#accessExclusionCount").textContent = `(${excluded})`;
}
async function loadPermissionParcels() {
  if (!document.querySelector("#showPermissionParcels").checked) return;
  if (cqMap.getZoom() < 13) {
    layers.permissionParcels.clearLayers();
    document.querySelector("#permissionParcelCount").textContent = "(zoom in)";
    return;
  }
  const token = ++parcelRequest,
    b = cqMap.getBounds(),
    geometry = [b.getWest(), b.getSouth(), b.getEast(), b.getNorth()].join(","),
    url = `${PARCEL}/query?where=lotplan%20is%20not%20null&geometry=${encodeURIComponent(geometry)}&geometryType=esriGeometryEnvelope&inSR=4326&spatialRel=esriSpatialRelIntersects&outFields=lotplan%2Ctenure%2Clot_area%2Clocality%2Cshire_name&returnGeometry=true&outSR=4326&resultRecordCount=2000&f=geojson`,
    data = await fetch(url).then((r) => r.json());
  if (token !== parcelRequest) return;
  if (data.error) throw Error(data.error.message);
  layers.permissionParcels.clearLayers();
  let count = 0;
  L.geoJSON(data, {
    filter: (f) =>
      /FREEHOLD|LEASE|LICENCE|PERMIT/i.test(f.properties?.tenure || ""),
    style: {
      color: "#96661f",
      weight: 1,
      fillColor: "#d99b38",
      fillOpacity: 0.2,
    },
    onEachFeature: (f, l) => {
      count++;
      const p = f.properties || {};
      l.bindPopup(
        `<p class="eyebrow dark">Written permission required</p><h3>${esc(p.lotplan)}</h3><p><strong>Tenure:</strong> ${esc(p.tenure)}<br><strong>Locality:</strong> ${esc(p.locality)}<br><strong>Council:</strong> ${esc(p.shire_name)}</p><p>This amber parcel is not absolutely prohibited, but do not fossick without identifying the authorised owner or holder and obtaining the required written permission.</p><a href="access.html">Open the permission workflow →</a>`,
      );
    },
  }).addTo(layers.permissionParcels);
  document.querySelector("#permissionParcelCount").textContent = `(${count})`;
}
function queuePermissionParcels() {
  clearTimeout(parcelTimer);
  parcelTimer = setTimeout(
    () =>
      loadPermissionParcels().catch(
        () =>
          (document.querySelector("#permissionParcelCount").textContent =
            "(unavailable)"),
      ),
    350,
  );
}
Promise.all([
  fetch("data/queensland-gold-occurrences.json").then((r) => r.json()),
  fetch("data/production-assay-register.json").then((r) => r.json()),
  fetch("data/occurrence-report-matches.json").then((r) => r.json()),
  fetch("data/gladstone-gold-occurrences.json").then((r) => r.json()),
])
  .then(async ([cq, p, m, local]) => {
    regions = cq.regions;
    const pages = Object.fromEntries(
      local.records.map((x) => [String(x.site_no), x.page]),
    );
    records = cq.records.map((x) => ({
      ...x,
      page: pages[String(x.site_no)] || "",
      data_source: "cq",
    }));
    production = Object.fromEntries(p.records.map((x) => [String(x.site), x]));
    matches = m.matches || {};
    if (window.CQStatewide) {
      const regionsMeta = await window.CQStatewide.loadRegionsMeta();
      stateRegions = regionsMeta.state_regions || [];
      const stateSelect = document.querySelector("#stateRegionFilter");
      for (const region of stateRegions) {
        stateSelect.insertAdjacentHTML(
          "beforeend",
          `<option value="${esc(region.id)}">${esc(region.name)}</option>`,
        );
      }
      stateSelect.onchange = () => changeStateRegion();
    }
    const select = document.querySelector("#regionFilter");
    regions.forEach((r) =>
      select.insertAdjacentHTML(
        "beforeend",
        `<option value="${esc(r.id)}">${esc(r.name)} (${r.record_count})</option>`,
      ),
    );
    drawStudyAreas();
    redraw();
    renderSaved();
    if (window.CQStatewide && !selectedStateRegion()) {
      loadStatewideRecords("").then(() => redraw()).catch(() => {});
    }
    const requestedRegion = requestedParameters.get("region");
    if (
      requestedRegion &&
      regions.some((region) => region.id === requestedRegion)
    ) {
      select.value = requestedRegion;
      changeRegion();
    }
    const requestedSite = requestedParameters.get("site");
    if (requestedSite) {
      const found = records.find((r) => String(r.site_no) === requestedSite);
      if (found) {
        cqMap.setView([found.latitude, found.longitude], 16);
        showDetail(found);
      }
    }
    const deepView = VIEWPORT_HELPER?.resolveQueryView?.({
      lat: requestedParameters.get("lat"),
      lng: requestedParameters.get("lng"),
      zoom: requestedParameters.get("z"),
      defaultZoom: VIEWPORT_HELPER.DEEP_LINK_ZOOM ?? 11,
    });
    if (deepView) cqMap.setView(deepView.center, deepView.zoom);
  })
  .catch((e) => {
    document.querySelector("#diggingsStatus").textContent =
      "The Central Queensland register could not be loaded.";
    console.error(e);
  });
loadBeginnerAccess().catch((e) => {
  document.querySelector("#beginnerAccessCount").textContent = "(unavailable)";
  console.error(e);
});
loadGemFieldData().catch((e) => {
  console.error(e);
});
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
    window.cqTrack?.("commodity_mode_changed", commodityGroup, {
      page: "field_map",
      commodity: commodityGroup,
    });
    window.CQFieldNotes?.commodityModeChanged?.();
    applyFieldCommodityMode();
    const u = new URL(location.href);
    u.searchParams.set("mode", commodityGroup);
    history.replaceState(null, "", u);
  };
});
document.querySelectorAll("[data-gem-layer]").forEach(
  (input) => (input.onchange = () => applyGemFieldLayers()),
);
document.querySelectorAll("[data-type]").forEach(
  (x) =>
    (x.onchange = () => {
      x.checked
        ? enabledTypes.add(x.dataset.type)
        : enabledTypes.delete(x.dataset.type);
      redraw();
    }),
);
[
  "showAccuracy",
  "showDensity",
  "conditionNotesOnly",
  "reportMatchesOnly",
].forEach((id) => (document.querySelector("#" + id).onchange = redraw));
document.querySelector("#showStudyAreas").onchange = (e) =>
  e.target.checked
    ? layers.study.addTo(cqMap)
    : cqMap.removeLayer(layers.study);
document.querySelector("#showClermontLegalGold").onchange = (e) =>
  e.target.checked
    ? layers.legalGold.addTo(cqMap)
    : cqMap.removeLayer(layers.legalGold);
document.querySelector("#regionFilter").onchange = changeRegion;
document.querySelector("#diggingsSearch").addEventListener("input", search);
document.querySelector("#resetDiggings").onclick = () => {
  document.querySelector("#regionFilter").value = "all";
  changeRegion();
};
document.querySelector("#closeDiggingsDetail").onclick = () =>
  document.querySelector("#diggingsDetail").classList.remove("open");
document.querySelector("#exportDiggings").onclick = () => {
  const bounds = cqMap.getBounds(),
    rows = visible.filter((r) => bounds.contains([r.latitude, r.longitude]));
  const csv = [
    "site_no,name,locality,research_regions,evidence_type,exposure,accuracy_m,latitude,longitude",
    ...rows.map((r) =>
      [
        r.site_no,
        r.occur_name,
        r.site_locality,
        r.research_regions.join(";"),
        typeName(evidenceType(r)),
        r.exposure_type,
        r.loc_accuracy,
        r.latitude,
        r.longitude,
      ]
        .map((v) => `"${String(v ?? "").replaceAll('"', '""')}"`)
        .join(","),
    ),
  ].join("\r\n");
  const a = document.createElement("a");
  a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv" }));
  a.download = "cqdiggings-central-queensland-visible-records.csv";
  a.click();
  URL.revokeObjectURL(a.href);
};
document.querySelector("#showBeginnerAccess").onchange = (e) =>
  e.target.checked
    ? layers.beginnerAccess.addTo(cqMap)
    : cqMap.removeLayer(layers.beginnerAccess);
document.querySelector("#showAccessExclusions").onchange = (e) =>
  e.target.checked
    ? layers.accessExclusions.addTo(cqMap)
    : cqMap.removeLayer(layers.accessExclusions);
document.querySelector("#showPermissionParcels").onchange = (e) => {
  if (e.target.checked) {
    layers.permissionParcels.addTo(cqMap);
    queuePermissionParcels();
  } else {
    cqMap.removeLayer(layers.permissionParcels);
    document.querySelector("#permissionParcelCount").textContent = "";
  }
};
cqMap.on("moveend zoomend", queuePermissionParcels);
document.querySelector("#locateDiggings").onclick = locateDiggings;
const explorerControls = document.querySelector(".explorer-controls");
const openMobileFilters = (scrollTarget) => {
  explorerControls.classList.add("filter-drawer-open");
  document.body.classList.add("filter-drawer-open");
  if (scrollTarget) {
    requestAnimationFrame(() =>
      scrollTarget.scrollIntoView({ behavior: "smooth", block: "start" }),
    );
  }
};
const closeMobileFilters = () => {
  explorerControls.classList.remove("filter-drawer-open");
  document.body.classList.remove("filter-drawer-open");
  requestAnimationFrame(() => cqMap.invalidateSize({ pan: false }));
};
document.querySelector("#mobileFilters").onclick = () => openMobileFilters();
document.querySelector("#closeMobileFilters").onclick = closeMobileFilters;
document.querySelector("#mobileNearby").onclick = () => {
  locateDiggings();
  openMobileFilters(document.querySelector(".nearby-tool"));
};
document.querySelectorAll("[data-nearby-km]").forEach(
  (b) =>
    (b.onclick = () => {
      nearbyKm = Number(b.dataset.nearbyKm);
      drawNearby();
    }),
);
const legacyCqRegionIds = new Set([
  "clermont-blair-athol",
  "mount-morgan-dee-river",
  "calliope-gladstone",
  "rockhampton-bouldercombe-crocodile",
  "many-peaks-boyne-valley",
  "springsure-emerald",
]);
const requestedRegionValue = new URLSearchParams(location.search).get("region");
const requestedRegion = legacyCqRegionIds.has(requestedRegionValue)
  ? "central-queensland"
  : requestedRegionValue;
if (requestedRegion) {
  const regionTimer = setInterval(() => {
    const select = document.querySelector("#regionFilter");
    if ([...select.options].some((x) => x.value === requestedRegion)) {
      clearInterval(regionTimer);
      select.value = requestedRegion;
      changeRegion();
    }
  }, 100);
  setTimeout(() => clearInterval(regionTimer), 10000);
}

if (window.CQFieldNotes) {
  window.CQFieldNotes.initMap({
    map: cqMap,
    getCommodityGroupFn: () => commodityGroup,
    getGemTypeFn: () => gemTypeFilter || "all_gems",
    visible: document.querySelector("#showPrivateFieldNotes")?.checked !== false,
  });
  window.CQFieldNotes.wireButtons({
    addButtonIds: ["#addPrivateFieldNote"],
    atLocationButtonIds: ["#addFieldNoteAtLocation"],
    listButtonIds: ["#openPrivateFieldNotesList"],
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
    map: cqMap,
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

async function initGemTypeFilterField() {
  const sel = document.querySelector("#gemTypeFilter");
  if (!sel) return;
  try {
    const summary = await fetch("data/statewide/gems/gem-type-summary.json?v=20260818phaseD").then((r) => r.json());
    sel.innerHTML = `<option value="all_gems">All Gems</option>`;
    for (const [type, count] of Object.entries(summary.records_by_gem_type || {}).sort((a, b) => b[1] - a[1])) {
      if (!count) continue;
      const label = GEM_TYPE_LABELS?.[type] || type;
      sel.innerHTML += `<option value="${type}">${label} (${count})</option>`;
    }
  } catch (_) {}
  sel.addEventListener("change", () => {
    gemTypeFilter = sel.value || "all_gems";
    redrawGems();
    applyGemFieldLayers();
  });
}
initGemTypeFilterField();

