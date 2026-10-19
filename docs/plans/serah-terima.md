# Serah terima antar sesi

Baca file ini sebelum menulis plan fase berikutnya. Perbarui di akhir setiap fase (status branch, daftar tunda, jebakan baru). Diperbarui terakhir: 2026-10-20, setelah Fase 4.

## Status repo
- Branch bertumpuk: `fase-0-fondasi` → `fase-1-autentikasi` → `fase-2-master-data` → `fase-3-pkpt` → `fase-4-pengajuan` (HEAD). `main` hanya berisi dokumen awal. Belum ada yang di-merge.
- Repo belum punya remote dan `gh` belum login; `fase-3-pkpt` dan `fase-4-pengajuan` belum di-push. Pengguna memilih "biarkan apa adanya" untuk keduanya. Untuk push/PR: pengguna menjalankan `gh auth login` dan `git remote add origin <url>` dulu.
- Fase berikutnya: **Fase 5 (SPT, status, dan penanda)**, buat branch `fase-5-spt` dari `fase-4-pengajuan`. Sisa fase: 5–11 (PRD §12), perkiraan ±30 tugas.

## Cara kerja yang dipakai
- Per fase: tulis `docs/plans/fase-N-<nama>.md` (Spec, Global Constraints, Exact values, Task 1..5), commit, lalu eksekusi dengan skill `superpowers:subagent-driven-development` (implementer per tugas + review per tugas + review akhir seluruh branch + satu gelombang perbaikan). Untuk UI, implementer wajib memanggil skill `ui-ux-pro-max:ui-ux-pro-max`.
- Keputusan dicatat di `decisions/fase-N.md` (lihat CLAUDE.md "Decisions log").
- Graph dokumen: setelah ekstraksi semantik, simpan + rebuild dengan `scripts/rebuild_graph.py` (lihat docstring-nya). Jangan hapus file chunk sebelum skrip mencetak `wrote True`.
- Model: Opus untuk konkurensi, form kompleks, dan review akhir; Sonnet untuk layar yang meniru pola yang ada dan review per tugas; Haiku untuk review ulang perbaikan kecil dan ekstraksi graphify.
- Cek browser: `playwright-cli --config .playwright/cli.config.json` terhadap `php artisan serve`; screenshot ke folder scratchpad sesi yang sedang berjalan. Akun dev: `admin`, `pimpinan`, `irban1`, `bagianumum` (kata sandi `password`).

## Jebakan yang sudah diketahui (salin ke Global Constraints setiap plan)
Masing-masing pernah lolos ke review dan memakan satu putaran perbaikan.
- **Fokus setelah aksi:** bila modal ditutup lalu tombol pemicu/baris/kartu hilang atau menjadi nonaktif, pindahkan fokus ke target stabil (heading atau elemen dengan `tabindex="-1"`) lewat event yang di-dispatch dari komponen; jangan biarkan fokus jatuh ke `body`.
- **`sr-only` di dalam kontainer gulir:** sel/pembungkus yang memuat span `sr-only` di dalam `overflow-x-auto` wajib `relative`, kalau tidak halaman melebar di 360px. Cek `document.documentElement.scrollWidth <= 360` dengan disclosure/detail terbuka.
- **"Diinput oleh":** selalu di footer form/modal (login.md §5), tidak di badan.
- **Aturan bisnis tidak dihitung ulang di komponen:** bila UI butuh status aturan (strip validasi, durasi), action/service menyediakan method non-throwing yang dipakai UI dan validasi sekaligus (contoh `ValidatePersonel::cek()`, `Tanggal::durasiPenugasan()`).
- **Galat non-kolom:** kunci `ValidationException` yang tidak punya field di form ditampilkan sebagai toast, tidak hilang diam-diam.
- **Lookup id selalu di-scope** ke irban pengguna, termasuk method pratinjau/peringatan, bukan hanya aksi simpan.
- **Zona waktu:** kolom tanggal/waktu disimpan dalam waktu Jakarta (APP_TIMEZONE); jangan `->utc()` saat membandingkan.
- **Ekspor xlsx:** pakai `App\Exports\ArrayExport` (sudah menyimpan teks berawalan `= + - @` sebagai string). Jangan membuat ekspor baru tanpa binder itu.
- **Carbon:** `parse('')` menghasilkan "sekarang"; tolak nilai kosong sebelum parse.
- **Livewire:** nama method tidak boleh sama dengan nama properti publik; properti id yang tidak boleh diubah klien diberi `#[Locked]`; otorisasi di `mount()` dan setiap aksi (endpoint update tidak menjalankan middleware route).
- **Factory dengan `unique()` tahun** bisa bentrok dengan tahun eksplisit di test; pakai rentang di luar tahun yang ditulis di test.
- **Penomoran:** `Penomoran::ambil` hanya di dalam `DB::transaction(..., attempts: 3)` dan selalu dengan klasifikasi.
- **Staging git:** stage file satu per satu; jangan pernah `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`.

