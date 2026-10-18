# Dashboard: Irban

> Read `DESIGN.md` and `design/dashboard/_shared.md` first.

**Purpose:** a shared unit account submits and maintains its own SPT quickly. The primary task is creating a number request and clearing the "Perlu tindakan" items.

## Dashboard hierarchy
1. Greeting band: "Irban II · Tahun 2026" with a CTA "Ajukan Nomor SPT" (primary lg).
2. "Perlu Tindakan" list, at the top because it is actionable.
3. Status summary as clickable stat cards: Draf, Menunggu Nomor, Perlu Revisi, Berjalan (3 blue statuses), Selesai, Terlambat.
4. "SPT Saya" table (recent 10) + "Rekap Saya" link.

## Perlu Tindakan
A card list. Each item has:
- the reason flag (Perlu Revisi from Bagian Umum / Terlambat penyusunan laporan / Permintaan Penjelasan terbuka)
- the SPT number or "Draf: Audit Kinerja Dinas PUPR"
- the due/age caption
- the button "Tinjau"

It is sorted by urgency. Empty state: a `check-badge` icon in mint-600 with "Tidak ada yang perlu ditindaklanjuti. Kerja bagus!"

## Layout
At 1280, stats 6 cols in one row, with Perlu Tindakan 7 cols + mini chart "Status SPT" (horizontal stacked bar) 5 cols. At 768, stats 3×2. At 360, stats 2×3 compact (value 24px), Perlu Tindakan first, table as cards.

## Submission form — "Ajukan Nomor SPT"
The key complex UI; target: 6 personnel in under 30s.

1. **Dasar:**
   - Klasifikasi (radio cards: REGULER, KHUSUS, DIKLAT, LUAR DAERAH, LAIN-LAIN).
   - Item PKPT (combobox; shows type chip + PKPT period). A "Non-PKPT" checkbox reveals the flag explanation.
   - Jenis Penugasan (auto from PKPT, editable if Non-PKPT).
   - Wilayah/Objek (hierarchical combobox "Kecamatan › Desa").
2. **Waktu:** `x-ui.date-range` with "Durasi: 19 hari". If the range falls outside the PKPT period, show an inline warning alert "Di luar jadwal PKPT (Maret 2026). SPT akan ditandai *Tidak Sesuai PKPT*." This warns but does not block.
3. **Untuk:** a textarea prefilled with a suggested sentence built from type + object + period. A "Gunakan saran" ghost button restores the suggestion. Character counter.
4. **Tim Pemeriksa** (two-pane at ≥1024; stacked on mobile):
   - **Left — "Pilih Tim":** checkbox list of teams for this irban/year ("Tim 1 · 5 orang"). Checking a team appends its members with default roles. Overlapping members are deduplicated automatically, with an info toast "Andi Saputra sudah ada dari Tim 1".
   - **Right — "Susunan Personel":** an ordered list. Each row (56px) contains:
     - drag handle (Alpine Sort) **plus** up/down icon buttons (keyboard alternative)
     - include checkbox (unchecking keeps the row grayed and excluded, for easy re-include)
     - name 14/600 + NIP caption tnum
     - role `x-ui.segmented` (Supervisor/Dalnis · Ketua Tim · Anggota)
     - remove ✕
   - Below the list: "+ Tambahkan anggota lainnya", a combobox searching name or NIP across all active employees (results show the unit), appended as Anggota.
   - **Live validation strip** above the list: three checks with icon + text — "1 Ketua Tim" ✓/✗, "Maks. 1 Supervisor/Dalnis" ✓/✗, "Tidak ada duplikat" ✓ — plus the count "6 personel dipilih". Failing checks turn danger-700 with an `x-circle` icon. Submit is blocked with the summary "Tetapkan tepat satu Ketua Tim".
   - **Speed:** checking one team typically fills 4–6 people in one click. Role change is one click per person. Tab moves through rows. Within a focused row, ↑/↓ moves focus and Alt+↑/↓ reorders (component-scoped, announced via a live region: "Budi dipindah ke posisi 2").
5. **Sticky footer:** Diinput oleh · "Simpan draf" · "Ajukan Nomor". Autosave indicator "Draf tersimpan 10.42" (caption, neutral-500) after each manual save.

## Other irban screens
- Edit SPT (same form).
- Upload dokumen (PDF dropzone with progress).
- Tandai Selesai modal: LHP cover PDF + "Nomor LHP" + "Tanggal LHP" + Diinput oleh; button "Tandai Selesai".
- Kendala form (textarea + kategori).
- Ajukan Revisi Jadwal: shows current dates (read-only) and new dates, with required "Alasan revisi".
- Balas komentar.
- Rekap Saya: tables per TW, with export.

## States
- Form save success: toast.
- Validation: error summary at the top + inline errors.
- Network failure: a persistent error toast with "Coba lagi". Keep the user's input; never clear the form.

## Motion
Dashboard stat cards may count up once on first load per session (≤600ms). **No motion in the form**, apart from row reorder feedback: a 150ms background flash on the moved row (mint-50), which is disabled under reduced motion.

## Accessibility
The team list is a fieldset with a legend. Role segmented controls are radio groups with labels "Peran untuk Budi Santoso". Validation strip uses `aria-live="polite"`.

## Copy
"Ajukan Nomor SPT", "Perlu Tindakan", "Pilih Tim", "Susunan Personel", "Tambahkan anggota lainnya", "Cari nama atau NIP", "Supervisor/Dalnis", "Ketua Tim", "Anggota", "Simpan draf", "Ajukan Nomor", "Tandai Selesai", "Ajukan Revisi Jadwal", "Isi Kendala".
