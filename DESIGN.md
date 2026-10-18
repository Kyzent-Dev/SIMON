# SIMON-SPT Design System

Accessible mint brand, components and motion specs for Inspektorat Daerah Kabupaten Muara Enim.

Use deep mint (#17735B, `mint-700`) for every interactive element and every piece of mint-colored text. Keep the light signature mint (#8FE0C0, `mint-300`) for surfaces, dark-hero accents and the dark-mode primary only. Give "Selesai" a solid leaf-green pill with a check icon so it never reads as a brand element. Each pair in the system below has been checked against WCAG 2.1 AA.

## TL;DR
- **Color:** mint is the brand identity, but it carries text only from step 700 upward. White on `mint-700` is 5.77:1 and white on `mint-800` is 7.90:1. `mint-300` against white is only 1.55:1, so it is forbidden for text and controls. Gold (`accent-400` #F2C14E), taken from the yellow wings of the Muara Enim coat of arms, is the single accent. It is used as a decorative rule and on dark surfaces.
- **Status and categories:** status badges use one fixed pill-plus-icon shape that brand elements never use. "Selesai" is the only *solid* badge, in leaf green #1D6B34 (white text 6.55:1). Assignment types draw from a curated 12-color categorical set, and every color clears 3:1 against white. That set excludes mint, teal and green.
- **Motion and type:** motion is concentrated on public pages (hero crossfade + Ken Burns, staggered fade-up, count-up, chart draw-in, timeline bar grow-in). It is near-zero on the Bagian Umum inbox, the register and all forms. Everything is built with Tailwind + Alpine (`x-transition`, `x-intersect`) + CSS keyframes, with an exact `prefers-reduced-motion` fallback. Fonts are Plus Jakarta Sans for display and Inter for UI and tables (tabular numerals).

---

## 0. How Claude Code must use this pack
1. Read this file first. It defines the tokens, the components and the rules. Page files (`design/public/*`, `design/auth/*`, `design/dashboard/*`) only *compose* what is defined here. If a page file seems to conflict with this file, this file wins.
2. The stack is Laravel 13 + Blade + Livewire + Alpine.js + Tailwind CSS + Chart.js. **Do NOT install React, Vue, shadcn, Radix UI, Framer Motion, GSAP or Swiper.** 21st.dev items are visual references only. Re-implement them as Blade components with Alpine.
3. Allowed JS: Alpine core plus the official plugins `@alpinejs/intersect`, `@alpinejs/focus` (focus trap) and `@alpinejs/sort` (reordering), Chart.js, and the `motion` package **under the restrictions in §7.6 only** (vanilla API, public pages only, through the wrapper module). No other animation library.
   - **Never import from `motion/react`, `framer-motion` or any `motion/*` React entry, and never install `react` or `react-dom`.** Code examples on motion.dev are mostly React; translate them to the vanilla API (`animate`, `inView`, `stagger`) instead of copying them.
4. Every UI string is Bahasa Indonesia. Dates use `d F Y` in Indonesian (`Carbon::setLocale('id')`, `translatedFormat('d F Y')` gives "18 Oktober 2026"). Timezone is Asia/Jakarta.
5. Name components `x-ui.*` (primitives) and `x-app.*` (domain composites). Name tokens with CSS custom properties `--color-*`, `--dur-*`, `--ease-*`.

File map:
```
DESIGN.md                      ← this file (core system)
design/
├── public/
│   ├── landing.md
│   ├── jadwal.md
│   └── realisasi.md
├── auth/
│   └── login.md
└── dashboard/
    ├── _shared.md             ← read before any role file
    ├── irban.md
    ├── pimpinan.md
    ├── bagian-umum.md
    └── admin.md
```

## 1. Design principles
1. **Resmi, bukan kaku (official, not stiff).** A deep-ink, mint-tinted neutral base keeps it official. Mint and gold carry identity. There are no gradients on controls and no glassmorphism.
2. **Data first.** Numbers use tabular figures and are right-aligned. Every chart has a table twin.
3. **One accent, one category language.** Gold is the only accent. Assignment-type colors are identical across timeline bars, charts and chips.
4. **Color never alone.** Every status or flag carries an icon and a text label, and a shape where relevant.
5. **Motion serves first impressions, not daily work.** Public pages may delight. Work screens must feel instant.
6. **Signature motif: "Delapan Sungai" (eight rivers).** It is a set of 8 thin wavy lines derived from the eight blue river lines in the regency emblem. It is used as an SVG texture on dark brand panels (hero scrim edge, login panel, footer) and never behind text that must be read.

## 2. Logo reconciliation
Research found the official regency description of the Lambang Kabupaten Muara Enim. It names these colors:
- **Cokelat (brown):** the 7-tooth gear.
- **Kuning (yellow):** the 8 wing feathers.
- **Putih (white):** the ring and the small shield.
- **Biru (blue):** 8 wavy river lines (Lematang, Enim, Penukal, Belido, Lubai, Rambang, Lengie, Niru).
- **Hitam (black):** the triangle (oil drilling / coal).

The official text does not name the color of the large shield's field, and no hex values are published. A regency regulation on official documents (Perbup 35/2024) only requires a "logo Kabupaten berwarna".

How the logo colors are used:

| Logo color | Used as | Token |
|---|---|---|
| Kuning (wings) | The single accent: decorative rules, active-tab underline on dark, hero eyebrow, focus halo on dark panels | `accent-400` #F2C14E, `accent-700` #8A5A00 (text) |
| Biru (rivers) | The "Delapan Sungai" motif shape (drawn in mint tones, not blue, to keep one accent) | n/a |
| Cokelat (gear) | Categorical color #12 "Cokelat" | `cat-12` #7A4E2D |
| Putih / Hitam | Surfaces and ink | `white`, `neutral-900` |

Mint stays the primary. **Action for the team:** sample exact hex values from the official vector file supplied by Diskominfo, then update `accent-400` if the official yellow differs.

## 3. Color tokens (light mode, mandatory)

### 3.1 Mint primary scale
Relative luminance (L) is calculated per WCAG 2.1. Contrast ratios are computed as (L1 + 0.05) / (L2 + 0.05).

| Token | Hex | L | vs white | Allowed use |
|---|---|---|---|---|
| mint-50 | #F0FBF7 | 0.942 | — | section backgrounds, selected-row bg, hover bg |
| mint-100 | #DDF6EC | 0.873 | — | soft surfaces, info cards, active nav bg |
| mint-200 | #BCEDD9 | 0.763 | — | decorative borders/dividers on mint surfaces, motif lines on dark |
| mint-300 | #8FE0C0 | 0.630 | 1.55 ✗ | **signature mint**: large color fields, dark-hero accent text, dark-mode primary button bg, illustrations |
| mint-400 | #5ECBA5 | 0.478 | 1.99 ✗ | illustration only; dark-mode focus ring |
| mint-500 | #34B08A | 0.336 | 2.72 ✗ | illustration only |
| mint-600 | #1E8F70 | 0.211 | 4.02 | focus ring, UI graphics, icons ≥20px, large text ≥24px (or ≥18.66px bold) only |
| mint-700 | #17735B | 0.132 | **5.77** | **primary button bg, links, mint text, active nav text, chart "realisasi" series** |
| mint-800 | #145C4A | 0.083 | 7.90 | primary hover/pressed, headings on mint-100/200 |
| mint-900 | #124C3E | 0.057 | 9.86 | text on mint-300 |
| mint-950 | #082B23 | 0.019 | 15.2 | dark brand panels: hero scrim, login panel, footer |

### 3.2 Tinted neutrals (cool, slightly mint)
| Token | Hex | vs white | Role |
|---|---|---|---|
| neutral-25 | #F7FAF9 | — | app/page background |
| neutral-50 | #F1F5F4 | — | table header, group rows, input disabled bg |
| neutral-100 | #E6ECEA | — | Draf badge bg, skeleton base |
| neutral-200 | #D5DEDB | 1.32 | decorative borders, dividers, card borders |
| neutral-300 | #B8C4C0 | — | chart axis lines, skeleton highlight |
| border-strong | #7D8B87 | 3.55 | **input/checkbox/select borders** (meets 1.4.11) |
| neutral-400 | #8A9894 | 3.00 | disabled text and icons only (exempt); never informative |
| neutral-500 | #64736F | 4.97 | `text-muted` (captions, timestamps, helper text) |
| neutral-600 | #4B5955 | 7.34 | `text-secondary` |
| neutral-700 | #37433F | 10.31 | Draf badge text, table body alt |
| neutral-800 | #242E2B | — | tooltip bg (dark) |
| neutral-900 | #151C1A | 17.32 | `text-primary`, headings |
| neutral-950 | #0B1110 | — | dark-mode background |

### 3.3 Accent (logo gold) and semantic colors
| Token | Hex | Notes |
|---|---|---|
| accent-400 | #F2C14E | 1.68 on white: **decorative only** on light backgrounds. 9.07 on mint-950. 10.32 behind neutral-900 text |
| accent-700 | #8A5A00 | gold text on white 5.93 |
| danger-700 | #B91C1C | error text and destructive button bg (white text 6.47) |
| info-800 | #1E40AF | info text (8.72 on white) |
| success-700 | #1D6B34 | "Selesai" solid badge and success toast icon (white text 6.55) |

### 3.4 Verified text/background pairs — light mode (ONLY these are allowed)
| Foreground | Background | Ratio | Use |
|---|---|---|---|
| neutral-900 | white | 17.32 | body/headings |
| neutral-900 | neutral-25 | 16.49 | body on page bg |
| neutral-900 | neutral-50 | 15.75 | table header text |
| neutral-900 | mint-50 | 16.36 | text in mint sections |
| neutral-900 | accent-400 | 10.32 | gold highlight chip |
| neutral-600 | white | 7.34 | secondary text |
| neutral-600 | neutral-25 | 6.99 | secondary on page |
| neutral-600 | mint-50 | 6.94 | secondary in mint sections |
| neutral-600 | neutral-100 | 6.14 | secondary on gray chips |
| neutral-500 | white | 4.97 | muted text |
| neutral-500 | neutral-25 | 4.73 | muted on page |
| neutral-500 | mint-50 | 4.70 | muted in mint sections |
| neutral-500 | neutral-50 | 4.52 | muted in table header (min.) |
| mint-700 | white | 5.77 | links, mint text, outline buttons |
| mint-700 | neutral-25 | 5.49 | links on page bg |
| mint-700 | mint-50 | 5.45 | active nav text |
| mint-700 | mint-100 | 5.07 | text on soft mint |
| mint-800 | mint-100 | 6.95 | headings on soft mint |
| mint-800 | mint-200 | 6.12 | text on mint-200 |
| mint-800 | mint-300 | 5.11 | text on signature mint |
| mint-900 | mint-300 | 6.38 | text on signature mint (preferred) |
| mint-950 | mint-300 | 9.85 | "brand-soft" button (mint-300 bg) |
| white | mint-700 | 5.77 | primary button |
| white | mint-800 | 7.90 | primary hover |
| white | mint-950 | 15.2 | dark panels |
| mint-200 | mint-950 | 11.78 | secondary text on dark panels |
| mint-300 | mint-950 | 9.85 | accent text on dark panels |
| neutral-300 | mint-950 | 8.48 | muted text in footer |
| accent-400 | mint-950 | 9.07 | gold text/rules on dark |
| accent-700 | white | 5.93 | gold-ish text |
| danger-700 | white | 6.47 | error text |
| white | danger-700 | 6.47 | destructive button |
| white | success-700 | 6.55 | Selesai badge |
| info-800 | white | 8.72 | info text |
| white | hero scrim (mint-950 @80% over pure white, worst case ≈ #39554F) | 8.12 | hero text over any photo |

UI components and graphics (≥3:1):
- Focus ring `mint-600` on white: 4.02. On mint-50: 3.80.
- Input border `border-strong` on white: 3.55. On neutral-25: 3.38.
- `mint-700` bar on white: 5.77.

### 3.5 Forbidden combinations (lint for these in code review)
| Combination | Ratio | Why |
|---|---|---|
| white text on mint-300/400/500 | 1.55 / 1.99 / 2.72 | illegible |
| mint-300/400/500 text or icons on white | same | invisible |
| white text on mint-600 below 24px (18.66px bold) | 4.02 | fails normal text |
| mint-600 body text on white | 4.02 | fails normal text |
| neutral-500 on neutral-100 or darker | 4.16 | fails |
| neutral-400 for any informative text | 3.00 | fails |
| accent-400 text or meaningful icons on white/neutral-25/mint-50 | ≈1.6–1.7 | decorative only |
| white text on accent-400 | 1.68 | illegible |
| any text directly on a photo without the scrim | variable | unverifiable |
| status meaning conveyed by color only | — | WCAG 1.4.1 |

### 3.6 Status badge system (fixed by the PRD)
Anatomy:
- **Shape:** pill (`rounded-full`), height 24px, `px-2.5`, 12px/600 text, 14px leading icon, 1px border.
- **Shape reservation:** brand elements (tags, nav, buttons) never use pill + leading icon.
- **Solid vs tinted:** "Selesai" is the only *solid* badge ("finished = filled"). That makes it visually distinct from the mint brand, which is never a solid green pill.

| Status | Icon (Heroicons) | Bg | Text | Border | Ratio |
|---|---|---|---|---|---|
| Draf | `pencil-square` | #E6ECEA | #37433F | #B8C4C0 | 8.62 |
| Menunggu Nomor (yellow) | `clock` | #FEF3C7 | #713F12 (dark, mandatory) | #E9C46A | 7.79 |
| Perlu Revisi (orange) | `arrow-uturn-left` | #FFEDD5 | #7C2D12 | #FDBA74 | 8.18 |
| Ditolak (red) | `x-circle` | #FEE2E2 | #991B1B | #FCA5A5 | 6.80 |
| Siap Dilaksanakan (blue) | `calendar-days` | #DBEAFE | #1E40AF | #93C5FD | 7.15 |
| Pelaksanaan (blue) | `play-circle` | #DBEAFE | #1E40AF | #93C5FD | 7.15 |
| Penyusunan Laporan (blue) | `document-text` | #DBEAFE | #1E40AF | #93C5FD | 7.15 |
| **Selesai** (green, solid) | `check-circle` (solid) | #1D6B34 | #FFFFFF | #1D6B34 | 6.55 |
| Dibatalkan | `no-symbol` | #FFFFFF | #4B5955 | dashed #8A9894 | 7.34 |

The three blue "On Process" statuses share a color and differ by icon and label. Dashboards may group them as "Berjalan".

**Flags** use a different shape: a rectangle with `rounded` (4px), outline style and a 12px icon. They can sit next to a status badge without being mistaken for one.

| Flag | Icon | Bg | Text | Ratio |
|---|---|---|---|---|
| Terlambat | `exclamation-triangle` | #FEE2E2 | #991B1B | 6.80 |
| Tidak Sesuai PKPT | `arrows-right-left` | #F5F3FF | #5B21B6 | 8.19 |
| SPT Final Belum Diupload | `arrow-up-tray` | #FFF7ED | #7C2D12 | 8.83 |
| Non-PKPT | `minus-circle` | #F1F5F4 | #37433F | 9.38 |

### 3.7 Assignment-type categorical palette (curated set for the admin picker)
Rules:
- Mint, teal and green are excluded. They are reserved for the brand and for "Selesai".
- Every color is ≥3:1 against white, so it works for bars, slices and dots.
- The label color stated for each color is used for text placed *on* the fill.
- Base: the rcartocolor "Safe" and Okabe-Ito colorblind-safe palettes, darkened where needed to reach 3:1.
- Assign colors 1–6 first. They have the most separation under color-vision deficiency.

| # | Name (ID) | Hex | vs white | Label on fill | Label ratio |
|---|---|---|---|---|---|
| 1 | Nila | #332288 | 12.17 | white | 12.17 |
| 2 | Jingga | #C77C00 | 3.33 | neutral-900 | 5.20 |
| 3 | Biru Baja | #3F6FA6 | 5.20 | white | 5.20 |
| 4 | Mawar | #CC6677 | 3.66 | neutral-900 | 4.73 |
| 5 | Sian Tua | #146C8F | 5.88 | white | 5.88 |
| 6 | Oker | #A68A1F | 3.35 | neutral-900 | 5.17 |
| 7 | Ungu | #AA4499 | 5.26 | white | 5.26 |
| 8 | Bata | #B84E00 | 5.09 | white | 5.09 |
| 9 | Zaitun | #8A8A2E | 3.65 | neutral-900 | 4.74 |
| 10 | Anggur | #882255 | 8.73 | white | 8.73 |
| 11 | Plum | #6A3D9A | 7.65 | white | 7.65 |
| 12 | Cokelat (logo) | #7A4E2D | 7.10 | white | 7.10 |
| — | Lainnya (donut) | #5A6B7B | 5.49 | white | 5.49 |

Admin color picker: offer these 12 swatches. A "Warna lain" option accepts a hex value only if it is ≥3:1 against white and is not within ΔE 10 of mint-700 or success-700. The system then auto-selects white or neutral-900 for the label, whichever gives the higher contrast and ≥4.5. If neither reaches 4.5, the save is rejected with "Warna terlalu terang/gelap untuk label teks".

Twelve hues cannot all be mutually distinct under every type of color-vision deficiency. A legend with text, direct labels and tooltips is therefore always required. Never rely on color matching alone.

## 4. Typography
- **Display: Plus Jakarta Sans** (600/700/800). Designed by Gumpita Rahayu (Tokotype), originally commissioned for the Jakarta Provincial Government's "+Jakarta City of Collaboration" identity in 2020, which suits a modern Indonesian government feel. Tabular figures were added in v2.600 (November 2021).
- **UI/body/tables: Inter** (400/500/600). The INA Digital Design System names Inter as its main font, chosen because it is clean, simple and legible across devices. It has excellent `tnum`.
- Self-host both as WOFF2 subsets (latin + latin-ext) in `public/fonts`, using `font-display: swap` and preloading only Inter 400 and Jakarta 700. Do not hot-link Google Fonts. This saves DNS and TLS round-trips on shared hosting.
- Numbers: add `font-variant-numeric: tabular-nums` (`tabular-nums` utility) to all tables, stat cards, SPT numbers, dates and chart ticks.

| Token | Size/Line | Weight | Font | Use |
|---|---|---|---|---|
| display-xl | 48/56 (mobile 32/40) | 800 | Jakarta | hero title |
| display-lg | 36/44 (mobile 28/36) | 700 | Jakarta | landing section titles |
| h1 | 28/36 (mobile 24/32) | 700 | Jakarta | page title |
| h2 | 22/30 | 700 | Jakarta | card/section title |
| h3 | 18/26 | 600 | Inter | sub-section |
| stat | 40/44 (mobile 32/36) | 700 | Jakarta tnum | KPI numbers |
| body | 16/24 | 400 | Inter | default (public) |
| body-sm | 14/20 | 400 | Inter | dashboard default, table cells |
| caption | 12/16 | 500 | Inter | badges, helper text, timestamps |
| overline | 12/16, tracking 0.08em, uppercase | 600 | Inter | eyebrow labels |

The minimum text size is 12px, and only for badges and captions. The minimum for form inputs is 16px on mobile, which prevents iOS zoom.

## 5. Spacing, radius, elevation, z-index, breakpoints
- **Spacing:** 4px base, using the Tailwind default scale. Card padding is `p-5` desktop / `p-4` mobile. Section rhythm on public pages is `py-16 lg:py-24`. The dashboard content gutter is `px-4 md:px-6 lg:px-8`.
- **Radius:** `--radius-sm` 4px (flags, inputs inside tables), `--radius-md` 8px (buttons, inputs), `--radius-lg` 12px (cards, modals), `--radius-xl` 20px (hero media, landing cards), `rounded-full` (status badges, avatars only).
- **Shadows** are mint-ink tinted, never pure black:
  - `--shadow-xs: 0 1px 2px rgb(8 43 35 / .06)`
  - `--shadow-sm: 0 1px 3px rgb(8 43 35 / .08), 0 1px 2px rgb(8 43 35 / .04)`
  - `--shadow-md: 0 6px 16px -4px rgb(8 43 35 / .12)`
  - `--shadow-lg: 0 16px 40px -8px rgb(8 43 35 / .18)`
- **Elevation levels:** 0 (page), 1 (cards: border neutral-200 + shadow-xs), 2 (dropdowns/popovers: shadow-md), 3 (modals/drawers: shadow-lg + scrim `rgb(8 43 35 / .55)`).
- **z-index:** base 0 · sticky table header 10 · public header 30 · sidebar 40 · dropdown/popover 50 · drawer scrim 60 · modal 70 · toast 80 · tooltip 90 · skip link 100.
- **Breakpoints:** Tailwind defaults (sm 640, md 768, lg 1024, xl 1280, 2xl 1536). Test widths are **360**, **768**, **1280** and 1536. The content max width is 1200px (public) and fluid with a 1600px cap (dashboard).

## 6. Focus, iconography, imagery
- **Focus ring (all interactive elements):** `outline: 2px solid var(--color-mint-600); outline-offset: 2px`. On dark panels use `outline-color: var(--color-accent-400)` (9.07 on mint-950). Use `:focus-visible` only. Never `outline: none` without this replacement.
- **Icons:** Heroicons via `blade-ui-kit/blade-heroicons`, rendered as inline SVG, so no icon font. Use 20px (mini) in dense UI and 24px (outline) elsewhere. Stroke icons inherit `currentColor`. Icon-only buttons need `aria-label` and a tooltip.
- **Imagery:** hero photos are admin-uploaded. Serve them as WebP at 1920 and 960 widths (`srcset`), at most 250KB, with required `alt` text set by the admin.

## 7. Motion system

### 7.1 Tokens
```css
--dur-instant: 80ms;   /* press feedback */
--dur-fast: 150ms;     /* hover, dropdown */
--dur-base: 220ms;     /* modal, drawer, toast */
--dur-slow: 400ms;     /* reveal, bar grow */
--dur-hero: 800ms;     /* carousel crossfade */
--dur-kenburns: 9000ms;
--ease-standard: cubic-bezier(.2,0,0,1);
--ease-enter: cubic-bezier(0,0,.2,1);
--ease-exit: cubic-bezier(.4,0,1,1);
--ease-emphasized: cubic-bezier(.3,1.3,.5,1); /* tiny overshoot: badge pop only */
```

### 7.2 Named animations
| Name | Spec | Where |
|---|---|---|
| `hero-crossfade` | opacity 0→1, `--dur-hero`, `--ease-standard`; the outgoing slide fades out simultaneously | landing hero |
| `ken-burns` | `transform: scale(1)→scale(1.06)` + translate(−1%,−1%) over `--dur-kenburns`, linear, on the active slide's `<img>` only | landing hero |
| `fade-up` | from `opacity:0; translateY(16px)` to rest, `--dur-slow`, `--ease-enter`; triggered by `x-intersect.once.threshold.20`; stagger 60ms per child, max 5 children (cap 300ms) | landing sections, public stat cards |
| `count-up` | rAF from 0 to the value over 1200ms, ease-out-cubic, formatted `id-ID`; starts on intersect, once | landing stats, public KPI |
| `chart-draw` | Chart.js `animation: {duration: 600, easing: 'easeOutQuart'}`, first render only; filter updates use `duration: 200` | public charts, Pimpinan chart |
| `bar-grow` | `transform: scaleX(0)→1`, `transform-origin: left`, `--dur-slow`; stagger 15ms per row, total ≤600ms, once | Jadwal timeline |
| `card-lift` | hover `translateY(-2px)` + `shadow-sm→shadow-md`, `--dur-fast` | clickable landing/link cards only |
| `sidebar-collapse` | `width 264px↔72px`, `--dur-base`; labels fade `--dur-fast` | dashboard shell |
| `bell-ring` | keyframes rotate 0→14°→−12°→8°→−4°→0 over 600ms, origin top center, **once per new notification** (never looping) + count badge `scale .6→1` with `--ease-emphasized` | header bell |
| `toast-in` | from `translateX(16px)` (desktop) / `translateY(16px)` (mobile) + opacity, `--dur-base`; exit `--dur-fast` | all toasts |
| `shimmer` | linear-gradient sweep neutral-100→neutral-50→neutral-100, 1.4s infinite | skeletons (only while loading) |
| `modal` | scrim opacity 0→1 `--dur-base`; panel `scale(.97)+opacity` →1; exit `--dur-fast` | modals |
| `drawer` | `translateX(100%)→0` `--dur-base` | detail drawers, mobile nav |
| `nav-progress` | Livewire `wire:navigate` progress bar (appears only when a page takes longer than 150ms to load), color mint-600, height 3px | dashboard |

Dashboards use `wire:navigate` for SPA-like navigation. The sidebar is kept across pages with `@persist('sidebar')`. **No page fade transitions** in dashboards. The public site may use full page loads.

### 7.3 Alpine recipes
```html
<!-- fade-up group -->
<div x-data="{shown:false}" x-intersect.once.threshold.20="shown=true">
  <template x-for="(c,i) in 4">
    <div :style="`transition-delay:${i*60}ms`"
         :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
         class="transition duration-[400ms] ease-[cubic-bezier(0,0,.2,1)] motion-reduce:transition-none motion-reduce:opacity-100 motion-reduce:translate-y-0">…</div>
  </template>
</div>
```
```js
// count-up (resources/js/countup.js)
export default (target) => ({
  value: 0,
  start() {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) { this.value = target; return; }
    const t0 = performance.now(), d = 1200;
    const step = (t) => { const p = Math.min((t - t0) / d, 1);
      this.value = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(step); };
    requestAnimationFrame(step);
  },
  get formatted() { return this.value.toLocaleString('id-ID'); }
});
```
Render the final number server-side inside an `sr-only` span. Put `aria-hidden="true"` on the animated span so screen readers read the real value once.

### 7.4 Reduced motion (exact behavior)
```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important; animation-iteration-count: 1 !important;
    transition-duration: .01ms !important; scroll-behavior: auto !important; }
  .ken-burns { transform: none !important; }
}
```
```js
const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
if (reduce) { Chart.defaults.animation = false; }
```
Under reduced motion:
- Carousel: no auto-advance. Manual slide change is instant. Ken Burns is off.
- Count-up shows the final value immediately.
- Reveals are visible immediately.
- Bell: the badge appears without ring or pop.
- Shimmer becomes a static neutral-100 block.
- Toasts appear without sliding.

### 7.5 Where motion is prohibited
- **Data-entry forms:** no field reveals or animated validation shakes. Errors appear instantly.
- **Dense tables:** Bagian Umum inbox, Register/stambook, Pimpinan monitoring table, admin master-data tables, activity log. No row stagger, no hover lift, and sorting and filtering re-render instantly. A `wire:loading` opacity of 60% on the table body is the only feedback.
- No parallax or scroll-jacking anywhere, and no looping ambient animation apart from the paused-able carousel.
- Performance budget: animate only `transform` and `opacity`. No `filter: blur` animations, which are expensive on low-end Android. No more than 2 simultaneous running animations outside the hero.

### 7.6 Motion library (`motion`, vanilla API) — restricted use
`motion` is an optional enhancement layer for **public pages only**. Alpine + CSS (§7.2–7.3) stays the default and remains the fallback: every page must look correct and fully usable if `motion` fails to load.

**Allowed**
| Where | What `motion` may drive |
|---|---|
| `design/public/landing.md` | hero content entrance sequence (overline → title → quote → controls), stat-card stagger + count-up, link-card reveal |
| `design/public/jadwal.md` | timeline `bar-grow` stagger on first render |
| `design/public/realisasi.md` | chart-card reveal on first scroll (Chart.js still draws its own series) |

**Prohibited**
- Any page under `design/dashboard/*` or `design/auth/*`, forms, tables, modals, toasts, dropdowns, the bell. These keep Alpine `x-transition` + CSS only.
- Hover/press micro-interactions (CSS handles them).
- The carousel crossfade and Ken Burns (CSS; they must stay pausable and work without JS animation).
- Layout animations, `AnimatePresence`, `motion.div`, or anything React-only.

**Rules**
1. Import only from the package root: `import { animate, inView, stagger } from "motion"`.
2. Never call `animate()` directly in page code. Use the wrapper `resources/js/motion.js` (below). Elements opt in with `data-motion="…"` attributes.
3. Durations and easings come from the tokens in §7.1 (the wrapper reads them from CSS variables). No hard-coded values in Blade.
4. Reduced motion: the wrapper sets every element to its final state and returns immediately when `prefers-reduced-motion: reduce` is active.
5. Hidden initial state is applied **only by JS** (`html.motion-ready [data-motion]`), so content is visible if JS fails.
6. Re-initialize on `livewire:navigated`. Mark each element as done (`data-motion-done`) to prevent double runs. Wrap animated regions that Livewire re-renders in `wire:ignore`, or keep them out of Livewire components.
7. Load `motion` only in the public Vite entry (`resources/js/public.js`), never in `resources/js/app.js` (dashboard).

**Wrapper (`resources/js/motion.js`)**
```js
import { animate, inView, stagger } from "motion";

const reduce = () => matchMedia("(prefers-reduced-motion: reduce)").matches;
const css = (name, fallback) =>
  getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
const ms = (v) => parseFloat(v) / 1000; // "400ms" -> 0.4 (motion uses seconds)
const bezier = (v) => v.match(/[\d.-]+/g).map(Number); // "cubic-bezier(0,0,.2,1)" -> [0,0,.2,1]

const T = () => ({
  slow: ms(css("--dur-slow", "400ms")),
  enter: bezier(css("--ease-enter", "cubic-bezier(0,0,.2,1)")),
});

const presets = {
  // children of [data-motion="stagger"] fade up, 60ms apart, max 5 staggered
  stagger(el) {
    const kids = [...el.children];
    return animate(kids, { opacity: [0, 1], y: [16, 0] },
      { duration: T().slow, ease: T().enter, delay: stagger(0.06, { startDelay: 0 }) });
  },
  "fade-up"(el) {
    return animate(el, { opacity: [0, 1], y: [16, 0] }, { duration: T().slow, ease: T().enter });
  },
  // [data-motion="bars"]: each [data-bar] scales in from the left, total ≤600ms
  bars(el) {
    const bars = [...el.querySelectorAll("[data-bar]")];
    const step = Math.min(0.015, 0.2 / Math.max(bars.length, 1));
    return animate(bars, { scaleX: [0, 1] },
      { duration: T().slow, ease: T().enter, delay: stagger(step) });
  },
  // hero content sequence
  hero(el) {
    const parts = [...el.querySelectorAll("[data-hero-part]")];
    return animate(parts, { opacity: [0, 1], y: [24, 0] },
      { type: "spring", bounce: 0, duration: 0.7, delay: stagger(0.08) });
  },
};

function finish(el) {
  el.dataset.motionDone = "";
  el.style.opacity = "";
  el.style.transform = "";
}

export function initMotion(root = document) {
  document.documentElement.classList.add("motion-ready");
  root.querySelectorAll("[data-motion]:not([data-motion-done])").forEach((el) => {
    const preset = presets[el.dataset.motion];
    if (!preset || reduce()) { finish(el); return; }
    inView(el, () => {
      el.dataset.motionDone = "";
      preset(el);
    }, { amount: 0.2 });
  });
}
```

**CSS for the JS-only hidden state** (add to `resources/css/app.css`)
```css
html.motion-ready [data-motion="stagger"]:not([data-motion-done]) > *,
html.motion-ready [data-motion="fade-up"]:not([data-motion-done]),
html.motion-ready [data-motion="hero"]:not([data-motion-done]) [data-hero-part] { opacity: 0; }
html.motion-ready [data-motion="bars"]:not([data-motion-done]) [data-bar] { transform: scaleX(0); transform-origin: left; }
@media (prefers-reduced-motion: reduce) {
  html.motion-ready [data-motion] *, html.motion-ready [data-motion] { opacity: 1 !important; transform: none !important; }
}
```

**Public entry (`resources/js/public.js`)**
```js
import "./bootstrap";
import { initMotion } from "./motion";

document.addEventListener("DOMContentLoaded", () => initMotion());
document.addEventListener("livewire:navigated", () => initMotion());
```

**Blade usage**
```blade
<section data-motion="stagger" class="grid gap-4 lg:grid-cols-3">
  <x-ui.stat-card … /> <x-ui.stat-card … /> <x-ui.stat-card … />
</section>

<div data-motion="hero">
  <p data-hero-part class="overline text-accent-400">INSPEKTORAT DAERAH</p>
  <h1 data-hero-part class="…">…</h1>
  <blockquote data-hero-part>…</blockquote>
</div>
```
The count-up for stat numbers stays the Alpine recipe in §7.3; `motion` only handles the card entrance.

**Acceptance checks before merging any `motion` change**
- Page renders fully with JS disabled (no element stuck at opacity 0).
- With OS "reduce motion" on, nothing moves and all content is visible immediately.
- Navigate public pages back and forth 5× via `wire:navigate`: no element stuck hidden, no animation replaying on already-seen content.
- `npm run build`: the dashboard bundle (`app.js`) does not contain `motion`.

## 8. Component library (Blade)
All components listed here are required. Variants and states are mandatory; nothing is optional.

| Component | Variants | States / rules |
|---|---|---|
| `x-ui.button` | `primary` (mint-700 bg, white text), `secondary` (white bg, mint-700 text, border border-strong), `ghost` (transparent, mint-700 text, hover mint-50), `danger` (danger-700), `brand-soft` (mint-300 bg, mint-950 text; dark panels only); sizes sm 32px / md 40px / lg 48px (touch default on mobile: md ≥ 44px hit area) | hover: primary → mint-800; active: translateY(1px) `--dur-instant`; focus-visible ring; disabled: neutral-100 bg + neutral-400 text + `aria-disabled`; loading: spinner replaces leading icon, label stays ("Menyimpan…"), `wire:loading.attr="disabled"` |
| `x-ui.input`, `x-ui.textarea`, `x-ui.select` | default, with-prefix/suffix, error | label above (14px/500 neutral-900); helper (12px neutral-500); border border-strong; focus: border mint-700 + ring; error: border danger-700 + `exclamation-circle` icon + message linked with `aria-describedby`; required: "*" + sr-only "(wajib)" |
| `x-ui.combobox` | single, multi | Alpine listbox pattern (`role="combobox"`, `aria-expanded`, `aria-activedescendant`), search debounced 250ms via Livewire; "Tidak ditemukan" empty item |
| `x-ui.checkbox`, `x-ui.radio`, `x-ui.switch` | — | 20px box, border border-strong, checked mint-700 fill + white check |
| `x-ui.date-range` | — | two native `type="date"` inputs (reliable on Android) + computed duration text "Durasi: 5 hari" (inclusive) |
| `x-ui.file-upload` | PDF only | dropzone (dashed border-strong, mint-50 on drag-over), shows filename, size, "Ganti"/"Hapus"; max size stated in helper |
| `x-ui.card` | default, interactive (link), mint (mint-50 bg) | radius-lg, border neutral-200, shadow-xs |
| `x-ui.stat-card` | default, alert (left 3px danger-700 bar), link | label (caption, neutral-600), value (stat, tnum), delta/subtext, optional icon tile (mint-100 bg, mint-800 icon) |
| `x-app.status-badge` | 9 statuses (§3.6) | always icon + text; `title` not required |
| `x-app.flag` | 4 flags | rectangle shape |
| `x-app.type-chip` | assignment type | 10px color dot (or 4px left bar) + text in neutral-900; never colored text |
| `x-ui.table` | comfortable (48px rows), compact (36px rows) | sticky header neutral-50; row hover neutral-50; selected mint-50 + left 3px mint-700; numbers right-aligned tnum; sortable headers are buttons with `aria-sort`; container `overflow-x-auto` with a visible right fade-edge hint |
| `x-ui.pagination` | — | "Menampilkan 1–25 dari 312"; per-page select 25/50/100 |
| `x-ui.tabs`, `x-ui.segmented` | — | segmented used for view toggles (Timeline/Bulanan); `aria-pressed` |
| `x-ui.modal`, `x-ui.drawer` | sm 400 / md 560 / lg 720 | `x-trap.noscroll`, Esc closes, focus returns to trigger, title `aria-labelledby` |
| `x-ui.toast` | success, error, info, warning | `role="status"` (error: `role="alert"`); success auto-dismiss 5s, errors persist until closed |
| `x-ui.alert` | info, warning, danger, success | inline banner with icon + title + body |
| `x-ui.empty-state` | — | 48px line icon in mint-600, title, one sentence, primary action |
| `x-ui.skeleton` | line, card, table-row, chart | shimmer |
| `x-ui.dropdown`, `x-ui.popover`, `x-ui.tooltip` | — | open on click (tooltip: hover + focus), dismiss on Esc/outside |
| `x-ui.progress` | bar | mint-700 fill on neutral-100, label "3 dari 4 SPT selesai" always visible as text |
| `x-app.chart-card` | line, donut, bar | title, filter summary, canvas `role="img"` + `aria-label` summary, "Lihat sebagai tabel" toggle revealing a real `<table>` (sr-only by default) |
| `x-app.filter-bar` | — | see `design/dashboard/_shared.md` |
| `x-app.export-buttons` | — | see `design/dashboard/_shared.md` |
| `x-app.diinput-oleh` | — | see `design/auth/login.md` §5 |

## 9. Chart theming (Chart.js)
```js
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size = 12;
Chart.defaults.color = '#4B5955';                 // neutral-600 ticks/labels (7.34)
Chart.defaults.borderColor = '#E6ECEA';           // grid neutral-100
Chart.defaults.plugins.tooltip.backgroundColor = '#151C1A';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.locale = 'id-ID';
```
- **Line (completed SPT per month):** a single series uses mint-700, width 2.5, points 3px. With multiple irban series, each series uses a different `pointStyle` (circle, rect, triangle, rectRot, crossRot) **and** `borderDash` ([], [6,4], [2,3], [10,4,2,4], [4,4]) in addition to color.
- **Irban series colors:** I #145C4A, II #C77C00, III #6A3D9A, IV #CC6677, V #146C8F. A chart encodes either irban or assignment type, never both, and always has a text legend.
- **Donut:** cutout 62%, `borderWidth: 2, borderColor: '#fff'` (separates adjacent slices). At most 6 slices plus "Lainnya" (#5A6B7B). Slices are sorted descending. The center label shows the total ("128 SPT"). The legend lists value and percentage as text.
- **Bar (realisasi vs rencana PKPT per irban per TW):** Rencana is drawn as an outline bar (fill neutral-100, border #7D8B87, 1.5px). Realisasi is solid mint-700. A value label sits above each bar. Grouped by irban, with TW chosen by filter or as small multiples on desktop.
- Y-axis starts at 0 with integer ticks (`precision: 0`). Tooltips show "Irban II · TW III: 12 dari 15 (80%)".
- Every chart has a table twin (USWDS pattern). The table always exists in the DOM.

## 10. Accessibility rules (non-negotiable)
- WCAG 2.1 AA. Use only the pairs listed in §3.4.
- Provide a skip link "Lewati ke konten utama". Use landmarks (`header`, `nav`, `main`, `footer`) and one `h1` per page.
- Everything is keyboard operable, and tab order matches visual order. Popovers, modals and drawers trap and return focus.
- Every form field has a `<label>`. Error summary at the top on submit failure ("Periksa 2 isian berikut"), with links to the fields.
- Touch targets are at least 44×44px on mobile and 24×24px on dense desktop UI.
- `lang="id"` on `<html>`.
- The carousel follows WCAG SC 2.2.2 Pause, Stop, Hide (Level A): motion that starts automatically, lasts more than five seconds and appears alongside other content must be pausable. Single-key shortcuts must be turn-off-able (2.1.4).
- Charts: `role="img"` + summary + table.
- Text must survive 200% zoom and 320px reflow.

## 11. Responsive rules
- Mobile-first. At 360px, no horizontal page scroll is allowed. Wide tables either become cards (public and role tables listed per file) or scroll inside their own `overflow-x-auto` container with the first column sticky.
- Filter bars collapse into a "Filter (3)" button that opens a bottom drawer below `md`.
- Multi-column grids: 1 col (<640), 2 col (≥640), 3–4 col (≥1024).

## 12. Dark mode (optional; class strategy `.dark` on `<html>`, user toggle + `prefers-color-scheme`)
| Pair | Ratio |
|---|---|
| neutral-50 #F1F5F4 text on neutral-950 #0B1110 bg | 17.34 |
| neutral-300 #B8C4C0 (secondary) on neutral-950 | 10.62 |
| neutral-400 #8A9894 (muted) on neutral-950 | 6.35 |
| mint-300 (links) on neutral-950 | 12.34 |
| neutral-50 on surface neutral-900 #151C1A | 15.75 |
| neutral-400 on neutral-900 | 5.77 |
| mint-300 on neutral-900 | 11.21 |
| primary button: mint-950 text on mint-300 bg | 9.85 |
| focus ring mint-400 on neutral-900 | 8.71 |

In dark mode, status badges switch to an outline style: transparent bg, text and border at the 300-level of each hue. Selesai stays solid #1D6B34 with white text (6.55). Verify these with a contrast checker before shipping; dark mode is out of the MVP scope.

## 13. Tailwind v4 theme (CSS-first; for v3 move the same values to `theme.extend`)
```css
@import "tailwindcss";
@plugin "@tailwindcss/forms";
@theme {
  --font-sans: "Inter", ui-sans-serif, system-ui, sans-serif;
  --font-display: "Plus Jakarta Sans", "Inter", sans-serif;
  --color-mint-50:#F0FBF7; --color-mint-100:#DDF6EC; --color-mint-200:#BCEDD9;
  --color-mint-300:#8FE0C0; --color-mint-400:#5ECBA5; --color-mint-500:#34B08A;
  --color-mint-600:#1E8F70; --color-mint-700:#17735B; --color-mint-800:#145C4A;
  --color-mint-900:#124C3E; --color-mint-950:#082B23;
  --color-neutral-25:#F7FAF9; --color-neutral-50:#F1F5F4; --color-neutral-100:#E6ECEA;
  --color-neutral-200:#D5DEDB; --color-neutral-300:#B8C4C0; --color-neutral-400:#8A9894;
  --color-neutral-500:#64736F; --color-neutral-600:#4B5955; --color-neutral-700:#37433F;
  --color-neutral-800:#242E2B; --color-neutral-900:#151C1A; --color-neutral-950:#0B1110;
  --color-border-strong:#7D8B87;
  --color-accent-400:#F2C14E; --color-accent-700:#8A5A00;
  --color-danger-700:#B91C1C; --color-info-800:#1E40AF; --color-success-700:#1D6B34;
  --color-cat-1:#332288; --color-cat-2:#C77C00; --color-cat-3:#3F6FA6; --color-cat-4:#CC6677;
  --color-cat-5:#146C8F; --color-cat-6:#A68A1F; --color-cat-7:#AA4499; --color-cat-8:#B84E00;
  --color-cat-9:#8A8A2E; --color-cat-10:#882255; --color-cat-11:#6A3D9A; --color-cat-12:#7A4E2D;
  --color-cat-other:#5A6B7B;
  --radius-sm:4px; --radius-md:8px; --radius-lg:12px; --radius-xl:20px;
  --shadow-xs:0 1px 2px rgb(8 43 35 / .06);
  --shadow-sm:0 1px 3px rgb(8 43 35 / .08),0 1px 2px rgb(8 43 35 / .04);
  --shadow-md:0 6px 16px -4px rgb(8 43 35 / .12);
  --shadow-lg:0 16px 40px -8px rgb(8 43 35 / .18);
  --ease-standard:cubic-bezier(.2,0,0,1); --ease-enter:cubic-bezier(0,0,.2,1);
  --ease-exit:cubic-bezier(.4,0,1,1);
  --animate-shimmer: shimmer 1.4s linear infinite;
  --animate-bell: bell 600ms ease-in-out 1;
}
@keyframes shimmer { from{background-position:-200% 0} to{background-position:200% 0} }
@keyframes bell { 0%,100%{rotate:0} 20%{rotate:14deg} 40%{rotate:-12deg} 60%{rotate:8deg} 80%{rotate:-4deg} }
:root { color-scheme: light; }
body { @apply bg-neutral-25 text-neutral-900 font-sans antialiased; }
```
Assignment-type colors stored in the database are applied via inline CSS variables (`style="--type: #3F6FA6; --type-fg: #fff"`) and used as `bg-[var(--type)] text-[var(--type-fg)]`. Never interpolate hex values into Tailwind class names.

## 14. Do's and Don'ts
**Do**
- Use mint-700/800 for anything clickable or textual.
- Put light mint behind dark text.
- Add the 80% mint-950 scrim behind hero text.
- Give every status an icon and a label.
- Use `tabular-nums` for all numbers.
- Provide a table alternative for every chart.
- Use skeletons for loads over 300ms.
- Use Indonesian sentence-case labels ("Simpan draf", not "SIMPAN DRAF").

**Don't**
- White text on signature mint.
- Mint or green as an assignment-type color.
- Gold as text on light backgrounds.
- Animating tables or forms.
- Looping bell animations.
- Auto-advancing the carousel without a pause button.
- Install React or any shadcn code.
- Import from `motion/react`, or use `motion` outside public pages or outside the `resources/js/motion.js` wrapper (§7.6).
- Mix `motion` and Alpine `x-transition` on the same element.
- Use more than one accent.
- Use gradients on buttons.
- Show personnel names, NIP or SPT numbers on public pages.

## 15. Reference board — patterns borrowed and why
| Reference | Pattern borrowed |
|---|---|
| GOV.UK Design System (Summary list, Table, Tag, Notification banner, Error summary) | `<dl>` key–value layout for the SPT detail page; error summary with anchor links; plain status tags; high-visibility focus treatment |
| U.S. Web Design System, *Data visualizations* | screen-reader data table with `usa-sr-only`-style hiding next to every chart |
| INA Digital Design System (Indonesia's GovTech, Peruri) | Inter as the government UI font; Indonesian component vocabulary; Figma kit structure (Landasan → Komponen → Pola Desain incl. Dashboard, Card Data) |
| BPKP SIMA-NG "Monsima" dashboard and BPKP Sumbar reporting (realisasi penugasan vs target sesuai PKPT) | phrasing and framing of realization-vs-PKPT KPIs |
| Jakarta BPKD "Transparansi Realisasi" dashboard | plain, list-first public transparency pages |
| Radix Colors (12-step roles; Mint is one of the scales designed for dark foreground text at steps 9–10) | step-role discipline (1–2 bg, 3–5 component bg, 6–8 borders, 9–10 solids, 11–12 text); confirms light mint needs dark text |
| Tailwind teal/emerald scales | calibration for the custom mint ramp |
| Okabe-Ito and rcartocolor "Safe" palettes; Harvard digital accessibility data-viz guidance | colorblind-safe categorical base; white separators between slices; 4.5:1 for chart text |
| Figma Community: "Gantt Wizard" (customizable calendar, week/month grid), "Project timeline (Gantt chart)" (color-coded task blocks + status toggles), "Radix Color Scales" library, "Mint" free admin dashboard kit, DashboardX, Themesberg Figma Admin Dashboard (Tailwind-based) | timeline grid anatomy; mint admin density; Tailwind-mapped component inventory |
| 21st.dev: Hero Carousel (Crafter UI), Number Ticker, Vercel Notification Popover (popover on desktop, bottom sheet on mobile, tabs), 21st.dev blog posts on carousel accessibility, notifications and popovers | visual and interaction references only, re-implemented in Alpine (see page files) |
| Alpine Intersect docs; Livewire `wire:navigate` / `@persist` docs | reveal triggers; SPA navigation with a persisted sidebar and a progress bar after 150ms |

## 16. Risks and trade-offs
- **Mint is weak as an interactive color.** Below step 700 it fails text contrast, so the "mint" the public sees on buttons is a deep jade. Light mint shows up in surfaces and the hero instead. Communicate this to stakeholders who expect "light mint buttons".
- **Selesai vs brand.** Even as a solid leaf green, Selesai sits in the green family. Distinction relies on the solid pill shape, the check icon and the label. Do not soften this rule.
- **The 12 categorical colors** are not all pairwise CVD-safe. Labels are mandatory.
- **Contrast values** were calculated by hand with the WCAG formula. Add an automated check (e.g., a Pest test running a contrast function over the token table) before release.
- **Logo hex values are unverified.** Sample them from the official file.

## 17. Caveats
- **Logo colors** come from the official regency description of the emblem (cokelat, kuning, putih, biru, hitam). The large shield's field color and exact hex values could not be verified online. Treat `accent-400` #F2C14E as provisional until it is sampled from the official vector file.
- **Do not use** `inspektorat.muaraenimkab.go.id` as a reference. Search results show compromised storage pages carrying gambling spam on that subdomain, which is also a reputational risk the new app should not inherit or link to.
- **Contrast ratios** were computed by hand from the WCAG 2.1 luminance formula. Verify them with an automated contrast test before release, especially the dark-mode table, which is marked optional.
- **21st.dev components** are React/shadcn-based. This pack describes behavior to re-implement in Alpine; copying their code would pull in React and violate the stack constraints.

## 18. Sources
- Lambang Daerah Kabupaten Muara Enim: https://muaraenimkab.edata.id/web/kontent/9/lambang_daerah · https://www.muaraenimkab.go.id/web/kontent/9/
- Perbup Muara Enim 35/2024: https://jdih.muaraenimkab.go.id/upload/download/ab1d67d216dc965629ec8b43efa566f8_Perbub%2035%20Th%202024.pdf
- INA Digital Design System: https://design.inadigital.go.id/getting-started/guide/ · https://design.inadigital.go.id/getting-started/overview/
- GOV.UK Summary list: https://design-system.service.gov.uk/components/summary-list/
- USWDS Data visualizations: https://designsystem.digital.gov/components/data-visualizations/
- BPKP Sumbar: https://www.bpkp.go.id/sumbar/konten/1742/Ringkasan-Informasi-Kerja.bpkp · BPKP Monsima: https://sima-ng.bpkp.go.id/monsima/dashboard
- Jakarta BPKD Transparansi Realisasi: https://dashboard-bpkd.jakarta.go.id/transparansi/?type=Realisasi
- Tailwind colors: https://tailwindcss.com/docs/colors
- Harvard data-viz accessibility: https://accessibility.huit.harvard.edu/data-viz-charts-graphs
- Colorblind-friendly palettes: https://scifigure.org/colorblind-friendly-palette · https://emitanaka.org/blog/2022-02-20-color-considerations/color-considerations.html
- Figma: https://www.figma.com/community/file/1254824512633963661/radix-color-scales · https://www.figma.com/community/file/1309975729854846509/gantt-wizard · https://www.figma.com/community/file/1195850074518926318/project-timeline-gantt-chart · https://www.figma.com/community/file/1244293267600418871/free-admin-dashboard-ui-kit · https://github.com/themesberg/figma-admin-dashboard-template
- 21st.dev: https://21st.dev/@crafterui/components/hero-carousel · https://21st.dev/@patrick-xin/components/vercel-notification-popover · https://21st.dev/blog/react-carousel-components · https://21st.dev/blog/react-toast-notification-components
- Alpine Intersect: https://alpinejs.dev/plugins/intersect · Livewire Navigate: https://livewire.laravel.com/docs/4.x/navigate
