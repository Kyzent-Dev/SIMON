# Dashboard: Admin

> Read `DESIGN.md` and `design/dashboard/_shared.md` first.

**Purpose:** configure the system and maintain master data safely. Accuracy over speed; destructive actions are always confirmed.

## Dashboard
Health cards (Pengguna aktif, Pegawai, PKPT 2026 status (Draf/Disahkan), Slide aktif, Impor terakhir) + quick links grid + the "Aktivitas terbaru" list (last 10 log entries).

## Navigation groups
- Pengguna
- Master Data (Pegawai, Irban, Komposisi Irban Tahunan, Tim, Objek Pemeriksaan, Jenis Penugasan, Klasifikasi & Aturan)
- PKPT
- Konten Beranda (Slide, Kontak & Footer)
- Pengaturan Sistem
- Impor Excel
- Log Aktivitas

## Patterns per screen
- **Pengguna:** table (name, username, role, irban, status, last login) + "Tambah pengguna" drawer form. The bulk action "Wajibkan ganti kata sandi (akun Irban)" opens a confirm modal explaining that users will be forced to change at next login.
- **Pegawai:** NIP input with an 18-digit mask, tnum and counter "18/18". Validation "NIP harus 18 digit angka".
- **Tim:** member list with role assignment, using the same validation strip as the irban form (exactly one Ketua, ≤1 Supervisor/Dalnis).
- **Objek Pemeriksaan:** a tree (Kecamatan > Desa/Sekolah/Puskesmas/BUMDes) shown as expandable rows with indent guides, plus flat tabs for Dinas/Badan, BUMD, RSUD. Add-child action per node.
- **Jenis Penugasan:** table with a color swatch + label preview of the bar and chip exactly as they render publicly. The color picker is a grid of the 12 curated swatches (`DESIGN.md` §3.7) with names. "Warna lain" offers hex input with live validation: contrast vs white, auto label color, and rejection near mint/green. A warning appears if the color is already used by another type.
- **PKPT:** list per year with a Draf/Disahkan tag. Disahkan uses a mint-100 bg / mint-800 text *rectangular* tag, not a status pill, to keep pills reserved for SPT status. Item editor table with inline add. "Sahkan PKPT" requires a confirm modal; the PKPT becomes read-only after.
- **Slide carousel manager:**
  - Card list with thumbnail, title, active switch, and drag reordering (Alpine Sort) **plus** "Naikkan/Turunkan" buttons.
  - Editor: image upload (crop guide 16:9 with a safe-area overlay showing where text sits), title (max 80 characters), quote (max 200), owner name and position, and a required alt text ("Deskripsi gambar untuk pembaca layar").
  - A live preview renders with the real scrim, so the admin sees legibility.
- **Kontak & Footer:** form for phone, email, Instagram, address and the map embed URL, with a preview.
- **Impor Excel:**
  - Stepper (1 Unggah → 2 Pratinjau & Validasi → 3 Impor).
  - The preview table marks rows: valid (neutral), warning (amber row tint #FFFBEB + icon), error (red tint #FEF2F2 + `x-circle` + message per cell, e.g., "Baris 14: NIP 19870312 tidak 18 digit").
  - Summary chips: "120 valid · 3 peringatan · 2 galat". Toggle "Tampilkan hanya galat".
  - The "Impor 120 baris valid" button is disabled while errors are unresolved, unless "Lewati baris galat" is checked.
  - The error list can be downloaded as .xlsx.
- **Log Aktivitas:** read-only compact table (waktu, pengguna, "Diinput oleh", aksi, objek, IP) with filters and a JSON diff expandable per row in a mono font. No edit or delete controls.
- **Pengaturan Sistem:** grouped settings cards with explicit "Simpan perubahan" per card.

## Layout
At 1280, tables full width with drawers for create/edit (lg 720). At 768, the same with horizontal scroll. At 360, admin is supported but not optimized: tables scroll, forms stack.

## States
- Empty master tables: "Belum ada data pegawai. Tambah manual atau impor dari Excel." with two actions.
- Import errors as above.
- Save success via toast. Validation errors inline.

## Motion
Drawer and modal transitions only; drag reorder uses Alpine Sort's default ghost with no extra animation. Nothing on tables or logs.

## Accessibility
Tree rows use `aria-expanded` and `aria-level`. The color picker is a radio group with names, not color alone. Reordering is available without drag.

## Copy
"Tambah pengguna", "Wajibkan ganti kata sandi", "Komposisi Irban Tahunan", "Objek Pemeriksaan", "Jenis Penugasan", "Pilih warna", "Warna lain", "Sahkan PKPT", "Slide Beranda", "Aktif", "Naikkan", "Turunkan", "Teks alternatif gambar", "Impor Excel", "Pratinjau & Validasi", "Lewati baris galat", "Log Aktivitas".
