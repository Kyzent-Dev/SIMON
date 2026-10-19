# Serah terima antar sesi

Baca file ini sebelum menulis plan fase berikutnya. Perbarui di akhir setiap fase (status branch, daftar tunda, jebakan baru). Diperbarui terakhir: 2026-10-19, setelah Fase 5.

## Status repo
- Branch bertumpuk: `fase-0-fondasi` → `fase-1-autentikasi` → `fase-2-master-data` → `fase-3-pkpt` → `fase-4-pengajuan` → `fase-5-spt` (HEAD). `main` hanya berisi dokumen awal. Belum ada yang di-merge.
- Repo belum punya remote dan `gh` belum login; `fase-3-pkpt` s.d. `fase-5-spt` belum di-push. Pengguna memilih "biarkan apa adanya" untuk Fase 3 dan 4. Untuk push/PR: pengguna menjalankan `gh auth login` dan `git remote add origin <url>` dulu.
- Fase 5 selesai: 5 tugas + review akhir (`decisions/fase-5.md`), 458 test lulus. Data demo: `php artisan migrate:fresh --seed` membuat SPT Irban I di setiap status pelaksanaan (Pelaksanaan, Penyusunan Laporan, Terlambat, Selesai, Tidak Sesuai PKPT + kendala). Setelah review akhir, controller mengecek sendiri di browser: detail SPT 9–13 di 360/768/1280 tanpa scroll horizontal, Lihat/Unduh 200 PDF, fokus empat modal, dan alur nyata Tandai Selesai; data demo lalu di-seed ulang.
- Device sempat bluescreen di tengah Task 4; `git fsck` bersih dan commit "WIP on fase-*" yang yatim hanyalah sisa stash tes yang isinya sudah tercakup commit berikutnya (sudah dicocokkan). Tidak ada pekerjaan yang hilang.
- Pengguna akan membuat repo GitHub dan menghubungkannya nanti; sampai saat itu cukup commit lokal, jangan push.
- Fase berikutnya: **Fase 6 (Notifikasi)**, buat branch `fase-6-notifikasi` dari `fase-5-spt`. Sisa fase: 6–11 (PRD §12).

## Cara kerja yang dipakai
- Per fase: tulis `docs/plans/fase-N-<nama>.md` (Spec, Global Constraints, Exact values, Task 1..5), commit, lalu eksekusi dengan skill `superpowers:subagent-driven-development` (implementer per tugas + review per tugas + review akhir seluruh branch + satu gelombang perbaikan). Untuk UI, implementer wajib memanggil skill `ui-ux-pro-max:ui-ux-pro-max`.
- Keputusan dicatat di `decisions/fase-N.md` (lihat CLAUDE.md "Decisions log").
- Graph dokumen: setelah ekstraksi semantik, simpan + rebuild dengan `scripts/rebuild_graph.py` (lihat docstring-nya). Jangan hapus file chunk sebelum skrip mencetak `wrote True`.
- Model: Opus untuk konkurensi, form kompleks, dan review akhir; Sonnet untuk layar yang meniru pola yang ada dan review per tugas; Haiku untuk review ulang perbaikan kecil dan ekstraksi graphify.
- Cek browser: `playwright-cli --config .playwright/cli.config.json` terhadap `php artisan serve`; screenshot ke folder scratchpad sesi yang sedang berjalan. Halaman login di `/masuk`. Untuk skrip uji, tulis fungsi `async page => {...}` ke file lalu `playwright-cli run-code --filename <file>` (`run-code` tidak menerima `--config`; `open` menerimanya). Hentikan server lewat PID yang mendengar di port-nya (`netstat -ano`), bukan PID shell pembungkus. Akun dev: `admin`, `pimpinan`, `irban1`, `bagianumum` (kata sandi `password`).

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
- **Penundaan dari fase sebelumnya:** saat fase N mewujudkan sesuatu yang dulu ditunda "ke Fase N" (mis. filter SPT Saya yang menyembunyikan Selesai), cari dan hapus sisa penundaannya (`grep "Fase N" decisions/ app/ resources/`).
- **Tab basi:** aksi yang ditolak karena status sudah berubah di tab lain (mis. SPT sudah Selesai) tampil sebagai toast lewat guard bersama, bukan 403, dan fokus pindah ke h1.
- **Pesan unggah Livewire:** batas sementara Livewire (12 MB) dan kegagalan unggah memakai `lang/id/validation.php` (`max.file`, `uploaded`, atribut field); field unggah baru wajib punya atribut di sana.

## Dibawa ke fase berikutnya
### Untuk Fase 6 (Notifikasi)
- Notifikasi revisi jadwal (diajukan/diputuskan), pengajuan baru, diterima/revisi/ditolak, dan nomor dibatalkan belum dikirim; titik kejadiannya ada di `app/Actions/Pkpt/*` dan `app/Actions/Spt/*`. Fase 5 menambah kejadian `tanggal_diubah`, `final_diunggah`, `ditandai_selesai`, `kendala_diisi` (`KejadianSpt`).
- Lonceng dan pengganti tahun di header masih ditunda (decisions/fase-2.md).
- Penanda Terlambat belum dites pada batas 23.30 WIB; uji bersama cron harian Terlambat.
- Batas unggah sementara Livewire tetap bawaan 12 MB; bila `upload.maks_mb` > 12, publikasikan config Livewire (`temporary_file_upload.rules`).

### Untuk Fase 7 (Komentar)
- Halaman detail SPT memakai urutan CSS `order-*` di mobile sehingga urutan DOM berbeda dari visual; cek ulang urutan fokus saat slot Komentar menambah kontrol.

### Untuk Fase 8 (Dashboard, rekap, ekspor)
- Ekspor register masih sinkron tanpa batas baris; ekspor besar lewat antrean belum ada.
- Kartu dasbor Bagian Umum, Admin (kesehatan), Pimpinan (Menunggu Persetujuan Anda) belum dibuat; daftar persetujuan revisi ada di `/pimpinan/revisi-jadwal`.
- Scope SQL untuk penanda (filter monitoring) belum ada; `PenandaSpt::untuk()` menjalankan ±2 query per SPT (dibatasi ukuran halaman).
- Tes status: tambahkan baris untuk SPT non-diterima dengan `ditandai_selesai_pada` lama (BU dapat membatalkan nomor SPT yang sudah Selesai; perilaku sudah benar).
- SPT Saya: kolom Ubah terdorong keluar layar di 1280 (tabel bergulir di kontainernya); Ringkasan Kesesuaian di detail dihitung untuk semua status, penanda hanya untuk diterima.

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
- `x-ui.file-upload`: input file tanpa nama aksesibel yang layak dan tanpa penanda wajib (Cover LHP); perbaiki di komponen bersama.
- Tes Fase 5: binding `x-ui.date-range` di modal Ubah Tanggal hanya dicek di browser; `assertSee` Tidak Sesuai PKPT ambigu; ketiadaan alert perlu-kendala belum dites; normalisasi `tanggal_lhp` hanya dengan input kanonis; `TandaiSelesai` mem-parse `tanggal_lhp` dua kali.
- Detail SPT: tautan nomor di Register tanpa ukuran target sentuh; `Spt\Detail` memakai trait `MelaksanakanSpt` hanya untuk `izinkan` (ikut membawa method privat yang tak terpakai).
- Seeder demo: PDF tulisan tangan tanpa tabel xref; `migrate:fresh --seed` meninggalkan PDF yatim di `storage/app/private/spt`; personel = dua pegawai pertama (bukan anggota Irban I), tanpa PPJ.
