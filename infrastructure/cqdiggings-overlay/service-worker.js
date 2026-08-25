const VERSION = "cqdiggings-field-v55";
const CORE = [
  "/index.html?app=20260825ask",
  "/map.html?app=20260824a",
  "/old-diggings-map.html?app=20260820g",
  "/gold-occurrences.html?app=20260818a",
  "/unified-search.html?app=20260824a",
  "/ask.html?app=20260825h",
  "/detector-settings.html?app=20260825a",
  "/detector-manuals.html?app=20260825a",
  "/obriens-creek.html?app=20260821b",
  "/glenalva.html?app=20260821b",
  "/willows.html?app=20260821b",
  "/yowah.html?app=20260821b",
  "/record.html?app=20260818a",
  "/my-diggings.html?app=20260818a",
  "/offline.html?app=20260818a",
  "/access.html?app=20260818a",
  "/glossary.html?app=20260818a",
  "/starter-packs.html?app=20260818a",
  "/gold-guide.html?app=20260818a",
  "/prospecting-equipment.html?app=20260818a",
  "/research-regions.html?app=20260818a",
  "/clermont-blair-athol.html?app=20260820g",
  "/clermont-gold-investigation.html?app=20260826a",
  "/clermont-gold-investigation.css?v=20260826b",
  "/clermont-gold-investigation.js?v=20260826b",
  "/data/clermont-field-validation-points.geojson",
  "/mount-morgan-dee-river.html?app=20260818a",
  "/rockhampton-bouldercombe-crocodile.html?app=20260818a",
  "/many-peaks-boyne-valley-region.html?app=20260818a",
  "/springsure-emerald.html?app=20260818a",
  "/heatmap-method.html?app=20260818a",
  "/historical-overlays.html?app=20260818a",
  "/manifest.webmanifest?v=20260825home",
  "/styles.css?v=20260825dense2",
  "/brand.css",
  "/home-ask.css?v=20260825a",
  "/mobile.css",
  "/ask.css?v=20260825h",
  "/ask.js?v=20260825h",
  "/detector-model-profiles.js?v=20260825c",
  "/detector-coils-data.js?v=20260823a",
  "/detector-manuals.css?v=20260825a",
  "/detector-manuals.js?v=20260825a",
  "/map-layer-guidance.js?v=20260825a",
  "/data/ask-knowledge.json",
  "/data/ask-intents.json",
  "/data/ask-source-families.json",
  "/data/ask-place-anchors.json",
  "/data/detector-settings.json",
  "/data/detector-manuals.json",
  "/data/community-detector-settings.json",
  "/assets/ask/evidence-stack.svg",
  "/assets/ask/detector-noise-check.svg",
  "/assets/ask/palaeodrainage-evidence.svg",
  "/data/field-knowledge/australian-howto-knowledge.json",
  "/data/field-knowledge/fieldcraft-failure-lessons.json",
  "/data/statewide/search-index.json",
  "/data/historical-research/historical-evidence-search-index.json",
  "/data/evidence-intelligence/historical-map-coverage-index.json",
  "/data/statewide/historical-leases-index.json",
  "/data/statewide/fossicking-areas.geojson",
  "/restoration.css?v=20260818a",
  "/map.css?v=20260820g",
  "/map-enhancements.css?v=20260821q-qlddem",
  "/map-restoration.css?v=20260820g",
  "/map-mobile-fixes.css?v=20260820g",
  "/field-notes.css?v=20260823d",
  "/evidence-intelligence.css?v=20260820g",
  "/old-diggings-map.css?v=20260818b",
  "/evidence-drawer.css?v=20260821a",
  "/regional-scope.css?v=20260818b",
  "/map-20260820a.js?v=20260822b",
  "/research-data.css?v=20260822b",
  "/research-data-map.js?v=20260822b",
  "/historical-localities-map.js?v=20260824a",
  "/data/statewide/historical-localities/metadata.json",
  "/research-data-explorer.js?v=20260822a",
  "/data/historical-research/research-data-catalog.json",
  "/data/research-map/evidence-index/metadata.json",
  "/map-mobile-fixes.js?v=20260820g",
  "/old-diggings-map-regional.js?v=20260821a",
  "/clermont-district.js?v=20260820g",
  "/unified-search.css?v=20260824a",
  "/unified-search.js?v=20260824a",
  "/gem-area-detail.js?v=20260821b",
  "/analytics.js?v=20260820g",
  "/field-notes-ui.js?v=20260821n-longpress",
  "/evidence-intelligence.js?v=20260820g",
  "/evidence-intelligence-ui.js?v=20260820g",
  "/data/evidence-intelligence/bore-stratigraphy-index.json",
  "/data/geoscience/historical-leases/clermont-blair-athol.geojson",
  "/data/geoscience/historical-leases/mount-morgan-dee-river.geojson",
  "/data/geoscience/historical-leases/calliope-gladstone.geojson",
  "/data/geoscience/historical-leases/many-peaks-boyne-valley.geojson",
  "/data/geoscience/field-observations/mount-morgan-dee-river.geojson",
  "/data/geoscience/field-observations/calliope-gladstone.geojson",
  "/data/geoscience/field-observations/rockhampton-bouldercombe-crocodile.geojson",
  "/research-utils.js?v=20260818e",
  "/record.js?v=20260818e",
  "/data/statewide/gems/gem-type-summary.json?v=20260818phaseD",
  "/supabase-config.js?v=20260818c",
  "/field-notes-store.js?v=20260818d",
  "/field-notes-exif.js?v=20260818d",
  "/field-notes-sync.js?v=20260818d",
  "/field-notes-supabase.js?v=20260818d",
  "/field-validation-layer.js?v=20260818d",
  "/data/gem-research/validation/glenalva-field-validation-pack.json",
  "/data/gem-research/validation/glenalva-field-validation-points.geojson",
  "/research-utils.js?v=20260818a",
  "/statewide-data.js?v=20260818a",
  "/site-nav.js?v=20260823u",
  "/gold-occurrences.js?v=20260818a",
  "/analytics.js?v=20260820g",
  "/plain-language.js",
  "/retention.css?v=20260823e",
  "/page-local-nav.css?v=20260825b",
  "/retention.js?v=20260823e",
  "/my-diggings.js?v=20260816a",
  "/app.js?v=20260818a",
  "/locality-detail.js?v=20260818a",
  "/field-map-viewport.js?v=20260821a",
  "/assets/vendor/leaflet/leaflet.css",
  "/assets/vendor/leaflet/leaflet.js",
  "/assets/vendor/leaflet/MarkerCluster.css",
  "/assets/vendor/leaflet/MarkerCluster.Default.css",
  "/assets/vendor/leaflet/leaflet.markercluster.js",
  "/assets/vendor/leaflet/leaflet-heat.js",
  "/data/queensland-gold-occurrences.json",
  "/data/evidence-intelligence/evidence-source-registry.json",
  "/data/evidence-intelligence/experimental-research-registry.json",
  "/data/statewide/regions.json",
  "/data/statewide/districts.json",
  "/data/statewide/historical-maps-index.json",
  "/data/statewide/reports-index.json",
  "/data/statewide/phase-b2/historical-evidence-index.json",
  "/data/gold-path-model.json",
  "/assets/cqdiggings-logo.png",
  "/assets/app-icon-192.png",
  "/assets/app-icon-512.png",
  "/assets/app-icon-maskable-192.png",
  "/assets/app-icon-maskable-512.png",
  "/assets/apple-touch-icon.png"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(VERSION)
      .then((cache) => Promise.allSettled(CORE.map((url) => cache.add(url))))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith("cqdiggings-") && key !== VERSION).map((key) => caches.delete(key))))
      .then(() => self.clients.claim()),
  );
});

