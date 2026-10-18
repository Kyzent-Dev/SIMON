# Plan: Fase 1 (Autentikasi dan peran)

Spec: `PRD-SIMON.md` §5, §8 (`users`), §10 (rate limit), §12 Fase 1, §13; `design/auth/login.md`; `DESIGN.md` §3.4–3.5, §6, §8, §10; `design/dashboard/_shared.md` §1. Project rules: `CLAUDE.md`. Fase 0 decisions: `DECISIONS.md`.

PRD Fase 1: Login, logout, lupa kata sandi, wajib ganti kata sandi. Role enum (Admin, Pimpinan, Irban, Bagian Umum) dan Policy dasar. Seeder satu akun per role. *Selesai jika:* setiap role diarahkan ke dashboard-nya, dan akses lintas role ditolak (ada test).

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `DESIGN.md`, `CLAUDE.md` or anything in `design/`.
- Scope is Fase 1 only. No master data (pegawai, irban tables), no "Diinput oleh", no sidebar/bell shell, no admin user-management UI.
- Stack: Blade + Livewire + Alpine (Livewire's bundled Alpine, already started by `resources/js/app.js`) + Tailwind v4 tokens in `resources/css/app.css`. No React/Vue/etc. `motion` is NEVER used on auth or dashboard pages; auth and dashboard layouts load `resources/js/app.js`, never `public.js`.
- Business logic in action/service classes, not controllers (PRD §13). New table/column names in Bahasa Indonesia where PRD §8 names them (`role`, `irban_id`, `wajib_ganti_password`); keep Laravel's built-in `name`/`email`/`password` columns.
- UI copy Bahasa Indonesia, exactly as written in `design/auth/login.md`. Colors only from DESIGN §3.4 pairs; never §3.5 combos. Visible focus ring per DESIGN §6. Status/meaning never by color alone (icon + text).
- Tests: PHPUnit feature tests, TDD (RED then GREEN). Full `php artisan test` and `npm run build` green before each commit.
- Every deviation from PRD/DESIGN → one bullet in `DECISIONS.md` (UTF-8).
- Branch `fase-1-autentikasi`. Commit per task; message ends with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Exact values
- Enum `App\Enums\Role` (string-backed): `Admin = 'admin'`, `Pimpinan = 'pimpinan'`, `Irban = 'irban'`, `BagianUmum = 'bagian_umum'`; method `label()` → `Admin`, `Pimpinan`, `Irban`, `Bagian Umum`; method `dashboardRoute()` → `admin.dashboard`, `pimpinan.dashboard`, `irban.dashboard`, `bagian-umum.dashboard`.
- `users` new columns (new migration, do not edit the skeleton migration): `username` string unique, `role` string (cast to `Role`), `irban_id` unsignedBigInteger nullable, no FK (the `irban` table arrives in Fase 2), `wajib_ganti_password` boolean default false.
- Role dashboard routes (all `auth` + password-change guard): `GET /admin` `admin.dashboard`; `GET /pimpinan` `pimpinan.dashboard`; `GET /irban` `irban.dashboard`; `GET /bagian-umum` `bagian-umum.dashboard`. Middleware alias `role` (e.g. `role:admin`); wrong role → 403. `GET /dashboard` (name `dashboard`, auth) → redirect to the user's role dashboard (replaces the Fase 0 placeholder route).
- Auth routes: `GET /masuk` `login`; `POST /masuk`; `POST /keluar` `logout`; `GET /lupa-kata-sandi` `password.request`; `POST /lupa-kata-sandi` `password.email`; `GET /atur-ulang-kata-sandi/{token}` `password.reset`; `POST /atur-ulang-kata-sandi` `password.update`; `GET /perbarui-kata-sandi` `password.force.edit`; `PUT /perbarui-kata-sandi` `password.force.update`.
- Login by `username` + password, "Ingat saya" = remember. Rate limit: 5 failed attempts per `lower(username)|ip`, decay 60s. Wrong credentials message: `Nama pengguna atau kata sandi salah.` Lockout message: `Terlalu banyak percobaan masuk. Coba lagi dalam :seconds detik.`
- Forgot password success (always, registered or not): `Jika email terdaftar, tautan reset telah dikirim. Periksa kotak masuk Anda.`
- New password rule (forced change and reset): `Password::min(8)->mixedCase()->numbers()`, `confirmed`; forced change also requires it differs from the current password, message `Kata sandi baru harus berbeda dari kata sandi lama.`
- Forced change: users with `wajib_ganti_password = true` are redirected to `password.force.edit` from every authenticated route except that page and `logout`. Saving sets the flag false and redirects to the role dashboard. Info alert text: `Administrator mewajibkan pembaruan kata sandi untuk tahun :tahun.` (`:tahun` = current year, Asia/Jakarta).
- Seeder (dev only, password `password`): `admin` / Administrator / Admin; `pimpinan` / Inspektur / Pimpinan; `irban1` / Irban I / Irban; `bagianumum` / Bagian Umum / BagianUmum. Emails `<username>@simon-spt.test`.
- CSRF/expired session (419) → redirect to `login` with message `Sesi berakhir, silakan masuk kembali.`

## Task 1: Roles, users columns, role dashboards, policy, seeder
1. TDD feature tests first (`tests/Feature/Auth/RoleAccessTest.php`, `tests/Feature/Auth/UserPolicyTest.php`): for each role, `GET /dashboard` redirects to its dashboard and that dashboard returns 200; each role gets 403 on the other three dashboards; guest on any dashboard → redirect to `/masuk`; `UserPolicy` allows `viewAny/view/create/update/delete` only for Admin; seeder creates exactly the four accounts with the right roles and hashed passwords.
2. Migration, `Role` enum, `User` casts/fillable, `UserFactory` with a state per role (`admin()`, `pimpinan()`, `irban()`, `bagianUmum()`) plus default role `Irban` with generated unique `username`.
3. `role` middleware (alias in `bootstrap/app.php`), routes, `App\Policies\UserPolicy`.
4. Dashboard placeholder views `resources/views/dashboard/{admin,pimpinan,irban,bagian-umum}.blade.php` using `<x-layouts::app>`: one `h1` each (`Dasbor Admin`, `Dasbor Pimpinan`, `Dasbor Irban`, `Dasbor Bagian Umum`) — no real content. Remove the Fase 0 `placeholder/app.blade.php` view and update `LayoutPlaceholderTest` (the dashboard test now goes through auth).
5. `DatabaseSeeder` calls a new `UserSeeder` with the exact accounts above.
6. `lang/id/` with `auth.php`, `passwords.php`, `validation.php` containing Indonesian strings for every key Fase 1 uses (required, email, confirmed, min.string, unique, the Password rule keys `password.mixed`/`password.numbers`, attributes `username` → "nama pengguna", `password` → "kata sandi", `email` → "email"). Other keys may fall back to `en`; record in DECISIONS.md.
7. Commit "Fase 1: role, kolom users, dasbor per role, policy, seeder".

## Task 2: Auth flows + auth UI (login, logout, lupa/atur ulang, wajib ganti)
1. TDD feature tests first (`tests/Feature/Auth/LoginTest.php`, `PasswordResetTest.php`, `ForcedPasswordChangeTest.php`): login success per role lands on its dashboard; wrong password shows the generic message and no field-specific hint; 6th failed attempt within 60s shows the lockout message with seconds and does not authenticate even with the correct password; logout invalidates session; logged-in user visiting `/masuk` is redirected to `/dashboard`; forgot password returns the neutral success message for both existing and unknown emails and sends `ResetPassword` notification only for the existing one (`Notification::fake()`); reset with valid token changes the password; forced-change user is redirected from every dashboard to `/perbarui-kata-sandi`, can still log out, cannot reuse the old password, weak passwords fail each rule, success clears the flag and redirects to the role dashboard; `App\Actions\Auth\RequireIrbanPasswordChange` sets the flag on all Irban users only and returns the count.
2. Actions: `App\Actions\Auth\AttemptLogin` (rate limiting + attempt), `App\Actions\Auth\RequireIrbanPasswordChange`, `App\Actions\Auth\ChangeForcedPassword`. Thin controllers in `App\Http\Controllers\Auth\`. Middleware `EnsurePasswordIsChanged` applied to authenticated app routes. Guest redirect to `/dashboard` and auth redirect to `/masuk` configured in `bootstrap/app.php`.
3. UI — invoke the `ui-ux-pro-max:ui-ux-pro-max` skill for UX/a11y guidance, but `DESIGN.md` and `design/auth/login.md` are binding and win any conflict. Build:
   - `resources/views/layouts/auth.blade.php` (`lang`, `@vite(['resources/css/app.css','resources/js/app.js'])`, `@livewireStyles`/`@livewireScriptConfig`, skip link "Lewati ke konten utama", `<main id="konten">`). Split 5/7 at ≥1024 with the mint-950 brand panel ("SIMON-SPT" display-lg white, subtitle in mint-200, accent-400 rule, Delapan Sungai motif = inline SVG of 8 wavy lines in mint-200 at 15% opacity, decorative `aria-hidden`); <1024 a 120px mint-950 band and the white card overlapping by −24px. Card: white, max-w 420, p-8, radius-lg, shadow-sm on neutral-25.
   - The logo image is not available yet: omit it, record in DECISIONS.md.
   - Components (only these, with the variants DESIGN §8 lists): `x-ui.button`, `x-ui.input` (label above, error state with `exclamation-circle` icon + `aria-describedby`, required marker), `x-ui.checkbox`, `x-ui.alert` (info/warning/danger/success, icon + text). Icons via `blade-ui-kit/blade-heroicons` if compatible with Laravel 13 (check Packagist; else inline Heroicons SVG and record).
   - Views: login (fields/labels/toggle exactly per login.md §1, password show/hide button with `aria-pressed` and label "Tampilkan kata sandi", lockout alert with `clock` icon and an Alpine countdown in an `aria-live="polite"` region that announces only every 15s, submit disabled until 0), forgot password (§2, link "Kembali ke halaman masuk"), reset password, forced change (§3: no navigation, title "Perbarui Kata Sandi", live requirement checklist with icon + text for the four rules, 4-segment strength meter labelled Lemah/Sedang/Kuat/Sangat kuat, button "Simpan & Lanjutkan").
   - §4: autofocus first field on desktop only (≥1024), the error alert/summary receives focus after failed submit, inputs 48px tall on mobile (≥16px font).
   - No motion except the button loading spinner; no shake.
4. Verify at 360, 768, 1280 px (no horizontal scroll) and keyboard focus visibility. If a headless browser is available (e.g. `npx playwright` / `.playwright` config in the repo) take screenshots into the scratchpad and report; otherwise state it was not checked.
5. Commit "Fase 1: login, logout, lupa kata sandi, wajib ganti kata sandi".

## Task 3: Dashboard header with logout, 419 handling, decisions
1. TDD first (`tests/Feature/Auth/SessionExpiredTest.php`, extend `RoleAccessTest`): a 419 (TokenMismatchException) on a web POST redirects to `/masuk` with `Sesi berakhir, silakan masuk kembali.`; every role dashboard shows the account name, the role label and a "Keluar" button that POSTs to `logout`; dashboard layout has the skip link and `<main id="konten">`.
2. `layouts/app.blade.php`: minimal top header per `_shared.md` §1 (64px, white, border-b): right side shows account name + role caption (avatar initials mint-100/mint-800) and a "Keluar" button. No sidebar, bell, breadcrumbs or year switcher (deferred; record in DECISIONS.md). Skip link + `<main id="konten">` with content gutter `px-4 md:px-6 lg:px-8` on neutral-25.
3. 419 handling in `bootstrap/app.php` `withExceptions`.
4. Responsive (360/768/1280) + keyboard check as in Task 2.
5. Append DECISIONS.md: username login (login.md) vs PRD email column, `name` kept, `irban_id` without FK until Fase 2, partial `lang/id`, logo omitted, shell items deferred, seeder dev passwords, any package/version notes.
6. Commit "Fase 1: header dasbor, sesi berakhir, catatan keputusan".
