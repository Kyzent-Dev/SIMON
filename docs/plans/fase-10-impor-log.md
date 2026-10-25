# Plan: Fase 10 (Impor data lama dan log aktivitas)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Admin can import legacy SPT (Sistem A/B) from an Excel template with preview and per-row errors, and every important data change is recorded in an immutable activity log that Admin and Pimpinan can read.

**Architecture:** `spatie/laravel-activitylog` 4.x with an app model `App\Models\Aktivitas` (adds `diinput_oleh` and `ip`, refuses update/delete) and a trait `DicatatAktivitas` on the important models; one Livewire log page shared by Admin and Pimpinan, plus a log card on SPT detail and the Admin dashboard. The SPT import copies the existing PKPT import pattern (parser `read()`/`analyze()` → Livewire stepper → action in one transaction).

**Tech Stack:** Laravel 13, Livewire 4, Alpine, Tailwind, maatwebsite/excel + PhpSpreadsheet, spatie/laravel-activitylog ^4.12.

**Spec:** `PRD-SIMON.md` §3.1, §5 ("Diinput oleh ... Simpan di log aktivitas"), §7.8 (detail SPT: log aktivitas), §7.11, §7.12, §8 (`activity_log`, `spt.sumber`), §11 (package), §12 Fase 10 ("Selesai jika: impor file contoh berhasil dan baris bermasalah ditolak dengan pesan jelas"). Design: `DESIGN.md` (all), `design/dashboard/_shared.md` (§6, §7 "Log Aktivitas (collapsed)"), `design/dashboard/admin.md` ("Dashboard", "Impor Excel", "Log Aktivitas"), `design/dashboard/pimpinan.md`. Rules: `CLAUDE.md`. Handoff: `docs/plans/serah-terima.md`. Plan-level rulings: `decisions/fase-10.md` (section "Plan").

