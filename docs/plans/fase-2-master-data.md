# Plan: Fase 2 (Master data) + perbaikan kecil Fase 1

Spec: `PRD-SIMON.md` §5, §7.4, §8, §9.4, §12 Fase 2, §13, §15; `design/dashboard/_shared.md`; `design/dashboard/admin.md`; `DESIGN.md` §3.4–3.7, §5, §6, §7.5, §8, §10, §11, §14. Rules: `CLAUDE.md`. Earlier decisions: `DECISIONS.md`.

PRD Fase 2: CRUD pegawai (dengan impor Excel), irban, irban tahunan, tim beserta anggota, objek hierarkis, wilayah irban, jenis penugasan, klasifikasi, pengaturan. Seeder: 5 irban, objek contoh dari tabel wilayah Irban I (5 kecamatan, 9 dinas/badan, Perumdam Lematang Enim). *Selesai jika:* validasi tim (satu Supervisor/Dalnis, satu Ketua) berjalan dan ada test-nya.

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `DESIGN.md`, `CLAUDE.md` or `design/`.
- Graph: `graphify-out/graph.json` exists. Before your first grep/search/source read for a codebase question, run `graphify query "<question>"` (see CLAUDE.md "graphify"); read raw files only for lines the graph located. After changing code run `graphify update .`.
- Scope: Fase 2 only. No PKPT, SPT, notifications, activity log, landing content, user-management UI.
- Stack: Blade + Livewire 4 (class-based components for CRUD screens) + Livewire's bundled Alpine + Tailwind v4 tokens. No React/Vue/etc. `motion` never on dashboard pages. No animation on tables or forms (DESIGN §7.5); drawer/modal transitions only.
- Business logic (validation of team composition, wilayah rules, color validation, settings access, import parsing) in `app/Actions` or `app/Services`, not in controllers/Livewire components (PRD §13). Tables/columns in Bahasa Indonesia per PRD §8; models set `$table` explicitly.
- Every master-data route: `auth` + `role:admin` + `EnsurePasswordIsChanged`, prefix `/admin`, route names `admin.*`. Authorization also via a Policy per model (Admin only) used in the Livewire components.
- UI: invoke the `ui-ux-pro-max:ui-ux-pro-max` skill (Skill tool) for every task that touches UI; `DESIGN.md` wins conflicts with it, and `DESIGN.md` wins over page files (`CLAUDE.md`). Only §3.4 color pairs; never §3.5. Status/meaning never by color alone. Copy in Bahasa Indonesia exactly as in `admin.md`/`_shared.md` where given. Dates `translatedFormat('d F Y')`.
- Check every new screen at 360/768/1280 (no page-level horizontal scroll; wide tables scroll inside their own container) and keyboard focus, using the global `playwright-cli --config .playwright/cli.config.json` (chromium) against `php artisan serve`; screenshots into `C:\Users\andhi\AppData\Local\Temp\claude\C--Users-andhi-Programs-Laravel-C7\58736c29-d851-4719-9d4b-eb454ee7390a\scratchpad\f2\`. Stop only the server process you started (by PID).
- Tests: PHPUnit feature tests, TDD (RED then GREEN). Full `php artisan test` + `npm run build` green before each commit.
- Every deviation → one bullet in `DECISIONS.md` (UTF-8, LF).
- Branch `fase-2-master-data`. Commit per task, message ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Never stage `.claude/`, `.playwright/`, `graphify-out/`.

## Exact values
- Tables (all with timestamps):
  - `pegawai`: `nama_bergelar` string, `nip` char(18) unique, `pangkat` string nullable, `golongan` string nullable, `jabatan` string nullable, `aktif` boolean default true.
  - `irban`: `nama` string unique, `kode_nomor` string unique, `email_notifikasi` string nullable, `email_tembusan` json nullable (array of emails).
  - `irban_tahunan`: `irban_id` FK cascade, `tahun` unsignedSmallInteger, `ppj_pegawai_id` FK nullable nullOnDelete; unique(`irban_id`,`tahun`).
  - `irban_anggota`: `irban_tahunan_id` FK cascade, `pegawai_id` FK restrict; unique(`irban_tahunan_id`,`pegawai_id`).
  - `tim`: `irban_id` FK cascade, `tahun` unsignedSmallInteger, `nama` string; unique(`irban_id`,`tahun`,`nama`).
  - `tim_anggota`: `tim_id` FK cascade, `pegawai_id` FK restrict, `peran_default` string (enum `App\Enums\PeranTim`: `SupervisorDalnis='supervisor_dalnis'`, `Ketua='ketua'`, `Anggota='anggota'`; labels `Supervisor/Dalnis`, `Ketua`, `Anggota`), `urutan` unsignedSmallInteger; unique(`tim_id`,`pegawai_id`).
  - `objek`: `nama` string, `jenis` string (enum `App\Enums\JenisObjek`: `kecamatan`, `desa`, `sekolah`, `puskesmas`, `rsud`, `bumdes`, `dinas_badan`, `bumd`; labels `Kecamatan`, `Desa`, `Sekolah`, `Puskesmas`, `RSUD`, `BUMDes`, `Dinas/Badan`, `BUMD`), `parent_id` FK self nullable restrict.
  - `irban_wilayah`: `irban_id` FK cascade, `tahun` unsignedSmallInteger, `objek_id` FK restrict; unique(`tahun`,`objek_id`).
  - `jenis_penugasan`: `nama` string unique, `warna` char(7) (`#RRGGBB`, uppercase), `warna_teks` char(7) (`#FFFFFF` or `#151C1A`).
  - `klasifikasi`: `kode` string unique (`REGULER`, `KHUSUS`, `DIKLAT`, `LUAR_DAERAH`, `LAIN_LAIN`), `nama` (`REGULER`, `KHUSUS`, `DIKLAT`, `LUAR DAERAH`, `LAIN-LAIN`), `wajib_item_pkpt` bool, `syarat_selesai` string (enum `App\Enums\SyaratSelesai`: `lhp_lengkap` "Cover LHP + nomor + tanggal LHP", `konfirmasi` "Tanpa dokumen, cukup konfirmasi", `laporan_opsional` "Laporan perjalanan opsional", `dokumen_opsional` "Dokumen opsional"), `tampil_publik` bool. Seeded rows per PRD §9.4: REGULER (true, lhp_lengkap, true), KHUSUS (false, lhp_lengkap, false), DIKLAT (false, konfirmasi, false), LUAR_DAERAH (false, laporan_opsional, false), LAIN_LAIN (false, dokumen_opsional, false). Admin edits rules only; no create/delete.
  - `settings`: `key` string primary, `value` json.
  - `users.irban_id`: add FK to `irban` nullOnDelete (new migration).
