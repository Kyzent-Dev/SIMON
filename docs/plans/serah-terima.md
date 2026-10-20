# Serah terima antar sesi

Baca file ini sebelum menulis plan fase berikutnya. Perbarui di akhir setiap fase (status branch, daftar tunda, jebakan baru). Diperbarui terakhir: 2026-10-20, setelah Fase 6 (termasuk review akhir dan perbaikannya).

## Status repo
- Branch bertumpuk: `fase-0-fondasi` → `fase-1-autentikasi` → `fase-2-master-data` → `fase-3-pkpt` → `fase-4-pengajuan` → `fase-5-spt` → `fase-6-notifikasi` (HEAD). `main` hanya berisi dokumen awal. Belum ada yang di-merge.
- Repo belum punya remote dan `gh` belum login; `fase-3-pkpt` s.d. `fase-5-spt` belum di-push. Pengguna memilih "biarkan apa adanya" untuk Fase 3 dan 4. Untuk push/PR: pengguna menjalankan `gh auth login` dan `git remote add origin <url>` dulu.
- Fase 5 selesai: 5 tugas + review akhir (`decisions/fase-5.md`), 458 test lulus. Data demo: `php artisan migrate:fresh --seed` membuat SPT Irban I di setiap status pelaksanaan (Pelaksanaan, Penyusunan Laporan, Terlambat, Selesai, Tidak Sesuai PKPT + kendala). Setelah review akhir, controller mengecek sendiri di browser: detail SPT 9–13 di 360/768/1280 tanpa scroll horizontal, Lihat/Unduh 200 PDF, fokus empat modal, dan alur nyata Tandai Selesai; data demo lalu di-seed ulang.
- Device sempat bluescreen di tengah Task 4; `git fsck` bersih dan commit "WIP on fase-*" yang yatim hanyalah sisa stash tes yang isinya sudah tercakup commit berikutnya (sudah dicocokkan). Tidak ada pekerjaan yang hilang.
- Pengguna akan membuat repo GitHub dan menghubungkannya nanti; sampai saat itu cukup commit lokal, jangan push.
- Fase 6 (Notifikasi): 5 tugas + review akhir selesai (`decisions/fase-6.md`, bagian "Final review"), 526 test lulus. Review akhir memperbaiki jendela ringkasan mingguan yang bergantung locale (`startOfWeek(MONDAY)`) dan sinkronisasi hitungan lonceng dengan halaman daftar (event `notifikasi-diperbarui`/`lonceng-diperbarui`). Infrastruktur: tabel `notifications`, basis `App\Notifications\NotifikasiSimon` (lonceng `database`/`sync` + email antrean, afterCommit), penerima hanya dari `App\Services\PenerimaNotifikasi`, ikon per jenis di `App\Support\JenisNotifikasi`, baca/tandai di `App\Support\NotifikasiPengguna`. Kejadian: pengajuan baru, diputuskan (terima/revisi/tolak), nomor dibatalkan, revisi jadwal diajukan/diputuskan, Terlambat dan pengingat SPT final (`notifikasi:harian` 06.00), ringkasan mingguan (Senin 07.00). UI: lonceng di header (popover ≥640, bottom sheet <640) dan halaman `/notifikasi`. Data demo: `migrate:fresh --seed` memberi `irban1`, `bagianumum`, `pimpinan` notifikasi dibaca dan belum dibaca. Catatan deploy (cron, `queue:work`, SMTP, isi kolom idempotensi sebelum cron pertama) di `decisions/catatan-deploy.md`.
- Fase berikutnya: **Fase 7 (Komentar)**, buat branch `fase-7-komentar` dari `fase-6-notifikasi`. Sisa fase: 7–11 (PRD §12).

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
- **Notifikasi:** kirim dari action (setelah `DB::transaction`) atau command, tidak dari komponen; penerima hanya dari `PenerimaNotifikasi`; kelas baru turunan `NotifikasiSimon` (payload `jenis, judul, konteks, url, catatan`, `url` relatif); id notifikasi dari klien selalu lewat `$user->notifications()`. Konteks yang menggabungkan bagian opsional (jenis penugasan null untuk SPT khusus) dirangkai dengan `filter()->implode(' · ')`, bukan konkatenasi.
- **Alpine `$refs` setelah request Livewire:** bila tombol pemicu terhapus oleh morph, ambil referensi target fokus sebelum `$wire.aksi()` (`const h = $refs.x; $wire.aksi().then(() => h.focus())`); `$refs` dari elemen yang sudah terlepas tidak terselesaikan dan fokus jatuh ke `body`.
- **`Notification::sendNow` meng-clone notifikasi:** `$n->id` tidak terisi setelah kirim; isi `$n->id` sendiri bila barisnya perlu dicari lagi (seeder).