async function networkFirst(request) {
  const cache = await caches.open(VERSION);
  try {
    const response = await fetch(request);
    if (response.ok) cache.put(request, response.clone());
    return response;
  } catch (_) {
    return (await cache.match(request, { ignoreSearch: true })) || (await cache.match("/offline.html"));
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(VERSION);
  const cached = await cache.match(request);
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) cache.put(request, response.clone());
  return response;
}

self.addEventListener("fetch", (event) => {
  const url = new URL(event.request.url);
  if (event.request.method !== "GET" || url.origin !== self.location.origin || url.pathname.includes("/analytics/")) return;
  if (url.pathname.includes("/api/private/")) return;
  if (url.hostname.includes("supabase.co")) return;
  if (event.request.mode === "navigate") {
    event.respondWith(networkFirst(event.request));
    return;
  }
  if (/\/data\/(?:community-contributions|community-detector-settings|marketplace-listings)\.json$/i.test(url.pathname)) {
    event.respondWith(networkFirst(event.request));
    return;
  }
  if (/\.(?:css|js|json|png|webp|svg|woff2?|webmanifest)$/i.test(url.pathname)) {
    event.respondWith(cacheFirst(event.request));
  }
});

self.addEventListener("sync", (event) => {
  if (event.tag !== "cqd-field-notes-sync") return;
  event.waitUntil(
    self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((clients) => {
      for (const client of clients) {
        client.postMessage({ type: "cqd-field-notes-sync" });
      }
    }),
  );
});

