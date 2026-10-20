# Keputusan Fase 6

## Fase 6, Task 1
- Migration baru `2026_10_22_000012_create_notifications_table.php` (standar `make:notifications-table`, nama file diurutkan setelah migration Fase 5) dan `2026_10_22_000013_add_notifikasi_columns_to_spt.php` (`terlambat_diberitahu_pada`, `pengingat_final_pada`). Cast kolom baru di `Spt` diserahkan ke Task 3 yang memakainya.
- `NotifikasiSimon` mengimplementasikan `ShouldQueueAfterCommit` (turunan `ShouldQueue`), bukan properti `$afterCommit = true`: properti itu bentrok dengan `$afterCommit` milik trait `Queueable` (PHP fatal error). `SendQueuedNotifications` membaca interface ini sebagai afterCommit, sehingga baris lonceng (koneksi `sync`) dan email sama-sama batal bila transaksi di-rollback (diuji).
- `via()` memakai `routeNotificationFor('mail', $this)`; `User::routeNotificationForMail()` mengembalikan `list<string>|null` (selalu array, `null` bila kosong). Irban tanpa relasi irban (`irban_id` kosong) jatuh ke email akun sendiri.
- Email: tema markdown `resources/views/vendor/mail/html/themes/simon.css` + `config/mail.php` `markdown.theme = 'simon'` dan `markdown.paths = [resource_path('views/vendor/mail')]` (tanpa `paths`, komponen yang dipublikasikan tidak terbaca). Komponen yang diubah saja yang dipublikasikan: `html/layout` (lebar 600, media query header), `html/header` (pita mint-950, teks `SIMON-SPT`), `html/footer` (600), `html/button` (tabel bulletproof, warna di sel dan tautan lewat kelas tema), `html/message` dan `text/message` (teks header dan footer Indonesia). Sisanya jatuh ke komponen bawaan Laravel.
- Isi email memakai view markdown sendiri `resources/views/mail/notifikasi.blade.php` (lewat `MailMessage::markdown()`), bukan template `notifications::email` global: tanpa sapaan "Hello!" dan penutup "Regards", baris `> Catatan: …` sebagai blockquote (baris baru di catatan diganti spasi agar tetap satu kutipan), tombol rata kiri, subcopy tautan cadangan dalam bahasa Indonesia. Email bawaan Laravel lain (mis. reset password) tidak berubah isinya, hanya ikut header/footer/tema.
- Tidak ada file logo di repo, jadi header email hanya teks `SIMON-SPT` putih di atas mint-950 dengan garis bawah 3px accent-400.
- Satu-satunya lokasi hex mentah adalah `simon.css` (email tidak bisa memakai Tailwind); nilainya disalin dari token `DESIGN.md` §13 dan pasangan warnanya dari §3.4 (white/mint-950, neutral-900/white, neutral-900/mint-50, white/mint-700, mint-700/white, neutral-600/white, neutral-600/neutral-25). Blade email tidak memakai atribut `bgcolor` agar tetap tanpa hex.
- Label tombol `Buka SPT` untuk jenis `pengajuan_*`, `nomor_dibatalkan`, `spt_terlambat`, `pengingat_spt_final`; `Buka SIMON-SPT` untuk revisi jadwal dan ringkasan mingguan. Daftar ada di konstanta `NotifikasiSimon::JENIS_SPT`.
- `isi()` dimasukkan lewat `MailMessage::lines()` sebelum `action()`, karena baris yang ditambahkan setelah `action()` menjadi outro (di bawah tombol).
- `PenerimaNotifikasi` adalah service instance (`app(PenerimaNotifikasi::class)`) mengikuti pola `PenandaSpt`, mengembalikan Eloquent `Collection<User>`. Tidak ada filter akun nonaktif karena tabel `users` tidak punya kolom status.

## Fase 6, Task 2
- Notifikasi dikirim dari action setelah `DB::transaction` selesai, lewat `Notification::send(app(PenerimaNotifikasi::class)->...)`. `SimpanSpt` mengirim `PengajuanBaru` setiap mode `ajukan`: hanya Draf/Perlu Revisi yang bisa disimpan, jadi setiap ajukan adalah transisi ke `menunggu_nomor` (pengajuan pertama dan ajukan ulang); simpan draf atau ubah draf tidak mengirim.
- Notifikasi menyimpan string/skalar (bukan model) yang dihitung di konstruktor, supaya payload antrean kecil dan isi bel/email sama dengan saat kejadian. Rentang tanggal memakai `Tanggal::rentang` (contoh `2–20 Maret 2026`), bukan format `d F Y – d F Y` harfiah, agar konsisten dengan tampilan jadwal yang sudah ada.
- `PengajuanDiputuskan` mengambil catatan dari alasan riwayat yang dikembalikan `putuskan()` (null untuk diterima); tiga action memakai trait yang sama sehingga satu titik kirim.
- `CatatNomorManual` tidak mengirim notifikasi: PRD §7.10 tidak mencantumkan kejadian itu.

## Fase 6, Task 3
- `notifikasi:harian` sends `SptTerlambat` and `PengingatSptFinal` once per SPT (`terlambat_diberitahu_pada`, `pengingat_final_pada`). A date change that clears and later re-creates Terlambat does not resend.
- Terlambat uses `PenandaSpt::untuk()` (no second implementation of the rule); the pengingat rule (today >= tanggal_mulai + 3 days, no `final` document) is evaluated in the command because `PenandaSpt` has no 3-day flag.
- `RingkasanMingguan::hitung(CarbonImmutable $senin)` returns a plain array (keys `awal, akhir, nomor, selesai, menunggu_nomor, revisi, terlambat, daftar, lainnya`); the notification takes that array. The window is `[senin-7d 00.00, senin 00.00)` on `spt_riwayat.created_at` (Jakarta time); the terlambat list is oldest tanggal_selesai first, capped at 10.
- Command classes are auto-discovered from `app/Console/Commands`; schedule entries live in `routes/console.php`.

## Fase 6, Task 4
- Shared read logic lives in `App\Support\NotifikasiPengguna` (`tandaiDibaca`, `tandaiSemua`, `buka`, all through `$user->notifications()`), so the bell in Task 5 reuses it and the list component holds no query rules.
- Page uses `#[Layout('layouts::app', ['breadcrumbs' => ['Dasbor' => null, 'Notifikasi' => null]])]`; "Dasbor" is plain text (not a link) because each role's dashboard route differs. No sidebar item was added (the bell is the entry point).
- `x-ui.page-header` gained an optional `titleId` prop (h1 gets `id` + `tabindex="-1"`) so focus can move to the heading after a bulk action empties the view.
- `x-ui.pagination` per-page options are 20/50/100 here (default 20 per the plan) instead of 25/50/100.
- Icon tile tones reuse the already verified status-badge token pairs: info = siap_dilaksanakan, success = selesai, warning = menunggu_nomor, danger = ditolak, neutral = neutral-100/neutral-700 (no new color pairs).
- Selection (`terpilih`) is cleared on page change; header checkbox and the bulk button are driven by Alpine `$wire.terpilih`, the bulk button is disabled with none selected. A bulk action that marks 0 rows toasts info `Tidak ada notifikasi yang perlu ditandai dibaca` (no false success).
- Item link has a real `href` (works without JS) and `wire:click.prevent="buka(id)"` to mark read before navigating.
