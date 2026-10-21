# Plan: Fase 9.1 (Detail Realisasi di Rekap dan Kalender Jadwal PKPT)

Spec: `tugas-detail-realisasi-rekap.md` (repo root; binding authority, every section of it). Also `PRD-SIMON.md` §7.2, §7.9, §9.3–9.6; `DESIGN.md` (all, esp. §3.4–3.5, §6, §7.5, §11, §13, §14); `design/dashboard/_shared.md`, `design/dashboard/pimpinan.md` ("Rekap Triwulanan", "Monitoring"), `design/dashboard/irban.md` ("Jadwal PKPT", "Rekap Saya"), `design/public/jadwal.md`. Rules: `CLAUDE.md`. Earlier decisions: `decisions/fase-8.md`, `decisions/fase-9.md`. Handoff: `docs/plans/serah-terima.md`.

Branch: `fase-9.1-revisi-realisasi` (from `main`). **Never push.** Commit only.

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `DESIGN.md`, `CLAUDE.md`, `design/` or `tugas-detail-realisasi-rekap.md`.
- Graph: `graphify-out/graph.json` exists. Before your first grep/search/source read for a codebase question, run `graphify query "<question>"`; read raw files only for lines the graph or an earlier read located. Do not stage `graphify-out/`.
- UI work (any Blade view/component, Tailwind, Alpine): first read `DESIGN.md` in full plus the design files named in the task, and invoke the skill `ui-ux-pro-max:ui-ux-pro-max` before writing markup. Only Tailwind tokens from DESIGN §13 and text/background pairs from §3.4; never §3.5 combinations; no raw hex in Blade except `--type`/`--type-fg`. Status always `x-app.status-badge` / `x-app.flag` (icon + label). No animation on dashboard tables, forms, calendar (§7.5); only the built-in modal/drawer transitions. Respect `prefers-reduced-motion`. UI copy in Bahasa Indonesia; dates `translatedFormat('d F Y')` or `App\Support\Tanggal::rentang`; timezone Asia/Jakarta.
- Stack: Blade + Livewire + Alpine + Tailwind + Chart.js. No new npm/composer packages.
- Decisions: record every technical decision or deviation in `decisions/fase-9.md` under `## Fase 9, Task Tambahan A: Detail Realisasi` (Tasks 1–4) or `## Fase 9, Task Tambahan B: Kalender Jadwal` (Tasks 5–8); create the heading if missing, append bullets, never rewrite older bullets. `DECISIONS.md` is an index only.
- Business rules are never recomputed in components/views: status via `Spt::status()`, flags via `PenandaSpt`, kesesuaian via `KesesuaianPkpt`, quarter via `Triwulan`, rencana via `RekapTriwulan::rencanaItem()`, weeks via `App\Support\MingguPkpt` (Task 5). Do not copy their logic.
- Tests: `php artisan test --filter=<Name>` while iterating; before committing run the full suite `php artisan test`, `vendor/bin/pint --test` (fix with `vendor/bin/pint <files>`), and `npm run build` when views/JS changed. All old tests stay green. Tests must also run on MySQL (no SQLite-only SQL).
- Git: stage files one by one (never `git add -A`/`.`); never stage `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`, `tugas-detail-realisasi-rekap.md` is staged only by the controller. Commit message prefix `Fase 9.1: `, ending with the line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Browser checks (UI tasks): `php artisan serve` + `playwright-cli --config .playwright/cli.config.json` (see `docs/plans/serah-terima.md` "Cara kerja"), dev accounts `pimpinan`, `irban1` (password `password`), screenshots to the scratchpad dir given in the dispatch. Check 360, 768, 1280 px: `document.documentElement.scrollWidth <= innerWidth`, keyboard reachability, visible focus ring. Stop the server by the PID listening on its port.