## Dibawa ke fase berikutnya
### Untuk Fase 7 (Komentar)
- Halaman detail SPT memakai urutan CSS `order-*` di mobile sehingga urutan DOM berbeda dari visual; cek ulang urutan fokus saat slot Komentar menambah kontrol.
- Notifikasi komentar / minta penjelasan / balasan (PRD §7.10) dibangun di atas infrastruktur Fase 6: kelas baru turunan `NotifikasiSimon`, penerima lewat method baru di `PenerimaNotifikasi`, jenis + ikon baru di `JenisNotifikasi::ikon()` (lonceng dan halaman daftar otomatis ikut), label tombol email lewat `NotifikasiSimon::JENIS_SPT` bila terkait SPT.
- Pengganti tahun di header dan menu pengguna ("Profil", "Ubah kata sandi") masih ditunda (decisions/fase-2.md); lonceng sudah ada.

### Untuk Fase 8 (Dashboard, rekap, ekspor)
- Ekspor register masih sinkron tanpa batas baris; ekspor besar lewat antrean belum ada. Notifikasi "File ekspor siap" (dengan "Unduh") dibuat di sini sebagai turunan `NotifikasiSimon`.
- Breadcrumb "Dasbor" di halaman Notifikasi masih teks biasa (rute dasbor berbeda per peran).
- Kartu dasbor Bagian Umum, Admin (kesehatan), Pimpinan (Menunggu Persetujuan Anda) belum dibuat; daftar persetujuan revisi ada di `/pimpinan/revisi-jadwal`.
- Scope SQL untuk penanda (filter monitoring) belum ada; `PenandaSpt::untuk()` menjalankan ±2 query per SPT (dibatasi ukuran halaman).
- Tes status: tambahkan baris untuk SPT non-diterima dengan `ditandai_selesai_pada` lama (BU dapat membatalkan nomor SPT yang sudah Selesai; perilaku sudah benar).
- SPT Saya: kolom Ubah terdorong keluar layar di 1280 (tabel bergulir di kontainernya); Ringkasan Kesesuaian di detail dihitung untuk semua status, penanda hanya untuk diterima.

### Untuk Fase 11 (Pengerasan dan deploy)
- Jalankan `PenomoranKonkurensiTest` terhadap MySQL (locking di MySQL baru dianalisis, belum dijalankan).
- SQLite IMMEDIATE hanya berlaku di PHP ≥ 8.4; composer masih `^8.3`. Naikkan bila SQLite dipakai di produksi.
- Batas unggah sementara Livewire tetap bawaan 12 MB; bila `upload.maks_mb` > 12, publikasikan config Livewire (`temporary_file_upload.rules`).
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
- Notifikasi (review Fase 6): baris `catatan`/isi email melewati Markdown (teks pengguna bisa menambah tautan); entri `email_tembusan` tidak di-trim; CTA `Buka SIMON-SPT` hanya dites untuk `ringkasan_mingguan`; tes rollback di `NotifikasiPengajuanRevisiTest` tidak membedakan afterCommit; `toMail` hanya diperiksa untuk `PengajuanDiputuskan`, belum untuk isi `SptTerlambat`/`PengingatSptFinal`; baris `dan {k} lainnya.` belum dites; `RingkasanMingguan` menyimpan `CarbonImmutable` di payload antrean; SPT dengan `tanggal_mulai` null atau yang tak pernah mengunggah final dipindai ulang setiap hari; kolom idempotensi tetap diisi walau irban tak punya akun; keputusan Task 3 ditulis dalam bahasa Inggris.
- Halaman daftar notifikasi: hitungan di tab "Belum dibaca (N)" adalah tambahan di luar spesifikasi; `updated()` + `resetPage` rapuh; belum ada tes potongan halaman 2, pengosongan pilihan saat ganti halaman, clamp `perPage`, dan penangan klik-dengan-modifier. (Komentar basi di awal `daftar.blade.php` sudah diperbarui di Task 5.)
