# Dashboard: Pimpinan

> Read `DESIGN.md` and `design/dashboard/_shared.md` first.

**Purpose:** Inspektur and Sekretaris monitor everything, comment, request clarification, approve schedule revisions and export recaps. They are read-only on SPT data, so no edit controls are ever rendered.

## Hierarchy
1. Stat cards (6).
2. "Realisasi vs Rencana per Irban" chart + "Menunggu Persetujuan Anda" list.
3. Monitoring table.
4. Links to Rekap Triwulanan.

## Stat cards
On Process ("Berjalan"), Selesai, Terlambat (alert variant), Tidak Sesuai PKPT, Permintaan Penjelasan Terbuka, Revisi Jadwal Menunggu (alert variant if >0). Each links to the filtered monitoring table. Grid: 6 cols at 1280, 3 at 768, 2 at 360.

## Chart
Grouped bar per irban (Rencana outline vs Realisasi mint-700), with a TW selector and a table twin.

## Approval list
Cards for each revision request with:
- original vs proposed dates, using the dashed vs solid mini-bar visual
- alasan and requester
- buttons "Setujui" (primary) and "Tolak" (danger-outline, which opens a modal with required reason)

## Monitoring table
Replaces the old system; comfortable density, desktop-first.

- Columns: Jenis Penugasan · Periode Penugasan (PKPT) · Realisasi Periode · Kesesuaian dengan Jadwal PKPT · Status · Permasalahan/Kendala.
- **Grouping:** each PKPT item is a group row (bg neutral-50, 14/600, type chip, PKPT period, "3 SPT", expand/collapse chevron button `aria-expanded`). Nested SPT rows are indented 24px with a 2px left guide line in neutral-200 and show:
  - realization period
  - Kesesuaian: "Sesuai" as text with a `check` icon in neutral-700, or the Tidak Sesuai PKPT flag
  - status badge + Terlambat flag
  - kendala truncated to 2 lines with "Selengkapnya"
- Rows link to SPT detail. Non-PKPT SPT appear in a final group "Di luar PKPT".
- Controls: "Buka semua / Tutup semua". Filters: Tahun, TW, Irban, Status, Jenis, Kesesuaian.
- Export split button (follows filters; queued when large).
- **Mobile:** one card per PKPT item with nested mini-rows (status badge + period). The table gives a horizontal scroll option at 768.

## Rekap Triwulanan
Tabs "Per Irban | Gabungan". Sections: Rencana vs Realisasi, per Klasifikasi, per Jenis Penugasan, as compact tables with totals rows (bold, top border neutral-300) and percentages. Export.

## Commenting
On SPT detail, the comment composer has a toggle "Tandai sebagai Minta Penjelasan" (switch). These comments create Perlu Tindakan items for the irban and count in the stat card.

## States
- Approval list empty: "Tidak ada revisi jadwal yang menunggu persetujuan."
- Table empty: "Tidak ada SPT sesuai filter."
- Export in progress: toast + a notification when ready.

## Motion
Stat count-up on first load and chart draw-in. The monitoring table has none, and expand/collapse is instant.

## Accessibility
Group rows use `<tbody>` per group with the group row as a `<th scope="rowgroup">`. Approve/reject buttons include context ("Setujui revisi jadwal Audit Kinerja Irban II").

## Copy
"Berjalan", "Terlambat", "Tidak Sesuai PKPT", "Permintaan Penjelasan Terbuka", "Revisi Jadwal Menunggu", "Menunggu Persetujuan Anda", "Setujui", "Tolak", "Kesesuaian dengan Jadwal PKPT", "Permasalahan/Kendala", "Buka semua", "Rekap Triwulanan", "Gabungan".
