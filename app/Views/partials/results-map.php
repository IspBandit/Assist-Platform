<?php
$mapItems = array_values($mapItems ?? []);
$mapOrigin = $mapOrigin ?? null;
$mapTitle = (string) ($mapTitle ?? (count($mapItems) . ' located results'));
$mapDescription = (string) ($mapDescription ?? 'Select a numbered pin to identify the matching result in the list.');
if ($mapItems === []) { return; }
?>
<section class="results-map-shell" data-results-view-shell data-active-view="list" aria-label="Map and list results">
 <div class="results-view-switch" role="group" aria-label="Choose results view"><button type="button" data-results-view="map" aria-pressed="false">Map</button><button type="button" data-results-view="list" aria-pressed="true">List</button></div>
 <div class="results-map-heading"><div><span class="directory-eyebrow">Map and list</span><h2><?= $this->e($mapTitle) ?></h2></div><p><?= $this->e($mapDescription) ?></p></div>
 <div class="results-map" data-results-map hidden aria-label="Map of these results">
  <div class="results-map-canvas" data-results-map-canvas tabindex="0" aria-label="Interactive results map. Drag to move, pinch or use the controls to zoom."></div>
  <div class="results-map-controls" role="group" aria-label="Map controls"><button type="button" data-results-map-zoom-in aria-label="Zoom in">+</button><button type="button" data-results-map-zoom-out aria-label="Zoom out">&minus;</button><button type="button" data-results-map-fit>Fit results</button></div>
  <aside class="results-map-summary" data-results-map-summary hidden aria-live="polite"><button type="button" data-results-map-summary-close aria-label="Close result summary">&times;</button><div class="results-map-summary-tools"><button type="button" data-results-map-summary-drag aria-label="Move result summary">Move</button><button type="button" data-results-map-summary-toggle aria-expanded="true">Collapse</button></div><span data-results-map-summary-position></span><strong data-results-map-summary-name></strong><div class="results-map-summary-body" data-results-map-summary-body><small data-results-map-summary-location></small><div><a class="btn btn-primary btn-sm" data-results-map-summary-profile href="#">Details</a><button class="btn btn-secondary btn-sm" type="button" data-results-map-summary-list>Show in list</button><a class="btn btn-secondary btn-sm" data-results-map-summary-directions href="#" target="_blank" rel="noopener noreferrer">Directions</a></div></div></aside>
  <div class="results-map-key"><?php if (is_array($mapOrigin)): ?><span><i class="results-map-key__origin"></i>Your search</span><?php endif; ?><span><i></i>Result</span></div>
  <p class="results-map-attribution"><a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">Map © OpenStreetMap contributors</a></p>
 </div>
 <p class="results-map-status muted" data-results-map-status role="status" aria-live="polite">The list remains available if the map cannot load.</p>
 <script type="application/json" data-results-map-data><?= json_encode(['origin'=>$mapOrigin,'providers'=>$mapItems], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) ?></script>
</section>