## Dibawa ke fase berikutnya
### Untuk Fase 5 (SPT, status, penanda)
- Tambahkan aturan Selesai di `Spt::status()` (titik ekstensi `statusBerjalan`) dan samakan `Spt::scopeBerstatus` dengan `status()`, termasuk kasus `tanggal_selesai` null.
- Penanda Tidak Sesuai PKPT memakai `KesesuaianPkpt::sesuai()`; Terlambat memakai `penanda.batas_terlambat_hari`; SPT Final Belum Diupload dari `spt_dokumen` jenis `final`.
- `spt_dokumen` sudah mendukung jenis `final` dan `cover_lhp`; unggah lewat pola `SimpanSpt` (disk privat, SHA-256, route `spt.dokumen`).
- Label kejadian riwayat masih di-hardcode di `resources/views/livewire/bagian-umum/partials/detail-pengajuan.blade.php`; pindahkan ke `KejadianSpt::label()` saat membuat halaman detail SPT.
- Halaman detail SPT bersama (_shared.md §7) belum ada; inbox BU dan SPT Saya belum menautkan ke detail.

### Untuk Fase 6 (Notifikasi)
- Notifikasi revisi jadwal (diajukan/diputuskan), pengajuan baru, diterima/revisi/ditolak, dan nomor dibatalkan belum dikirim; titik kejadiannya ada di `app/Actions/Pkpt/*` dan `app/Actions/Spt/*`.
- Lonceng dan pengganti tahun di header masih ditunda (decisions/fase-2.md).

### Untuk Fase 8 (Dashboard, rekap, ekspor)
- Ekspor register masih sinkron tanpa batas baris; ekspor besar lewat antrean belum ada.
- Kartu dasbor Bagian Umum, Admin (kesehatan), Pimpinan (Menunggu Persetujuan Anda) belum dibuat; daftar persetujuan revisi ada di `/pimpinan/revisi-jadwal`.

### Untuk Fase 11 (Pengerasan dan deploy)
- Jalankan `PenomoranKonkurensiTest` terhadap MySQL (locking di MySQL baru dianalisis, belum dijalankan).
- SQLite IMMEDIATE hanya berlaku di PHP ≥ 8.4; composer masih `^8.3`. Naikkan bila SQLite dipakai di produksi.
- `phpunit.xml` memakai `memory_limit` 512M (puncak suite ±118 MB; tumbuh, bukan bocor).
- Produksi wajib `CACHE_STORE` bersama (decisions/catatan-deploy.md).

### Minor tertunda (belum dijadwalkan)
- Impor item PKPT: `analyze()` menjalankan ±2 query per baris setiap render; muat irban/jenis sekali.
- `{klasifikasi}` di template nomor hanya dihapus bersama `/` di sebelahnya; validasi template di Pengaturan (juga cegah tabrakan `nomor_lengkap` bila scope/template diganti di tengah tahun).
- Kotak masuk: penghitung karakter catatan `aria-live` terlalu sering bicara; tes pintasan keyboard hanya memeriksa string sumber JS.
- Form pengajuan: render ulang memuat semua pegawai aktif dan objek; ganti ke pencarian server bila daftar membesar (decisions/fase-4.md).
- Halaman Ubah SPT tidak menyorot item sidebar.
- `PkptDetail` menghitung `rentang()` per item yang direvisi (N+1 kecil).
- Revisi/Tolak memvalidasi catatan sebelum cek peran (422, bukan 403).