### Jebakan yang sudah diketahui (dari `docs/plans/serah-terima.md`)
- **Fokus setelah aksi:** bila modal ditutup lalu tombol pemicu/baris/kartu hilang atau menjadi nonaktif, pindahkan fokus ke target stabil (heading atau elemen `tabindex="-1"`) lewat event dari komponen; jangan biarkan fokus jatuh ke `body`.
- **`sr-only` di dalam kontainer gulir:** sel/pembungkus yang memuat `sr-only` di dalam `overflow-x-auto` wajib `relative`, kalau tidak halaman melebar di 360px.
- **"Diinput oleh":** selalu di footer form/modal.
- **Aturan bisnis tidak dihitung ulang di komponen:** action/service menyediakan method non-throwing yang dipakai UI dan validasi sekaligus.
- **Galat non-kolom:** kunci `ValidationException` tanpa field di form tampil sebagai toast.
- **Lookup id selalu di-scope** ke irban pengguna, termasuk method pratinjau/peringatan.
- **Zona waktu:** tanggal disimpan dalam waktu Jakarta; jangan `->utc()` saat membandingkan.
- **Ekspor xlsx:** pakai `App\Exports\ArrayExport`.
- **Carbon:** `parse('')` = sekarang; tolak nilai kosong sebelum parse.
- **Livewire:** nama method ≠ nama properti publik; id yang tak boleh diubah klien `#[Locked]`; otorisasi di `mount()` dan setiap aksi.
- **Factory `unique()` tahun** bisa bentrok dengan tahun eksplisit di test.
- **Elemen sticky di dasbor:** header sticky 64px (`z-30`); `sticky top-*` baru ≥ `top-16`.
- **Staging git:** file satu per satu.
- **Tab basi:** aksi yang ditolak karena status berubah di tab lain tampil sebagai toast, fokus ke h1.
- **Alpine `$refs` setelah request Livewire:** ambil referensi target fokus sebelum `$wire.aksi()`.
- **Job antrean berdurasi panjang:** `failed()` wajib; `retry_after` > `timeout`.
- **PDF dompdf ±0,26 MB per baris:** PDF besar lewat antrean (`Ekspor::BATAS_LANGSUNG_PDF = 200`).
- **Morph Livewire menghapus keadaan sisi klien:** keadaan Alpine harus diatur ulang setelah morph.
- **Rentang satu hari:** `Tanggal::rentang` untuk mulai = selesai ditulis `1 April 2026`.
- **Tes harus jalan di MySQL juga:** tanpa SQL khusus SQLite; `expectException` tepat sebelum baris yang diuji.
- **Data publik hanya lewat `RealisasiPublik`/`JadwalPublik`:** komponen publik hanya memegang skalar; kolom baru yang mendekati publik → penanda di `PrivasiPublikTest`.

## Exact values

