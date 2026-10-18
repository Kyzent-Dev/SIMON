# Plan: Fase 0 (Fondasi) + konfigurasi motion

Spec: `PRD-SIMON.md` §11–12 (Fase 0) and `DESIGN.md` §0, §4, §7, §13. Project rules: `CLAUDE.md`.

## Global Constraints
- Do NOT modify `PRD-SIMON.md`, `DESIGN.md`, `CLAUDE.md` or anything in `design/`. Never delete them.
- Scope is Fase 0 only: no migrations, models, auth, features or real page content. (The Laravel skeleton's own default migrations may stay; do not add any.)
- Stack: Laravel 13 (PHP 8.3+; local PHP is 8.5), Blade + Livewire + Alpine + Tailwind CSS v4 + Chart.js. Do NOT install React, react-dom, Vue, shadcn, Radix, Framer Motion (`framer-motion`), GSAP or Swiper. Never import `motion/react`.
- `motion` is imported ONLY in `resources/js/motion.js`, ONLY as `import { animate, inView, stagger } from "motion"`, and loaded ONLY via `resources/js/public.js`. `resources/js/app.js` must never import motion (directly or transitively).
- `APP_TIMEZONE=Asia/Jakarta`, `APP_LOCALE=id`.
- Tests: PHPUnit (Laravel default). TDD: write the failing test first, show RED, then GREEN.
- Every deviation from PRD/DESIGN goes into `DECISIONS.md` (repo root), one bullet each: decision, reason.
- Commit at the end of each task on branch `fase-0-fondasi`. End commit messages with `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.
- Shell: Windows; Git Bash available. Project root: `C:\Users\andhi\Programs\Laravel\C7`.

## Task 1: Scaffold Laravel 13 + timezone/locale
1. Root is non-empty (design docs), so run `composer create-project laravel/laravel:^13.0 _scaffold` inside the root, then move every file and folder (including dotfiles) from `_scaffold/` into the root and remove the empty `_scaffold/`. No file in the root may be overwritten (there are no name clashes expected; if one exists, stop and report).
2. Make sure `.gitignore` keeps ignoring `vendor`, `node_modules`, `public/build`, `.env` (skeleton default). Also add `/.superpowers/` to `.gitignore`.
3. TDD: add `tests/Feature/FondasiConfigTest.php` asserting `config('app.timezone') === 'Asia/Jakarta'` and `app()->getLocale() === 'id'`. Run it → RED.
4. Set `APP_TIMEZONE=Asia/Jakarta` and `APP_LOCALE=id` in `.env` and `.env.example`. If `config/app.php` hard-codes the timezone, change it to `env('APP_TIMEZONE', 'UTC')`. Check `phpunit.xml` doesn't override them. Run → GREEN, then full `php artisan test`.
5. Verify `php artisan --version` reports Laravel 13.x.
6. Commit ("Fase 0: scaffold Laravel 13, timezone dan locale").

## Task 2: Frontend stack (Tailwind v4, Livewire, Alpine + plugins, Chart.js) + app.css theme
1. Check versions for Laravel 13 compatibility on Packagist/npm before installing: `livewire/livewire` (latest stable supporting Laravel 13), `tailwindcss` v4 + `@tailwindcss/vite`, `@tailwindcss/forms` (required by DESIGN §13's `@plugin`), `alpinejs`, `@alpinejs/intersect`, `@alpinejs/focus`, `@alpinejs/sort`, `chart.js`. Record chosen versions + any incompatibility in `DECISIONS.md`.
2. Install them (composer for Livewire, npm for the rest). Keep the skeleton's Tailwind v4 Vite setup if present.
3. Livewire already bundles Alpine. Do NOT start a second Alpine instance. In `resources/js/app.js` use Livewire's ESM build (`vendor/livewire/livewire/dist/livewire.esm`), register via `Alpine.plugin(...)` only those of intersect/focus/sort that the installed Livewire does NOT already bundle (inspect the Livewire dist to decide), then `Livewire.start()`. The layout then uses `@livewireScriptConfig` (Task 3). Record the bundled-vs-registered outcome in `DECISIONS.md`.
4. Chart.js: install only; do not import it in `app.js` yet (no chart exists in Fase 0). Record in `DECISIONS.md`.
5. Replace `resources/css/app.css` with: the full `DESIGN.md` §13 block verbatim (from `@import "tailwindcss";` through the `body { @apply … }` line), plus the §7.1 duration tokens (`--dur-instant`, `--dur-fast`, `--dur-base`, `--dur-slow`, `--dur-hero`, `--dur-kenburns`, `--ease-emphasized`) in a `:root` block (the motion wrapper reads `--dur-slow` via getComputedStyle), plus the §7.4 reduced-motion CSS verbatim, plus the §7.6 "CSS for the JS-only hidden state" verbatim. Keep the skeleton's `@source` lines if Tailwind needs them to scan Blade views.
6. `resources/js/app.js` must not import `motion`.
7. Tests (TDD, `tests/Feature/FrontendStackTest.php`, static file assertions): `package.json` has `alpinejs`, `@alpinejs/intersect`, `@alpinejs/focus`, `@alpinejs/sort`, `chart.js`, `tailwindcss`, `@tailwindcss/forms`, and does NOT have `react`, `react-dom`, `framer-motion`; `app.css` contains `@theme`, `--color-mint-700:#17735B`, `prefers-reduced-motion`, `html.motion-ready [data-motion="fade-up"]`; `app.js` contains no `motion` import. RED first, then GREEN.
8. `npm run build` succeeds. Commit ("Fase 0: Tailwind v4, Livewire, Alpine, Chart.js, token desain").

## Task 3: motion, public entry, two layouts, placeholder pages
1. `npm install motion`. Check `package.json` still has no react/react-dom/framer-motion (motion must not pull them in as deps in `package.json`).
2. Create `resources/js/motion.js` = the DESIGN.md §7.6 wrapper, verbatim.
3. Create `resources/js/public.js` = the DESIGN.md §7.6 public entry, verbatim.
4. Add `resources/js/public.js` to the `input` array in `vite.config.js` (keep app.css and app.js).
5. `resources/views/layouts/public.blade.php`: minimal HTML shell, `<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">`, meta charset/viewport, `<title>`, `@vite(['resources/css/app.css', 'resources/js/public.js'])`, `{{ $slot }}` (anonymous component layout via `<x-layouts.public>`) — no Livewire scripts in Fase 0. The `<html>` element must NOT have the `motion-ready` class server-side.
6. `resources/views/layouts/app.blade.php`: same shell with `@vite(['resources/css/app.css', 'resources/js/app.js'])`, `@livewireStyles` in head and `@livewireScriptConfig` before `</body>`. No sidebar/bell (user scoped Fase 0 to empty layouts; record in DECISIONS.md as deferred from PRD Fase 0).
7. Placeholder pages: `resources/views/placeholder/public.blade.php` (uses public layout; contains one element with `data-motion="fade-up"` holding a short Indonesian placeholder text, e.g. "Halaman publik (placeholder)") and `resources/views/placeholder/app.blade.php` (uses app layout, text "Dashboard (placeholder)"). Use only DESIGN §3.4 allowed pairs (e.g. `text-neutral-900` on `bg-neutral-25`). Routes in `routes/web.php`: `GET /` → public placeholder (replace the welcome route; delete `welcome.blade.php` since nothing uses it), `GET /dashboard` → app placeholder. No auth.
8. Tests first (RED → GREEN), `tests/Feature/LayoutPlaceholderTest.php`:
   - `GET /` 200, renders `data-motion="fade-up"` and the placeholder text, the `<html` tag has no `motion-ready` class, and the response has no inline `opacity:0`/`opacity: 0` (so content is visible without JS).
   - `GET /dashboard` 200 and renders its placeholder text.
   - Static: `layouts/public.blade.php` references `resources/js/public.js` and not `resources/js/app.js`; `layouts/app.blade.php` references `resources/js/app.js` and not `public.js`; `vite.config.js` contains `resources/js/public.js`; `motion.js` imports from `"motion"` exactly and nowhere in `resources/js` contains `motion/react` or `framer-motion`; `app.js` does not import `./motion` or `motion`.
   - Use `$this->withoutVite()` in the HTTP tests if the manifest is absent.
9. Verification (put command + output in report): `npm run build` succeeds; `grep -l -E "inView|motion-ready|motionDone" public/build/assets/app-*.js` returns nothing while the same grep on `public-*.js` matches; `php artisan test` all green.
10. Write/complete `DECISIONS.md` (create if missing) including: scaffold via temp subfolder (no design file moved), user-scoped empty layouts (sidebar/bell deferred), backend packages from PRD §11 (maatwebsite/excel, barryvdh/laravel-dompdf, spatie/laravel-activitylog) deferred to the phases that use them, self-hosted fonts (DESIGN §4) deferred, DB left at skeleton default until a phase needs it, and any items from Task 2.
11. Commit ("Fase 0: motion (vanilla), entry publik, layout dan placeholder").
