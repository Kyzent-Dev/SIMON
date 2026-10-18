# Landing Page (Home)

> Read `DESIGN.md` first. This file only composes tokens and components defined there.

**Purpose:** let a citizen understand within 5 seconds what the Inspektorat does and how this year's audit plan is progressing. Primary task: reach Jadwal or Realisasi.

**Information hierarchy:** 1) header · 2) hero carousel · 3) summary stats for the current year · 4) two large link cards (Jadwal, Realisasi) · 5) footer.

## Header (shared by all public pages)
- **Height and behavior:** 64px, sticky `top-0 z-30`, white with `border-b neutral-200`. On scroll beyond 8px it adds shadow-xs. Background stays solid; no backdrop blur, which costs performance.
- **Left:** logo 36px + "Inspektorat Daerah" (14/600) over "Kabupaten Muara Enim" (12, neutral-600).
- **Center:** nav Home · Jadwal · Realisasi. Active link is mint-700 with a 2px mint-700 underline offset 6px, plus `aria-current="page"`.
- **Right:** login icon button (`arrow-right-on-rectangle`, `aria-label="Masuk"`). When authenticated it becomes "Dasbor" (`squares-2x2`) and links to the role dashboard.
- **Mobile (<768):** logo + short name "Inspektorat Muara Enim", login icon and hamburger stay visible. The menu opens as a full-width panel below the header (`x-transition` opacity + translateY(-8px), `--dur-base`). Links are 48px tall. Focus is trapped and Esc closes.

## Hero carousel
Reference: 21st.dev Crafter UI "Hero Carousel" re-grades the background to the focused photo; we use a fixed mint-950 scrim instead, for guaranteed contrast.

- **Size:** `min-h-[560px]` desktop, `min-h-[480px]` tablet, `min-h-[420px]` mobile. Radius 0 (full-bleed) desktop.
- **Layers:** image (`object-cover`) → scrim `bg-gradient-to-r from-mint-950/90 via-mint-950/80 to-mint-950/40` (mobile: `bg-mint-950/80` uniform) → "Delapan Sungai" SVG motif in mint-200 at 12% opacity on the right edge → content.
- **Content** (max-w 640, left-aligned, bottom third):
  - overline in accent-400 ("INSPEKTORAT DAERAH")
  - title display-xl white
  - quote/explanation body-lg mint-200 (11.78 on mint-950)
  - optional owner "— Nama, Jabatan" in caption mint-300
  - 40×3px accent-400 rule above the overline
- **Controls** (bottom-right, 44px each, white/10 bg, white icons, focus ring accent-400): Pause/Putar (**first in tab order**, `aria-label` toggles "Jeda slide"/"Putar slide"), Sebelumnya, Berikutnya. Slide dots are `<button aria-label="Slide 2 dari 4">`, with the active one rendered as a 24px pill and the rest as 8px dots.
- **Behavior:**
  - Auto-advance every 7s, with `hero-crossfade` + `ken-burns` on the active image.
  - Pause on hover and on focus within. The explicit pause button stays paused until the user presses play.
  - Swipe: pointer events, horizontal threshold 40px, `touch-action: pan-y`.
  - Keyboard: ←/→ when the carousel has focus.
  - Semantics: `aria-roledescription="carousel"`, slides `role="group" aria-roledescription="slide" aria-label="2 dari 4"`. The live region is `aria-live="off"` while playing and `polite` when paused.
  - Preload only the first image (`fetchpriority="high"`). Others use `loading="lazy"`.
  - Single slide: no controls, no autoplay.
- **Reduced motion:** no autoplay (paused state shown), no Ken Burns, instant slide change.
- **Alpine skeleton:** `x-data="carousel({count, interval:7000})"`, holding `i`, `playing`, `next()`, `prev()`, `go(n)`, `toggle()`. A `setInterval` is cleared on pause and on `visibilitychange`.

## Summary stats
Bg white, overlapping the hero by −48px on desktop for depth.

- Three `x-ui.stat-card` items in a card band (`rounded-xl shadow-md`). Grid is 3 cols ≥1024, 1 col mobile stacked as rows with dividers.
- Cards:
  - "Item PKPT 2026" → **42**
  - "SPT Reguler Selesai" → **31**
  - "Persentase Realisasi" → **73,8%**, with an `x-ui.progress` bar beneath and the subtext "31 dari 42 item PKPT"
- Count-up on intersect (`fade-up` stagger 60ms). Caption: "Data per 18 Oktober 2026".

## Link cards
2 cols ≥768.
- **Jadwal card:** mint-50 bg with a mini timeline illustration of 4 colored bars using cat-1..4, title "Jadwal PKPT", text "Lihat siapa memeriksa apa dan kapan, sepanjang tahun.", CTA "Buka jadwal →" (mint-700).
- **Realisasi card:** white with a mini bar chart illustration, title "Realisasi Pengawasan", CTA "Lihat realisasi →".
- Both use `card-lift` on hover, and the whole card is one link.

## Footer (bg mint-950, admin-managed)
- 4 cols ≥1024 / 1 col mobile: Kontak (phone `tel:`, email `mailto:`), Instagram (handle link), Alamat Kantor, Lokasi (map embed, lazy-loaded behind a "Tampilkan peta" button to save data; static image fallback).
- Text: white headings, mint-200 body, accent-400 2px top rule.
- Bottom bar: "© 2026 Inspektorat Daerah Kabupaten Muara Enim" (neutral-300, 8.48).

## Layout per breakpoint
- **360px:** header 56px. Hero 420px, title 32/40, controls at the bottom in one row, dots centered. Stats stacked. Link cards stacked. Footer stacked.
- **768px:** hero 480px. Stats 3 cols compact (stat 32px). Cards 2 cols.
- **1280px:** as specified above. Container max 1200px.

## States
- **Loading:** stats render server-side, so there is no spinner. If the stats query fails, show "—" with caption "Data belum tersedia".
- **Empty carousel:** a static brand hero (mint-950 + motif + "Mengawal akuntabilitas pemerintahan Kabupaten Muara Enim").
- **Zero PKPT:** "Belum ada PKPT yang disahkan untuk 2026."

## Motion
Hero crossfade, Ken Burns, stats count-up, fade-up for the cards, card-lift. Not on the footer or header (apart from the shadow appearing).

## Accessibility
Admin must provide `alt` for each slide image, or mark it decorative. Quote uses `<blockquote>` + `<figcaption>`. Stat numbers have an sr-only final value. Contrast over photos is guaranteed by the scrim (≥8.12:1 worst case).

## Copy
"Masuk", "Dasbor", "Jeda slide", "Putar slide", "Slide berikutnya", "Jadwal PKPT", "Realisasi", "Item PKPT 2026", "SPT Reguler Selesai", "Persentase Realisasi", "Hubungi Kami", "Alamat Kantor".
