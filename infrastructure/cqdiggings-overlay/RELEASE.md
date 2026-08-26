# CQDiggings Clermont investigation overlay

- Source repository: `IspBandit/CQDiggings`
- Source commit: `1172690e6f50fea5b1e303dfad1ff6d73f8c8311`
- Source pull requests: CQDiggings #66, #67 and #71
- Content: Clermont investigation page, complete twenty-pass dossier, 8,666
  Queensland occurrence records, historical evidence, assay and report-link
  registers, three investigation GeoJSON layers, Research Map and Field Map
  integration, navigation, indexes and service worker
- Deployment owner: Assist Platform immutable production release

The files in this directory are exact copies from the source commit. Production
Compose mounts them read-only over the matching paths in the separately retained
CQDiggings release. The existing shared analytics, moderation and public-runtime
mounts remain unchanged.

Rollback is the normal Assist Platform release rollback. Restoring the preceding
Assist release restores the preceding Compose file and removes every overlay
mount without changing CQDiggings runtime data or its retained base release.

## SHA-256

```text
f25feae6c0b89fcef6f89b993dc3462d40b731f17737d67ae9532a185fa9eb90  clermont-blair-athol.html
87c0179ff3b63990c8296faac72364268d3c49a64920bacbd51ff706983d7843  clermont-gold-investigation.css
bdf6038da178f988d4ceb735f1563900e36fa366c3e993a09b2e53c042e2ba53  clermont-gold-investigation.html
1c2d73e683540863bd7f41a86d16c254baf5ca88f2a0875b8560f0df578863f6  clermont-gold-investigation.js
ed10a42d93afa23177f3111af377f0dd4f48d56e2e21edae3c89ab929080c46e  data/clermont-field-validation-points.geojson
59ec394b412d1845c12889c5e335511e14a3c59d129f63afec25aab97d82abd0  data/clermont-legal-gold-prospectivity.geojson
f85e98c53a13e9c6c4ecceb770bd8036630c332f414eed3d24f094dfd586f7ca  data/clermont-prospectivity-watercourses.geojson
d1407da8d44031b6fd354b92b3e33ab0fa33416a40e98af677fbc5d284803fbd  data/gladstone-gold-occurrences.json
e6deedb48fbdca744d0b9ad85264c81b45d5b1866101f26444957c7453545f38  data/gold-path-model.json
8cef4a1fdc9c6b41b583a71ca5baa4609284866828b0a4f0375abc94d9a7837e  data/historical-research/gold/historical-alluvial-evidence.geojson
b74404e535101db190a4e3a27b70598a2ff57a202d8dd924b328921fe393aa54  data/historical-research/gold/historical-gold-sources.geojson
6c8ca50e79bbe85b23712c2161ecd477a76607e6f3345e9e789e96bc8e668e12  data/historical-research/research-data-catalog.json
156755d918c18ea26b820ed09faa6149c45ce107752e1dcb4aa9cc54338db373  data/occurrence-report-matches.json
81d1893bd7906f3a171e9a9749e6c6cfed6ca69e129454b4e70d65bb4e83ba76  data/production-assay-register.json
558bc634df5aac379a30185c924db30399a35537c12f2a1c156ebf1e9abbefc6  data/queensland-gold-occurrences.json
06735a62630a012a91e8d4d2769de6ac8ad0c962d280f292674b757c0fd98a46  index.html
41ac0557c61303b1c459a7d4572efd974d396b91f0b694f4b9754f6c952f7904  map-20260814.js
e24231c171548a7c9ed0ef94945b99e1954c411923f6836961074476a6809980  map.html
f3a894018749f4e0d5fbe503b8416e44e10a9e7dd314277bf0665df564e1591d  old-diggings-map-regional.js
a0359cfbb771a667d3f95f6d906230c03102a61b8d250d89af1adb3fa12a54c3  old-diggings-map.html
69e61970fea9fa2c5c5164459577ddfe4368b5182369b20de236210082099758  service-worker.js
6b8d6230e883f71b65f503ea2104cbcf1c02aae87f1652bc68afcca448f350ef  site-index.html
d2dae4a3704f1e5e1889e8c3a587a0be4d99dc715a009bddb9255230e1904787  sitemap.xml
594bee1be801931315b85f630ea9d15ab140b45512ded458dc641ac89b27f179  research/queensland-high-grade-gold-investigation.md
```