### Demo data (Task 1) — PKPT 2026, Irban I, 25 items, `target_spt` null
`#` | tanggal_mulai | tanggal_selesai | jenis (master name used) | keterangan | expected weeks (MingguPkpt)
--|--|--|--|--|--
1 | 2026-01-12 | 2026-01-23 | Evaluasi Risk Register | – | Jan 2,3
2 | 2026-01-26 | 2026-01-30 | Evaluasi RB dan Reviu DAK | – | Jan 4
3 | 2026-02-02 | 2026-02-27 | Pengawasan Dana BOS | 1 tim 1 minggu 2 sekolah | Feb 1,2,3,4
4 | 2026-03-02 | 2026-03-06 | Reviu LKjIP | – | Mar 1
5 | 2026-03-09 | 2026-03-13 | Reviu DBH | – | Mar 2
6 | 2026-03-23 | 2026-03-27 | Reviu Tata Kelola Pajak | – | Mar 4
7 | 2026-03-30 | 2026-04-03 | Evaluasi RB dan Reviu DAK | – | Apr 1
8 | 2026-04-06 | 2026-04-10 | Evaluasi SAKIP PD | – | Apr 2
9 | 2026-04-13 | 2026-05-08 | Pengawasan Tata Kelola Desa/Keuangan Desa | 1 tim 1 minggu 2 desa | Apr 3,4,5; Mei 1
10 | 2026-05-11 | 2026-05-22 | Pendampingan dan Evaluasi ZI PD | – | Mei 2,3
11 | 2026-05-25 | 2026-06-05 | Audit Kinerja Program Strategis Daerah | – | Mei 4; Jun 1
12 | 2026-06-08 | 2026-06-26 | Audit Ketaatan Puskesmas | 1 tim 1 minggu 1 puskesmas | Jun 2,3,4
13 | 2026-06-29 | 2026-07-03 | Evaluasi RB dan Reviu DAK | – | Jul 1
14 | 2026-07-06 | 2026-07-17 | Reviu Kinerja Pelayanan Publik Sektor Kependudukan dan Pencatatan Sipil | – | Jul 2,3
15 | 2026-07-20 | 2026-07-24 | Monev Program Strategis Nasional Ketahanan Pangan dan Pembangunan Tiga Juta Rumah | – | Jul 4
16 | 2026-07-27 | 2026-08-14 | Audit Ketaatan Kecamatan | 1 tim 1 minggu 1 kecamatan | Jul 5; Agu 1,2
17 | 2026-08-17 | 2026-08-28 | Audit Kinerja Program Prioritas Daerah | – | Agu 3,4
18 | 2026-08-31 | 2026-09-25 | Audit Ketaatan OPD | 1 tim 1 minggu 1 OPD | Sep 1,2,3,4
19 | 2026-09-28 | 2026-10-02 | Evaluasi Pelayanan Publik Kemenpan RB | – | Sep 5
20 | 2026-10-05 | 2026-10-09 | Evaluasi Pelayanan Publik Ombudsman | – | Okt 1
21 | 2026-10-12 | 2026-10-16 | Audit Tujuan Tertentu Perusda/BUMD | Perumdam Lematang Enim | Okt 2
22 | 2026-10-19 | 2026-11-13 | Pengawasan Tata Kelola Desa/Keuangan Desa | 1 tim 1 minggu 2 desa | Okt 3,4; Nov 1,2
23 | 2026-11-16 | 2026-11-27 | Evaluasi Risk Register | – | Nov 3,4
24 | 2026-11-30 | 2026-12-04 | Evaluasi RB dan Reviu DAK | – | Des 1
25 | 2026-12-07 | 2026-12-25 | Monev | – | Des 2,3,4

Master-name mapping (spec "Cocokkan nama dengan master"): "Evaluasi Reformasi Birokrasi dan Reviu DAK" → existing `Evaluasi RB dan Reviu DAK`; "Pengawasan Pengelolaan Dana BOS Sekolah" → existing `Pengawasan Dana BOS`; "Reviu LKjIP Pemda" → existing `Reviu LKjIP`. All other names are created with `firstOrCreate(['nama' => …])` using `ColorRules::SWATCHES` cyclically (index by creation order, `warna` = `hex`, `warna_teks` = `label`). Months with 5 weeks in 2026: April, Juli, September, Desember (all others 4). Lebaran 16–20 Mar is NOT an item.

### Week rule (Task 5)
Week = Monday–Sunday. It belongs to the (year, month) of its **Wednesday**. Week number n = `intdiv(wednesday.day − 1, 7) + 1` (1–5). Label range = Monday–Friday. Boundary facts: 2026-01-01 → (2025, 12, 5); 2026-01-05 → (2026, 1, 1); 2026-12-31 → (2026, 12, 5); 2026-04-01 → (2026, 4, 1); 2026-09-30 → (2026, 9, 5).

## Task 1: Seeder demo PKPT 2026 Irban I

Files: create `database/seeders/PkptIrbanIDemo2026Seeder.php`; modify `database/seeders/DatabaseSeeder.php`; create `tests/Feature/Pkpt/PkptIrbanIDemo2026SeederTest.php`.

