# TowSmart and TrailerWise navigation

## Purpose

Explain how TowSmart and TrailerWise expose their primary journeys through the
shared public header, footer and optional save-to-phone control.

## Intended users

TowSmart customers checking towing combinations and TrailerWise customers finding
trailer services or registering a business.

## Permissions

No account is required to browse public pages. Saved TowSmart combinations and
provider sign-in require an authenticated account.

## Fields

The header shows brand navigation links, **Sign in** or **My account**, and one
primary button: **Check weights** on TowSmart or **Find services** on
TrailerWise. TowSmart also lists **Specialist categories** (`/services`) in the
header; TrailerWise lists **Service categories** and **Marketplace**. The footer
opens with a brand-specific action bar, then structured link columns for tools or
discovery, business registration and trust pages.

## Actions

On TowSmart, use **Check weights** or the footer **Check my combination**
action to open the weight calculator. Open **My combinations** when signed in to
review a saved result, its headline inputs and the original limit table. You can
edit and recalculate the saved snapshot, compare up to three combinations, print
or save the private report as PDF, or remove it. Recalculate whenever the load
or specifications change. Use
**Specialist categories** or the homepage **After the
check** tiles to browse weighing, towbar, brake and other towing specialists,
then search with your town or postcode. On TrailerWise, use **Find services** or
the footer **Find trailer services** action to open the service directory. Browse
**Service categories** from the header or homepage tiles when you know the type of
work you need. Open **Marketplace** for sale and hire listings separate from the
core directory.

When the Assist-search feature is enabled, **Ask TowSmart** and **Ask
TrailerWise** use reviewed brand-specific matrices. TowSmart routes weight,
capacity and safety wording to the calculator and specialist wording to its
curated directory. TrailerWise routes repair, mobile, parts, inspection,
certifier, manufacturer/dealer and engineering wording to its curated
categories. The result names its routing source. Unknown requests ask for a
category instead of substituting an unrelated business.

Direct `/find` links and saved structured-search links also open the current
brand's provider directory. TowSmart and TrailerWise keep the supplied business,
town/postcode and curated category filters; they do not show VanAssist stays,
fuel shortcuts, service runs or assistance-request actions.

In either provider directory, use **How should they help?** when you specifically
need a business that can come to you or when you can visit a workshop. A listing
marked **Verified for this service** has a reviewed category assignment. Direct
or verified service matches appear before featured placement. If that delivery
choice produces no result, **Show mobile and workshop options** widens only that
choice and keeps the other search filters.

## Workflows

Start from the homepage quick paths or the header primary button. Use the footer
Tools or Find columns for secondary journeys such as tow guides, rules,
provider registration or marketplace listings. On TowSmart and TrailerWise,
`/services` lists that brand's specialist categories—not VanAssist caravan
services. TowSmart and TrailerWise show curated categories only; import taxonomy
rows used for data classification are hidden from public navigation. Confirm
directory details directly with the business before travel or compliance work.

Each homepage also has the same focused service/location finder as its public
directory. Enter a service or business, add a town/suburb/postcode or use the
device-location control, and optionally choose one of that brand's curated
categories. Device location may fill the nearest town automatically, but it does
not submit the directory search; results open only after the user deliberately
presses the finder search button. Results stay within the current brand; TowSmart
does not return TrailerWise-only listings and neither finder exposes VanAssist
stays or assistance requests.

**Save TowSmart to your phone** or **Save TrailerWise to your phone** opens
installation instructions; Android may also show the browser install prompt when
available. After installation the control hides automatically.

The pending sale-readiness update preserves the page you are using when the
background app worker activates. It removes automatic reloads that could discard
an unfinished calculator form. This change is pending the reviewed production release.

## Examples

A TowSmart visitor opens **Check weights**, enters loaded figures and saves the
combination after signing in, then opens **Weighing services** from **Specialist
categories** to find a public weighbridge. A TrailerWise visitor opens **Find
services**, filters by category and opens a provider profile to verify contact
details.

## Common mistakes

- Assuming a green calculator result is certification or legal advice.
- Treating an unclaimed directory listing as verified current contact details.
- Expecting VanAssist stays, traveller facilities or assistance requests on
  TowSmart or TrailerWise. Product-brand Ask only routes their reviewed towing
  and trailer intents.

## Related pages

See **Account and My Garage** for saved combinations and account tools. See
**Finding nearby help** for VanAssist search behaviour.

## FAQ

**Can I install TowSmart or TrailerWise like VanAssist?** Yes. Each brand publishes
its own install manifest and footer control. The installed shortcut opens that
brand's homepage and primary journeys. Home-screen icons use each brand's
favicon asset, not the retired platform symbol.

## Version introduced

2026-08-13 shell parity release.

## Last updated

2026-09-07 (homepage device location fills without automatically opening the
provider directory; shared TowSmart/TrailerWise finder regression coverage).

## Owner

Assist Platform product and engineering.
