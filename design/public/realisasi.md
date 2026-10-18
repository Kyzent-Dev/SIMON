# Realisasi SPT (Public Realization)

> Read `DESIGN.md` first. Header and footer are defined in `design/public/landing.md`.

**Purpose:** show the public how much of the PKPT has been realized. Data covers REGULER SPT with status Selesai that are linked to a PKPT item.

**Privacy (hard rule):** never render personnel names, NIP, SPT number, objects below kecamatan level, kendala, comments or documents. Keep these out of the query (select only allowed columns), so it is not just hidden in the view.

**Hierarchy:** title → filter bar → KPI line → line chart → donut + bar → table.

## Filter bar (`x-app.filter-bar`)
- Fields: Tahun, Triwulan (TW I–IV), Bulan, Irban, Wilayah (kecamatan, searchable combobox), Jenis Penugasan.
- Every filter is bound to the URL with Livewire `#[Url]` and is shareable.
- Active filters show as removable chips ("Irban II ✕") plus "Atur ulang".
- Desktop: one row, sticky under the header (`top-16`) with a white bg and bottom border. Mobile: a "Filter (2)" button opens a bottom drawer with "Terapkan".
- Bulan options are constrained by the selected Triwulan.

## KPI line
"**31** SPT selesai dari **42** item PKPT · **73,8%** realisasi". Numbers in stat style, 28px, mint-800.

## Charts (`x-app.chart-card`)
1. **Line:** "SPT Selesai per Bulan". Full width, height 280. Optional "Per Irban" switch adds 5 lines (markers + dash patterns).
2. **Donut:** "Proporsi per Irban | per Jenis Penugasan" (segmented switch inside the card). At most 6 slices + Lainnya, with center total and a text legend that includes percentages.
3. **Bar:** "Realisasi vs Rencana PKPT per Irban". Outline bars for Rencana, solid mint-700 for Realisasi, a TW selector inside the card, and value labels.

**Grid:** line 12 cols. Donut 5 cols + bar 7 cols at ≥1024. Stacked below that.

## Table
Columns Jenis Penugasan (type chip), Irban, Wilayah, Tanggal Pelaksanaan ("3–21 Maret 2026"), Triwulan. 25 per page, sortable by date. Below 768 each row becomes a card: type chip at the top, a key–value list beneath.

## States
- **Loading:** chart skeletons (gray rectangles with a faint axis) + 5 skeleton rows. Use `wire:loading.delay` (200ms) to avoid flicker.
- **Empty** (filters too narrow): "Tidak ada realisasi untuk filter ini." + "Atur ulang filter".
- **Error:** inline alert per chart card, so one failure does not blank the page.

## Motion
`chart-draw` on first load, plus `fade-up` for the chart cards on first scroll. Filter updates use a 200ms chart transition, and the table swaps instantly.

## Accessibility
Each canvas has `aria-label` (e.g., "Grafik garis: SPT selesai per bulan 2026, tertinggi Mei dengan 6 SPT") plus a table twin. The filter drawer is focus-trapped. Chips are buttons named "Hapus filter Irban II".

## Copy
"Realisasi Pengawasan 2026", "Triwulan", "TW I", "Semua Bulan", "Wilayah", "Atur ulang", "Terapkan", "Lainnya", "Rencana", "Realisasi", "Lihat sebagai tabel", "Sembunyikan tabel".

## Performance
Aggregate in SQL (GROUP BY) with indexes on (tahun, status, klasifikasi, irban_id). Pass chart data as JSON in the initial payload; no extra XHR on load. This meets the 2s target with ~5,000 SPT.
