# DECISIONS

- Versions: livewire/livewire v4.4.7 (installed on laravel/framework ^13.17, no conflicts), tailwindcss 4.3.3 + @tailwindcss/vite 4.3.3 (vite 8.3.4), @tailwindcss/forms 0.5.11, alpinejs/@alpinejs/intersect/focus/sort ^3.17.4, chart.js ^4.5.1. Reason: latest stable; `npm run build` passes.
- Alpine plugins: Livewire 4.4.7's `livewire.esm.js` already bundles Alpine with intersect, sort, focus (plus morph, history, resize, collapse, anchor, mask). `resources/js/app.js` therefore registers none via `Alpine.plugin` and only calls `Livewire.start()`. Reason: avoid a second Alpine instance and double registration. The npm packages `alpinejs`, `@alpinejs/*` stay installed as requested but are unused.
- Chart.js: installed only, not imported in `app.js` (no chart in Fase 0).
- `@source` lines for Pagination views and `storage/framework/views` kept from the skeleton, above `@plugin`.
- `resources/js/bootstrap.js` created as a one-line comment-only module so `public.js` stays verbatim from DESIGN §7.6 (`import "./bootstrap"`). Reason: the Laravel 13 skeleton has no bootstrap.js.
- Removed the skeleton's `fonts: [bunny('Instrument Sans')]` block and `bunny` import from `vite.config.js`. Reason: DESIGN §4 specifies Inter and Plus Jakarta Sans, self-hosted, no hot-linking. Self-hosting itself is deferred to a later phase.
- Layouts: `layouts/public.blade.php` (loads `app.css` + `public.js`, no Livewire) and `layouts/app.blade.php` (`app.css` + `app.js`, `@livewireStyles`, `@livewireScriptConfig`) are empty shells, used as `<x-layouts::public>` / `<x-layouts::app>` (Livewire's `layouts::` namespace maps to `resources/views/layouts`). Sidebar and bell deferred from PRD Fase 0 (user scoped Fase 0 to empty layouts).
- Livewire script: no `config/livewire.php` is published and none changed; `/dashboard` renders only `@livewireScriptConfig` + the Vite `app.js` module (no auto-injected `livewire.js`), so Livewire starts once.
- Scaffold was created in a temp subfolder and moved to the root; no design file was moved or overwritten.
- Backend packages from PRD §11 (maatwebsite/excel, barryvdh/laravel-dompdf, spatie/laravel-activitylog) are deferred to the phases that use them.
- DB left at the skeleton default until a phase needs it.
- Removed `welcome.blade.php` and the stock `ExampleTest` (covered by `LayoutPlaceholderTest`); `/` is the public placeholder, `/dashboard` the app placeholder, no auth.
