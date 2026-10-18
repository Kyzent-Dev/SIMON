# DECISIONS

- Versions: livewire/livewire v4.4.7 (installed on laravel/framework ^13.17, no conflicts), tailwindcss 4.3.3 + @tailwindcss/vite 4.3.3 (vite 8.3.4), @tailwindcss/forms 0.5.11, alpinejs/@alpinejs/intersect/focus/sort ^3.17.4, chart.js ^4.5.1. Reason: latest stable; `npm run build` passes.
- Alpine plugins: Livewire 4.4.7's `livewire.esm.js` already bundles Alpine with intersect, sort, focus (plus morph, history, resize, collapse, anchor, mask). `resources/js/app.js` therefore registers none via `Alpine.plugin` and only calls `Livewire.start()`. Reason: avoid a second Alpine instance and double registration. The npm packages `alpinejs`, `@alpinejs/*` stay installed as requested but are unused.
- Chart.js: installed only, not imported in `app.js` (no chart in Fase 0).
- `@source` lines for Pagination views and `storage/framework/views` kept from the skeleton, above `@plugin`.
- `resources/js/bootstrap.js` does not exist in the Laravel 13 skeleton; Task 3's `public.js` imports `./bootstrap`, so Task 3 must create it or drop that import.
