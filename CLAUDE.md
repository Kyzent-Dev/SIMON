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

3. If a page file conflicts with `DESIGN.md`, `DESIGN.md` wins. If either conflicts with `PRD` business rules, the PRD wins on behavior and data; `DESIGN.md` wins on visuals. Note the conflict in the current phase's decisions file (see "Decisions log" below).

## Session handoff
- Before writing the next phase's plan (or resuming work in a new session), read `docs/plans/serah-terima.md`: repo/branch status, working method, the "Jebakan yang sudah diketahui" list (copy it into every plan's Global Constraints), and items carried to later phases.
- At the end of each phase, update that file: status, new pitfalls that cost a fix round, and deferred minors from the reviews.
- To rebuild the graph after a semantic doc extraction, use `scripts/rebuild_graph.py` (see its docstring); delete the chunk file only after it prints `wrote True`.

## Decisions log (one file per phase)
- Record every technical decision or deviation in `decisions/fase-N.md` for the phase being worked on (heading `# Keputusan Fase N`, sections `## Fase N, Task M`). Cross-phase deploy notes go in `decisions/catatan-deploy.md`.
- `DECISIONS.md` is only an index: never add decisions to it. When starting a new phase, create `decisions/fase-N.md` and add one line to the index.
- Never edit an earlier phase's file except to fix it; add a new bullet to the current phase instead. This keeps `/graphify . --update` re-extracting only the changed phase file.
- Put this rule in every plan's Global Constraints and every implementer/subagent prompt that may record a deviation (replace "DECISIONS.md under `## Fase N, Task M`" with "`decisions/fase-N.md` under `## Fase N, Task M`").

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

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

Session start (mandatory, before the first task of every new session):
- If `graphify-out/graph.json` exists, read `graphify-out/GRAPH_REPORT.md` (Summary, Community Hubs, God Nodes, Surprising Connections) and build a mental map of the codebase before doing anything else. This is the one case where reading the whole report is expected.
- If it does not exist (fresh clone: `graphify-out/` is git-ignored), build it from scratch with `/graphify .` first, then read the report as above.

Keeping the graph current (automatic, not optional):
- After adding or changing code, run `graphify update .` before you finish the task or hand back to the user.
- After changing docs (`DECISIONS.md`, `decisions/*`, `docs/plans/*`, `PRD`, `DESIGN.md`, `design/*`), run `/graphify . --update` so the semantic nodes are re-extracted.
- If the git post-commit hook is installed (`graphify hook status`; install with `graphify hook install`), it also rebuilds the code part of the graph after every commit. It does not cover docs, and it does not replace the steps above.

Rules:
- When graphify-out/graph.json exists, run `graphify query "<question>"` before your first grep, search or source-file read for a codebase question, and put this rule in every subagent prompt that explores code. Read raw files only for lines the graph or an earlier read already located. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
- `graphify-out/` is git-ignored: never stage or commit it. Each clone builds its own graph.
