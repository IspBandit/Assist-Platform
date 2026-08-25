# CQDiggings Clermont investigation overlay

- Source repository: `IspBandit/CQDiggings`
- Source commit: `d3f4f5ea76c00ecea5ce6159abe1fa79e8ece3a0`
- Source pull requests: CQDiggings #66 and #67
- Content: Clermont investigation page, three investigation GeoJSON layers,
  Research Map and Field Map integration, navigation, indexes and service worker
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
b804bb36283c3b9f39f75c48d097bea0483770edaf63cceb89d9582915e4fa76  clermont-gold-investigation.html
1c2d73e683540863bd7f41a86d16c254baf5ca88f2a0875b8560f0df578863f6  clermont-gold-investigation.js
ed10a42d93afa23177f3111af377f0dd4f48d56e2e21edae3c89ab929080c46e  data/clermont-field-validation-points.geojson
59ec394b412d1845c12889c5e335511e14a3c59d129f63afec25aab97d82abd0  data/clermont-legal-gold-prospectivity.geojson
f85e98c53a13e9c6c4ecceb770bd8036630c332f414eed3d24f094dfd586f7ca  data/clermont-prospectivity-watercourses.geojson
06735a62630a012a91e8d4d2769de6ac8ad0c962d280f292674b757c0fd98a46  index.html
ddb16dd49546d15678410016f0f5506a555a524484e45bb1b5bb3bf53917fb10  map-20260814.js
e24231c171548a7c9ed0ef94945b99e1954c411923f6836961074476a6809980  map.html
edc26e34d03b36a41329cbbd9e2b803b78ee239e732952a002a2f555eee13883  old-diggings-map-regional.js
a0359cfbb771a667d3f95f6d906230c03102a61b8d250d89af1adb3fa12a54c3  old-diggings-map.html
69e61970fea9fa2c5c5164459577ddfe4368b5182369b20de236210082099758  service-worker.js
6b8d6230e883f71b65f503ea2104cbcf1c02aae87f1652bc68afcca448f350ef  site-index.html
d2dae4a3704f1e5e1889e8c3a587a0be4d99dc715a009bddb9255230e1904787  sitemap.xml
```
