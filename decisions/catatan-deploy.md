# Catatan deploy (lintas fase)

- `Cache::lock` per tahun (`irban-tahunan-{tahun}`, `irban-wilayah-{tahun}`) hanya melindungi bila cache store dipakai bersama semua proses: produksi wajib `CACHE_STORE=database` atau `redis` (bukan `array`/`file` per server). Indeks unik tetap menjadi jaring pengaman untuk Pegawai, Irban, Tim dan impor; pelanggarannya dikembalikan sebagai galat validasi, bukan 500.


## Notifikasi (Fase 6)
- Scheduler: pasang cron `* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1`. `notifikasi:harian` berjalan 06.00 WIB dan `notifikasi:ringkasan-mingguan` Senin 07.00 WIB (`routes/console.php`).
- Email dikirim lewat antrean (`QUEUE_CONNECTION=database`): `php artisan queue:work` wajib berjalan terus (supervisor, atau cron `queue:work --stop-when-empty` di shared hosting). Tanpa worker, entri lonceng tetap tertulis (koneksi `sync`) tetapi email tidak terkirim.
- SMTP di `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. `APP_URL` harus URL produksi karena tombol email memakai `url()`.
- Sebelum scheduler diaktifkan pada database produksi yang sudah berisi SPT: jalankan `notifikasi:harian` pertama kali akan mengirim notifikasi untuk setiap SPT yang sudah Terlambat atau sudah lewat 3 hari tanpa SPT final. Isi dulu kolom idempotensi untuk SPT yang sudah dalam keadaan itu, misalnya `UPDATE spt SET terlambat_diberitahu_pada = NOW(), pengingat_final_pada = NOW() WHERE status_pengajuan = 'diterima' AND ditandai_selesai_pada IS NULL;` (atau saring per kondisi bila pengingat untuk SPT tertentu tetap diinginkan).

## Ekspor (Fase 8)
- Ekspor > 1.000 baris diantrekan (`App\Jobs\BuatEkspor`, antrean `database`): `queue:work` wajib berjalan (lihat bagian Notifikasi). Tanpa worker, ekspor besar tetap `menunggu` dan pengguna tidak pernah mendapat notifikasi `File ekspor siap`.
- `DB_QUEUE_RETRY_AFTER` harus ≥ 660 (di atas timeout job ekspor 600 detik); bawaan `config/queue.php` sudah 660. `.env` lama dengan `DB_QUEUE_RETRY_AFTER=90` membuat job ekspor besar dijalankan ulang di tengah jalan oleh worker lain dan merusak ekspor.
- File ekspor ditulis ke disk privat `storage/app/private/ekspor/{uuid}.{xlsx|pdf}` dan hanya diunduh lewat rute `ekspor.unduh` (pemilik saja). Pastikan folder itu dapat ditulis oleh proses worker dan web, dan tidak berada di bawah `public/`.
- Ekspor PDF > 200 baris (dan xlsx > 1.000) diantrekan. Job menaikkan `memory_limit` ke 512M dan batas waktu ke 570 detik selama membangun file; pastikan host/worker mengizinkan `ini_set('memory_limit')` (tidak di `disable_functions`, bukan `php_admin_value` yang lebih rendah) dan RAM cukup untuk ±512 MB per worker ekspor. dompdf butuh ±0,26 MB per baris.
- `ekspor:bersihkan` dijadwalkan setiap hari 05.00 WIB (`routes/console.php`) dan menghapus file serta barisnya yang berumur > 7 hari; butuh cron `schedule:run` yang sama.

## Konten beranda (Fase 9)
- Jalankan `php artisan storage:link` sekali di server: gambar slide disimpan di `storage/app/public/slide/` dan dilayani lewat `public/storage`. Tanpa tautan ini gambar hero dan thumbnail Admin tidak tampil. Di shared hosting tanpa symlink, salin/arahkan `public/storage` ke `storage/app/public`.
- PHP butuh ekstensi GD dengan dukungan WebP (`imagewebp`). `upload_max_filesize` dan `post_max_size` minimal 6M agar gambar slide 5 MB bisa diunggah (batas unggahan sementara Livewire 12 MB). Pemrosesan menaikkan `memory_limit` ke 512M (lihat catatan ekspor tentang `ini_set`).
- Aktifkan ekstensi PHP `exif` agar foto JPEG dari ponsel diputar sesuai orientasi EXIF sebelum dipotong; tanpa ekstensi itu foto tegak bisa tersimpan miring.

## Profil dan sesi (Fase 9.2)
- Middleware `AuthenticateSession` aktif: setiap sesi divalidasi ulang terhadap hash kata sandi. Saat rilis, sesi lama yang belum punya hash tersimpan diisi otomatis pada permintaan berikutnya (tidak ada yang keluar); sejak itu setiap ganti kata sandi (Profil) atau atur ulang oleh Admin mengeluarkan sesi lain akun itu, termasuk perangkat "Ingat saya".
- Foto/latar profil disimpan di `storage/app/public/profil/` dan butuh `php artisan storage:link` (lihat catatan Konten beranda), GD dengan WebP, dan sebaiknya ekstensi `exif`.
- Tautan verifikasi email memakai `APP_URL` dan `APP_KEY`: pastikan `APP_URL` sama dengan alamat publik (https), kalau tidak tanda tangan tautan tidak cocok dan pengguna mendapat 403.
- Berkas SK Perubahan PKPT disimpan di disk privat `storage/app/private/perubahan-pkpt/{id}/{uuid}.pdf` dan hanya diunduh lewat rute `perubahan-pkpt.sk` (otorisasi per peran). Folder harus dapat ditulis proses web, tidak di bawah `public/`, dan ikut dicadangkan bersama database.

## Migrasi revisi jadwal (Fase 9.2)
- Migrasi `2026_11_20_000020_migrasi_revisi_jadwal` mengubah isi `pkpt_item_revisi` menjadi paket Perubahan (`sumber = migrasi_revisi`) dan Usulan, lalu menghapus tabel lama. **Tidak dapat dibalik** (`down()` melempar exception). Cadangkan database sebelum `php artisan migrate` di produksi; jalankan di jendela rilis dan jangan rollback. `migrate:fresh` pada database lokal tidak terpengaruh.
- Setelah migrasi, `queue:work` tetap wajib (email pegawai dan notifikasi Perubahan lewat antrean). Isi email pegawai (halaman Pegawai atau impor) agar pegawai "Diinput oleh" menerima pemberitahuan.

## Log aktivitas dan impor (Fase 10)
- `php artisan migrate` menambah tabel `activity_log` (paket `spatie/laravel-activitylog`) dan kolom `diinput_oleh`, `ip` serta migrasi SPT impor. Tidak ada seeder baru.
- **Jangan pernah menjadwalkan atau menjalankan `php artisan activitylog:clean`.** Paket tetap mendaftarkan perintah itu dan `config/activitylog.php` berisi `delete_records_older_than_days` = 365, tetapi perintah menghapus lewat query builder sehingga melewati penjaga `App\Models\Aktivitas` (log tidak boleh diubah/dihapus, PRD §7.12). Jangan masukkan ke `routes/console.php`/cron; sebaiknya larang dijalankan siapa pun (atau nonaktifkan di pipeline deploy). Penghapusan manual atas baris log juga dilarang.
- Tabel `activity_log` hanya bertambah. Perkiraan kasar 0,5-1 KB per baris (kolom `properties` JSON hanya memuat kolom yang berubah); beberapa ribu baris per bulan dalam pemakaian normal, dan satu impor SPT menambah 1 baris ringkasan plus baris per SPT/personel/kendala. Pantau ukuran tabel; pengarsipan di luar aplikasi dibahas di Fase 11.
- Sertakan `activity_log` dalam cadangan database (Fase 11): ini satu-satunya jejak audit.
- Setelah mengimpor SPT data lama ke produksi, bila pratinjau/hasil impor menampilkan peringatan `Nomor urut {n} melebihi nomor terakhir register {tahun}`, atur `Nomor awal` di Register untuk tahun berjalan sebelum menerbitkan nomor baru, agar nomor baru tidak bentrok dengan nomor impor.
- Impor berjalan sinkron di satu permintaan web (bukan antrean), maksimal 2000 baris per berkas `.xlsx` (5 MB). Berkas yang lebih besar dipecah. Pastikan `max_execution_time` PHP cukup (disarankan 120 detik) dan `upload_max_filesize`/`post_max_size` minimal 6M. Impor tidak mengirim notifikasi.
