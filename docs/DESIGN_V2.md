# CodeFanz Design v2: the premium upgrade

> Figma file `gZIo9Cvoi4CNAB2iUYVBMo` · pages **"Website — v2"** (`76:28`) and **"v2 — Image Assets"** (`75:21`) · 2026-11-14
> Status: design upgrade ready for implementation. v1 ("Website — Screens" `17:16`) and every component page are **unchanged**. They stay as the signed-off reference for comparison.

## 1. What v2 is

**v2 is an upgrade of v1, not a redesign.** Per the owner's instruction, v1 website frames were duplicated onto "Website — v2" and those copies were upgraded in place:
- The page structure, section order, copy and the v1 component instances (Navbar, Footer, Button, Card/*, Section Header…) are kept.
- The upgrades are mostly **instance overrides** (fills, strokes, radius, effects, text styles) plus new v2 tokens. The instances stay linked to the v1 components, so the website can adopt v2 as a token and utility refresh rather than a rebuild.

| v2 frame (page `76:28`) | Duplicated from v1 (page `17:16`) |
|---|---|
| `80:17509` v2 · Home — Desktop — EN (1440) | `19:16` |
| `80:17697` v2 · Home — Desktop — AR (1440, RTL) | `19:487` |
| `80:17885` v2 · Home — Mobile — EN (390) | `19:951` |
| `80:18085` v2 · Home — Mobile — AR (390, RTL) | `19:1367` |
| `80:18285` v2 · Services — Desktop — EN (inner-page example) | `22:4008` |
| `80:18327` v2 · 404 — Desktop — EN | `31:17280` |
| `86:2714` v2 — States & Motion (header top/scrolled, button and card hover/focus, motion spec) | new board, built from clones of upgraded instances |

Useful child nodes:
- Hero sections: EN `80:17511`, AR `80:17699`, mobile EN `80:17887`, mobile AR `80:18087`.
- CTA sections: EN `80:17688`, AR `80:17876`.
- Services page hero: `80:18287`, with its image accent `84:3342`.
- 404 image: `85:2714`.

## 2. Visual direction

The brief was "top-tier software studio" polish (Linear / Vercel / Stripe level) with the same brand. That means:
1. **Calmer, brighter canvas.** Use near-white `#FCFCFF` with a lavender tint `#F6F4FF` for alternate bands, not grey. Dark moments (stats, delivery promise, CTA, footer) use one deep "night" `#07091A` overlaid with the generated glow texture.
2. **Confident typography.** Display and section titles are larger and tighter: −3.5% tracking on the hero and −2.5% on H2. The hero's second line uses an indigo→teal gradient. Arabic (Cairo) keeps 0 tracking and taller line heights.
3. **Hairlines, not borders.** Card and header borders are 1px ink at 8% opacity, paired with a soft two-layer shadow. On hover the shadow becomes a brand-tinted lift.
4. **Pill controls.** Every button, the language switch, chips and the search input use radius 9999. The primary button gets a brand glow.
5. **Glass header.** It is transparent at the top of the page, then switches to white at 72% with a 16px backdrop blur and a hairline after 8px of scroll.
6. **Generated imagery replaces fake UI.** The v1 hero dashboard mock (revenue chart, lead stats) and the "browser window" thumbnails are replaced by the 8-image glass set. This makes the site look more premium and avoids showing invented numbers as real.
7. **Hero backdrop.** A 64px grid of brand lines at 7% (32px on mobile), radially faded, with two soft radial glows (indigo top-start, teal bottom-end, mirrored for RTL).
8. **More air.** Desktop section padding goes from 96 to 128 and centered section headers from 720 to 960 wide.

## 3. Token changes (exact values)

All new tokens live in the Figma variable collection **`v2`** (`VariableCollectionId:78:21`, mode Light), with WEB code syntax set. Existing v1 tokens are unchanged.

### 3.1 Colors (new)

| Token (Figma / CSS) | Value | Use |
|---|---|---|
| `v2/bg/canvas` · `--color-v2-canvas` | `#FCFCFF` | Page and white sections (replaces `#FFFFFF` section fills) |
| `v2/bg/tint` · `--color-v2-tint` | `#F6F4FF` | Alternate sections and the inner page hero (replaces the `ink/50` / `#F5F6FA` bands) |
| `v2/bg/night` · `--color-v2-night` | `#07091A` | Stats band, delivery promise card, CTA card, footer (replaces `ink/950` and the brand gradients) |
| `v2/bg/glass` · `--color-v2-glass` | `#FFFFFF` @ 72% | Scrolled header, glass chips |
| `v2/border/hairline` · `--color-v2-hairline` | `#141826` @ 8% | Card, header, input and image borders |
| `v2/border/hairline-dark` · `--color-v2-hairline-dark` | `#FFFFFF` @ 10% | Borders on dark cards |
| `v2/text/display` · `--color-v2-display` | `#0A0D17` | Hero and section titles |
| `v2/text/muted` · `--color-v2-muted` | `#5B6478` | Secondary copy on canvas (AA on `#FCFCFF`: 5.8:1) |
| `v2/grid/line` · `--color-v2-grid` | `#5A3DF5` @ 7% | Hero grid lines |
| `v2/accent/violet` · `--color-v2-violet` | `#8B5CF6` | Third accent: gradient stops, product tile |

Gradients:
- **Display gradient** (hero second line): `linear-gradient(90deg, #5A3DF5 0%, #6E56FF 55%, #1AD5C8 100%)`, applied with `background-clip: text`. In RTL the line reads right→left, but keep the gradient direction at 90deg so the teal end stays visually consistent.
- **Icon tile (service and industry cards):** `linear-gradient(135deg, #F0EEFF, #E8FFFC)` with a 1px border of `brand/600` at 12%.
- **Product tile:** `linear-gradient(135deg, #5A3DF5, #8B5CF6)` with the glow shadow.
- **Hero glows:** `radial-gradient(closest-side, rgb(110 86 255 / .22), transparent)` at about 840px, top-start. `radial-gradient(closest-side, rgb(26 213 200 / .18), transparent)` at about 630px, bottom-end.

### 3.2 Effects (new Figma effect styles)

| Style | CSS |
|---|---|
| `v2/Shadow/Card` | `0 1px 2px rgb(10 13 23 / .04), 0 8px 24px -8px rgb(27 19 80 / .08)` |
| `v2/Shadow/Lift` (hover) | `0 2px 4px rgb(10 13 23 / .04), 0 24px 48px -16px rgb(90 61 245 / .22)` |
| `v2/Shadow/Glow` (primary button, product tile) | `0 8px 24px -6px rgb(90 61 245 / .45), inset 0 1px 0 rgb(255 255 255 / .25)` |
| `v2/Glass/Header` | `background: rgb(255 255 255 / .72); backdrop-filter: blur(16px) saturate(180%); box-shadow: 0 1px 0 rgb(20 24 38 / .06)` |
| `v2/Focus/Ring` | `box-shadow: 0 0 0 2px var(--color-v2-canvas), 0 0 0 5px rgb(90 61 245 / .6)` (use on `:focus-visible` only) |

### 3.3 Radii

| Element | v1 | v2 |
|---|---|---|
| Buttons, language switch, chips, search input | 10 | **9999** (pill) |
| Cards (service, project, testimonial, product, industry) | 20 | **24** |
| Article cover | 20 (no shadow) | 20 + `v2/Shadow/Card` |
| Accordion item (FAQ) | 14 | **16** |
| Hero visual | 28 | **32** desktop / **24** mobile |
| CTA card | 28 | **32** desktop / **24** mobile |
| Delivery promise card | 24 | **28** |
| Icon tile | 14 | 14 (gradient fill, see above) |

### 3.4 Type (new Figma text styles `v2/*`)

| Style | EN (Plus Jakarta Sans) | AR (Cairo) | Replaces |
|---|---|---|---|
| Display XL (home hero h1, desktop) | Bold 80/84, −3.5% | Bold 68/96, 0 | EN/Display/XL 72/80 ExtraBold −2px |
| Display M (home hero h1, mobile) | Bold 44/48, −3% | Bold 38/56 | — |
| H1 (inner page hero) | Bold 60/64, −3% | Bold 52/76 | 56px |
| H2 (section title, desktop) | Bold 48/56, −2.5% | Bold 42/64 | EN/Heading/H2 36/44 |
| H2 Mobile | Bold 32/40, −2% | Bold 28/44 | — |
| H3 | SemiBold 22/30, −1% | SemiBold 21/34 | (cards keep v1 H5 18/26 for now) |
| Lead | Regular 20/32, −0.5% | Regular 19/34 | Body LG 18/30 |
| Body | Regular 16/26 | Regular 16/28 | unchanged |
| Small | Regular 14/22 | Regular 14/24 | unchanged |
| Label | SemiBold 15/20, −0.5% | SemiBold 15/22 | — |
| Eyebrow | SemiBold 13/16, +2% | SemiBold 13/20 | Overline 12/16 |
| Mono | JetBrains Mono Medium 13/16 | — | annotations, step numbers |

Tailwind mapping suggestion: `text-[80px]/[84px] tracking-[-0.035em] font-bold` (EN) and `rtl:tracking-normal rtl:text-[68px]/[96px]`. Better: add `--text-display-xl` etc. to `@theme`.

### 3.5 Spacing and layout

- Desktop section padding-block: **96 → 128**. Unchanged for the hero (96/112) and stats (72).
- Mobile section padding stays at **56**. Hero mobile keeps 40/56.
- Centered Section Header max width: **720 → 960** (desktop). Left-aligned headers are unchanged.
- Container stays at 1280 (80px gutters at 1440). Card gaps are unchanged.
- Blur: header 16, chat bubble glass 16.

## 4. Component-by-component changes

Each row describes the v2 treatment as applied to the instances in the v2 frames. Node IDs are from `v2 · Home — Desktop — EN` unless noted. The states are shown on the board `86:2714`.

| Component (v1 main) | v2 change | How it was applied in Figma |
|---|---|---|
| **Navbar** (Layout; instance in every frame) | Glass header: state=top is transparent with no border. State=scrolled is `v2/bg/glass` + `v2/Glass/Header` + a 1px bottom hairline. Language switch and CTA become pills; primary CTA gets the glow. | Instance overrides (fills, effectStyle, strokeBottomWeight 1). Both states are shown on `86:2714`. |
| **Button** (Core) | Pill radius. Primary: `v2/Shadow/Glow`; hover `brand/700` + translateY(−1px); focus-visible `v2/Focus/Ring`. Secondary: white + hairline; hover `ink/50` + border brand at 35%. | `cornerRadius 9999`, effect style. States on `86:2714`. |
| **Hero** (frame `80:17511`) | New `v2-backdrop` layer: grid + radial fade + 2 glows (mirrored in AR). h1 uses v2 Display, gradient on line 2. `hero-visual` uses **hero-glass-shapes.jpg** (radius 32, hairline, card shadow). The chart and stat mock cards are **hidden**; the AI-assistant chat bubble is kept as a glass chip (white at 86%, blur 16). | Section edit + image fill (`imageHash 1fcab828…`). |
| **Section Header** | Title uses `v2/EN/H2` (or AR / H2 Mobile) in `v2/text/display`. Centered headers are 960 wide. | Text style override + resize. |
| **Card/Service** | Radius 24, hairline, `v2/Shadow/Card`; icon tile uses the gradient. Hover: Lift + border brand at 30% + translateY(−4px) + arrow nudges 4px toward the reading direction (mirrored in RTL). The whole card is the link, so focus goes on the card. | Overrides; states on `86:2714`. |
| **Card/Project** | `thumb` uses generated covers (indigo-torus → teal-arch → violet-orbit, rotating). The fake "browser window" layer is hidden. Radius 24, hairline, card shadow. Hover: lift and cover `scale(1.04)` over 500ms (the cover clips). | Image fill overrides. |
| **Card/Article** | `thumb` uses generated covers (about-glass-stack → process-glass-steps → cover-indigo-torus), radius 20 + card shadow. | Image fill overrides. |
| **Card/Product** | Radius 24 + hairline + card shadow. Icon tile uses the brand→violet gradient + glow. | Overrides. |
| **Card/Testimonial, Card/Industry, Accordion Item** | Radius 24 (accordion 16), hairline, card shadow. Content is unchanged (testimonials stay CMS-driven). | Overrides. |
| **Why CodeFanz → delivery promise card** | `v2/bg/night` + **cta-dark-glow.jpg** at 90%, radius 28, dark hairline, Lift shadow. | Frame fill. |
| **Stats band** | `v2/bg/night` + **cta-dark-glow.jpg** at 85%. Numbers keep the accent color. | Frame fill. |
| **CTA band** (`cta-card`, desktop CTA section `80:17688`) | `v2/bg/night` + **cta-dark-glow.jpg** (cover, anchored end so the glow sits behind the actions, mirrored in RTL), radius 32/24, dark hairline, +16px vertical padding. | Frame fill + padding. |
| **Footer** | Background `v2/bg/night` (`#07091A`) instead of `ink/950`, so it reads as one continuous dark run with the CTA. Content is unchanged. | Instance fill override. |
| **Page Hero (inner pages)** (`80:18287`) | Becomes a 2-column row: copy (720) with an image accent of 440×320, radius 28, hairline, card shadow. Uses the `v2/bg/tint` background + grid (60% opacity) + a brand glow top-end. h1 uses `v2/EN/H1`. | Restructured copy of the v1 section (the copy keeps all v1 children). |
| **404** (`80:18327`) | **not-found-lost-cube.jpg** at 360×360, radius 32, above a smaller "404" (72/76 gradient). Title uses v2 H2. Pill buttons, pill search input and chips. | Image frame `85:2714` inserted. |
| **Section backgrounds** | White → `v2/bg/canvas`; grey bands → `v2/bg/tint`. | Fill swap. |

Inner pages adopt v2 the same way as the Services example: Page Hero with an image accent, tint background, cards with v2 radius, shadow and hairline, CTA band, and night footer. No new layout is needed.

## 5. Image map

All images are in `resources/images/v2/`; the generation log is in `resources/images/v2/README.md`. Covers use `object-fit: cover` and keep the subject centered. Images are decorative unless noted. Decorative images get `alt=""` on the page, but the alt text below is provided for CMS/OG use and for cases where an image becomes content.

| Image | Where (page → slot) | Crop / ratio | Alt EN | Alt AR |
|---|---|---|---|---|
| `hero-glass-shapes.jpg` | Home → hero visual (desktop 560×540, mobile full-width 4:5). Also the default OG image. | cover, center | Floating glass shapes in indigo and teal | أشكال زجاجية عائمة بدرجات النيلي والفيروزي |
| `about-glass-stack.jpg` | Services index → page-hero accent. About → story visual. Blog card cover #1 when the article has no cover. | 11:8, center | Stacked glass layers representing a product being built | طبقات زجاجية متراصة ترمز إلى بناء منتج رقمي |
| `process-glass-steps.jpg` | Home → "How we work" (optional side visual). Service detail → process. Blog fallback cover #2. | 4:3, center | Glass discs in an ascending sequence, one step at a time | أقراص زجاجية في تسلسل تصاعدي خطوة بخطوة |
| `cover-indigo-torus.jpg` | Fallback cover A for project, article and product cards. | 16:10, center | Abstract indigo glass ring | حلقة زجاجية تجريدية باللون النيلي |
| `cover-teal-arch.jpg` | Fallback cover B (square source; crop center for 16:10). | 16:10, center | Abstract teal glass arch and steps | قوس ودرجات زجاجية تجريدية باللون الفيروزي |
| `cover-violet-orbit.jpg` | Fallback cover C. | 16:10, center | Abstract violet and teal glass orbit | مدار زجاجي تجريدي بالبنفسجي والفيروزي |
| `cta-dark-glow.jpg` | CTA band on every page; stats band; delivery promise card. | cover, `object-position: right` (`left` in RTL, or flip with `rtl:-scale-x-100`, since it contains no text) | (decorative) | (زخرفية) |
| `not-found-lost-cube.jpg` | 404 page visual (360×360; 280 on mobile). 500 page may reuse it. | 1:1 | A single glass cube drifting away from its grid | مكعب زجاجي منفرد ابتعد عن مجموعته |

Fallback cover rule: when a project, article or product has no uploaded image, pick `[indigo, teal, violet][id % 3]` so covers stay stable across requests.

## 6. Motion spec

| Interaction | Spec |
|---|---|
| Reveal on scroll | Sections and cards fade up: opacity 0→1, translateY 16px→0, **300ms** `cubic-bezier(.2,.7,.2,1)`. Stagger is 60ms per card (max 6). IntersectionObserver threshold .15, fires once. Content must be visible with no JS (add the hidden state only after the module initialises). |
| Hover (cards, buttons) | 200ms ease-out on transform, box-shadow and border-color. Card lifts −4px, button −1px, arrow moves 4px toward the inline end. |
| Cover hover | `img` scale 1.04 over 500ms ease-out inside an `overflow:hidden` radius wrapper. |
| Header | Add the `is-scrolled` class after 8px of scroll. 200ms transition on background-color, border-color and backdrop-filter. |
| Hero image | Optional slow float ±6px, 6s ease-in-out infinite. |
| Focus | `:focus-visible` uses `v2/Focus/Ring`, with no transition delay. |
| `prefers-reduced-motion: reduce` | No translate, scale or float. Reveal becomes opacity only (≤150ms) or instant. The header still changes state, with no transition. |

## 7. Implementation checklist for the website agent

1. [x] **Tokens.** Add the v2 colors (§3.1), shadows (§3.2) and the display/H1/H2 sizes (§3.4) to `@theme` in `resources/css/app.css`. Use the same names (`--color-v2-*`, `--shadow-v2-card|lift|glow`, `--text-display-xl`…). Do not remove v1 tokens.
2. [x] **Buttons.** Switch `x-ui.button` to `rounded-full`. Primary gets `shadow-v2-glow`, `hover:bg-brand-700 hover:-translate-y-px` and the focus ring. Secondary gets a hairline border and `hover:bg-ink-50`.
3. [x] **Header.** Make it sticky and transparent at the top. Add a small vanilla module (`resources/js/modules/header-scroll.js`) that toggles `is-scrolled` after 8px and applies `bg-white/72 backdrop-blur-[16px] backdrop-saturate-180 border-b border-[--color-v2-hairline]`. Pill language switch.
4. [x] **Home hero.** Add the grid backdrop (CSS `background-image` with two `linear-gradient`s at 64px/32px and a `mask-image: radial-gradient(...)`) and the two radial glows (mirror with `rtl:` positions). Apply the display type and the gradient span on the second line. Replace the dashboard mock with the `hero-glass-shapes` image in a radius-32 frame, keeping the assistant chat bubble as a glass chip (it opens the AI assistant).
5. [x] **Section headers.** Use the v2 H2 sizes and `max-w-[960px]` when centered. Arabic uses the AR sizes with no negative tracking (`rtl:tracking-normal`).
6. [x] **Cards.** Service, project, article, product, testimonial and industry cards get `rounded-3xl` (24), the hairline border, `shadow-v2-card`, and on hover `shadow-v2-lift` + `-translate-y-1` + `border-brand-600/30`. Project and article covers use the uploaded image, otherwise the fallback cover rule (§5). Remove the "browser window" placeholder markup.
7. [x] **Dark bands.** Stats, the delivery promise card and the CTA card use `bg-[--color-v2-night]` with `cta-dark-glow` as a cover background (mirrored in RTL). The footer uses `bg-[--color-v2-night]`.
8. [x] **Inner page hero component.** Make it 2-column with an optional `image` prop (default `about-glass-stack`), tint background, faint grid and glow. Use it on Services index first, then all other inner pages.
9. [x] **404 page.** Add the lost-cube image above a smaller gradient "404"; pill search and chips.
10. [x] **Section rhythm.** Change desktop `py-24` (96) to `py-32` (128) on home sections; leave mobile at `py-14`.
11. [x] **Motion.** Add `resources/js/modules/reveal.js` (IntersectionObserver, `data-reveal`, stagger via a CSS variable) with a full `prefers-reduced-motion` fallback. Add hover transitions per §6.
12. [x] **Images.** Serve `resources/images/v2/*` through the existing image pipeline as WebP/AVIF with JPEG fallback, with `width`/`height` set, `loading="lazy"` below the fold and `fetchpriority="high"` on the hero. Use alt text from §5 via `__()` with entries in `lang/ar.json`.
13. [x] **RTL check.** Glows, the CTA texture and arrow nudges mirror. Gradient text, numbers and the logo do not. Compare against `80:17697` / `80:18085`.
14. [x] **QA.** Check contrast (muted `#5B6478` on canvas is 5.8:1; white on night is about 19.8:1), visible keyboard focus everywhere, no layout shift from images, and a Lighthouse pass. Compare screenshots against the v2 frames.

### Implementation status (website, 2026-11-14)

All 14 items are implemented on the public website (branch `design-v2-upgrade`). Where the build differs from the
frames or the spec, the reason is below.

- **Scope.** v2 applies to the public site and the error pages only: their layouts mark `<body data-site>`, a
  `site:` Tailwind variant scopes the shared `x-ui.button` / `x-ui.input` / language-switch changes, and
  `[data-site]` remaps `--cf-bg-canvas` to `#FCFCFF` and `--cf-text-secondary` to `#5B6478`. Admin and portal keep v1.
- **Type.** The v2 styles are `@utility` classes like the v1 ones (`type-display-xl`, `type-display-m`,
  `type-v2-h1`, `type-v2-h2`, `type-v2-h2-mobile`, `type-v2-h3`, `type-lead`, `type-label`, `type-eyebrow`), each
  switching to the Arabic metrics with 0 tracking under RTL, rather than `--text-*` theme sizes.
- **Focus ring.** It is drawn as a 3px outline 2px off the element, not as the spec's box-shadow, so it survives the
  shadow utilities (button glow) and forced-colors mode. Brand is at 85% instead of 60%, because 60% gave 2.8:1 against
  the canvas and the ring needs at least 3:1 (WCAG 1.4.11).
- **Display gradient.** It ends on `#0A958C` (`--color-v2-gradient-end`) instead of `#1AD5C8`, because teal/400 on the
  canvas is 1.8:1 and the hero line needs at least 3:1 as large text (now 3.6:1). The 404 code is decorative
  (`aria-hidden`), so it keeps the original teal.
- **Glow texture opacity.** The stats band and the delivery promise run `cta-dark-glow` at 40% (spec 85% and 90%).
  The CTA card uses 40% on mobile and 90% from `lg`, with its copy capped at 576px so it stays on the dark half. At the
  spec values the labels crossing the glow measured 1.4–2.2:1. They are now at least 4.5:1, measured on rendered
  pixels in EN and AR at 1440 and 390.
- **Header.** Without JavaScript the header keeps the glass (scrolled) state, so content never scrolls under a
  transparent bar. `header-scroll.js` opts in to the transparent top. With the mobile drawer open, the header is solid
  white.
- **Reveal.** `reveal.js` uses `rootMargin: 0 0 -15% 0` with threshold 0 instead of threshold .15, so elements
  taller than the viewport still reveal. Elements already on screen when it starts are shown immediately, so there is
  no flash and the LCP is never hidden.
- **Hero visual (mobile).** It keeps the desktop 560:540 frame, as in the mobile frames (about 350×330), not 4:5. The
  chat chip is a real link to `/{locale}/assistant`.
- **Images.** Run `php artisan site-images:build` to generate them (`App\Services\SiteImages`). It writes centre-cropped AVIF and
  WebP at 480/640/960/1280/1600 (capped at the crop width) plus one progressive JPEG fallback per image to
  `public/images/v2/`, and `og-default.jpg` (1200×630), which is now the default OG image. `x-site.image` renders the
  `<picture>`. Card covers and the CTA/stats texture are decorative (`alt=""`). The hero, the page-hero accent, the
  About story visual and the 404 cube use the localized alt text.
- **Page hero accents.** The default is `about-glass-stack`. Service detail uses `process-glass-steps` and About uses
  `cover-violet-orbit`, because its story section already shows the glass stack. There is no accent on project and
  product detail (their cover or gallery follows directly), on legal pages, on search or on the centred blog hero.
- **Inputs.** Only search fields are pills (per §3.3). Contact and quote form fields keep radius 10.
- **Removed.** The v1 dashboard mock, `x-site.window-mock` and `x-site.screen-mockup` are removed. Every placeholder is
  now a generated cover, picked by `SiteImages::coverFor($id)`.

## 8. Notes and limits

- Testimonials, stats, projects and articles in the frames are the v1 sample/demo content. On the site they stay CMS/DB-driven (no fabricated content).
- The hidden hero mock layers (`Chart Card`, `Stat Card`) remain in the v2 copies with `visible=false`, so the change is easy to compare.
- Screenshots of the finished frames were exported during design; see the final report for paths.
