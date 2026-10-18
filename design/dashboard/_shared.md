# Dashboard Shared Patterns

> Read `DESIGN.md` first, then this file, before any role file in `design/dashboard/`.

## 1. Shell
- **Sidebar** (`@persist('sidebar')`):
  - Sizing: 264px expanded / 72px collapsed (≥1024), white with border-r neutral-200.
  - Top: logo + "SIMON-SPT".
  - Nav items: 40px tall, `rounded-md`, icon 20 + label 14/500 neutral-700. Hover neutral-50. Active mint-50 bg + mint-700 text + 3px left mint-700 indicator + `aria-current="page"`.
  - Section labels use overline style.
  - Collapse toggle at the bottom ("Ciutkan menu"); its state is persisted with Alpine `$persist`. Collapsed items show tooltips.
  - Below 1024 the sidebar becomes an off-canvas `x-ui.drawer` from the left (hamburger in the header).
- **Top header:** 64px, white, border-b.
  - Left: hamburger (<1024) + breadcrumbs ("Dasbor / SPT / Detail").
  - Right: bell, year switcher ("Tahun 2026"), user menu (avatar initials on mint-100/mint-800, account name, role caption; items "Profil", "Ubah kata sandi", "Keluar").
- **Page header:** h1 + description + right-aligned actions (primary at the far right). Mobile: actions wrap below, and the primary goes full width.
- **Content:** bg neutral-25, cards white.

## 2. Notification bell, dropdown, list page
- **Bell:** 40px ghost icon button, `aria-label="Notifikasi, 3 belum dibaca"`. Unread shows a red dot plus a count badge (danger-700 bg, white 11px/700, min 18px, "9+" cap) at the top right.
- **New notification** (polled via `wire:poll.60s` or a broadcast): the bell plays `bell-ring` once, the badge pops, and an sr-only `aria-live="polite"` region announces "1 notifikasi baru".
- **Dropdown** (reference: 21st.dev Vercel Notification Popover):
  - 400px popover, right-aligned (desktop); bottom sheet on mobile.
  - Header "Notifikasi" + "Tandai semua dibaca".
  - Tabs "Semua | Belum dibaca".
  - Items: icon tile by type, title 14/600, context caption ("SPT 700/751/INSPEKTORAT-II/SPT/2026"), relative time ("5 menit lalu", absolute on hover/title). Unread items get a mint-50 bg + 8px mint-700 dot.
  - Show at most 8 items, with a footer link "Lihat semua notifikasi".
  - Opens on click; closes on Esc or outside click.
- **List page:** filter tabs, a table/list with per-item "Tandai dibaca", bulk select, pagination.
  - Empty state: "Belum ada notifikasi".
- **Email template:**
  - 600px single column, system font stack (Arial/Helvetica fallback).
  - Header band mint-950 with logo + "SIMON-SPT" white and a 3px accent-400 bottom rule.
  - White body, 16px neutral-900 text.
  - CTA button as a bulletproof table: mint-700 bg, white text, 8px radius, "Buka SPT".
  - Footer in neutral-600 12px: "Email otomatis dari SIMON-SPT Inspektorat Daerah Kabupaten Muara Enim. Jangan membalas email ini."
  - Always include a plain-text part.
  - Subject format: "[SIMON-SPT] Pengajuan nomor SPT perlu direvisi".

## 3. Tables
- `x-ui.table`. Default density is comfortable; Bagian Umum and Register use compact.
- Search input "Cari…" (debounce 300ms) at the top left; filters and export at the top right.
- Row actions use a kebab menu (`ellipsis-vertical`, `aria-label="Aksi untuk SPT …"`) plus the primary action inline.
- SPT numbers use tnum and are copyable (`clipboard` icon button, toast "Nomor disalin").
- Mobile: tables listed as "card" in role files become cards. Others scroll horizontally with the sticky first column.

## 4. Forms
- Single column, max-w 720 (complex forms use a 2-col grid ≥1024).
- Sections are separated by an h3 + divider.
- Sticky footer action bar (white, border-t, shadow-up): left "Diinput oleh" (irban), right "Batal" (ghost), "Simpan draf" (secondary), primary.
- Unsaved-changes guard on leaving: "Perubahan belum disimpan. Tinggalkan halaman?"
- Validation: server-side via Livewire, displayed instantly with no animation.

## 5. Modals, toasts, empty states, loading
- **Confirm modal (md):** title, body explaining the consequence, buttons "Batal" + primary or danger. Destructive actions need a typed reason when the PRD requires it.
- **Toasts:** top-right stack (max 3) desktop, bottom-center mobile above safe-area.
  - Copy: "Draf tersimpan", "Pengajuan terkirim ke Bagian Umum", "Gagal menyimpan. Periksa koneksi Anda."
- **Empty states** are task-specific, each with an action.
- **Loading:**
  - Skeletons for first load.
  - `wire:loading.delay` (≥200ms) dims the region to 60% with `aria-busy="true"`.
  - Buttons show a spinner + "Memproses…".

## 6. Filter bar and export
- **Filter bar:** inline selects (Tahun, Triwulan, Irban, Status, Jenis Penugasan, Klasifikasi as relevant), "Atur ulang", and active chips. Filters live in the URL. Mobile uses a drawer.
- **Export:** `x-app.export-buttons` is a split button "Ekspor" with menu items "Excel (.xlsx)" and "PDF". Subtext "Mengikuti filter aktif".
  - Small exports download immediately.
  - Large exports (>1,000 rows) are queued: toast "Ekspor sedang disiapkan. Anda akan menerima notifikasi saat file siap." The notification then carries "Unduh".

## 7. SPT detail page (shared; permissions vary)
- **Header:**
  - SPT number (h1, tnum; "Nomor belum terbit" in neutral-500 italic if pending)
  - status badge + flags
  - type chip, klasifikasi, irban
  - role-permitted actions on the right
- **Layout ≥1024:** 8/4.
- **Left column** (tabs or anchored sections):
  - "Ringkasan": GOV.UK-style summary list `<dl>` with Untuk, Wilayah/Objek, Periode ("3–21 Maret 2026 · 19 hari"), PKPT terkait, Kesesuaian.
  - "Personel": table of name, NIP, peran, in order.
  - "Dokumen": PDF list with icon, name, size, uploader and "Diinput oleh", plus "Lihat" and "Unduh".
  - "Komentar": threaded comments. A "Minta Penjelasan" comment gets a warning-tinted card with label "Permintaan Penjelasan · Terbuka/Selesai". Reply box at the bottom.
- **Right column:**
  - "Riwayat Status": a vertical stepper. Each step shows status badge, date-time "18 Oktober 2026 10.42 WIB", actor and note.
  - "Kendala" card.
  - "Log Aktivitas" (collapsed).
- **Mobile:** the header, then sections stacked in this order: Ringkasan, Riwayat, Personel, Dokumen, Komentar.
- **Motion:** none, apart from the tab underline (`--dur-fast`).