- Settings keys and defaults (`App\Services\Settings`, typed `get(key)` falling back to these defaults, `set(key, value)`; cache cleared on set):
  `penomoran.format` = `{kode_arsip}/{nomor_urut}/{kode_irban}/SPT/{tahun}`; `penomoran.kode_arsip` = `700`; `penomoran.scope` = `global` (`global`|`per_irban`); `penomoran.reset_tahunan` = true; `penomoran.kode_sekretariat` = `INSPEKTORAT`; `penomoran.klasifikasi_di_nomor` = false; `penugasan.hitungan_hari` = `kalender` (`kalender`|`kerja`); `penanda.batas_terlambat_hari` = 14; `rekap.dasar_triwulan` = `tanggal_selesai` (`tanggal_selesai`|`tanggal_mulai`|`tanggal_lhp`); `pkpt.draf_memblokir` = false; `publik.tampilkan_nomor_spt` = false; `publik.tampilkan_nama_objek` = false; `upload.maks_mb` = 10; `kendala.kategori` = [`Jadwal PKPT belum disahkan`, `Bentrok penugasan lain`, `Personel tidak tersedia`, `Objek tidak siap`, `Lainnya`].
- Validation messages: NIP `NIP harus 18 digit angka.`; team `Setiap tim wajib punya tepat satu Supervisor/Dalnis.` and `Setiap tim wajib punya tepat satu Ketua.`; objek parent `Desa, sekolah, puskesmas, dan BUMDes wajib punya induk kecamatan.`; color `Warna terlalu terang/gelap untuk label teks`, `Kontras warna terhadap putih minimal 3:1.`, `Warna terlalu mirip dengan warna utama/Selesai.`; color reuse warning `Warna ini sudah dipakai oleh jenis penugasan lain.`
- Team composition (PRD wins over admin.md "≤1 Supervisor"): exactly one `supervisor_dalnis` and exactly one `ketua`; any number of `anggota`; a pegawai at most once per tim. Implemented in `App\Actions\MasterData\ValidateTimComposition` (throws `ValidationException` with the messages above) used by every save path.
- Irban tahunan: a pegawai belongs to at most one irban's anggota per tahun (`Pegawai ini sudah menjadi anggota :irban pada tahun :tahun.`).
- Wilayah: only `kecamatan`, `dinas_badan`, `bumd`, `rsud` objek may be assigned; one irban per objek per tahun.
- Color rule (DESIGN §3.7): 12 swatches exactly as the table (Nila #332288 … Cokelat #7A4E2D) with their label colors; custom hex accepted only if contrast vs white ≥ 3:1 and CIE76 ΔE ≥ 10 from mint-700 `#17735B` and success-700 `#1D6B34`; label = white or neutral-900 `#151C1A`, whichever has higher contrast and ≥ 4.5, else reject.
- Admin routes: `/admin/pengaturan` `admin.pengaturan`; `/admin/klasifikasi` `admin.klasifikasi`; `/admin/pegawai` `admin.pegawai`; `/admin/pegawai/impor` `admin.pegawai.impor`; `/admin/irban` `admin.irban`; `/admin/irban-tahunan` `admin.irban-tahunan`; `/admin/tim` `admin.tim`; `/admin/objek` `admin.objek`; `/admin/wilayah-irban` `admin.wilayah-irban`; `/admin/jenis-penugasan` `admin.jenis-penugasan`.
- Seeders (dev): 5 irban `Irban I`…`Irban V`, `kode_nomor` `INSPEKTORAT-I`…`INSPEKTORAT-V`; Irban I wilayah for the current year: 5 kecamatan, 9 dinas/badan, BUMD `Perumdam Lematang Enim`. Real names of the Irban I table are not in the repo: use real Muara Enim kecamatan names as samples and generic dinas names, and record in DECISIONS.md that they must be replaced from the official table. Link the `irban1` user to Irban I. Seeder skips in production (like `UserSeeder`).

## Task 1: Perbaikan kecil dari Fase 1
1. `User::$fillable`: remove `role`, `irban_id`, `wajib_ganti_password`; set them with `forceFill` where written (seeder, factory states stay working, actions). Test: mass-assigning `role` via `User::create([...])`/`fill()` does not change it.
2. Move `EnsurePasswordIsChanged` onto the `web` middleware group (it must pass guests and exempt `password.force.*`, `logout`, and Livewire's update endpoint only when the user is not flagged); remove it from per-route groups. Test: loop over every registered route with `auth` middleware (except the exempt ones) and assert a flagged user is redirected to `/perbarui-kata-sandi`.
3. Test `POST /atur-ulang-kata-sandi` throttle (7th → 429).
4. Tighten the role-caption assertion in `RoleAccessTest` (assert the caption element, not the h1 text).
5. `User::initials()` accessor (or method) replacing the `@php` block in `layouts/app.blade.php`; test it.
6. Login lockout countdown survives reload: in the login `create()` read `RateLimiter::availableIn()` for the old-input username + IP and render the lockout alert/countdown; test.
7. Commit "Fase 2: perbaikan kecil Fase 1".

## Task 2: Dashboard shell + komponen bersama
1. Shell per `_shared.md` §1 in `layouts/app.blade.php`: sidebar (264/72px ≥1024, `@persist('sidebar')`, Alpine `$persist` collapse "Ciutkan menu", tooltips when collapsed, active item mint-50 + mint-700 + 3px left indicator + `aria-current="page"`, section labels overline), off-canvas drawer <1024 with hamburger, top header 64px with breadcrumbs (passed by the page) and the existing user area. Nav for Admin per `admin.md` "Navigation groups", showing only routes that exist after this plan (Master Data items + Pengaturan Sistem + Dasbor); other roles see only "Dasbor". Bell and year switcher stay deferred (record). Page header slot: h1 + description + right actions.
2. Components (DESIGN §8 rows, all variants/states listed there): `x-ui.card`, `x-ui.table` (comfortable/compact, sticky header neutral-50, numbers right tnum, sortable header buttons with `aria-sort`, `overflow-x-auto` container with fade hint), `x-ui.pagination` ("Menampilkan 1–25 dari 312", per-page 25/50/100), `x-ui.select`, `x-ui.textarea`, `x-ui.modal` (sm/md/lg, `x-trap.noscroll`, Esc, focus return, `aria-labelledby`), `x-ui.drawer` (lg 720 for create/edit), `x-ui.toast` (success auto-dismiss 5s, error `role="alert"` persists; top-right desktop, bottom-center mobile; triggered by a Livewire/Alpine event), `x-ui.empty-state`, `x-ui.switch`, `x-ui.radio`. Reuse existing `x-ui.button/input/checkbox/alert`.
3. Admin dashboard keeps only its h1 plus a quick-links grid to the Master Data pages (health cards need PKPT/log data — deferred, record).
4. Tests: sidebar renders the admin nav with `aria-current` on the active item; non-admin roles don't see master-data links; a Blade component render test per component for its key a11y attributes.
5. Commit "Fase 2: shell dasbor dan komponen bersama".

## Task 3: Pengaturan Sistem + Klasifikasi
1. `settings` migration, `App\Services\Settings` with the exact keys/defaults; `klasifikasi` migration + model + enum + seeder rows.
2. Livewire `Admin\Pengaturan` page: grouped settings cards (Penomoran, Penugasan & Penanda, Rekap, PKPT, Publik, Unggahan, Kategori Kendala) each with its own "Simpan perubahan"; validation per key type; toast on save.
3. Livewire `Admin\Klasifikasi` page: table of the 5 rows; edit rules in a drawer (wajib item PKPT switch, syarat selesai select, tampil publik switch); no create/delete.
4. Tests: defaults returned when unset; set/get round trip and cache clear; non-admin 403; each card saves only its own keys; klasifikasi seeded exactly per §9.4; update persists; create/delete impossible.
5. Commit "Fase 2: pengaturan sistem dan klasifikasi".

## Task 4: Pegawai CRUD
1. Migration/model/policy/factory; Livewire `Admin\Pegawai` index: search "Cari…" (debounce 300ms, nama/NIP), filter aktif, sortable columns, pagination, table (nama bergelar, NIP tnum, pangkat/golongan, jabatan, status with icon+label), create/edit drawer, deactivate instead of delete when referenced (hard delete only if unreferenced, confirm modal).
2. NIP input: 18-digit numeric mask, tnum, counter "18/18", message `NIP harus 18 digit angka.`; unique.
3. Empty state: "Belum ada data pegawai. Tambah manual atau impor dari Excel." with both actions (import links to Task 5 route — create the route stub returning the page shell if Task 5 is not done yet? No: Task 5 follows immediately; link to `admin.pegawai.impor` which Task 5 implements; in Task 4 register the route with a minimal placeholder view).
4. Tests: CRUD, validation (NIP length/digits/unique), search, delete blocked when referenced (use irban_anggota once it exists — for now test deactivate path), non-admin 403.
5. Commit "Fase 2: CRUD pegawai".

## Task 5: Impor pegawai dari Excel
1. `composer require maatwebsite/excel:^4.0` (4.0.4 supports Laravel 13; verify). Template download (.xlsx with headers `nama_bergelar`, `nip`, `pangkat`, `golongan`, `jabatan`, `aktif`).
2. `App\Services\Import\PegawaiImportParser`: parse rows, validate each (NIP rule, required nama, duplicate NIP within file = error, NIP already in DB = warning "akan diperbarui"), return rows with status valid/warning/error and per-cell messages like `Baris 14: NIP 19870312 tidak 18 digit`.
3. Livewire `Admin\PegawaiImpor` stepper per `admin.md` (1 Unggah → 2 Pratinjau & Validasi → 3 Impor): preview table with row tints (warning #FFFBEB + icon, error #FEF2F2 + `x-circle` + message) via CSS variables — no raw hex classes; summary chips "120 valid · 3 peringatan · 2 galat"; toggle "Tampilkan hanya galat"; button "Impor N baris valid" disabled while errors exist unless "Lewati baris galat" checked; error list downloadable as .xlsx; upsert by NIP in a transaction.
4. Tests: parser unit-style feature tests for each rule; full import happy path; errors block unless skip; error xlsx download; non-admin 403.
5. Commit "Fase 2: impor pegawai dari Excel".

## Task 6: Irban + Komposisi Irban Tahunan
1. Migrations/models/policies/factories for `irban`, `irban_tahunan`, `irban_anggota`; `users.irban_id` FK migration; `IrbanSeeder` (5 irban) and link `irban1` → Irban I (update `UserSeeder`/`DatabaseSeeder` order).
2. Livewire `Admin\Irban`: table + drawer (nama, kode nomor, email notifikasi, email tembusan as a list editor with validation).
3. Livewire `Admin\IrbanTahunan`: year select (default current year), per irban card: PPJ select (active pegawai), anggota multi-select (`x-ui.combobox`-style searchable list, Alpine listbox pattern from DESIGN §8), rule "at most one irban per pegawai per tahun" in an action.
4. Tests: CRUD irban, email validation, anggota uniqueness across irban per year, PPJ optional, seeder counts, FK on users, non-admin 403.
5. Commit "Fase 2: irban dan komposisi irban tahunan".

## Task 7: Tim + anggota (kriteria selesai Fase 2)
1. Migrations/models/policy/factory for `tim`, `tim_anggota`; `PeranTim` enum; `ValidateTimComposition` action.
2. Livewire `Admin\Tim`: filter irban + tahun, list of teams (Tim 1, Tim 2, …), editor with member list (add pegawai, choose peran, reorder with "Naikkan/Turunkan" buttons, remove) and the validation strip showing each rule with icon + text (met / not met); save blocked with the exact messages.
3. Tests (the PRD acceptance test for Fase 2): zero/two Supervisor/Dalnis rejected; zero/two Ketua rejected; exactly one each passes; duplicate pegawai rejected; validation also enforced when saving via the action directly (not only UI); non-admin 403.
4. Commit "Fase 2: tim dan validasi komposisi".

## Task 8: Objek Pemeriksaan + Wilayah Irban
1. Migrations/models/policy/factory for `objek`, `irban_wilayah`; `JenisObjek` enum; parent rule action.
2. Livewire `Admin\Objek`: tree for Kecamatan > Desa/Sekolah/Puskesmas/BUMDes (expandable rows, indent guides, `aria-expanded`, `aria-level`, add-child per node) + flat tabs for Dinas/Badan, BUMD, RSUD; drawer create/edit; delete blocked when it has children or wilayah.
3. Livewire `Admin\WilayahIrban`: year select; per irban assign kecamatan/dinas_badan/bumd/rsud (one irban per objek per year), shown like the old wilayah table.
4. `ObjekSeeder` + `IrbanWilayahSeeder` per Exact values (Irban I, current year).
5. Tests: parent rule per jenis, tree query, wilayah uniqueness and allowed jenis, seeder counts (5 kecamatan, 9 dinas/badan, 1 BUMD for Irban I), non-admin 403.
6. Commit "Fase 2: objek pemeriksaan dan wilayah irban".

## Task 9: Jenis Penugasan
1. Migration/model/policy/factory; `App\Services\ColorRules` (contrast ratio, CIE76 ΔE vs mint-700/success-700, label color choice) with tests against the DESIGN §3.7 table values.
2. Livewire `Admin\JenisPenugasan`: table with swatch + bar and chip preview exactly as rendered publicly (via `--type`/`--type-fg` CSS variables, no raw hex in classes), drawer with color picker as a radio group of the 12 named swatches + "Warna lain" hex input with live validation and messages; reuse warning.
3. Tests: each of the 12 swatches accepted with its stated label color; a mint-ish and a green hex rejected; a very light hex rejected (contrast); label auto-choice; reuse warning; non-admin 403.
4. Commit "Fase 2: jenis penugasan".
