# Catatan deploy (lintas fase)

Panduan operator yang lengkap dan terurut ada di `DEPLOY.md` (akar repo); file ini menyimpan riwayat per fase.

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
- `php artisan migrate` menjalankan empat migrasi `activity_log`: `2026_11_25_000001_create_activity_log_table`, `..._000002_add_event_column_to_activity_log_table`, `..._000003_add_batch_uuid_column_to_activity_log_table` (ketiganya dari paket `spatie/laravel-activitylog`) dan `..._000004_add_diinput_oleh_ip_to_activity_log` (kolom `diinput_oleh`, `ip`). Tidak ada migrasi Fase 10 yang mengubah tabel `spt`. Tidak ada seeder baru.
- `php artisan activitylog:clean` dinonaktifkan: perintah paket diganti `App\Console\Commands\TolakBersihkanLog` (di-bind di `AppServiceProvider`), yang menolak dengan kode keluar 1 dan pesan `Log aktivitas tidak boleh dihapus (PRD §7.12).` tanpa menghapus apa pun (PRD §7.12). Nilai `delete_records_older_than_days` = 365 di `config/activitylog.php` tidak dipakai. Jangan menghapus baris log secara manual (query builder melewati penjaga `App\Models\Aktivitas`).
- Tabel `activity_log` hanya bertambah. Terukur rata-rata sekitar 230 B isi `properties` per baris (JSON hanya memuat kolom yang berubah), ditambah kolom tetap dan indeks. Impor SPT data lama mencatat 1 baris `created` per SPT ditambah 1 baris ringkasan `impor` per berkas (sekitar 1 baris per SPT; berkas 2000 baris kurang dari 2001 baris log), bukan baris personel/kendala/objek. Pantau ukuran tabel; pengarsipan di luar aplikasi dibahas di Fase 11.
- Bila aplikasi berada di balik reverse proxy (Nginx/load balancer), atur `trustProxies` di `bootstrap/app.php` (`$middleware->trustProxies(at: ...)`); tanpa itu `activity_log.ip` mencatat alamat proxy, bukan alamat klien.
- Sertakan `activity_log` dalam cadangan database (Fase 11): ini satu-satunya jejak audit.
- Setelah mengimpor SPT data lama ke produksi, bila pratinjau/hasil impor menampilkan peringatan `Nomor urut {n} melebihi nomor terakhir register {tahun}`, atur `Nomor awal` di Register untuk tahun berjalan sebelum menerbitkan nomor baru, agar nomor baru tidak bentrok dengan nomor impor.
- Impor berjalan sinkron di satu permintaan web (bukan antrean), maksimal 2000 baris per berkas `.xlsx` (5 MB). Berkas yang lebih besar dipecah. `Impor::impor()` menaikkan batas waktu ke 300 detik (`set_time_limit`); bila PHP-FPM/web server punya batas sendiri (`request_terminate_timeout`, `fastcgi_read_timeout`), samakan minimal 300 detik dan `upload_max_filesize`/`post_max_size` minimal 6M. Impor tidak mengirim notifikasi.