Branch: `fase-10-impor-log` (from `main` = `470b29e`). **Never push.** Commit only.

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `DESIGN.md`, `CLAUDE.md`, `design/`.
- Graph: `graphify-out/graph.json` exists. Before your first grep/search/source read for a codebase question, run `graphify query "<question>"` (or `graphify explain "<X>"`, `graphify path "<A>" "<B>"`); read raw files only for lines the graph or an earlier read located. After changing code run `graphify update .`. Never stage `graphify-out/`.
- UI work (any Blade view/component, Tailwind, Alpine): first read `DESIGN.md` in full plus the design files named in the task, then invoke the skill `ui-ux-pro-max:ui-ux-pro-max` before writing markup. Only Tailwind tokens from DESIGN §13 and text/background pairs from §3.4; never §3.5 combinations; no raw hex in Blade. Status always icon + label, never colour alone. No animation on dashboard tables, forms, logs (§7.5; admin.md "Motion": nothing on tables or logs); only built-in modal/drawer transitions. UI copy Bahasa Indonesia; dates `translatedFormat('d F Y')`, times `H.i` + ` WIB`, ranges `App\Support\Tanggal::rentang`; timezone Asia/Jakarta. Reuse `x-ui.*`/`x-app.*` components (page-header, card, table/th, alert, checkbox, button, file-upload, empty-state, select, stat-card). In your summary, state which design files you followed.
- Stack: Blade + Livewire + Alpine + Tailwind + Chart.js. The only new package of the phase is `spatie/laravel-activitylog:^4.12` (Task 1). No npm packages.
- Decisions: record every technical decision or deviation in `decisions/fase-10.md` under `## Fase 10, Task M` (create the heading if missing, append bullets, never rewrite older bullets; never edit an earlier phase's file except to fix it). `DECISIONS.md` is an index only (its Fase 10 line exists). Cross-phase deploy notes go to `decisions/catatan-deploy.md`.
- Business rules are never recomputed in components/views: SPT status via `Spt::status()`, flags via `PenandaSpt`, kesesuaian via `KesesuaianPkpt`, personnel composition via `ValidatePersonel::cek()`, log labels via `App\Support\LabelAktivitas` (Task 1), import rules via `SptImportParser::analyze()` (Task 3).
- Tests: `php artisan test --filter=<Name>` while iterating; before committing run the full suite `php artisan test`, `vendor/bin/pint --test` (fix with `vendor/bin/pint <files>`), and `npm run build` when views/JS changed. All old tests stay green unless the task explicitly rewrites them (say which in decisions). Tests must also run on MySQL (no SQLite-only SQL).
- Git: stage files one by one (never `git add -A`/`.`); never stage `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`. Commit message prefix `Fase 10: `, ending with the line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Browser checks (UI tasks): `php artisan serve` + `playwright-cli --config .playwright/cli.config.json` (see `docs/plans/serah-terima.md` "Cara kerja"), run `php artisan migrate:fresh --seed` first, dev accounts `admin`, `pimpinan`, `irban1`, `bagianumum` (password `password`), screenshots to the scratchpad dir given in the dispatch. Check 360, 768, 1280 px: `document.documentElement.scrollWidth <= innerWidth` (also with disclosures/rows expanded), keyboard reachability, visible focus ring. Stop the server by the PID listening on its port.
- Log immutability: no code path, UI or command may update or delete `activity_log` rows (PRD §7.12); never schedule `activitylog:clean`.

### Jebakan yang sudah diketahui (dari `docs/plans/serah-terima.md`, lengkap)
Masing-masing pernah lolos ke review dan memakan satu putaran perbaikan.
- **Fokus setelah aksi:** bila modal ditutup lalu tombol pemicu/baris/kartu hilang atau menjadi nonaktif, pindahkan fokus ke target stabil (heading atau elemen dengan `tabindex="-1"`) lewat event yang di-dispatch dari komponen; jangan biarkan fokus jatuh ke `body`.
- **`sr-only` di dalam kontainer gulir:** sel/pembungkus yang memuat span `sr-only` di dalam `overflow-x-auto` wajib `relative`, kalau tidak halaman melebar di 360px. Cek `document.documentElement.scrollWidth <= 360` dengan disclosure/detail terbuka.
- **"Diinput oleh":** selalu di footer form/modal (login.md §5), tidak di badan.
- **Aturan bisnis tidak dihitung ulang di komponen:** bila UI butuh status aturan (strip validasi, durasi), action/service menyediakan method non-throwing yang dipakai UI dan validasi sekaligus (contoh `ValidatePersonel::cek()`, `Tanggal::durasiPenugasan()`).
- **Galat non-kolom:** kunci `ValidationException` yang tidak punya field di form ditampilkan sebagai toast, tidak hilang diam-diam.
- **Lookup id selalu di-scope** ke irban pengguna, termasuk method pratinjau/peringatan, bukan hanya aksi simpan.
- **Zona waktu:** kolom tanggal/waktu disimpan dalam waktu Jakarta (APP_TIMEZONE); jangan `->utc()` saat membandingkan.
- **Ekspor xlsx:** pakai `App\Exports\ArrayExport` (sudah menyimpan teks berawalan `= + - @` sebagai string). Jangan membuat ekspor baru tanpa binder itu.
- **Carbon:** `parse('')` menghasilkan "sekarang"; tolak nilai kosong sebelum parse.
- **Livewire:** nama method tidak boleh sama dengan nama properti publik; properti id yang tidak boleh diubah klien diberi `#[Locked]`; otorisasi di `mount()` dan setiap aksi (endpoint update tidak menjalankan middleware route).
- **Factory dengan `unique()` tahun** bisa bentrok dengan tahun eksplisit di test; pakai rentang di luar tahun yang ditulis di test.
- **Penomoran:** `Penomoran::ambil` hanya di dalam `DB::transaction(..., attempts: 3)` dan selalu dengan klasifikasi.
- **Elemen sticky di dasbor:** header dasbor sticky 64px (`z-30`). Elemen `sticky top-*` baru terhadap halaman wajib diberi offset ≥ `top-16`, dan `max-h` dikurangi 4rem; `sticky` di dalam kontainer `overflow-*` tidak terpengaruh.
- **Staging git:** stage file satu per satu; jangan pernah `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`.
- **Penundaan dari fase sebelumnya:** saat fase N mewujudkan sesuatu yang dulu ditunda "ke Fase N", cari dan hapus sisa penundaannya (`grep -rn "Fase 10" app/ resources/ tests/`). Known: `app/Actions/Profil/*` (`shortcut: Log facade until the Fase 10 ...`, Task 1), `app/Livewire/Admin/Dasbor.php` docblock (Task 2 and 4), `resources/views/livewire/spt/detail.blade.php` comment (Task 2), `tests/Feature/DashboardShellTest.php` using `admin.impor` as a not-yet-existing route (Task 4).
- **Tab basi:** aksi yang ditolak karena status sudah berubah di tab lain tampil sebagai toast lewat guard bersama, bukan 403, dan fokus pindah ke h1.
- **Pesan unggah Livewire:** batas sementara Livewire (12 MB) dan kegagalan unggah memakai `lang/id/validation.php` (`max.file`, `uploaded`, atribut field); field unggah baru wajib punya atribut di sana.
- **Notifikasi:** kirim dari action (setelah `DB::transaction`) atau command, tidak dari komponen; penerima hanya dari `PenerimaNotifikasi`; kelas baru turunan `NotifikasiSimon`.
- **Alpine `$refs` setelah request Livewire:** bila tombol pemicu terhapus oleh morph, ambil referensi target fokus sebelum `$wire.aksi()`.
- **Morph Livewire menghapus atribut/keadaan sisi klien:** keadaan Alpine harus diatur ulang setelah morph.
- **Rentang satu hari:** `Tanggal::rentang` untuk mulai = selesai ditulis `1 April 2026`.
- **Tes harus jalan di MySQL juga:** jangan pakai SQL khusus SQLite di tes; `expectException` dipasang tepat sebelum baris yang diuji.
- **Drawer/popover dengan keadaan Alpine:** setiap jalur tutup (X, Esc, scrim) mengosongkan item aktif; `$wire.$hook` disimpan dan di-`unhook` di `destroy()`.
- **Data publik hanya lewat `RealisasiPublik`/`JadwalPublik`:** tambahkan penanda unik ke `PrivasiPublikTest` setiap kali kolom baru dari tabel SPT/PKPT bisa mendekati halaman publik.
- **DDL di dalam `RefreshDatabase` pada MySQL meng-commit transaksi:** tes migrasi yang mengubah skema merusak isolasi suite.
- **Penulis yang tidak mengambil kunci yang diandalkan validasi ulang:** setiap penulis wajib mengambil kunci yang sama, urutan kunci PKPT dulu.
- **Predikat aturan bisnis disalin ke view, dasbor, dan command:** satu method di model/service dipakai semua pemanggil.
- **Item basi dalam alur yang di-scope ke pengguna:** item yang hilang menjadi toast, bukan 404 dari `findOrFail`.
- **Kolom impor baru menghapus nilai tersimpan bila berkas lama tak punya kolom itu:** hanya timpa bila kolom ada di berkas.
- **Rahasia sementara tertinggal di state Livewire:** kata sandi sementara dan sejenisnya di-reset dari properti publik segera setelah ditampilkan/disimpan. Untuk fase ini juga: kata sandi/token tidak pernah masuk `properties` log.
- **Impor PKPT `analyze()` ±2 query per baris (minor tertunda):** parser baru memuat semua lookup sekali per `analyze()`, bukan per baris.

## Review Focus
1. **NIP and nomor SPT typed as numbers in Excel.** An 18-digit NIP read as float loses digits; the row must get the error `NIP tersimpan sebagai angka; ubah kolom menjadi teks.`, never a silent match to a wrong pegawai. Test in Task 3.
2. **The same file uploaded twice.** Second run: every row is an error `Nomor SPT {nomor} sudah ada.`; nothing is duplicated. Test in Task 3.
3. **Imported SPT colliding with future numbering.** A legacy number in the current register year above the counter must be warned about, and the race between preview and import (number inserted meanwhile) becomes a toast, not a 500. Tests in Task 3.
4. **Secrets in the log.** Changing a password (Profil, Pengguna reset) logs the event but `properties` never contain `password`, `remember_token` or any hidden attribute. Test in Task 1.
5. **Imported history raising alarms.** Importing hundreds of finished/late legacy SPT sends no notification now or at the next `notifikasi:harian`, and imported SPT never get the flag `spt_final_belum_diupload`. Tests in Task 3.

## Exact values

### E1. Logged models (Task 1)
`User`, `Pegawai`, `Irban`, `IrbanTahunan`, `Tim`, `TimAnggota`, `Objek`, `IrbanWilayah`, `JenisPenugasan`, `Klasifikasi`, `Pkpt`, `PkptItem`, `PkptPerubahan`, `PkptPerubahanItem`, `PkptUsulan`, `Spt`, `SptPersonel`, `SptDokumen`, `SptKendala`, `Komentar`, `LandingSlide`, `NomorCounter`. Not logged: `Ekspor`, `PkptPerubahanRiwayat`, `SptRiwayat` (already histories), notifications, `Aktivitas`.

### E2. Labels (`App\Support\LabelAktivitas`, Task 1)
- Object key → class → label: `spt` Spt `SPT`; `spt_personel` SptPersonel `Personel SPT`; `spt_dokumen` SptDokumen `Dokumen SPT`; `spt_kendala` SptKendala `Kendala SPT`; `komentar` Komentar `Komentar`; `pkpt` Pkpt `PKPT`; `pkpt_item` PkptItem `Item PKPT`; `pkpt_perubahan` PkptPerubahan `Perubahan PKPT`; `pkpt_perubahan_item` PkptPerubahanItem `Operasi Perubahan PKPT`; `pkpt_usulan` PkptUsulan `Usulan Perubahan`; `user` User `Pengguna`; `pegawai` Pegawai `Pegawai`; `irban` Irban `Irban`; `irban_tahunan` IrbanTahunan `Komposisi Irban Tahunan`; `tim` Tim `Tim`; `tim_anggota` TimAnggota `Anggota Tim`; `objek` Objek `Objek Pemeriksaan`; `irban_wilayah` IrbanWilayah `Wilayah Irban`; `jenis_penugasan` JenisPenugasan `Jenis Penugasan`; `klasifikasi` Klasifikasi `Klasifikasi`; `landing_slide` LandingSlide `Slide Beranda`; `nomor_counter` NomorCounter `Nomor SPT`; `pengaturan` (no subject, log name `pengaturan`) `Pengaturan Sistem`; `impor` (no subject, log name `impor`) `Impor`. Unknown subject type → class basename.
- Event → label: `created` `Dibuat`; `updated` `Diubah`; `deleted` `Dihapus`; `impor` `Impor data`; `profil.foto` `Foto profil diubah`; `profil.latar` `Gambar latar diubah`; `profil.hapus_foto` `Foto profil dihapus`; `profil.hapus_latar` `Gambar latar dihapus`; `profil.kata_sandi` `Kata sandi diubah`. Unknown → the raw event.
- Impor `jenis` → label: `spt` `SPT data lama`; `pegawai` `Pegawai`; `pkpt` `Item PKPT`.
- Actor fallback: no causer → `Sistem`; no diinput_oleh → `—`.

### E3. SPT import template (Task 3), header row in this order
`nomor_spt`, `tanggal_spt`, `irban`, `klasifikasi`, `jenis_penugasan`, `uraian_untuk`, `periode_diperiksa`, `objek`, `tanggal_mulai`, `tanggal_selesai`, `nip_supervisor`, `nip_ketua`, `nip_anggota`, `nomor_lhp`, `tanggal_lhp`, `selesai`, `kendala_kategori`, `kendala_uraian`.
Text columns in the downloaded template (ArrayExport `textColumns`): `nomor_spt`, `nip_supervisor`, `nip_ketua`, `nip_anggota`, `nomor_lhp`. `MAX_ROWS = 2000`, file `.xlsx` ≤ 5 MB. Dates: Excel serial, `dd/mm/yyyy` or `yyyy-mm-dd` (same parser rule as `PkptItemImportParser::tanggal`; reuse it, move to a shared place if needed). List separator in `objek` and `nip_anggota`: `;`.

### E4. SPT import row rules and messages (Task 3)
`{x}` = the cell value as typed. Errors (row cannot be imported):
- `nomor_spt` empty → `Nomor SPT wajib diisi.`; > 255 chars → `Nomor SPT maksimal 255 karakter.`; exists in `spt.nomor_lengkap` → `Nomor SPT {x} sudah ada.`; repeated in file → on the later row `Nomor SPT {x} ganda dengan baris {n}.`; typed as number → `Nomor SPT tersimpan sebagai angka; ubah kolom menjadi teks.`
- `tanggal_spt`, `tanggal_mulai`, `tanggal_selesai` empty → `Tanggal SPT wajib diisi.` / `Tanggal mulai wajib diisi.` / `Tanggal selesai wajib diisi.`; unparseable → `Format tanggal tidak dikenali (gunakan dd/mm/yyyy).`; selesai < mulai → `Tanggal selesai tidak boleh sebelum tanggal mulai.`
- `irban` (nama or kode_nomor, case-insensitive): empty → `Irban wajib diisi.`; unknown → `Irban "{x}" tidak ditemukan.`
- `klasifikasi` (nama, case-insensitive): empty → `Klasifikasi wajib diisi.`; unknown → `Klasifikasi "{x}" tidak ditemukan.`
- `jenis_penugasan` optional; unknown → `Jenis penugasan "{x}" tidak ditemukan.`
- `uraian_untuk` empty → `Uraian wajib diisi.`; > 1000 → `Uraian maksimal 1000 karakter.`; `periode_diperiksa` > 255 → `Periode maksimal 255 karakter.`
- `objek` each part (trimmed, case-insensitive on `objek.nama`): none → `Objek "{x}" tidak ditemukan.`; more than one → `Objek "{x}" lebih dari satu; gunakan nama yang unik.`
- Personel: any NIP cell typed as number → `NIP tersimpan sebagai angka; ubah kolom menjadi teks.`; NIP not in `pegawai` (active or not) → `NIP {x} tidak ditemukan.`; any personnel given and `nip_ketua` empty or more than one → `Isi tepat satu NIP ketua.`; more than one supervisor → `Isi paling banyak satu NIP supervisor.`; same NIP twice → `NIP {x} tercantum lebih dari sekali.` Personnel all empty is allowed. Order/urutan: supervisor, ketua, anggota in cell order; `peran` from `PeranTim`; `sumber` `tambahan`, `tim_id` null.
- `nomor_lhp`/`tanggal_lhp` only one filled → `Nomor dan tanggal LHP diisi bersama.`; `tanggal_lhp` unparseable → date message above.
- `selesai` value not in `ya`, `tidak`, empty (case-insensitive) → `Kolom selesai diisi ya atau tidak.`
- `kendala_kategori`/`kendala_uraian` only one filled → `Kategori dan uraian kendala diisi bersama.`; kategori not in setting `kendala.kategori` → `Kategori kendala "{x}" tidak dikenal.`; uraian > 2000 → `Uraian kendala maksimal 2000 karakter.`
Warnings (row still imported):
- No PKPT item linked and `klasifikasi.wajib_item_pkpt` → `Tidak ada item PKPT yang cocok; SPT tercatat Non-PKPT.`; several candidates (any klasifikasi) → `Lebih dari satu item PKPT cocok; SPT tidak dikaitkan.`
- `nomor_urut` not readable → `Nomor urut tidak terbaca; SPT tidak tampil di Register.`
- Register collision risk (E5) → `Nomor urut {n} melebihi nomor terakhir register {tahun}; atur nomor awal di Register sebelum menerbitkan nomor baru.`
- Selesai while the klasifikasi `syarat_selesai` is `lhp_lengkap` and the LHP pair is empty → `SPT selesai tanpa nomor dan tanggal LHP.`
Several messages on one row are all listed. Status: `error` if any error, else `warning` if any warning, else `valid`.

### E5. Imported SPT columns (Task 3)
- `status_pengajuan` `diterima`, `sumber` `impor`, `dibuat_oleh` admin, `diinput_oleh` null, `diajukan_pada` = `tanggal_spt` 00:00 Jakarta, `tahun` = year of `tanggal_spt`, `nomor_lengkap` = `nomor_spt` trimmed, `nomor_counter_id` null.
- `nomor_urut`: the second `/`-separated segment of `nomor_spt` when it is all digits (`700/751/INSPEKTORAT-I/SPT/2024` → 751), else null.
- Register collision check: compute the counter key with `Penomoran::kunci($irban, $tahun)`; warn when `$tahun >= current year` (Jakarta) and `nomor_urut > (counter nomor_terakhir ?? 0)`.
- `ppj_pegawai_id` = `IrbanTahunan` (irban, year of `tanggal_mulai`) `ppj_pegawai_id`, else null.
- `pkpt_item_id`: candidates = `PkptItem::aktif()` of the irban, same `jenis_penugasan_id` (no jenis → no candidates), whose PKPT has `tahun` = year of `tanggal_mulai` and status Disahkan, and whose `KesesuaianPkpt::rentang($item)` overlaps `[tanggal_mulai, tanggal_selesai]` (inclusive). Exactly one → linked; else null.
- Selesai when `selesai` = `ya`, or `selesai` empty and the LHP pair is filled: `ditandai_selesai_pada` = `tanggal_lhp` (else `tanggal_selesai`) 00:00 Jakarta, `ditandai_selesai_oleh` admin. `nomor_lhp`, `tanggal_lhp` stored when given.
- `terlambat_diberitahu_pada` and `pengingat_final_pada` = now (so `notifikasi:harian` never fires for imported rows). No notification is sent by the import.
- Riwayat: one `spt_riwayat` row `kejadian` `diimpor` (new `KejadianSpt::DIIMPOR`, label `Diimpor dari data lama`), `nilai_baru` `{"nomor_lengkap": ..., "berkas": <file name>}`, `oleh` admin.
- Objek attached, personel created, kendala created (`diinput_oleh` null, `dibuat_oleh` admin) when given.

### E6. Routes, navigation, copy
- `GET /admin/log` → `admin.log` (Admin group); `GET /pimpinan/log` → `pimpinan.log` (Pimpinan group); same component `App\Livewire\LogAktivitas`. Pimpinan sidebar gets `Log Aktivitas` (icon `clipboard-document-list`) as its last item. Query params: `dari`, `sampai` (Y-m-d), `pengguna` (user id), `objek` (E2 key), `aksi` (`created`|`updated`|`deleted`|`lainnya`), `spt` (SPT id: the SPT and its personel/dokumen/kendala/komentar rows). 25 rows per page, newest first.
- `GET /admin/impor` → `admin.impor`, component `App\Livewire\Admin\Impor`, title `Impor Data Lama`. Template file `templat-impor-spt.xlsx`; error file `galat-impor-spt.xlsx` (columns `Baris`, `Kolom`, `Pesan`). Button `Impor {n} baris` where n = valid + warning rows (minus nothing; warnings import). Steps `Unggah`, `Pratinjau & Validasi`, `Impor`.
- Dashboard Admin: card `Impor terakhir` (value `{jumlah} baris`, caption `{jenis label} · {d F Y H.i} WIB`, or value `Belum ada`); list `Aktivitas terbaru` (10 newest, link `Lihat semua` → `admin.log`).
- SPT detail: card `Log Aktivitas` collapsed by default, only for Admin and Pimpinan, 20 newest rows of the `spt` filter, link `Lihat semua` → log page with `?spt={id}`.

---

### Task 1: Activity log foundation

**Files:**
- Modify: `composer.json`, `composer.lock` (`composer require spatie/laravel-activitylog:^4.12`)
- Create: `config/activitylog.php` (published; set `activity_model` => `App\Models\Aktivitas::class`), the published activitylog migrations, `database/migrations/2026_11_25_000001_add_diinput_oleh_ip_to_activity_log.php`
- Create: `app/Models/Aktivitas.php`, `app/Models/Concerns/DicatatAktivitas.php`, `app/Support/LabelAktivitas.php`
- Modify: the 22 models of E1; `app/Services/Settings.php` (`set()`); `app/Support/DiinputOleh.php`; `app/Actions/Profil/GantiKataSandi.php`, `HapusGambarProfil.php`, `SimpanGambarProfil.php`; every action under `app/Actions` that validates a `diinput_oleh` (grep `diinput_oleh`)
- Test: `tests/Feature/Log/LogAktivitasTest.php`

**Interfaces:**
- Produces: `App\Models\Aktivitas` (extends `Spatie\Activitylog\Models\Activity`; columns + `diinput_oleh` unsignedBigInteger nullable indexed, **no FK** (the log outlives master data), `ip` string(45) nullable; relation `diinputOleh()` → Pegawai; scope `untukSpt(Builder $q, int $sptId)` = subject Spt id OR subject in SptPersonel/SptDokumen/SptKendala/Komentar whose `spt_id` = id; static `catatImpor(User $user, string $jenis, string $berkas, int $jumlah): void` writing log name `impor`, event `impor`, properties `{jenis, berkas, jumlah}`; static `imporTerakhir(): ?self`).
- Produces: trait `DicatatAktivitas` (uses `LogsActivity`; options `logAll()`, `logExcept([...$this->getHidden(), 'created_at', 'updated_at', 'password', 'remember_token'])`, `logOnlyDirty()`, `dontSubmitEmptyLogs()`).
- Produces: `LabelAktivitas::objek(?string $subjectType, ?string $logName = null): string`, `aksi(string $event): string`, `jenisImpor(string $jenis): string`, `opsiObjek(): array<string,string>` (key → label, E2 order), `kelas(string $key): ?string`.
- Produces: `DiinputOleh::catat(?int $pegawaiId): void` = `Context::add('diinput_oleh', $id)` when not null.

- [ ] **Step 1: Write the failing tests** in `LogAktivitasTest`:
  - `test_model_penting_memakai_trait`: every E1 class uses `DicatatAktivitas` (`class_uses_recursive`).
  - `test_ubah_pegawai_mencatat_lama_dan_baru`: actingAs admin, update a pegawai's `jabatan` → one `Aktivitas` with `event` `updated`, `causer_id` admin, `properties['old']` and `['attributes']` contain only `jabatan`, `ip` `127.0.0.1` when done through a Livewire call on the Pegawai page.
  - `test_simpan_tanpa_perubahan_tidak_mencatat`: `save()` with no dirty attribute adds no row.
  - `test_kata_sandi_tidak_pernah_masuk_log`: update a user's password via `AturUlangKataSandi` and via Profil `GantiKataSandi` → no row's `properties` JSON contains `password` or `remember_token`; Profil writes an `Aktivitas` with event `profil.kata_sandi`, subject the user.
  - `test_diinput_oleh_tercatat_untuk_akun_irban`: Irban saves an SPT draft via `SimpanSpt` with `diinput_oleh` X → the Spt `created` row and its SptPersonel rows have `diinput_oleh` X.
  - `test_log_tidak_bisa_diubah_atau_dihapus`: `$a->update([...])` and `$a->delete()` each throw `LogicException`; row unchanged (put `expectException` right before the call, one test each).
  - `test_pengaturan_dicatat`: `Settings::set('penanda.batas_terlambat_hari', 9)` → row with log name `pengaturan`, `properties['old']['penanda.batas_terlambat_hari']` = previous value, `['attributes'][...]` = 9.
  - `test_label`: `LabelAktivitas::objek(Spt::class)` = `SPT`, `objek(null, 'pengaturan')` = `Pengaturan Sistem`, `aksi('profil.foto')` = `Foto profil diubah`, `aksi('x')` = `x`.
  - `test_untuk_spt_memuat_baris_anak`: rows for an Spt, its SptKendala and a Komentar are returned by `untukSpt`, a row of another SPT is not.
- [ ] **Step 2: Run** `php artisan test --filter=LogAktivitasTest` → FAIL (class not found).
- [ ] **Step 3: Install and implement.** `Aktivitas` boot: `creating` fills `ip` from `request()->ip()` (null when there is none, e.g. scheduler/queue), and `diinput_oleh` ??= `Context::get('diinput_oleh')` ?? the subject's `diinput_oleh` attribute when present; `updating`/`deleting` throw `LogicException('Log aktivitas tidak dapat diubah atau dihapus.')`. `Settings::set` logs only when the value changed. Replace the three Profil `Log::info` lines (and their `shortcut:` comments) with `activity()->performedOn($user)->causedBy($user)->event(...)->log(...)` after `DiinputOleh::catat($penginput)`. Call `DiinputOleh::catat()` in every action that accepts `diinput_oleh`, right after it is validated and before the writes.
- [ ] **Step 4: Run** the filter, then full suite + pint. Expected all green (seeders and factories now write log rows; that is intended).
- [ ] **Step 5:** decisions bullets (`## Fase 10, Task 1`: package version and why 4.x; no FK on `diinput_oleh`; pivot tables `spt_objek`/`irban_anggota` not logged), `graphify update .`, commit `Fase 10: Task 1, fondasi log aktivitas`.

### Task 2: Log Aktivitas screens (Admin, Pimpinan, SPT detail, Admin dashboard list)

**Files:**
- Create: `app/Livewire/LogAktivitas.php`, `resources/views/livewire/log-aktivitas.blade.php`, `resources/views/components/app/log-aktivitas-baris.blade.php` (one row + expandable diff, reused by page, SPT card and dashboard if useful)
- Modify: `routes/web.php`, `app/Support/Navigation.php`, `app/Providers/AppServiceProvider.php` (Gate `lihat-log`: Admin or Pimpinan), `app/Livewire/Spt/Detail.php` + `resources/views/livewire/spt/detail.blade.php`, `app/Livewire/Admin/Dasbor.php` + its view
- Test: `tests/Feature/Log/HalamanLogAktivitasTest.php`

**Interfaces:**
- Consumes: Task 1 `Aktivitas`, `untukSpt`, `LabelAktivitas::*`.
- Produces: route names `admin.log`, `pimpinan.log`; Gate `lihat-log`.

Design: `DESIGN.md`, `design/dashboard/_shared.md` (§6 filter bar, §7 SPT detail right column), `design/dashboard/admin.md` ("Log Aktivitas", "Dashboard"), `design/dashboard/pimpinan.md`. Invoke `ui-ux-pro-max:ui-ux-pro-max` first. Compact read-only table: Waktu (`d F Y H.i` WIB, tnum), Pengguna, Diinput oleh, Aksi, Objek (label + `#id`; SPT rows link to `spt.show` with the nomor or `#id`), IP. A disclosure button per row (`aria-expanded`, `aria-controls`) shows the diff as a table `Kolom | Lama | Baru` in the mono font; arrays/objects JSON-encoded, null `—`, long values wrap (no page overflow at 360). Filters in the URL with "Atur ulang" and active chips; filter selects: Pengguna (users who appear as causer), Objek (`opsiObjek()`), Aksi (Dibuat/Diubah/Dihapus/Lainnya), dari/sampai dates. Empty state `Belum ada aktivitas.` / with filters `Tidak ada aktivitas yang cocok dengan filter.` No edit/delete controls anywhere. No animation.

- [ ] **Step 1: Failing tests:**
  - `test_akses`: admin `GET /admin/log` 200, pimpinan `GET /pimpinan/log` 200; irban1, bagianumum 403 on both; admin on `/pimpinan/log` 403 (role middleware) — the Livewire action calls also re-check `lihat-log`.
  - `test_filter`: rows created for two users/objects/events on two dates; each of `pengguna`, `objek=pegawai`, `aksi=deleted`, `aksi=lainnya` (only non created/updated/deleted events), `dari`/`sampai`, `spt={id}` narrows to the expected rows (assert via `viewData`/`assertSee` unique markers).
  - `test_paginasi_25_terbaru_dulu`: 30 rows → page 1 shows the newest 25 in id desc order.
  - `test_diff_ditampilkan`: an `updated` row shows old and new values; `Sistem` shown when no causer; `—` when no diinput_oleh; pegawai name when set.
  - `test_tanpa_kontrol_hapus`: page HTML contains no `wire:click` to anything named hapus/delete and no "Hapus" button.
  - `test_kartu_log_di_detail_spt`: pimpinan on `/spt/{id}` sees `Log Aktivitas` with the SPT's row; irban1 owner does not see the card.
  - `test_dasbor_admin_aktivitas_terbaru`: 12 rows → dashboard shows exactly the 10 newest and `Lihat semua` to `admin.log`.
  - `test_navigasi_pimpinan`: Pimpinan sidebar contains `Log Aktivitas` linking `pimpinan.log`; Admin's still `admin.log`.
- [ ] **Step 2: Run** `php artisan test --filter=HalamanLogAktivitasTest` → FAIL.
- [ ] **Step 3: Implement** (eager-load causer, diinputOleh, subject; constant query count per page). Remove the "Fase 10" deferral comments in `spt/detail.blade.php` and the Dasbor docblock part about Aktivitas terbaru.
- [ ] **Step 4: Run** filter, full suite, pint, `npm run build`. Browser: `/admin/log` and `/pimpinan/log` (rows expanded), `/spt/{id}` as pimpinan with the card open, `/admin` — 360/768/1280, no horizontal page scroll, Tab order and focus ring on disclosures/filters.
- [ ] **Step 5:** decisions bullets, `graphify update .`, commit `Fase 10: Task 2, layar log aktivitas`.

### Task 3: SPT legacy import backend

**Files:**
- Create: `app/Services/Import/SptImportParser.php`, `app/Actions/Spt/ImporSpt.php`
- Modify: `app/Enums/KejadianSpt.php` (`DIIMPOR`), `app/Services/PenandaSpt.php` (skip `spt_final_belum_diupload` when `sumber` is `SumberSpt::Impor`), `app/Actions/MasterData/ImportPegawai.php` and `app/Actions/Pkpt/ImportPkptItems.php` (new trailing parameter `string $berkas = ''`, call `Aktivitas::catatImpor` after the transaction with jenis `pegawai` / `pkpt`), and their components to pass the file name
- Test: `tests/Feature/Impor/SptImportParserTest.php`, `tests/Feature/Impor/ImporSptTest.php`, plus one case in the existing Penanda test file

**Interfaces:**
- Consumes: Task 1 `Aktivitas::catatImpor`.
- Produces: `SptImportParser::HEADERS` (E3), `TEXT_COLUMNS`, `MAX_ROWS = 2000`, `read(string $path): array` (same contract as `PkptItemImportParser::read`, plus `{kolom}_numerik` flags for `nomor_spt`, `nip_*` cells read as int/float, like `PegawaiImportParser`), `analyze(array $raw): list<array{baris:int, data:array, status:'valid'|'warning'|'error', errors:list<array{kolom:string,pesan:string}>, warnings:list<string>}>` (normalizes client-tampered raw like the PKPT parser; `data` holds resolved ids plus display strings `irban`, `klasifikasi`, `jenis`, `nomor_spt`, `tanggal_mulai`, `tanggal_selesai`, `jumlah_personel`, `selesai` bool), `capMessage(): string`.
- Produces: `ImporSpt::handle(User $admin, array $rows, string $berkas): int` — authorizes `manage-master-data`, imports rows with status `valid` or `warning` in one `DB::transaction`, writes E5, then `Aktivitas::catatImpor($admin, 'spt', $berkas, $n)`; a unique-key violation on `nomor_lengkap` becomes `ValidationException` key `impor` message `Nomor SPT {nomor} sudah dipakai. Muat ulang pratinjau.` (nothing saved).

- [ ] **Step 1: Failing tests.** Build xlsx fixtures in the test with PhpSpreadsheet (helper writing E3 headers + rows, NIP/nomor as explicit strings unless the test needs numbers). Seed: irban I with kode `I`, klasifikasi rows (existing seeder/factory), jenis `Reviu LKjIP`, pegawai with known NIPs, objek `Kecamatan Lawang Kidul`, a Disahkan PKPT 2025 with one Irban I `Reviu LKjIP` item Mar 1–31 2025.
  - Parser: `test_header_wajib` (missing column → InvalidArgumentException listing it); `test_contoh_valid` (one fully filled row → `valid`, resolved ids, pkpt item linked); one test per E4 error message (data provider with the cell override and the exact message); `test_nip_angka` (numeric NIP cell → numeric message, never a pegawai match); `test_nomor_ganda_dalam_berkas` (`... ganda dengan baris 2.`); warnings: no PKPT match for Reguler, two candidate items, unreadable nomor urut, counter collision (current-year counter nomor_terakhir 5, imported nomor urut 10), selesai LHP-less for `lhp_lengkap`; `test_query_konstan` (200 valid rows analyze with ≤ 25 queries via `DB::enableQueryLog`); `test_raw_dirusak` (non-scalar cells and > MAX_ROWS rows handled like the PKPT parser).
  - Action: `test_impor_berkas_contoh` (3 rows: Reguler finished with LHP, personnel and two objek; Khusus not finished without personnel; one error row) → 2 SPT with every E5 column, personel order supervisor/ketua/anggota, objek attached, kendala saved, riwayat `diimpor`, `nomor_counter` table unchanged, `Notification::fake()` nothing sent, one `Aktivitas` event `impor` with `jumlah` 2; `test_impor_ulang_semua_galat` (same file analyzed after import → every row error `Nomor SPT ... sudah ada.`); `test_balapan_nomor` (insert an SPT with the same nomor after analyze, then handle → ValidationException `impor`, zero imported); `test_harian_tidak_memberi_tahu` (import a late unfinished SPT, `travelTo` next day 06.00, run `notifikasi:harian`, nothing sent); `test_hanya_admin` (pimpinan → AuthorizationException); `test_impor_pegawai_dan_pkpt_mencatat_ringkasan` (existing import actions write `impor` rows with jenis `pegawai` / `pkpt`).
  - Penanda: imported SPT past `tanggal_mulai` without final document → no `spt_final_belum_diupload`; a `sistem` SPT in the same state still has it.
- [ ] **Step 2: Run** `php artisan test --filter=Impor` → FAIL.
- [ ] **Step 3: Implement** (load irban, klasifikasi, jenis, objek names, pegawai by NIP for all NIPs in the file, existing `nomor_lengkap` for all nomor in the file, candidate PKPT items for the years in the file, counters — once per `analyze()`; personnel rules through `ValidatePersonel::cek()`).
- [ ] **Step 4: Run** filter, full suite, pint.
- [ ] **Step 5:** decisions bullets (`## Fase 10, Task 3`), `graphify update .`, commit `Fase 10: Task 3, impor SPT data lama (backend)`.

### Task 4: Impor Data Lama screen and Admin dashboard card

**Files:**
- Create: `app/Livewire/Admin/Impor.php`, `resources/views/livewire/admin/impor.blade.php`
- Modify: `routes/web.php` (`admin.impor`), `app/Livewire/Admin/Dasbor.php` + view (card `Impor terakhir`), `tests/Feature/DashboardShellTest.php` (it fakes `admin.impor` as a missing route: switch its "route not yet built" examples to a name that still does not exist, keep the assertions' intent), `lang/id/validation.php` (attribute for `file` if not already generic)
- Test: `tests/Feature/Impor/HalamanImporTest.php`

**Interfaces:**
- Consumes: Task 3 `SptImportParser`, `ImporSpt::handle`; Task 1 `Aktivitas::imporTerakhir`, `LabelAktivitas::jenisImpor`.

Design: `DESIGN.md`, `design/dashboard/_shared.md`, `design/dashboard/admin.md` ("Impor Excel", "Dashboard", "States"). Invoke `ui-ux-pro-max:ui-ux-pro-max` first. Copy the structure and markup conventions of `resources/views/livewire/admin/pkpt-impor.blade.php` (stepper, chips, row tints `bg-row-error`/`bg-row-warning`, per-row messages `Baris {n}: ...`, "Tampilkan hanya galat", "Lewati baris galat", error list download) with E6 copy. Step 1 also has a collapsed `Petunjuk kolom` (`<details>`) table: kolom, wajib (Ya/Tidak), format/contoh — one row per E3 header — and links to the existing `Impor pegawai` page and the PKPT list (item PKPT are imported per PKPT). Preview columns: Baris, Nomor SPT, Irban, Klasifikasi, Jadwal (`Tanggal::rentang`), Personel (count), Selesai (Ya/Tidak), Status/messages (errors and warnings each with icon). Step 3: success alert `{n} SPT berhasil diimpor.` with a link `Ke dasbor` (`admin.dashboard`) and button `Impor berkas lain` (Register is Bagian Umum only). The uploaded file is never stored (parse then drop, like `PkptImpor`).

- [ ] **Step 1: Failing tests:** `test_akses` (admin 200; pimpinan, irban1, bagianumum 403; Livewire actions re-check `manage-master-data`); `test_unduh_templat` (headers exactly E3, NIP/nomor columns text-formatted); `test_alur_unggah_pratinjau_impor` (Livewire upload of a generated xlsx with 2 valid, 1 warning, 1 error rows → step 2, counts `2 valid`, `1 peringatan`, `1 galat`; `impor` blocked while `lewati` false; with `lewati` true → step 3, 3 SPT saved, toast); `test_unduh_galat` (xlsx rows `Baris, Kolom, Pesan`); `test_raw_dirusak_lebih_dari_maks` (client sets 2001 raw rows → reset to step 1 with cap message); `test_toast_balapan` (ImporSpt `impor` ValidationException → error toast, stays on step 2); `test_kartu_impor_terakhir` (no import → `Belum ada`; after `Aktivitas::catatImpor(admin,'spt','a.xlsx',12)` → `12 baris` and `SPT data lama`).
- [ ] **Step 2: Run** `php artisan test --filter=HalamanImporTest` → FAIL.
- [ ] **Step 3: Implement.** Sidebar `Impor Excel` appears by itself once the route exists. Remove the Dasbor docblock deferral for Impor terakhir.
- [ ] **Step 4: Run** filter, full suite, pint, `npm run build`. Browser as admin: `/admin/impor` steps 1→3 with a real file containing errors and warnings (Petunjuk open, only-errors toggle), `/admin` card; 360/768/1280 without horizontal page scroll; keyboard through the stepper controls; focus visible.
- [ ] **Step 5:** decisions bullets, `graphify update .`, commit `Fase 10: Task 4, layar impor data lama`.

### Task 5: Phase close

**Files:** `decisions/fase-10.md`, `decisions/catatan-deploy.md`, `tests/Fixtures/impor-spt-contoh.xlsx` (optional, see step 2), nothing else unless a check fails.

- [ ] **Step 1:** Run `grep -rn "Fase 10" app/ resources/ tests/` → no deferral left (only historical text in decisions/plans).
- [ ] **Step 2:** "Impor file contoh" (PRD §12): add `tests/Feature/Impor/FileContohTest.php` that builds the example file from `SptImportParser::HEADERS` with 5 realistic rows matching the demo seed (`migrate:fresh --seed` data: Irban I, its pegawai NIPs, objek names) plus 2 bad rows, runs parser + action, asserts 5 imported and the 2 bad rows' exact messages. Also run it by hand in the browser once on fresh demo data.
- [ ] **Step 3:** MySQL/MariaDB run of the whole suite (`docs/plans/serah-terima.md` "Uji di MySQL"); record the count.
- [ ] **Step 4:** `decisions/catatan-deploy.md` section "Log aktivitas dan impor (Fase 10)": `php artisan migrate` adds `activity_log`; table only grows (no `activitylog:clean`, rough size per row; include it in backups, Fase 11); before importing legacy data into production set `Nomor awal` in Register for the current year after the import if warnings appeared; import is synchronous (≤ 2000 rows per file).
- [ ] **Step 5:** Full suite, pint, build; `graphify update .`; commit `Fase 10: Task 5, penutup fase`.
