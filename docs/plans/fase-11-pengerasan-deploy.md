# Plan: Fase 11 (Pengerasan dan deploy)

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Make SIMON-SPT safe and fast enough to hand over: every route and Livewire action is authorized, abuse-prone endpoints are rate limited, uploads are validated server-side, public aggregates are cached and invalidated on change, internal dashboards load in < 2 s on 5 years of data (±5.000 SPT), the database and files are backed up daily, `migrate:fresh --seed` gives a presentation-ready demo, and `DEPLOY.md` lets an operator install it on a VPS or shared host.

**Architecture:** No new subsystem. Hardening lands in the existing layers: Gates/Policies + `mount()`/action checks (Task 1), route middleware + `RateLimiter` + Laravel error views (Task 2), a measured optimization pass with a reusable 5-year data seeder (Task 3), a versioned cache wrapper `App\Support\CachePublik` used by `RealisasiPublik`/`JadwalPublik` (Task 4), `spatie/laravel-backup` scheduled in `routes/console.php` (Task 6), seeders + one artisan command (Task 7), and a Markdown deploy guide verified by a local production-like rehearsal (Task 8). Task 5 closes three questions left open in Fase 10.

**Tech Stack:** Laravel 13, Livewire 4, Alpine, Tailwind, MySQL 8 / MariaDB 10.6+, `spatie/laravel-backup` (new, Task 6).

**Spec:** `PRD-SIMON.md` §10 (Keamanan, Kinerja, Hosting, Cadangan), §11, §12 Fase 11 ("Audit Policy, rate limit, validasi upload, indeks database, cache publik, backup terjadwal. Panduan deploy (`DEPLOY.md`): kebutuhan server, cron, queue worker, SMTP. Data demo untuk presentasi. *Selesai jika:* semua test hijau, dan aplikasi berjalan di server staging."), §9.6, §14. Design: `DESIGN.md`, `design/dashboard/_shared.md`, `design/dashboard/irban.md`, `design/auth/login.md` (error pages reuse the auth shell). Rules: `CLAUDE.md`. Handoff: `docs/plans/serah-terima.md` ("Untuk Fase 11", "Catatan deploy"). Deploy notes so far: `decisions/catatan-deploy.md`. Plan-level rulings: `decisions/fase-11.md` (section "Plan").

