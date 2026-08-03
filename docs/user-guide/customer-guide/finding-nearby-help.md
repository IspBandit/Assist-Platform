# Finding nearby help

## Ask VanAssist traveller facilities

When Ask VanAssist traveller facilities are enabled, requests for toilets,
dump points and similar amenities appear as compact rows showing the facility
name, type, locality, address, distance and reviewed source status. These
facilities remain separate from business providers and places to stay.

## Purpose

Find VanAssist service listings near a town, postcode or the device's current location and compare the returned providers in a map and accessible list.

## Intended users

Caravan, motorhome and RV travellers planning before departure or searching while safely stopped. VanAssist is not intended to be operated by a driver while a vehicle is moving.

## Permissions

No account is required. On the main VanAssist search, the browser may ask for optional device-location permission automatically so the nearest town can be prepared. Denying permission leaves the full manual search available.

## Fields

**Service category** limits the results to the selected service. **Town, suburb or postcode** establishes the search location. **Preferred timeframe** carries context into an assistance request. **Distance** chooses the locality scope or an available straight-line radius; it is not a road-distance claim.

## Actions

The main search attempts to resolve the current location without submitting the form. **Use my current location** repeats that request when needed. Fuel, EV charging, Places to Stay, service-category buttons, the provider directory, result refinements and assistance requests all inherit the same recent device location. A discovery page with no location automatically resolves it; nearby shortcut links carry the coordinates into the destination page. Typing any town, suburb or postcode clears the device coordinates immediately, so the typed place always wins. **Update results** runs the search. On phones, **List** is the default compact view and **Map** reveals the same located results. Drag the map to move it, pinch or use **Zoom in**/**Zoom out**, and use **Fit results** to restore every returned pin. A numbered pin opens the exact result summary, including **Details**, **Show in list** and **Directions** when available. Each mapped result row also shows the same numbered pin symbol as the map. The summary can be collapsed or moved with pointer, touch or arrow keys so it does not hide the map. Selecting a result row highlights its matching pin. **Places to stay** carries the current location and a supported radius into caravan-friendly stay search. The result list remains fully usable without JavaScript or map tiles.

## Workflows

Choose a service and location, update the results, then compare the compact list and optional map. Featured results are separated first; organic direct results show verified listings first and then the remaining nearest listings; related services are in their own section. Open the provider profile to confirm its current services and contact details. Provider profiles use a simple heading without a decorative business-name initial or repeated business name in the breadcrumb. Workspace help is shown only inside signed-in account/provider areas, not on public provider pages. Open directions only when safely stopped.

A compact accuracy notice appears on the main search journey, results and Places to stay. It links to the full disclaimer and a contact path for reporting incorrect information. Only contact details explicitly designated public by the listing record are displayed; an unclaimed status never makes private contact fields public.

## Examples

Select **12 volt electrical**, enter **Boyne Island**, and choose a distance. The map shows only located providers returned by that same search; listings without usable coordinates still appear in the list rather than being silently discarded.

## Common mistakes

- Treating a straight-line distance as current driving distance.
- Assuming a base-locality pin is an exact mobile-provider destination.
- Assuming an unclaimed or related-service listing has confirmed the requested work.
- Assuming a club, publication or tourism organisation that shares VanAssist has endorsed every listing. An endorsement or partnership is never implied unless stated explicitly.
- Operating the search or map while driving.

## Ask VanAssist

When available on the brand, **Ask** accepts a plain-language question (for
example “dump point near Emerald”) alongside structured Find a service. Results
may include providers, places to stay and — when enabled — separate traveller
facilities such as public toilets or dump points. Ask never invents caravan-park
rows for toilets. Guidance remains non-authoritative; confirm details before you
travel.

Ask results reuse the same List and Map controls as category search. Providers,
places to stay and traveller facilities with reliable coordinates receive a
numbered map pin and the matching number in their list row. Results without
reliable coordinates remain in the list and are not given an invented pin.

The Ask field is also shown directly on the VanAssist homepage when enabled.
It is limited to providers, places to stay, roadside/caravan help and traveller
facilities; it is not a general-purpose AI assistant.

Ask recognises the full VanAssist service catalogue and common descriptions of
faults, including electrical, solar, refrigeration, plumbing, suspension,
body, appliance and roadside problems. If a request is clearly about a caravan
or RV fault but the precise trade cannot be established, it shows general
caravan repair and unsure-service options instead of returning an empty answer.
When the requested specialist category has no nearby listing, Ask may show a
wider set of related repair, mechanical or roadside providers. These are
clearly labelled as related help and users are told to confirm suitability.
Ask keeps conversational wording after a town out of the location name (for
example, “near Gympie on my caravan”). If a named place cannot be resolved,
Ask returns no providers rather than silently falling back to national results.
Heavy repeated use may show a branded pause/security page; the normal category
search remains available while Ask is paused.

## Related pages

Use **Places to stay** for caravan-friendly stops, the service directory to browse all categories, **Ask** when natural-language search is offered, or **Request assistance** when the right listing is not available.

## FAQ

**Why are there more list results than pins?** A public listing can be useful without having coordinates reliable enough to map.

**How do I reset the map after moving or zooming it?** Choose **Fit results** or focus the map and press `0` or `F`.

**Which map opens for directions?** iPhone and iPad use Apple Maps, Android hands off to the device's map handler, and desktop retains the Google Maps web fallback.

**How do I report an incorrect listing?** Use **Report incorrect details** in the accuracy notice. A business representative can use **Request to claim or correct this listing** on an unclaimed profile; VanAssist reviews authority before granting control.

**How do I list my business?** Start at **For providers** / register. VanAssist asks you to search for an existing listing first (“Is this your business?”) so you can claim it instead of creating a duplicate. A new listing is only offered after you confirm none of the matches apply; likely duplicates are held for review and are not published automatically.

**A club or organisation shared the VanAssist link. Do I need to join or pay?** No. Public search remains free and does not require membership of the organisation that shared it. Check each listing's claimed and verified status and confirm important details with the business.

**Did the tablet administration update change public search?** No. Its shared stylesheet changes are restricted to `admin-*` controls inside the authenticated administration shell; customer search, map, tracking and provider-ranking behaviour are unchanged.

**Is the national coverage heat map the customer results map?** No. The Australia/state heat map is an authenticated administrator planning view. Customer searches continue to use the interactive nearby-results map and its matching provider list.

## Version introduced

2026-07 VanAssist mobile map increment.

## Last updated

2026-08-03 (added numbered Ask maps, fail-closed location handling and safer per-visitor rate limiting).

## Owner

Assist Platform product and engineering.