- Dev only (`app()->isProduction()` → return). Needs irban named `Irban I` (return if missing). Fixed year **2026** (not `now()`).
- PKPT 2026: `Pkpt::firstOrCreate(['tahun' => 2026], [... status Disahkan, disahkan_pada, nomor_sk like PkptSeeder])`. If it exists but is not Disahkan, leave its status alone (record it).
- Idempotent: delete Irban I items of PKPT 2026 that have no SPT (`whereDoesntHave('spt')` or equivalent relation; check `PkptItem` relations) and are not one of the 25 rows, then `firstOrCreate` each of the 25 rows on (`pkpt_id`, `irban_id`, `jenis_penugasan_id`, `tanggal_mulai`, `tanggal_selesai`) with `keterangan` (null for "–") and `target_spt` null. Running twice gives exactly 25 Irban I items.
- Jenis names exactly as in Exact values (mapping applied).
- `DatabaseSeeder`: call it right after `PkptSeeder`, inside the non-production branch only if that matches the file's pattern; it must run **before** `SptSeeder` so demo SPTs attach to demo items.
- Test: seeding `IrbanSeeder` (or creating `Irban I` via factory) + this seeder yields 25 items with the exact dates/jenis/keterangan above; running twice keeps 25; an existing Irban I item of PKPT 2026 without SPT is removed; Irban II items untouched.
- `php artisan migrate:fresh --seed` must still succeed (run it once against the dev database and report; dev DB is disposable).
- Decision bullets: name mapping, swatch cycling, non-Disahkan existing PKPT left alone.

## Task 2: Service `RealisasiItemPkpt`

Files: create `app/Services/RealisasiItemPkpt.php`; modify `app/Services/MonitoringSpt.php` (extract the SPT row builder into a public method reused by both, e.g. `barisSpt(Collection $spts): list<row>` that computes flags with one `PenandaSpt::untukBanyak` batch and the same kesesuaian mapping as `saring()`; `saring()`/`spt()` keep their behavior and all `MonitoringSptTest` stays green); create `tests/Feature/Rekap/RealisasiItemPkptTest.php`.

API: `untuk(int $tahun, int $tw, ?int $irbanId): array` — `$tw` 0 = Semua TW, 1–4 = quarter.

