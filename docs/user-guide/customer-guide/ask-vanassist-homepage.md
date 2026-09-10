# Ask VanAssist from the homepage

## Purpose

When Ask VanAssist is enabled, the VanAssist homepage presents it as the preferred starting point for travellers who want to describe what they need in ordinary language.

Ask can search across the VanAssist provider directory, places to stay and enabled traveller-facility data. Useful examples include `dump point near Emerald`, `pet-friendly stay near Rockhampton`, `mobile mechanic near me` and `drinking water near Gladstone`.

## Intended users

Australian caravan, camper and motorhome travellers visiting VanAssist, including visitors who are not signed in.

## Permissions

No account is required. Ask appears on the VanAssist homepage only when the production feature flag is enabled. Location permission is optional and can be denied without blocking the rest of the homepage.

## Fields

The Ask control accepts a plain-language question. A place written in the question takes priority. If no place is included, Ask can request the device's current location. Denying location permission does not block VanAssist; add a place to the question or use the structured Browse directly form instead.

## Actions

Submit an Ask question from the homepage, open trusted service shortcuts, choose Places to stay, fuel and essentials, browse all help, or use the structured category and town form when a controlled search is preferred.

## Workflows

1. Prefer Ask VanAssist when you can describe the need in everyday language.
2. Use direct shortcuts when you already know the journey type.
3. Use Browse directly when you want to choose a service category and town, suburb or postcode yourself.

The same hierarchy applies on phones. Ask is intentionally shown before the shortcut boxes and structured category/location form, while those direct paths remain available without requiring Ask.

## Examples

- Ask for `dump point near Emerald` from the homepage Ask control.
- Open Places to stay when you already know you need a stay search.
- Use Browse directly with a category and town when you prefer structured filters over plain language.

## Common mistakes

- Expecting Ask to replace `/find`, Places to stay, service directories or assistance requests.
- Treating Ask as a general-purpose assistant instead of a discovery layer over VanAssist data.
- Assuming denied location permission blocks the homepage; typed places and Browse directly remain available.

## Related pages

See **Ask VanAssist** for the full Ask journey and **Finding nearby help** for detailed search, map, distance and facility behaviour. Structured `/find`, Places to stay, service directories and assistance requests remain available and are not replaced by Ask.

## FAQ

**Does Ask invent businesses?** No. Provider, stay and traveller-facility results keep their existing source, verification and distance rules. Confirm important details with the business, operator or relevant authority before travelling.

**Is Ask mandatory on the homepage?** No. Shortcuts and structured browse remain available without using Ask.

## Version introduced

2026-08-25.

## Last updated

9 September 2026.

## Owner

Assist Platform product and engineering.