Branch: `fase-11-pengerasan-deploy` (from `main` = `aa148d4`). **Never push.** Commit only. Baseline: 1015 tests green.

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `CLAUDE.md`, `design/`. `DESIGN.md` may be changed only by Task 5 (one row in §3.4, approved by the user on 2026-10-26).
- Graph: `graphify-out/graph.json` exists. Before your first grep/search/source read for a codebase question, run `graphify query "<question>"` (or `graphify explain "<X>"`, `graphify path "<A>" "<B>"`); read raw files only for lines the graph or an earlier read located. After changing code run `graphify update .`. Never stage `graphify-out/`.
- UI work (any Blade view/component, Tailwind, Alpine): first read `DESIGN.md` in full plus the design files named in the task, then invoke the skill `ui-ux-pro-max:ui-ux-pro-max` before writing markup (explicit user instruction; record its audit result in decisions). Only Tailwind tokens from DESIGN §13 and text/background pairs from §3.4; never §3.5 combinations; no raw hex in Blade. Status always icon + label. No animation on dashboard tables/forms (§7.5). UI copy Bahasa Indonesia; dates `translatedFormat('d F Y')`; timezone Asia/Jakarta. Reuse `x-ui.*`/`x-app.*`. In your summary, state which design files you followed.
- Stack: Blade + Livewire + Alpine + Tailwind + Chart.js. The only new package of the phase is `spatie/laravel-backup` (Task 6). No npm packages.
- Decisions: record every technical decision or deviation in `decisions/fase-11.md` under `## Fase 11, Task M` (create the heading if missing, append bullets, never rewrite older bullets; never edit an earlier phase's file except to fix it). `DECISIONS.md` is an index only (its Fase 11 line exists). Cross-phase deploy notes go to `decisions/catatan-deploy.md`.
- Business rules are never recomputed in components/views: SPT status via `Spt::status()`, flags via `PenandaSpt`, kesesuaian via `KesesuaianPkpt`, public data only via `RealisasiPublik`/`JadwalPublik`. An optimization (Task 3) may change *how* a rule is evaluated only inside its owning class, and the existing rule tests must stay green unchanged.
- Tests: `php artisan test --filter=<Name>` while iterating; before committing run the full suite `php artisan test`, `vendor/bin/pint --test` (fix with `vendor/bin/pint <files>`), and `npm run build` when views/JS changed. Old tests stay green unless the task explicitly rewrites them (say which in decisions). Tests must also run on MySQL (no SQLite-only SQL).
- Git: stage files one by one (never `git add -A`/`.`); never stage `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`, `storage/app/backup*`. Commit message prefix `Fase 11: `, ending with the line `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Browser checks (UI tasks): `php artisan serve` + `playwright-cli --config .playwright/cli.config.json` (see `docs/plans/serah-terima.md` "Cara kerja"), `php artisan migrate:fresh --seed` first, dev accounts `admin`, `pimpinan`, `irban1`, `bagianumum` (password `password`), screenshots to the scratchpad dir given in the dispatch. Check 360, 768, 1280 px: `document.documentElement.scrollWidth <= innerWidth`, keyboard reachability, visible focus ring. Stop the server by the PID listening on its port.
- MySQL/MariaDB: XAMPP on 3306, user `root` without password, `mysqldump` at `C:\xampp\mysql\bin\mysqldump.exe`. Throwaway databases only (`simon_uji`, `simon_kinerja`, `simon_rehearsal`); drop them when done.
- Log immutability (PRD §7.12) stays: nothing updates or deletes `activity_log` rows; backups include it.

### Jebakan yang sudah diketahui (dari `docs/plans/serah-terima.md`, lengkap)
Masing-masing pernah lolos ke review dan memakan satu putaran perbaikan.
- **Fokus setelah aksi:** bila modal ditutup lalu tombol pemicu/baris/kartu hilang atau menjadi nonaktif, pindahkan fokus ke target stabil (heading atau elemen dengan `tabindex="-1"`) lewat event yang di-dispatch dari komponen; jangan biarkan fokus jatuh ke `body`.
- **`sr-only` di dalam kontainer gulir:** sel/pembungkus yang memuat span `sr-only` di dalam `overflow-x-auto` wajib `relative`, kalau tidak halaman melebar di 360px.
- **"Diinput oleh":** selalu di footer form/modal (login.md §5), tidak di badan.
- **Aturan bisnis tidak dihitung ulang di komponen:** action/service menyediakan method non-throwing yang dipakai UI dan validasi sekaligus.
- **Galat non-kolom:** kunci `ValidationException` yang tidak punya field di form ditampilkan sebagai toast, tidak hilang diam-diam.
- **Lookup id selalu di-scope** ke irban pengguna, termasuk method pratinjau/peringatan, bukan hanya aksi simpan.
- **Zona waktu:** kolom tanggal/waktu disimpan dalam waktu Jakarta (APP_TIMEZONE); jangan `->utc()` saat membandingkan.
- **Ekspor xlsx:** pakai `App\Exports\ArrayExport`.
- **Carbon:** `parse('')` menghasilkan "sekarang"; tolak nilai kosong sebelum parse.
- **Livewire:** nama method tidak boleh sama dengan nama properti publik; properti id yang tidak boleh diubah klien diberi `#[Locked]`; otorisasi di `mount()` dan setiap aksi (endpoint update tidak menjalankan middleware route).
- **Factory dengan `unique()` tahun** bisa bentrok dengan tahun eksplisit di test; pakai rentang di luar tahun yang ditulis di test.
- **Penomoran:** `Penomoran::ambil` hanya di dalam `DB::transaction(..., attempts: 3)` dan selalu dengan klasifikasi.
- **Elemen sticky di dasbor:** offset ≥ `top-16`, `max-h` dikurangi 4rem.
- **Staging git:** stage file satu per satu.
- **Penundaan dari fase sebelumnya:** cari dan hapus sisa penundaan "Fase 11" (`grep -rn "Fase 11" app/ resources/ tests/ decisions/ docs/plans/serah-terima.md`); kode saat ini tidak punya, dokumen punya (Task 8).
- **Tab basi:** aksi yang ditolak karena status sudah berubah tampil sebagai toast lewat guard bersama, bukan 403, dan fokus pindah ke h1.
- **Pesan unggah Livewire:** batas sementara Livewire (12 MB) dan kegagalan unggah memakai `lang/id/validation.php`; field unggah baru wajib punya atribut di sana.
- **Notifikasi:** kirim dari action (setelah `DB::transaction`) atau command; penerima hanya dari `PenerimaNotifikasi`; kelas baru turunan `NotifikasiSimon`.
- **Alpine `$refs` setelah request Livewire:** ambil referensi target fokus sebelum `$wire.aksi()`.
- **Job antrean berdurasi panjang:** `failed()` wajib; `retry_after` > `timeout` job (660 > 600).
- **PDF dompdf ±0,26 MB per baris:** ekspor PDF besar lewat antrean.
- **Teks pengguna di view email Markdown:** wajib di-escape.
- **Morph Livewire menghapus atribut/keadaan sisi klien.**
- **Gambar unggahan:** batasi dimensi maksimum dan ubah gambar yang tidak bisa didekode menjadi galat field, bukan 500.
- **Tes harus jalan di MySQL juga:** jangan pakai SQL khusus SQLite; `expectException` tepat sebelum baris yang diuji.
- **Data publik hanya lewat `RealisasiPublik`/`JadwalPublik`:** komponen publik hanya memegang skalar; tambahkan penanda unik ke `PrivasiPublikTest` setiap kali kolom baru bisa mendekati halaman publik. Cache (Task 4) tidak boleh menyimpan model Eloquent atau kolom terlarang.
- **DDL di dalam `RefreshDatabase` pada MySQL meng-commit transaksi:** tes migrasi indeks (Task 3) tidak menjalankan DDL di tes.
- **Penulis yang tidak mengambil kunci yang diandalkan validasi ulang:** urutan kunci PKPT dulu.
- **Predikat aturan bisnis disalin ke view, dasbor, dan command:** satu method di model/service dipakai semua pemanggil.
- **Item basi dalam alur yang di-scope ke pengguna:** toast, bukan 404.
- **Rahasia sementara tertinggal di state Livewire:** reset segera; kata sandi/token tidak pernah masuk `properties` log atau keluaran command (Task 7 `simon:buat-admin`).
- **Penulisan massal query builder tidak tercatat di log aktivitas dan tidak memicu event model:** `->update()`/`->delete()` pada query melewati event; ini juga berlaku untuk invalidasi cache publik (Task 4) — tulis per model atau panggil penyegar secara eksplisit.
- **Tanggal di log tergeser ke UTC:** trait sudah menormalkan; jangan pakai `serializeDate` model.
- **Skill UI wajib dipanggil, bukan hanya "mengikuti pola".**
- **Aksi keamanan tanpa kolom yang berubah tidak tercatat:** tulis baris `activity()` eksplisit (berlaku juga untuk log pivot Task 5).
- **PRD menyisakan teks alur lama:** tidak relevan (PRD tidak diubah fase ini).

## Review Focus
1. **A Livewire action called directly by a user of the wrong role or another irban.** Every public action method refuses (403/`AuthorizationException`, or the existing toast guard) even when `mount()` was passed by a legitimate user and the payload is replayed by another. Tests in Task 1 (`OtorisasiAksiTest`).
2. **Stale public numbers after a change.** After BU accepts an SPT, an Irban marks one Selesai, Pimpinan approves a Perubahan PKPT, or Admin edits `publik.*` settings, the next public page view shows the new numbers without waiting for a TTL. Tests in Task 4.
3. **The cache leaking private data.** Cached payloads contain no NIP, personnel name, SPT number or objek below kecamatan, also when `publik.tampilkan_nomor_spt` is toggled after the cache was warmed. Test in Task 4 (`PrivasiPublikTest` markers run with a warm cache).
4. **A file that lies about its type.** A `.pdf` that is really a PHP/HTML file, an `.xlsx` that is a renamed CSV, a 13 MB upload when `upload.maks_mb` would allow it: each is a field error, never stored, never a 500. Tests in Task 2.
5. **First production install.** `php artisan db:seed --force` with `APP_ENV=production` writes no demo PKPT/SPT/akun; the operator can create the first Admin with `simon:buat-admin` without the password appearing in shell history output or logs. Tests in Task 7.

## Exact values

### E1. Rate limits (Task 2)
- Existing, keep: login 5 attempts / 60 s (`AttemptLogin`); `throttle:6,1` on `password.email` and `password.update`.
- Named limiter `publik`: 120 requests per minute per IP, on `beranda`, `publik.jadwal`, `publik.realisasi`.
- Ekspor: 10 export requests per minute per user (key `ekspor:{user_id}`), checked where an `Ekspor` row is created; refusal = toast with `Terlalu banyak permintaan ekspor. Coba lagi dalam :seconds detik.`
- Ganti email (Profil `MintaGantiEmail`): 3 per 10 minutes per user (key `ganti-email:{user_id}`); refusal = field error on the email field `Terlalu banyak permintaan. Coba lagi dalam :seconds detik.`
- HTTP 429 renders `resources/views/errors/429.blade.php` (E3).

### E2. Upload rules (Task 2)
- `upload.maks_mb` rule becomes `max:12` (Livewire's temporary upload limit is 12 MB, so a higher value could never be honoured). A data migration (no DDL) clamps an already stored value above 12 to 12.
- PDF (dokumen SPT, cover LHP, SK Perubahan): extension `pdf` AND sniffed MIME `application/pdf` AND the first 5 bytes `%PDF-` AND size ≤ `upload.maks_mb` MB. Keep the single check in `App\Support\BerkasSpt::pdf()`.
- xlsx (Impor SPT/Pegawai/PKPT): `mimes:xlsx` plus sniffed MIME in `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `application/zip`, `application/octet-stream`, max 5120 KB; parser failure stays the existing field error.
- Images (slide, profil): unchanged (already decode-checked); only confirm by test.

### E3. Error pages (Task 2)
Views `resources/views/errors/{403,404,419,429,500,503}.blade.php` on one shared layout (reuse the auth shell from `design/auth/login.md`: logo, card, no sidebar), h1 + one sentence + one button:
- 403 `Akses ditolak` / `Anda tidak memiliki izin untuk membuka halaman ini.` / `Kembali ke beranda` (→ `dashboard` when logged in, `beranda` otherwise)
- 404 `Halaman tidak ditemukan` / `Alamat yang Anda buka tidak ada atau sudah dipindahkan.` / same button
- 419 `Sesi kedaluwarsa` / `Halaman terlalu lama dibiarkan terbuka. Muat ulang lalu coba lagi.` / `Muat ulang`
- 429 `Terlalu banyak permintaan` / `Tunggu sebentar sebelum mencoba lagi.` / `Kembali ke beranda`
- 500 `Terjadi kesalahan` / `Sistem mengalami gangguan. Coba lagi beberapa saat lagi.` / `Kembali ke beranda` (no auth lookups, no DB queries in this view)
- 503 `Sedang dalam pemeliharaan` / `SIMON-SPT sedang diperbarui. Silakan kembali beberapa saat lagi.` / no button
Document title `{judul} · SIMON-SPT`.

### E4. Security headers (Task 2)
Middleware `App\Http\Middleware\HeaderKeamanan` appended to the `web` group: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`. No CSP (Alpine/Livewire need eval-free review first; out of scope). `trustProxies`: `$middleware->trustProxies(at: env('TRUSTED_PROXIES'))` style, reading a comma list or `*` from config `app.trusted_proxies` (env `TRUSTED_PROXIES`, default null = trust none).

### E5. Performance target (Task 3)
Data: `Database\Seeders\KinerjaSeeder` (never called by `DatabaseSeeder`): 5 irban, years 2022–2026, 1.000 accepted SPT per year (5.000 total) spread over all irban/klasifikasi/jenis, ±80 % linked to PKPT items (Disahkan PKPT per year, 24 items per irban per year), statuses mixed like real data for past years (≥ 90 % Selesai) and the current year (all statuses incl. Terlambat), 1–3 objek and 3–6 personel per SPT, dokumen rows (no files), ±300 komentar, ±2.000 `activity_log` rows.
Measured on MariaDB (`simon_kinerja`), `APP_ENV=production`, `APP_DEBUG=false`, `php artisan optimize`, `php artisan serve`, warm second request, 5 runs, report the median of full GET time:
`/pimpinan`, `/pimpinan/monitoring` (default filter), `/pimpinan/monitoring?penanda=terlambat`, `/pimpinan/rekap`, `/irban` (irban1), `/irban/spt` (SPT Saya), `/bagian-umum`, `/bagian-umum/register`, `/admin`, `/admin/log`, `/`, `/jadwal`, `/realisasi`. Target: every median < 2,0 s; dashboards (`/pimpinan`, `/irban`, `/bagian-umum`, `/admin`) < 1,0 s.

### E6. Public cache (Task 4)
- `App\Support\CachePublik::ingat(string $nama, array $argumen, Closure $hitung): mixed` → `Cache::remember("publik:{versi}:{nama}:".md5(json_encode($argumen)), 3600, $hitung)`; `versi()` = `Cache::get('publik:versi', 1)`; `segarkan(): void` = `Cache::forever('publik:versi', versi() + 1)` (old keys expire by TTL).
- Cached: `RealisasiPublik::ringkasan`, `perBulan`, `proporsi`, `rencanaVsRealisasi`, `opsi`; `JadwalPublik::item`, `wilayah`. Not cached: `tabel` (paginated), `basis`, `objekSpt`, `labelWilayah`, `itemDisahkan` (internal callers like `KalenderPkpt` must stay uncached). Cached values are arrays/scalars/Carbon only, never Eloquent models.
- `segarkan()` runs on `saved`/`deleted` of `Spt`, `Pkpt`, `PkptItem`, `PkptPerubahan`, `Objek`, `IrbanWilayah`, `Irban`, `JenisPenugasan`, `Klasifikasi` (trait `App\Models\Concerns\MenyegarkanCachePublik`, `afterCommit` semantics: call through `DB::afterCommit`), after the `spt_objek` sync/attach in `SimpanSpt` and `ImporSpt`, and in `Settings::set` for keys starting with `publik.`. Also once per day: `Schedule::call(fn () => CachePublik::segarkan())->dailyAt('00:00')->timezone('Asia/Jakarta')` (date-dependent values such as "Hari ini" and statuses roll over).

### E7. Pivot log (Task 5)
- `SimpanSpt` (only when the objek set changed, including from empty on create): `activity()->performedOn($spt)->causedBy($user)->event('updated')->withProperties(['old' => ['objek' => <names sorted>], 'attributes' => ['objek' => <names sorted>]])->log('updated')`, after `DiinputOleh::catat()`. `ImporSpt` does not log pivots (Fase 10 volume ruling).
- `SaveIrbanTahunan`: same shape with key `anggota` (pegawai names sorted), subject the `IrbanTahunan` row.
- The diff renders through the existing `x-app.log-aktivitas-baris` (arrays are JSON-encoded there today); render lists as `, `-joined names instead for these two keys only if that is a one-line change in the shared row, else keep JSON and record it.

### E8. RSUD in Wilayah (Task 5)
- `WilayahPengawasan::untuk` returns a fourth list `rsud` (no longer merged into `dinas_badan`); `JadwalPublik::wilayah` keeps the public three columns by merging `rsud` into `Dinas/Badan` itself (design/public/jadwal.md fixes three columns).
- Irban panel (`resources/views/livewire/irban/partials/wilayah.blade.php`): four lists `Kecamatan`, `Dinas/Badan`, `BUMD`, `RSUD`; the note becomes `Puskesmas, Desa, BUMDes, dan Sekolah mengikuti wilayah kecamatan.`

### E9. DESIGN pair (Task 5)
Add to `DESIGN.md` §3.4 after the `mint-700 | mint-100` row: `| mint-700 | neutral-50 | 5.25 | links in hovered/striped table rows and table headers |`. Verify the ratio with a script (sRGB relative luminance of `#17735B` and `#F1F5F4`); write the computed value with two decimals if it differs from 5.25 (and stop if it is below 4.5). Record in `decisions/fase-11.md` that this pair now covers the `x-ui.table` hover rows with mint-700 links kept in Fase 10; do not edit `decisions/fase-10.md` and do not change markup.

### E10. Backup (Task 6)
- `composer require spatie/laravel-backup` at the newest major whose requirements allow `illuminate/*` ^13 (check `composer show spatie/laravel-backup --all`); publish `config/backup.php`.
- `backup.name` = `env('APP_NAME')`; source files `storage/app/private`, `storage/app/public`; exclude `storage/app/private/ekspor`, `storage/app/private/livewire-tmp`, `storage/app/backup`; databases `[config('database.default')]`; destination disk `backup` (new disk in `config/filesystems.php`: local driver, root `storage_path('app/backup')`, not public); filename prefix `simon-`.
- Cleanup: keep all backups 7 days, daily 30 days, weekly 8 weeks, monthly 12 months; delete oldest when > 5000 MB.
- Notifications: mail to `env('BACKUP_MAIL_TO')`; when it is empty, no notification channel (do not crash).
- Schedule (WIB, `withoutOverlapping`): `backup:clean` 01.00, `backup:run` 01.30, `backup:monitor` 08.00. Monitor: newest backup ≤ 1 day old, total ≤ 5000 MB.
- MySQL dump binary path from env `DB_DUMP_BINARY_PATH` (config `database.connections.mysql.dump.dump_binary_path`, likewise `mariadb`), default unset.

### E11. Demo data (Task 7)
After `php artisan migrate:fresh --seed` (non-production):
- Accounts (password `password`): `admin`, `pimpinan`, `bagianumum`, `irban1` … `irban5` (each linked to Irban I … V).
- PKPT 2025 Disahkan and PKPT 2026 Disahkan, items for all five irban; one Perubahan PKPT 2026 Disetujui (moves one item) and one Diajukan (waiting for Pimpinan).
- 2025: ≥ 8 accepted SPT per irban, almost all Selesai with LHP (public `/realisasi?tahun=2025` non-empty for every irban and every chart).
- 2026: every irban has SPT in each status Draf, Menunggu, Perlu Revisi, Ditolak, Siap Dilaksanakan, Pelaksanaan, Penyusunan Laporan, Terlambat, Selesai; at least one Tidak Sesuai PKPT with kendala, one Non-PKPT (Khusus), one open Minta Penjelasan, unread notifications for each role.
- Keep everything the current seeders create; extend, do not replace (existing tests depend on current Irban I data).
- `APP_ENV=production`: `DatabaseSeeder` runs only reference seeders (Irban, Klasifikasi if seeded, Objek, Jenis Penugasan); `TimSeeder`, `IrbanWilayahSeeder`, `PkptSeeder`, `PkptIrbanIDemo2026Seeder` and every demo seeder are skipped. If a skipped seeder actually holds real reference data, keep it in production and record why.
- Command `simon:buat-admin {username} {--nama=} {--email=}`: asks the password twice with hidden input (`secret`), validates through the same rules as `SimpanPengguna` (username regex, unique, password policy), creates an Admin with `wajib_ganti_sandi` false, prints `Admin {username} dibuat.`; refuses with exit 1 and the validation messages otherwise; never echoes the password.

### E12. DEPLOY.md (Task 8)
Root file `DEPLOY.md`, Bahasa Indonesia, sections in this order: `Kebutuhan server` (PHP ≥ 8.3 with extensions pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, fileinfo, bcmath, zip, gd (WebP), exif, intl; MySQL 8 or MariaDB 10.6+; `mysqldump`; Composer 2; Node 20+ only on the build machine; RAM ≥ 1 GB, ±512 MB per export worker), `Instalasi pertama` (numbered commands incl. `composer install --no-dev --optimize-autoloader`, `npm ci && npm run build`, `.env`, `key:generate`, `migrate --force`, `db:seed --force`, `simon:buat-admin`, `storage:link`, `optimize`), `Konfigurasi .env` (table of every key the app reads that differs from Laravel defaults, with production value), `Cron dan scheduler` (the cron line and every scheduled job with its WIB time), `Queue worker` (supervisor config + shared-hosting cron alternative, `DB_QUEUE_RETRY_AFTER`), `Email (SMTP)`, `Cadangan dan pemulihan` (what is backed up, where, retention, how to copy off-server, restore steps for DB and files, test restore once), `Reverse proxy dan HTTPS` (`TRUSTED_PROXIES`, `SESSION_SECURE_COOKIE=true`, `APP_URL` https), `Batas PHP dan web server` (upload, memory, timeouts from catatan-deploy), `Memperbarui aplikasi` (maintenance mode, pull, install, migrate, `optimize`, `queue:restart`, up), `Migrasi khusus` (irreversible Fase 9.2 migration, scheduler idempotence SQL, legacy import counter note), `Data demo` (accounts, `migrate:fresh --seed` never in production, a 10-step presentation script through the four roles and the public pages), `Pemeriksaan setelah deploy` (checklist).

---

### Task 1: Authorization audit

**Files:**
- Create: `tests/Feature/Keamanan/OtorisasiRuteTest.php`, `tests/Feature/Keamanan/OtorisasiAksiTest.php`
- Modify: any `app/Livewire/**`, `app/Http/Controllers/**`, `app/Policies/**`, `app/Providers/AppServiceProvider.php` where the audit finds a gap (each gap = one bullet in decisions with file and fix)

**Interfaces:**
- Produces: nothing new for later tasks; only fixes.

- [ ] **Step 1: Write the route matrix test** `OtorisasiRuteTest`:
  - `test_setiap_rute_internal_butuh_login`: iterate `Route::getRoutes()`; every route except the allowlist (`beranda`, `publik.jadwal`, `publik.realisasi`, `login`, `password.*`, `livewire.*`, `storage.*`, `up`, signed `profil.email.verifikasi` only inside `auth`) has the `auth` middleware. A new unauthenticated route fails this test.
  - `test_matriks_peran` (data provider): for each GET route without parameters and each role (admin, pimpinan, irban1, bagianumum) assert 200 for the owning role and 403 for the others; the expected owner comes from the route-name prefix (`admin.` → admin, `pimpinan.` → pimpinan, `irban.` → irban, `bagian-umum.` → bagian_umum), shared routes (`profil`, `notifikasi.index`, `dashboard` redirect) 200/302 for all. Plus `irban.rekap`/`pimpinan.rekap` per their middleware.
  - `test_rute_berparameter_dicek_kepemilikan`: `spt.show`, `spt.dokumen`, `ekspor.unduh`, `perubahan-pkpt.sk`, `admin.pkpt.show`, `pimpinan.perubahan-pkpt.show`: an irban of Irban II gets 403 on Irban I's SPT/dokumen; a user gets 403 on another user's ekspor; a guest is redirected to `login`.
- [ ] **Step 2: Audit every Livewire component** (40 files under `app/Livewire`): list each public method that changes state or returns data, and confirm it authorizes (Gate/Policy/`izinkan`/role check) independent of `mount()`, scopes ids to the user's irban, and that client-writable id properties are `#[Locked]`. Write `OtorisasiAksiTest` with one test per component group that calls the state-changing actions as a wrong-role user (mount as the rightful user with `Livewire::actingAs($pemilik)->test(...)`, then `$this->actingAs($lain)` before calling the action, so the replayed call runs as the other user; for unlocked id properties also `->set()` another irban's id) and asserts 403/`AuthorizationException` or the toast guard. Cover at least: Admin master data saves/deletes (as pimpinan), `BagianUmum\Pengajuan` terima/tolak (as irban), `Irban\SptForm` simpan/ajukan with another irban's SPT id, `Spt\Detail` tandai selesai/unggah as another irban, `Spt\Komentar` tutup as irban, `Pimpinan\PerubahanPkptDetail` putuskan as admin, `Admin\Pengguna` atur ulang as pimpinan, `LogAktivitas` as irban, `Rekap` ekspor of another irban, `Notifikasi\Daftar` mark another user's notification.
- [ ] **Step 3: Run** `php artisan test --filter=Otorisasi` → failures name the gaps (or all green if none; then the tests are the proof).
- [ ] **Step 4: Fix each gap** in its component/controller/policy (smallest change, existing guard style). Policies: confirm every model reachable by id from a route or Livewire action has a policy or a Gate check; record the list in decisions.
- [ ] **Step 5: Run** the filter, full suite, pint. Decisions bullets (`## Fase 11, Task 1`: audit table "komponen → cek → temuan"), `graphify update .`, commit `Fase 11: Task 1, audit otorisasi`.

### Task 2: Rate limits, upload validation, error pages, security headers

**Files:**
- Create: `app/Http/Middleware/HeaderKeamanan.php`, `resources/views/errors/{403,404,419,429,500,503}.blade.php` (+ one layout component if the auth shell cannot be reused directly), `database/migrations/2026_12_01_000001_batasi_upload_maks_mb.php` (data only), `tests/Feature/Keamanan/RateLimitTest.php`, `tests/Feature/Keamanan/ValidasiUnggahTest.php`, `tests/Feature/Keamanan/HalamanGalatTest.php`
- Modify: `bootstrap/app.php` (middleware, trustProxies), `config/app.php` (`trusted_proxies`), `app/Providers/AppServiceProvider.php` (limiter `publik`), `routes/web.php`, `app/Services/Settings.php` (rule), `app/Support/BerkasSpt.php`, the three import components (`Admin\Impor`, `Admin\PegawaiImpor`, `Admin\PkptImpor`), the export entry point (find with `graphify query "Ekspor create row antrean"`), `app/Actions/Profil/MintaGantiEmail.php`, `.env.example` (`TRUSTED_PROXIES=`)

**Interfaces:**
- Consumes: none.
- Produces: limiter name `publik`; middleware `HeaderKeamanan`; config key `app.trusted_proxies`.

Design: `DESIGN.md`, `design/auth/login.md` (shell for error pages). Invoke `ui-ux-pro-max:ui-ux-pro-max` before the error views.

- [ ] **Step 1: Failing tests:**
  - `RateLimitTest`: `test_publik_dibatasi` (121st GET `/jadwal` within a minute from one IP → 429 and the E3 text; another IP 200); `test_ekspor_dibatasi` (11th export request in a minute → toast with E1 message, no 11th `Ekspor` row); `test_ganti_email_dibatasi` (4th request in 10 min → E1 field error, no mail queued); `test_login_tetap_5_kali` (existing behavior regression, 6th attempt refused).
  - `ValidasiUnggahTest` (data provider over dokumen SPT, cover LHP, SK Perubahan): a `.pdf` whose content is `<?php echo 1;` → field error, nothing on disk; an HTML file renamed `.pdf` → error; a real minimal PDF over `upload.maks_mb` → error; a valid PDF → stored. xlsx: a CSV renamed `.xlsx` on each import page → field error `Berkas tidak dapat dibaca. Pastikan berkas .xlsx yang valid.` or the mimes message, never 500. Settings: saving `upload.maks_mb = 13` → validation error; the data migration clamps a stored 50 to 12 (call the migration's `up()` on a seeded settings row; no DDL).
  - `HalamanGalatTest`: each code (abort 403/404/419/429/500/503 through a test-only route registered in the test) renders its E3 h1 and button target, title `{judul} · SIMON-SPT`, lang `id`; 500 view renders with the DB connection broken (`config(['database.default' => 'tidak_ada'])` inside the route).
  - `test_header_keamanan`: GET `/` and `/masuk` carry the four E4 headers.
  - `test_trusted_proxies`: with `config(['app.trusted_proxies' => '*'])` and header `X-Forwarded-For: 203.0.113.9`, `request()->ip()` inside a logged action is `203.0.113.9` (assert through an `Aktivitas` row's `ip`); without the config it stays `127.0.0.1`.
- [ ] **Step 2: Run** `php artisan test --filter="RateLimitTest|ValidasiUnggahTest|HalamanGalatTest"` → FAIL.
- [ ] **Step 3: Implement** E1–E4. `BerkasSpt::pdf()` adds the `%PDF-` magic check (read 5 bytes). Throttle refusals inside Livewire go through the existing toast mechanism (galat non-kolom), not a 429 page.
- [ ] **Step 4: Run** filter, full suite, pint, `npm run build`. Browser: each error page at 360/768/1280 (reach them through a temporary route or `abort` in tinker-served route only in your local session, not committed), focus ring on the button.
- [ ] **Step 5:** decisions bullets, `decisions/catatan-deploy.md` section `Pengerasan (Fase 11)` (TRUSTED_PROXIES, rate limits, upload cap 12 MB), `graphify update .`, commit `Fase 11: Task 2, rate limit, validasi unggah, halaman galat`.

### Task 3: Performance to < 2 s on 5 years of data, database indexes

**Files:**
- Create: `database/seeders/KinerjaSeeder.php`, `database/migrations/2026_12_01_000002_tambah_indeks_kinerja.php` (only indexes proven by EXPLAIN), `tests/Feature/Kinerja/KinerjaDasborTest.php`
- Modify: whatever the measurements point to, inside the owning classes: likely `app/Services/MonitoringSpt.php`, `app/Services/PenandaSpt.php`, `app/Models/Spt.php` (`status()`), `app/Services/KesesuaianPkpt.php`, the dashboard components (`app/Livewire/*/Dasbor.php`), `app/Livewire/Irban/SptSaya.php`, `app/Livewire/BagianUmum/Register.php`

**Interfaces:**
- Consumes: none.
- Produces: `KinerjaSeeder` (used again by Task 4 for the public measurement and by Task 8's rehearsal).

Known from `decisions/fase-8.md`: per-row cost ±0,6 ms sits in Eloquent date casts (±35 µs per access of `tanggal_mulai`/`tanggal_selesai`/`ditandai_selesai_pada`, called repeatedly by status, kesesuaian and penanda) and `now()` per row in `MonitoringSpt::bentukBaris`; dashboards count via `MonitoringSpt::jumlah` which loads every row for flag-based counts. The rule tests (`PenandaSptTest`, `StatusSpt*`, `KesesuaianPkpt*`, `MonitoringSptTest`, `DasborPimpinanTest`) are the safety net: they must pass unchanged.

- [ ] **Step 1: Build `KinerjaSeeder`** per E5 (bulk inserts in chunks of 500 via query builder is fine here: seed data, not app writes; `activity_log` rows inserted directly). Seed `simon_kinerja` on MariaDB and record row counts.
- [ ] **Step 2: Measure the baseline** per E5 (script in the scratchpad: `curl -s -o /dev/null -w "%{time_total}"` with a logged-in cookie jar per role, 5 runs, median). Record the table (page → median s → query count from `DB::listen` in a local debug run) in decisions. Also run `EXPLAIN` on the 5 slowest queries.
- [ ] **Step 3: Write `KinerjaDasborTest`** (SQLite, structural, not wall-clock): with 300 SPT over 3 years, `test_jumlah_query_konstan` for `/pimpinan`, `/pimpinan/monitoring`, `/irban`, `/bagian-umum` (query count with 300 SPT == with 30 SPT, via `DB::enableQueryLog`); `test_hasil_sama` (dashboard card numbers and monitoring rows for a fixed `travelTo` date equal the values computed before the optimization: capture them first from the current code in the test's expected arrays).
- [ ] **Step 4: Optimize** until every E5 median meets the target. Order of attack: (a) read each date cast once per row (e.g. raw attribute strings compared as `Y-m-d`) inside `Spt::status`/`PenandaSpt`/`KesesuaianPkpt`; (b) today's date once per batch in `MonitoringSpt`; (c) SQL counts for dashboard cards where the rule is expressible in SQL through an existing scope (`berstatus`) and stays one definition; (d) indexes from EXPLAIN. Re-measure after each step; stop when the target holds with margin.
- [ ] **Step 5: Run** full suite, pint; MariaDB run of the touched test files. Decisions bullets with before/after table, `graphify update .`, commit `Fase 11: Task 3, kinerja dasbor dan indeks`.

### Task 4: Public aggregate cache

**Files:**
- Create: `app/Support/CachePublik.php`, `app/Models/Concerns/MenyegarkanCachePublik.php`, `tests/Feature/Publik/CachePublikTest.php`
- Modify: `app/Services/Publik/RealisasiPublik.php`, `app/Services/Publik/JadwalPublik.php`, the models listed in E6, `app/Actions/Spt/SimpanSpt.php`, `app/Actions/Spt/ImporSpt.php`, `app/Services/Settings.php`, `routes/console.php`, `tests/Feature/Publik/PrivasiPublikTest.php`

**Interfaces:**
- Consumes: `KinerjaSeeder` (Task 3) for the measurement.
- Produces: `CachePublik::ingat(string $nama, array $argumen, Closure $hitung): mixed`, `CachePublik::segarkan(): void`, `CachePublik::versi(): int`; trait `MenyegarkanCachePublik`. Task 5 relies on `SimpanSpt` already calling `segarkan()` after the objek sync.

- [ ] **Step 1: Failing tests** in `CachePublikTest` (`config(['cache.default' => 'array'])` explicitly):
  - `test_agregat_disimpan`: second call of `ringkasan($f)` runs no query (`DB::enableQueryLog`).
  - One test per E6 trigger, each warming `/realisasi` and `/jadwal` first and asserting the new number appears on the next GET: BU accepts an SPT (`TerimaPengajuan`), Irban `TandaiSelesai`, Pimpinan approves a Perubahan PKPT moving an item (`PutuskanPerubahan`), Admin edits an objek name, Admin `Settings::set('publik.tampilkan_nama_objek', true)`, objek sync on `SimpanSpt` for an accepted SPT (`UbahTanggalRealisasi` path if objek can only change there; follow the actual flow).
  - `test_rollback_tidak_menyegarkan`: an exception inside the transaction after `Spt::save()` leaves `versi()` unchanged.
  - `test_pergantian_hari`: warm cache, `travelTo` next day, run the scheduled closure (`$this->artisan('schedule:run')` at 00:00 Jakarta) → `versi()` incremented.
  - `PrivasiPublikTest`: run the existing marker assertions twice, the second time with a warm cache and after toggling `publik.tampilkan_nomor_spt` false→true→false; also assert that `Cache` payloads (`Cache::get` of the keys) serialize without `App\Models\` class names.
- [ ] **Step 2: Run** `php artisan test --filter="CachePublikTest|PrivasiPublikTest"` → FAIL.
- [ ] **Step 3: Implement** E6. Grep for query-builder mass writes on the E6 tables (`->update(`, `->delete(`, `DB::table('spt')`) and add an explicit `CachePublik::segarkan()` where they bypass model events.
- [ ] **Step 4: Run** filter, full suite, pint. Measure `/`, `/jadwal`, `/realisasi` cold and warm on `simon_kinerja` (E5 method) and record.
- [ ] **Step 5:** decisions bullets (`decisions/catatan-deploy.md`: shared `CACHE_STORE` is now required for public freshness too), `graphify update .`, commit `Fase 11: Task 4, cache agregat publik`.

### Task 5: Fase 10 leftovers — pivot log, RSUD in Wilayah, DESIGN pair

**Files:**
- Modify: `app/Actions/Spt/SimpanSpt.php`, `app/Actions/MasterData/SaveIrbanTahunan.php`, possibly `resources/views/components/app/log-aktivitas-baris.blade.php` (E7 last bullet), `app/Services/WilayahPengawasan.php`, `app/Services/Publik/JadwalPublik.php`, `resources/views/livewire/irban/partials/wilayah.blade.php`, `DESIGN.md` (§3.4 one row)
- Test: `tests/Feature/Log/LogPivotTest.php`, existing `WilayahPengawasan`/`JadwalPublik`/Irban PKPT page tests (extend)

**Interfaces:**
- Consumes: Task 4 (`SimpanSpt` already calls `CachePublik::segarkan()` after the sync; keep it).

Design: `DESIGN.md`, `design/dashboard/_shared.md`, `design/dashboard/irban.md`, `design/public/jadwal.md` (three public columns stay). Invoke `ui-ux-pro-max:ui-ux-pro-max` before changing the panel.

- [ ] **Step 1: Failing tests:**
  - `LogPivotTest`: `test_objek_spt_dicatat` (draft with objek A, then A+B → second save writes one `updated` row whose properties are `old.objek = ['A']`, `attributes.objek = ['A','B']`; causer and `diinput_oleh` set); `test_objek_tidak_berubah_tidak_dicatat`; `test_anggota_irban_tahunan_dicatat` (same shape, key `anggota`); `test_impor_tidak_mencatat_pivot`.
  - `WilayahPengawasan`: an RSUD objek lands in `rsud`, not `dinas_badan`; `JadwalPublik::wilayah` still lists it under `Dinas/Badan`.
  - Irban PKPT page: panel shows heading `RSUD` with the objek name and the new note text exactly (E8).
- [ ] **Step 2: Run** `php artisan test --filter="LogPivotTest|Wilayah"` → FAIL.
- [ ] **Step 3: Implement** E7, E8, E9 (compute and print the ratio for E9 with a short PHP one-liner; paste the command and output into decisions).
- [ ] **Step 4: Run** filter, full suite, pint, `npm run build`. Browser: `/irban/pkpt` as irban1 at 360/768/1280 with the panel open; `/admin/log` with a pivot row expanded.
- [ ] **Step 5:** decisions bullets (answering the three open questions from `docs/plans/serah-terima.md`), `graphify update .`, commit `Fase 11: Task 5, log pivot, RSUD di wilayah, pasangan warna`.

### Task 6: Scheduled backup

**Files:**
- Modify: `composer.json`, `composer.lock`, `config/backup.php` (published), `config/filesystems.php` (disk `backup`), `config/database.php` (dump binary path for `mysql` and `mariadb`), `routes/console.php`, `.env.example` (`BACKUP_MAIL_TO=`, `DB_DUMP_BINARY_PATH=`), `.gitignore` (`/storage/app/backup` if not already ignored by `storage/app/.gitignore`)
- Test: `tests/Feature/Cadangan/CadanganTest.php`

**Interfaces:**
- Produces: disk `backup`; scheduled commands `backup:clean`, `backup:run`, `backup:monitor` (Task 8 documents them).

- [ ] **Step 1: Failing tests** in `CadanganTest`:
  - `test_jadwal`: `Schedule` events (resolve `Illuminate\Console\Scheduling\Schedule`, inspect `events()`) contain the three commands with the E10 cron expressions and timezone `Asia/Jakarta`.
  - `test_konfigurasi`: `config('backup.backup.source.files.include')` contains the two E10 paths, `exclude` contains the three, destination disks `['backup']`, cleanup values per E10, disk `backup` root under `storage_path('app/backup')`.
  - `test_tanpa_email_tidak_gagal`: with `BACKUP_MAIL_TO` empty, the notifiable routes no mail and `backup:run --only-files` (with `Storage::fake('backup')` and a temp file under `storage/app/private`) exits 0 and writes one zip on the `backup` disk containing that file.
- [ ] **Step 2: Run** `php artisan test --filter=CadanganTest` → FAIL.
- [ ] **Step 3: Install and configure** E10.
- [ ] **Step 4: Run** filter, full suite, pint. Manual: on MariaDB `simon_uji` with `DB_DUMP_BINARY_PATH=C:\xampp\mysql\bin`, `php artisan backup:run` → zip contains `db-dumps/*.sql` with `activity_log` and the private/public files; `backup:list` shows it; restore the dump into a second throwaway database and compare `SELECT COUNT(*)` of `spt`, `activity_log`. Record commands and results in decisions; delete the zips and databases.
- [ ] **Step 5:** decisions bullets, `decisions/catatan-deploy.md` section `Cadangan (Fase 11)`, `graphify update .`, commit `Fase 11: Task 6, cadangan terjadwal`.

### Task 7: Presentation demo data and first admin

**Files:**
- Create: `database/seeders/DemoPresentasiSeeder.php` (and smaller helpers only if it grows past ~400 lines), `app/Console/Commands/BuatAdmin.php`, `tests/Feature/Demo/DataDemoTest.php`, `tests/Feature/Demo/BuatAdminTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`, `database/seeders/UserSeeder.php` (irban2–irban5), seeders that must be skipped in production

**Interfaces:**
- Consumes: existing actions for realistic data (prefer calling actions/factories over raw inserts so statuses, numbering, riwayat, log and notifications are produced by real code; numbering through `Penomoran::ambil` inside `DB::transaction(..., attempts: 3)`).

- [ ] **Step 1: Failing tests:**
  - `DataDemoTest` (runs `DatabaseSeeder` once in `setUp` with `travelTo` a fixed date in 2026 that the seeder also uses as "today"): every E11 bullet as an assertion (accounts and irban links; both PKPT Disahkan with items for 5 irban; Perubahan Disetujui + Diajukan; per irban 2025 ≥ 8 accepted mostly Selesai; per irban 2026 each status present via `Spt::status()` and `PenandaSpt`; one open Minta Penjelasan; unread notification per role); `RealisasiPublik::ringkasan(['tahun' => 2025])` non-zero for every irban; `PrivasiPublikTest`-style check that `/realisasi?tahun=2025` HTML shows none of the seeded NIPs or SPT numbers.
  - `test_produksi_tanpa_demo`: `app()->detectEnvironment(fn () => 'production')`, run `DatabaseSeeder` → zero `users`, `spt`, `pkpt`, `tim`, `irban_wilayah` rows; reference rows present.
  - `BuatAdminTest`: creates an admin with `expectsQuestion` for the two hidden prompts; mismatched passwords → exit 1 `Kata sandi tidak sama.`; existing username → exit 1 with the `SimpanPengguna` message; output never contains the password; the user's `activity_log` row has no `password` key.
- [ ] **Step 2: Run** `php artisan test --filter="DataDemoTest|BuatAdminTest"` → FAIL.
- [ ] **Step 3: Implement** E11. Keep the existing seeders' Irban I data intact (other tests rely on it).
- [ ] **Step 4: Run** filter, full suite (watch for tests that counted seeded rows), pint. Browser: `migrate:fresh --seed`, log in as each role and open its dashboard, `/`, `/jadwal`, `/realisasi?tahun=2025` — every page has content, nothing empty; screenshots at 1280.
- [ ] **Step 5:** decisions bullets, `graphify update .`, commit `Fase 11: Task 7, data demo presentasi dan admin pertama`.

### Task 8: DEPLOY.md, production rehearsal, phase close

**Files:**
- Create: `DEPLOY.md`
- Modify: `decisions/fase-11.md`, `decisions/catatan-deploy.md` (point to `DEPLOY.md` as the operator guide; keep history), `docs/plans/serah-terima.md`, `README.md` (one link to `DEPLOY.md` if README has a setup section)

- [ ] **Step 1: Write `DEPLOY.md`** per E12, merging every operational fact from `decisions/catatan-deploy.md` and the "Catatan deploy" summary in `docs/plans/serah-terima.md`, plus Tasks 2, 4, 6, 7. Every command in it must be one that exists in this repo (`php artisan list` check).
- [ ] **Step 2: Rehearse on this machine as if it were staging** (the user has no staging server yet; PRD's "berjalan di server staging" is replaced by this rehearsal, ruling in `decisions/fase-11.md` "Plan"): fresh clone of the branch into the scratchpad, follow `DEPLOY.md` literally against MariaDB `simon_rehearsal` with `APP_ENV=production`, `APP_DEBUG=false`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`, `MAIL_MAILER=log`. Run `simon:buat-admin`, log in, create master data minimum (irban, pegawai, PKPT) through the UI only far enough to submit one SPT and have BU accept it, run `php artisan queue:work --stop-when-empty` and `php artisan schedule:run` (time-travel not available: run each scheduled command by name once), `backup:run`, open an error page (404) and confirm no stack trace. Every step of `DEPLOY.md` that was wrong or missing is fixed in the file. Record the transcript summary (commands, outcomes) in decisions. Drop `simon_rehearsal`, delete the clone.
- [ ] **Step 3:** `grep -rn "Fase 11" decisions/ docs/plans/serah-terima.md` → each deferral is either done (state where) or explicitly carried forward with reason in `serah-terima.md`.
- [ ] **Step 4:** Full suite on SQLite and on MariaDB (`simon_uji`, `docs/plans/serah-terima.md` "Uji di MySQL"); record both counts. `npm run build`, pint.
- [ ] **Step 5:** Update `docs/plans/serah-terima.md` (status: Fase 11 = last PRD phase done; what remains for a real staging/production deploy; new pitfalls; deferred minors), `graphify update .`, `/graphify . --update` is run by the controller after the commit (docs changed), commit `Fase 11: Task 8, DEPLOY.md dan penutup fase`.
