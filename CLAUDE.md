## Design source of truth (mandatory for all UI work)

Before writing or changing anything visual — Blade views, Blade/Livewire components, Tailwind classes, CSS, Alpine behavior, Chart.js config, animations, emails — you MUST:

1. Read `DESIGN.md` (repo root) in full. It defines tokens, colors, contrast rules, typography, components, motion and accessibility.
2. Read the page/role file(s) that match the task:

   | Task touches | Also read |
   |---|---|
   | Public header, footer, Home | `design/public/landing.md` |
   | Public Jadwal PKPT | `design/public/jadwal.md` |
   | Public Realisasi | `design/public/realisasi.md` |
   | Login, forgot/forced password, "Diinput oleh" | `design/auth/login.md` |
   | Any internal page (layout, sidebar, bell, tables, forms, modals, export, SPT detail) | `design/dashboard/_shared.md` |
   | Irban screens / submission form | `design/dashboard/_shared.md` + `design/dashboard/irban.md` |
   | Pimpinan screens / monitoring / recap | `design/dashboard/_shared.md` + `design/dashboard/pimpinan.md` |
   | Bagian Umum inbox / register | `design/dashboard/_shared.md` + `design/dashboard/bagian-umum.md` |
   | Admin screens | `design/dashboard/_shared.md` + `design/dashboard/admin.md` |

3. If a page file conflicts with `DESIGN.md`, `DESIGN.md` wins. If either conflicts with `PRD` business rules, the PRD wins on behavior and data; `DESIGN.md` wins on visuals. Note the conflict in `DECISIONS.md`.

## Hard rules (do not break without asking the user)
- Colors: use only Tailwind tokens defined in `DESIGN.md` §13 and only the text/background pairs in §3.4. Never use the forbidden combinations in §3.5. No raw hex in Blade except assignment-type colors via `--type` / `--type-fg` CSS variables.
- Status badges and flags: always `x-app.status-badge` / `x-app.flag` (icon + label). Never convey status by color alone.
- Stack: Blade + Livewire + Alpine + Tailwind + Chart.js. Do NOT install React, Vue, shadcn, Radix, Framer Motion, GSAP or Swiper.
- `motion` package: vanilla API only (`import { animate, inView, stagger } from "motion"`), public pages only, only through `resources/js/motion.js`, loaded only by `resources/js/public.js`. Never import `motion/react`. See `DESIGN.md` §7.6.
- No animation on dashboard tables, forms or the Bagian Umum screens (`DESIGN.md` §7.5). Every animation respects `prefers-reduced-motion`.
- Public pages never render personnel names, NIP, SPT numbers, objects below kecamatan, kendala, comments or documents.
- UI copy in Bahasa Indonesia; dates `translatedFormat('d F Y')`; timezone Asia/Jakarta.

## Before finishing a UI task
- Check the page at 360px, 768px and 1280px: no horizontal page scroll.
- Keyboard: every control reachable, visible focus ring (`DESIGN.md` §6).
- Re-read the "Do's and Don'ts" in `DESIGN.md` §14 against your diff.
- In your summary, state which design files you followed.
