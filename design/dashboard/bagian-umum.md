# Dashboard: Bagian Umum

> Read `DESIGN.md` and `design/dashboard/_shared.md` first.

**Purpose:** process number requests quickly and accurately all day. Optimize for density, keyboard flow and zero waiting. **No decorative motion on any screen in this role.**

## Dashboard (minimal)
Two stat cards — "Menunggu Keputusan" (link to inbox) and "Nomor Terbit Bulan Ini" — plus "5 pengajuan terlama" (compact list with age "3 hari"). No charts, no count-up.

## Inbox ("Kotak Masuk Pengajuan")
- **Layout ≥1280:** master–detail split.
  - **List (40%):** compact rows (44px) showing status badge, irban, type, requested date and age.
  - **Detail pane (60%):** summary list, personnel, PKPT link, history, comments.
- **Behavior:** selecting a row loads the detail without page navigation (Livewire). At 768–1279 the list is full width and the detail opens as a right drawer. At 360 it is a list of cards and the detail is a full page.
- **Filters:** Status (default "Menunggu Nomor"), Irban, date. Counts appear in the status tabs: "Menunggu (12) · Perlu Revisi (3) · Diterima · Ditolak".
- **Keyboard** (toggleable in "Pintasan keyboard" settings, on by default, as WCAG 2.1.4 requires; listed in a "?" help modal):
  - ↑/↓ or J/K move between rows
  - Enter focuses the detail
  - T = Terima, R = Perlu Revisi, X = Tolak (only when the list or detail has focus, never inside text fields)
  - Esc returns to the list
- **Actions** (sticky at the bottom of the detail pane):
  - **Terima** (primary): opens a modal with the **number preview** in large type — "700/752/INSPEKTORAT-II/SPT/2026" (Inter 24/700 tnum, mint-50 box with border mint-200) — with "Nomor berikutnya dalam register 2026" and buttons "Batal" / "Terbitkan Nomor". Enter confirms. On success, a toast "Nomor 700/752/… diterbitkan" and the list auto-advances to the next request.
  - **Perlu Revisi** (secondary) and **Tolak** (danger): modal with a required "Catatan" textarea (min 10 characters, counter) and helper "Catatan akan dikirim ke Irban II".
- **Catat Nomor Manual** (page action, for urgent SPT): form with nomor (validated pattern + uniqueness), tanggal, irban, perihal and alasan. A warning alert reminds "Gunakan hanya untuk SPT mendesak."
- **Batalkan Nomor:** a danger modal requiring "Alasan pembatalan". The number stays in the register as Dibatalkan.

## Register (digital stambook)
A compact table for the selected year with columns No. Urut (tnum, right-aligned), Nomor SPT, Tanggal, Irban, Perihal/Jenis, Status, Keterangan. Sticky header, search by number, filter by status/irban/month, export Excel/PDF, 100 rows per page. Cancelled rows show the Dibatalkan badge; the text is never struck through.

## States
- Inbox empty: "Semua pengajuan sudah diproses."
- Concurrent conflict (another user processed the request): alert "Pengajuan ini sudah diproses oleh pengguna lain." + refresh.
- Loading: dims only the detail pane; the list remains usable.

## Motion
Only the toast and the drawer (≤220ms, none under reduced motion). No row hover lift, no skeleton shimmer longer than needed, and no stagger.

## Accessibility
The list is a `role="listbox"`, or a table with roving tabindex. The selected row is announced. Shortcut keys are documented and can be disabled.

## Copy
"Kotak Masuk Pengajuan", "Menunggu Keputusan", "Nomor Terbit Bulan Ini", "Terima", "Terbitkan Nomor", "Perlu Revisi", "Tolak", "Catatan", "Catat Nomor Manual", "Batalkan Nomor", "Alasan pembatalan", "Register Nomor SPT", "Pintasan keyboard".