Rules (spec A.3, final):
1. Items: of the PKPT with `tahun = $tahun` and status Disahkan (`$irbanId` restricts to that irban). Item is in TW X if `RekapTriwulan::rencanaItem($item, $tahun)` returns TW X, **or** it has ≥1 SPT row (rule 2) in TW X. For `$tw = 0`: items satisfying either for any quarter of the year.
2. SPT rows: `status_pengajuan = diterima`, `pkpt_item_id` set (an item of rule 1's set), basis date (`Triwulan::tanggalDasar`; SQL narrowing with `Triwulan::batasi`) in `$tahun` and in quarter `$tw` when `$tw > 0`.
3. SPT without item never appear.
4. Order: irban by `id`; items by effective start (`KesesuaianPkpt::rentang`) then `id`; SPT by `tanggal_mulai` then `id`.
5. Row shape = MonitoringSpt row exactly (`{id, nomor_lengkap, irban, jenis, mulai, selesai, kesesuaian, status, penanda, kendala, url}`), built by the extracted shared method.
6. Output: `list<{irban: {id, nama}, item: list<{item_id, jenis: {nama, warna, warna_teks}, keterangan, mulai, selesai, direvisi: bool, spt: list<row>}>}>`. `mulai`/`selesai` = effective range (CarbonImmutable). `direvisi` = item has an approved revision. Every irban in scope appears (all irbans by id when `$irbanId` null), with `item: []` when none.
7. Constant number of queries regardless of item/SPT count (eager-load; one penanda batch). Add a test asserting the query count does not grow with N.

Tests (spec A.6 1, 2, 3-order, 4, 5): item without SPT → `spt: []`; Dana BOS-like item planned Feb (TW I), SPT in April → TW I item listed with `spt: []`, TW II item listed with the April row whose `kesesuaian = tidak_sesuai`; item with several SPT → order by `tanggal_mulai`, `id`; Non-PKPT SPT and SPT not `diterima` (draf, diajukan, ditolak, …) absent; approved revision changes `mulai`/`selesai`, `direvisi = true`, and the kesesuaian of an SPT inside the revised range becomes `sesuai`; `$tw = 0` includes all; irban filter. Use setting `rekap.dasar_triwulan = tanggal_selesai` explicitly (see `tests/Feature/Rekap/RekapFixture.php` for the pattern; reuse it if it fits, else build a small local fixture).

Decision bullets: the extracted method name/contract.

## Task 3: Tab "Detail Realisasi" di halaman Rekap

Files: modify `app/Livewire/Rekap.php`, `resources/views/livewire/rekap.blade.php`; if the Monitoring view's SPT cells (realisasi link, kesesuaian, status+flag, kendala "Selengkapnya") can be shared, extract them to a partial and use it in both `resources/views/livewire/pimpinan/monitoring.blade.php` and the rekap view without changing Monitoring's output; create `tests/Feature/Rekap/DetailRealisasiTest.php` (page part). Design: `design/dashboard/_shared.md`, `design/dashboard/pimpinan.md`, `design/dashboard/irban.md`. UI skill required.

- Tab values: Pimpinan `irban` "Per Irban" | `gabungan` "Gabungan" | `detail` "Detail Realisasi". Irban: `irban` "Ringkasan" | `detail` "Detail Realisasi" (Ringkasan = today's Irban view; keep value `irban` so existing URLs work). Unknown tab → `irban`.
- Filters Tahun, Triwulan, and Pimpinan's `irban` URL param apply; scope comes from `PembangunRekap::filter()` (Irban always own irban, tampered `irban` ignored). The detail tab calls `RealisasiItemPkpt::untuk($f['tahun'], $f['tw'], $f['irban'])`; the three existing sections are not rendered on the detail tab.
- ≥768px: per irban an `<h2>`/`<h3>` with the irban name and one `x-ui.table density="compact"`; columns `Jenis Penugasan | Periode Penugasan (PKPT) | Realisasi Periode | Kesesuaian dengan Jadwal PKPT | Status | Permasalahan/Kendala`. Jenis (`x-app.type-chip`) and Periode (`Tanggal::rentang(mulai, selesai)`, plus a small revised marker when `direvisi`, icon + sr-only "Jadwal direvisi") only on the item's first row (use `rowspan` = row count). SPT row: Realisasi = `Tanggal::rentang` + nomor SPT as small link to `spt.show`; Kesesuaian same as Monitoring; Status `x-app.status-badge` + `x-app.flag` terlambat when in `penanda`; Kendala `kategori: uraian` with Monitoring's "Selengkapnya" pattern. Item without SPT: one row, Realisasi "Belum ada SPT" (neutral text, no new color/flag), other SPT columns "–".
- Irban without items: empty state "Tidak ada item PKPT pada periode ini."
- <768px: card per item (jenis chip, periode), nested SPT rows like Monitoring's mobile cards.
- No animation. Loading state like the existing panel (`wire:target` includes `tab`).
- Tests (spec A.6 #6 page part + rendering): Pimpinan sees three tabs, Irban sees two; detail tab shows "Belum ada SPT" for item without SPT, the nomor SPT link, irban headings; Irban account sees only its irban even with `?irban=<other>`; Pimpinan `?irban=` scopes to one irban; empty-state text for an irban without items.
- Browser check 360/768/1280 for both accounts on `?tab=detail` (with the Task 1 demo data plus `migrate:fresh --seed`).

## Task 4: Ekspor bagian keempat

Files: modify `app/Services/Ekspor/PembangunRekap.php`, `resources/views/pdf/rekap-triwulan.blade.php`, `app/Services/Ekspor/Pembangun.php` + the three builders + `app/Jobs/BuatEkspor.php` (only for `halaman()`), tests in `tests/Feature/Rekap/DetailRealisasiTest.php` (export part) and existing export tests if signatures change.

- `export()` and `html()`: after the three sections, a fourth **"Detail Realisasi per Item PKPT"** from `RealisasiItemPkpt::untuk($filter['tahun'], $filter['tw'], $filter['irban'])` (same call as the screen). One sub-table per irban (title row = irban name). Columns: `Jenis Penugasan | Periode Penugasan (PKPT) | Nomor SPT | Realisasi Periode | Kesesuaian dengan Jadwal PKPT | Status | Permasalahan/Kendala`. Jenis and Periode only on the item's first row; later rows leave them empty (`ArrayExport` has no merge support → **no merging**; record the decision). Status text = status label, plus ` · Terlambat` when flagged. Kesesuaian text `Sesuai` / `Tidak Sesuai` (same labels as the screen). Kendala `kategori: uraian` in full. Item without SPT: Realisasi `Belum ada SPT`, Nomor/Kesesuaian/Status/Kendala `–`. Irban without items: one row `Tidak ada item PKPT pada periode ini.`
- PDF: same section, full-width table, same columns.
- `jumlah()`: return the number of detail rows (Σ max(1, count spt) per item, + 1 per empty irban) so large exports go through the queue (`Ekspor::BATAS_LANGSUNG_PDF = 200`, `BATAS_LANGSUNG = 1000`). Because Irban accounts can now queue a rekap export, change `Pembangun::halaman()` to `halaman(User $user): string` in the interface, all three builders and `BuatEkspor`; `PembangunRekap` returns `irban.rekap` for Irban accounts and `pimpinan.rekap` otherwise (check route names in `routes/web.php`).
- Tests (spec A.6 #3 export part, #6 export part, #7): xlsx rows contain the fourth section title, irban title, Jenis/Periode only on the first row of a multi-SPT item; PDF html contains the same section with the same values as the screen for one fixture; Irban export ignores a tampered `irban` (only own irban's title present); `jumlah()` counts rows; queued rekap export failure notification for Irban links to the Irban rekap page.

## Task 5: `App\Support\MingguPkpt`

Files: create `app/Support/MingguPkpt.php`, `tests/Unit/MingguPkptTest.php` (or `tests/Feature/...` if it needs the framework; Carbon works in Unit).

Static API (exact):
- `dari(CarbonInterface $t): array{tahun: int, bulan: int, minggu: int}` — owner per the Week rule.
- `daftar(int $tahun, int $bulan): list<array{minggu: int, mulai: CarbonImmutable, selesai: CarbonImmutable}>` — the month's 4 or 5 weeks; `mulai` = Monday, `selesai` = Friday.
- `untukRentang(CarbonInterface $mulai, CarbonInterface $selesai, int $tahun): list<array{bulan: int, minggu: int}>` — every week owned by `$tahun` whose Monday–Sunday overlaps `[mulai, selesai]`, in calendar order. If none but the range overlaps calendar year `$tahun`: `[{1, 1}]` when the range ends before the first week of `$tahun`, else the last week of December of `$tahun`. Range entirely outside calendar year `$tahun` → `[]`.
- Tests: data provider with all 25 rows of the demo table (expected weeks column) plus Lebaran 16–20 Mar → Mar 3; boundary facts from Exact values; `untukRentang(2026-01-01, 2026-01-02, 2026)` → `[{1,1}]`; `untukRentang(2026-12-31, 2026-12-31, 2026)` → `[{12,5}]`; `daftar(2026, 1)` = 4 weeks starting 5 Jan with label range 5–9 Jan; `daftar(2026, 4)` = 5 weeks; `daftar(2026, 3)` = 4 weeks (Mar M5 doesn't exist since 30 Mar–3 Apr is Apr M1).

## Task 6: `JadwalPublik` memakai `MingguPkpt`

Files: modify `app/Services/Publik/JadwalPublik.php` (`kolom()`, remove `minggu()`'s `ceil(day/7)`, `bulanan()`), `resources/views/livewire/publik/jadwal.blade.php` (+ `resources/js/jadwal.js` only if needed), `tests/Feature/Publik/JadwalPublikTest.php`, `tests/Feature/Publik/JadwalHalamanTest.php` (only tests depending on the old rule). Design: `design/public/jadwal.md`. UI skill required.

- `kolom($t, $tahun)`: owner = `MingguPkpt::dari($t)`; owner year < `$tahun` → 1, > `$tahun` → 60, else `(bulan − 1) × 5 + minggu`. Timeline stays 60 columns.
- `linimasa()` additionally returns `mingguBulan: array<1..12, 4|5>` (from `MingguPkpt::daftar`); in the view, column 5 of a 4-week month is hatched and `aria-hidden` (no new colors outside §13; reuse an existing hatch/neutral token pattern).
- `bulanan()`: an item is listed under **every** week it overlaps, via `MingguPkpt::untukRentang($i['mulai'], $i['selesai'], $tahun)` grouped by month; `jumlah` = number of distinct items in the month. Keep the output shape (`minggu` keyed 1–5, non-empty weeks only).
- Update the tests that encoded the old rule; add one asserting SAKIP 6–10 Apr → Apr col/week 2 only and Evaluasi RB 30 Mar–3 Apr → Apr week 1 only (not Mar 5). Public privacy tests unchanged and green.
- Browser check `/jadwal` and `/jadwal?tampilan=bulanan` at 360/768/1280.
- Decision bullets: rule change and which tests changed.

## Task 7: Service `KalenderPkpt` dan `WilayahPengawasan`

Files: create `app/Services/KalenderPkpt.php`, `app/Services/WilayahPengawasan.php`; modify `app/Services/Publik/JadwalPublik.php` (`wilayah()` delegates to `WilayahPengawasan`, same public output); create `tests/Feature/Pkpt/KalenderPkptTest.php`.

- `WilayahPengawasan::untuk(int $tahun, ?int $irbanId = null): list<array{irban_id: int, irban: string, kecamatan: list<string>, dinas_badan: list<string>, bumd: list<string>}>` — the query from `JadwalPublik::wilayah()` (objek jenis `kecamatan` → kecamatan, `dinas_badan` and `rsud` → dinas_badan, `bumd` → bumd; names sorted). `JadwalPublik::wilayah()` maps it back to its current labels/order; `JadwalPublikTest::test_wilayah_panel_per_irban` unchanged and green.
- `KalenderPkpt::untuk(int $tahun, int $irbanId): list<array{bulan: int, nama: string, minggu: array<1..5, array{ada: bool, mulai: ?CarbonImmutable, selesai: ?CarbonImmutable, ini: bool, item: list<item>}>}>` — 12 months; `ada` false for week 5 of a 4-week month (then `mulai`/`selesai` null, `item` []); `ini` = week containing today (Asia/Jakarta). Items: the irban's items of the Disahkan PKPT of `$tahun` (none when not Disahkan), placed by `MingguPkpt::untukRentang(rentang efektif)`; within a cell ordered by effective start then id. `item` = `{id, jenis: {id, nama, warna, warna_teks}, keterangan, mulai, selesai, semula: ?array{0: CarbonImmutable, 1: CarbonImmutable}, direvisi: bool, revisi_menunggu: bool, selesai_spt: int, target: int}`; `selesai_spt` = count of the item's SPT with status Selesai (`Spt::berstatus(StatusSpt::Selesai)` or equivalent existing scope, `diterima`); `target` = `target_spt ?? 1`. Constant query count.
- Tests (spec B.5 #2, #3, #4 service part): with `PkptIrbanIDemo2026Seeder`, the set of (bulan, minggu) → jenis names equals the demo table's weeks column for all 25 rows, and every other existing week is empty; an item with an approved revision appears in the revised weeks, not the original; another irban's items never appear; week 5 of Jan 2026 has `ada = false`; `ini` true for exactly one week when today is in 2026 (travel to a fixed date); `WilayahPengawasan` scoped to one irban.

## Task 8: Tampilan Kalender di halaman Jadwal PKPT Irban

Files: modify `app/Livewire/Irban/Pkpt.php`, `resources/views/livewire/irban/pkpt.blade.php`; create `resources/views/components/app/kalender-pkpt.blade.php` (reusable: takes the `KalenderPkpt` output and emits an event / calls a passed action for "Ajukan Revisi"; must not depend on `Irban\Pkpt` internals); create `tests/Feature/Pkpt/KalenderIrbanTest.php`. Design: `design/dashboard/_shared.md`, `design/dashboard/irban.md`, `design/public/jadwal.md` (calendar/popover/drawer patterns), DESIGN §7.5, §11. UI skill required.

- `#[Url] public string $tampilan = 'kalender'`; values `kalender`|`tabel`, anything else → `kalender`. `x-ui.segmented` "Kalender | Tabel" in the page header. Table view = today's table/cards unchanged.
- Calendar ≥1024: 2-column grid of month blocks (Jan–Feb, Mar–Apr, …). Block: month title; header row "Minggu 1…5" with small date range (neutral-500, Mon–Fri, e.g. "6–10 Apr"); one content row. Cell: small card per item with 4px left bar `var(--type)`, jenis name, keterangan small; stacked when several; empty cell "–"; nonexistent week 5 hatched + `aria-hidden`. Current week: mint-50 tint + label "Minggu ini". Revised item: small icon + sr-only "Jadwal direvisi".
- 768–1023: one month block per row, 5 columns kept. <768: month cards as accordion (current month open, others closed), vertical sections "Minggu 1 · 5–9 Jan" …, empty week one line "–"; no 5-column grid at 360.
- Item card is a button opening details: popover ≥768, `x-ui.drawer` <768. Details: jenis, jadwal berlaku, jadwal semula (if revised), keterangan, progress "x dari y SPT selesai", button **Ajukan Revisi Jadwal** calling the existing `buka(id)` (same modal, same `AjukanRevisiJadwal` rules); disabled with a written reason when a revision is pending (reuse the wording of `livewire.irban.partials.revisi-aksi`). Focus: Esc returns focus to the card; after the modal flow, focus handling as today (`revisi-terkirim`), falling back to a stable target if the card's button is gone.
- Panel "Wilayah Pengawasan" above the calendar: three lists Kecamatan, Dinas/Badan, Perusda/BUMD from `WilayahPengawasan::untuk($tahun, $irbanId)`; note "RSUD, Puskesmas, Desa, BUMDes, dan Sekolah mengikuti wilayah kecamatan."; collapsible (`<details>` or Alpine disclosure with `aria-expanded`), closed by default <768, open ≥768.
- Legend: `x-app.type-chip` for each jenis present, above the calendar.
- No animation besides modal/drawer built-ins.
- Tests (spec B.5 #4 page, #5): `?tampilan=tabel` renders the table, default renders calendar markup; invalid `tampilan` → calendar; Irban sees only own items and wilayah; calling `buka(id)` for own item from calendar opens the modal (`open = true`) and for another irban's item 404; pending revision → `buka` toasts error and the calendar renders the disabled reason; legend shows present jenis only.
- Browser check (spec B.5 #7) as `irban1` after `migrate:fresh --seed`: 360/768/1280 no horizontal page scroll (also with drawer/popover and wilayah panel open), keyboard path card → popover → Ajukan Revisi → modal → Esc.
