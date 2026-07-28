# Premium visual assets

Assist Platform uses an original, subject-specific hero image system. Hero
media must help a visitor understand the page before it decorates the page.
Generic stock imagery, readable text baked into images, third-party logos and
manufacturer marks are not part of the system.

## Production contract

- Keep headings, search controls and calls to action as live HTML.
- Provide an art-directed mobile crop rather than relying on desktop cropping.
- Serve AVIF first and WebP as the compatibility fallback.
- Use an empty `alt` value when the image repeats the adjacent hero message.
- Reserve the image dimensions in markup and load only the above-the-fold hero
  at high priority.
- Desktop derivatives are 1824 × 864 and must remain at or below 110 KB AVIF
  and 180 KB WebP.
- Mobile derivatives are 720 × 960 and must remain at or below 65 KB AVIF and
  90 KB WebP.
- A contextual image must never obscure official-source status, sponsorship
  labels, safety limitations or a primary form.

## Initial asset family

The first family was generated with OpenAI's built-in image generation tool on
2026-07-27 and then reviewed, cropped and encoded locally. The Garage source was
edited once to remove an incidental manufacturer grille mark.

| Family | Intended context | Creative direction |
|---|---|---|
| VanAssist | public home and travel assistance | Australian regional roadside, caravan assistance and calm onward travel |
| TowSmart | towing tools and weighing | professional 4WD/caravan coupling and measured technical safety |
| TrailerWise | trailer ownership and maintenance | specialist inspecting a clean dual-axle trailer, hub and brake area |
| LocalTorque | local automotive services | modern independent Australian workshop and a technician inspecting a road car |
| My Garage | shared ownership hub | a coherent 4WD, caravan, trailer and motorcycle collection at an Australian garage |
| Rules library | official compliance discovery | vehicle engineering desk, abstract technical linework and inspected vehicles |

All prompts requested premium editorial realism, restrained brand-compatible
colour, useful negative space, no readable text, no logos and no watermarks.
The distributable derivatives live in `public/assets/img/`; generated working
sources remain outside the repository.

## Verification

`HeroAssetTest` checks every family for desktop and mobile AVIF/WebP coverage,
exact dimensions, byte budgets and a smaller AVIF derivative. Representative
pages are inspected at 1440 px and at a true 390 px mobile viewport, including
horizontal-overflow checks.
