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
