# Jadwal PKPT (Public Schedule)

> Read `DESIGN.md` first. Header and footer are defined in `design/public/landing.md`.

**Purpose:** a layperson understands "who does what, when" within seconds. Only Disahkan PKPT is shown.

**Hierarchy:** page title + year/irban filters + view toggle → legend → timeline or monthly view → area panel per irban.

## Top bar
- h1 "Jadwal PKPT 2026" and subtitle "Program Kerja Pengawasan Tahunan yang telah disahkan".
- Filters: Tahun (select), Irban (select: "Semua Irban", "Irban I"…"Irban V").
- `x-ui.segmented` toggle "Linimasa | Bulanan" (icons `chart-bar-square`, `calendar`). Defaults: Linimasa ≥1024, Bulanan <1024. The choice is persisted in the query `?tampilan=`.
- Legend: a horizontal wrap of `x-app.type-chip` for the assignment types present. Clicking a chip toggles highlight, dimming other bars to 25% opacity; it is a toggle button with `aria-pressed`.

## Timeline view (Gantt)
Reference: Figma "Gantt Wizard" week/month grid; "Project timeline (Gantt chart)" color blocks.

- **Grid:** `grid-template-columns: 240px repeat(60, minmax(16px, 1fr))`, i.e. 12 months × Minggu 1–5. Header row 1 shows months ("Jan"…"Des", 12/600). Header row 2 shows week numbers 1–5 (caption, neutral-500).
- **Lines:** month boundaries 1px neutral-200; week boundaries dotted neutral-100. The current month column gets a mint-50 tint. A "Hari ini" vertical line in mint-700 (2px) carries a top label chip.
- **Rows** are assignment types (sticky first column, white, `shadow-[1px_0_0] neutral-200`), with the name and item count. Overlapping items in one type stack into lanes. Row height is 36px per lane and bars are 22px, `rounded-md`, filled with `var(--type)`.
  - The label inside the bar (irban + area, 12/600, `--type-fg`) appears only when the bar is ≥96px wide. Otherwise the label goes in a tooltip and the detail popover.
  - Each bar is a `<button>` with `aria-label="Audit Kinerja, Irban II, Kecamatan Lawang Kidul, 3 Maret–21 Maret 2026"`.
- **Revised item:** the original schedule is drawn as a dashed 2px outline (`border-dashed` in `--type` at 70%) on the same lane, behind the actual bar. The detail popover states "Jadwal direvisi: semula 3–21 Februari 2026".
- **Overflow:** at 768–1023px the grid keeps `min-width: 1040px` inside `overflow-x-auto`, with the sticky first column and a gradient hint on the right edge.
- **Detail on click:** a popover anchored to the bar (desktop) or a bottom drawer (<768) shows:
  - type chip
  - Irban
  - date range "3 Maret 2026 – 21 Maret 2026"
  - area (kecamatan or OPD name)
  - progress `x-ui.progress` with "3 dari 4 SPT selesai"
  - "Tutup"

## Monthly view (mobile default)
- A vertical list of 12 month cards (`x-ui.card`). The current month is expanded and the others collapsed (accordion, `button[aria-expanded]`). The header shows "Maret 2026 · 7 penugasan".
- Inside: sections "Minggu 1"…"Minggu 5". Each item is a row with a 4px left bar in `--type`, the type name (14/600), "Irban II · Kec. Lawang Kidul" (13, neutral-600) and a date range caption. A tap opens the same detail drawer.

## Area panel per irban (below the schedule)
- h2 "Wilayah Pengawasan". Tabs Irban I–V (scrollable on mobile).
- Three columns ≥1024 (stacked on mobile): Kecamatan, Dinas/Badan, BUMD, as plain lists with counts.

## Layout per breakpoint
- **360:** filters in a "Filter" drawer button. Toggle full width. Monthly view. The legend wraps into 2 rows and collapses into "Lihat legenda (8)" beyond 2 rows.
- **768:** monthly view by default; the timeline is available with horizontal scroll.
- **1280:** timeline by default, all 60 columns visible.

## States
- **Loading** (filter change via Livewire): skeleton grid of 6 rows with shimmer; the header stays.
- **Empty:** `x-ui.empty-state` "Belum ada PKPT yang disahkan untuk tahun 2026" + "Pilih tahun lain".
- **Error:** alert danger "Jadwal gagal dimuat. Muat ulang halaman." + "Coba lagi" button.

## Motion
`bar-grow` on first render (≤600ms total) and highlight dimming at `--dur-fast`. **No** animation on filter change (instant swap), no animation in the monthly view apart from the accordion height (`x-collapse` at 150ms, or instant under reduced motion).

## Accessibility
- Provide an sr-only table equivalent (type, irban, area, start, end, status) via the "Lihat sebagai tabel" toggle.
- Bars are focusable in DOM order (row by row). The popover closes on Esc and returns focus.
- Color plus the text label identifies the type, and the legend has text.

## Copy
"Linimasa", "Bulanan", "Semua Irban", "Minggu 1", "Hari ini", "Jadwal direvisi", "3 dari 4 SPT selesai", "Wilayah Pengawasan", "Kecamatan", "Dinas/Badan", "BUMD", "Lihat sebagai tabel".
