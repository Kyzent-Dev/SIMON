# Catatan deploy (lintas fase)

- `Cache::lock` per tahun (`irban-tahunan-{tahun}`, `irban-wilayah-{tahun}`) hanya melindungi bila cache store dipakai bersama semua proses: produksi wajib `CACHE_STORE=database` atau `redis` (bukan `array`/`file` per server). Indeks unik tetap menjadi jaring pengaman untuk Pegawai, Irban, Tim dan impor; pelanggarannya dikembalikan sebagai galat validasi, bukan 500.


## Notifikasi (Fase 6)
- Scheduler: pasang cron `* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1`. `notifikasi:harian` berjalan 06.00 WIB dan `notifikasi:ringkasan-mingguan` Senin 07.00 WIB (`routes/console.php`).
- Email dikirim lewat antrean (`QUEUE_CONNECTION=database`): `php artisan queue:work` wajib berjalan terus (supervisor, atau cron `queue:work --stop-when-empty` di shared hosting). Tanpa worker, entri lonceng tetap tertulis (koneksi `sync`) tetapi email tidak terkirim.
- SMTP di `.env`: `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. `APP_URL` harus URL produksi karena tombol email memakai `url()`.
- Sebelum scheduler diaktifkan pada database produksi yang sudah berisi SPT: jalankan `notifikasi:harian` pertama kali akan mengirim notifikasi untuk setiap SPT yang sudah Terlambat atau sudah lewat 3 hari tanpa SPT final. Isi dulu kolom idempotensi untuk SPT yang sudah dalam keadaan itu, misalnya `UPDATE spt SET terlambat_diberitahu_pada = NOW(), pengingat_final_pada = NOW() WHERE status_pengajuan = 'diterima' AND ditandai_selesai_pada IS NULL;` (atau saring per kondisi bila pengingat untuk SPT tertentu tetap diinginkan).