## Pengerasan (Fase 11)
- `TRUSTED_PROXIES` (env, `config('app.trusted_proxies')`): isi `*` atau daftar IP/CIDR proxy dipisah koma bila aplikasi berada di balik Nginx/load balancer, agar `request()->ip()` dan `activity_log.ip` mencatat alamat klien. Kosong = tidak percaya header `X-Forwarded-*`.
- Pembatas laju memakai cache default: pakai driver cache yang dibagi antarproses (database/redis/file), bukan `array`. Batas: halaman publik 120/menit per IP; ekspor 10/menit per pengguna; ganti email 3 per 10 menit per pengguna; login 5 per menit per nama pengguna+IP.
- Batas unggahan `upload.maks_mb` maksimal 12 MB (batas unggah sementara Livewire). `php artisan migrate` menjepit nilai tersimpan yang lebih besar menjadi 12. Samakan `upload_max_filesize` dan `post_max_size` PHP minimal 12M (disarankan 13M).
- Header keamanan (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`) dikirim aplikasi; jangan digandakan dengan nilai berbeda di web server. Belum ada CSP.
- Cache agregat publik (Fase 11 Task 4): `CACHE_STORE` yang dibagi antarproses (database/redis/file) kini wajib juga demi kesegaran halaman publik. Dengan `array` setiap proses PHP punya cache sendiri dan tidak melihat penyegaran dari proses lain. Cron `schedule:run` (sudah dipakai notifikasi) harus aktif: pekerjaan `publik:segarkan` 00:00 WIB mengganti hari pada angka publik.

## Cadangan (Fase 11)
- Paket `spatie/laravel-backup` ^10.3 (butuh `ext-zip`). Perintah terjadwal (WIB, butuh cron `schedule:run`): `backup:clean` 01.00, `backup:run` 01.30, `backup:monitor` 08.00. Isi zip: `storage/app/private` (tanpa `ekspor/` dan `livewire-tmp/`), `storage/app/public`, dan dump basis data. Berkas `.env` dan kode tidak ikut.
- Hasil tersimpan di disk `backup` = `storage/app/backup/<APP_NAME>/sintas-*.zip` (tidak ter-commit, tidak publik). Salin berkala ke luar server/mesin yang sama; cadangan di disk yang sama tidak melindungi dari kerusakan disk. Retensi: semua 7 hari, harian 30 hari, mingguan 8 pekan, bulanan 12 bulan, batas total 5000 MB.
- `DB_DUMP_BINARY_PATH`: folder berisi `mysqldump` (mis. `C:\xampp\mysql\bin`) bila tidak ada di PATH. Koneksi `DB_CONNECTION=mariadb` memanggil `mariadb-dump`; bila hanya ada `mysqldump` (XAMPP), pakai koneksi `mysql`.
- `BACKUP_MAIL_TO`: alamat penerima laporan sukses/gagal/tidak sehat; kosong = tanpa notifikasi (kegagalan hanya terlihat di keluaran perintah dan log). Isi dengan alamat valid, karena nilai tidak valid membuat perintah gagal. Memerlukan konfigurasi `MAIL_*` yang berfungsi.
- Pulihkan: ekstrak zip, muat `db-dumps/*.sql` dengan `mysql <db> < berkas.sql`, salin berkas `storage/app/private` dan `storage/app/public` dari zip kembali ke `storage/app/` (di dalam zip jalurnya memuat jalur absolut server asal). Uji pemulihan secara berkala.
- Perubahan Fase 11 Task 8: jalur di dalam zip cadangan kini relatif terhadap folder aplikasi (`storage/app/private/...`), bukan jalur absolut server asal seperti ditulis di butir "Pulihkan" di atas. Salin isi `storage/app` dari akar ekstraksi ke `storage/app/` aplikasi; langkah rinci di `DEPLOY.md`, "Cadangan dan pemulihan".

## Review akhir (Fase 11)
- `TRUSTED_PROXIES`: isi `127.0.0.1` (Nginx di mesin yang sama) atau IP/CIDR proxy. `*` hanya untuk proxy beralamat berubah-ubah dan hanya bila PHP-FPM/port aplikasi tidak dapat dijangkau langsung; kalau dapat, `X-Forwarded-For` palsu melewati pembatas laju publik dan memalsukan `activity_log.ip`. Menggantikan saran "`*` atau daftar IP" di bagian Pengerasan di atas.
- Pemangkasan cache: closure terjadwal `cache:pangkas` 00.10 WIB menghapus baris `cache`/`cache_locks` kedaluwarsa bila `CACHE_STORE=database` (store database tidak menghapusnya sendiri). Butuh cron `schedule:run`; `schedule:list` kini delapan pekerjaan.
- Dump cadangan tidak memuat `cache`, `cache_locks`, `sessions`, `jobs` (`exclude_tables` di `config/database.php`). Pemulihan ke basis kosong: `php artisan migrate --force` (kode versi sama dengan cadangan) dulu, lalu impor dump. Setelah pulih pengguna masuk ulang dan job antrean yang belum jalan hilang.
- `BACKUP_ARCHIVE_PASSWORD`: sangat disarankan di produksi (zip berisi hash kata sandi, NIP, PDF SPT dan disalin ke luar server). Zip AES-256: buka dengan `7z x`, bukan `unzip`. Simpan kata sandi bersama `APP_KEY`; tanpa itu cadangan tidak dapat dipulihkan.
- `KinerjaSeeder` kini menolak berjalan di `APP_ENV=production` (pesan galat, tanpa menulis data).
