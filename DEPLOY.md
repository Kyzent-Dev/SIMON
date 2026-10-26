# Panduan deploy SIMON-SPT

Panduan untuk operator server. Perintah di bawah ditulis untuk Linux (Ubuntu/Debian) dengan Nginx atau Apache dan PHP-FPM; jalur contoh `/var/www/simon`. Riwayat keputusan teknis ada di `decisions/catatan-deploy.md` dan `decisions/fase-11.md`.

## Kebutuhan server

- PHP 8.3 atau lebih baru dengan ekstensi `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `zip`, `gd` (dengan dukungan WebP), `exif`, `intl`. Periksa dengan `php -m` dan `php -r 'var_dump(function_exists("imagewebp"));'`.
- MySQL 8 atau MariaDB 10.6+. Biner dump harus ada di server: `mysqldump` untuk `DB_CONNECTION=mysql`, `mariadb-dump` untuk `DB_CONNECTION=mariadb` (paket klien MariaDB/MySQL).
- Composer 2.
- Node.js 20+ hanya di mesin build (`npm ci && npm run build`); server produksi tidak memerlukan Node.
- RAM minimal 1 GB, ditambah ±512 MB untuk setiap worker ekspor yang berjalan bersamaan.
- Disk: ruang untuk `storage/app` (dokumen SPT, SK, gambar) dan cadangan.
- Cron (untuk scheduler) dan supervisor (untuk queue worker), atau cron saja di shared hosting.

## Instalasi pertama

Jalankan sebagai pengguna aplikasi (bukan root) di folder aplikasi, mis. `/var/www/simon`.

1. Ambil kode: `git clone --branch main <url-repo> /var/www/simon && cd /var/www/simon`.
2. Pasang dependensi PHP: `composer install --no-dev --optimize-autoloader`.
3. Bangun aset (di mesin build bila server tanpa Node, lalu salin folder `public/build`): `npm ci && npm run build`.
4. Buat basis data kosong dan pengguna khusus:
   ```sql
   CREATE DATABASE simon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'simon'@'localhost' IDENTIFIED BY '<kata-sandi-kuat>';
   GRANT ALL PRIVILEGES ON simon.* TO 'simon'@'localhost';
   ```
5. Buat `.env`: `cp .env.example .env`, lalu isi sesuai bagian "Konfigurasi .env" (minimal `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, `DB_*`, `DB_DUMP_BINARY_PATH` bila perlu, `CACHE_STORE`, `QUEUE_CONNECTION`, `MAIL_*`).
6. Kunci aplikasi: `php artisan key:generate --force`. Simpan `APP_KEY` di tempat aman di luar server; kehilangan kunci ini membuat sesi dan tautan bertanda tangan tidak valid.
7. Migrasi: `php artisan migrate --force`.
8. Data rujukan: `php artisan db:seed --force`. Di produksi seeder hanya menulis Irban, Objek, dan Jenis Penugasan (klasifikasi datang dari migrasi). **Nama Objek dan Jenis Penugasan bawaan adalah contoh**: ganti dengan data sebenarnya lewat menu Admin sebelum aplikasi dipakai. Tidak ada akun, PKPT, atau SPT yang dibuat. `APP_ENV=production` di `.env` wajib sudah terisi: di lingkungan lain seeder demo ikut berjalan dan gagal (`Call to undefined function fake()`) karena paket pengembangan tidak terpasang (`--no-dev`).
9. Admin pertama: `php artisan simon:buat-admin <username> --nama="<Nama Lengkap>" --email=<email>`. Kata sandi (min. 8 karakter, huruf besar dan kecil, angka) ditanyakan dua kali tanpa ditampilkan dan tidak masuk riwayat shell maupun log; jalankan di terminal interaktif. Akun dibuat tanpa kewajiban ganti kata sandi karena operator memilihnya sendiri.
10. Tautan penyimpanan publik: `php artisan storage:link` (gambar slide dan foto profil dilayani lewat `public/storage`). Di shared hosting tanpa symlink, salin atau arahkan `public/storage` ke `storage/app/public`.
11. Izin folder: `storage` dan `bootstrap/cache` harus dapat ditulis oleh pengguna web dan worker, mis. `chown -R www-data:www-data storage bootstrap/cache`. `storage/app/private` tidak boleh berada di bawah `public/`.
12. Cache konfigurasi, rute, dan view: `php artisan optimize`.
13. Pasang scheduler dan queue worker (bagian "Cron dan scheduler" dan "Queue worker"), arahkan web server ke `public/`, lalu jalankan "Pemeriksaan setelah deploy".

`php artisan migrate:fresh` dan `migrate:fresh --seed` **tidak pernah** dijalankan di produksi: keduanya menghapus semua data, termasuk `activity_log` yang tidak boleh dihapus.

## Konfigurasi .env

Hanya kunci yang dibaca aplikasi dan berbeda dari bawaan Laravel atau `.env.example`.

| Kunci | Nilai produksi | Keterangan |
|---|---|---|
| `APP_NAME` | `SIMON-SPT` | Juga menjadi nama folder cadangan. |
| `APP_ENV` | `production` | Menentukan seeder produksi dan guard seeder demo. |
| `APP_DEBUG` | `false` | Wajib `false`; `true` menampilkan jejak galat. |
| `APP_URL` | `https://alamat-publik` | Harus alamat publik dengan https: tombol email dan tautan verifikasi memakai `url()` dan tanda tangannya bergantung pada nilai ini. |
| `APP_KEY` | dari `key:generate` | Jangan diganti setelah dipakai. |
| `APP_LOCALE` / `APP_TIMEZONE` | `id` / `Asia/Jakarta` | Jangan diubah: jadwal, tanggal, dan penomoran memakai zona Jakarta. |
| `DB_CONNECTION` | `mysql` atau `mariadb` | `mariadb` memanggil `mariadb-dump` saat cadangan; `mysql` memanggil `mysqldump`. |
| `DB_HOST` `DB_PORT` `DB_DATABASE` `DB_USERNAME` `DB_PASSWORD` | sesuai server | Pengguna khusus, bukan root. |
| `DB_DUMP_BINARY_PATH` | kosong | Folder berisi `mysqldump`/`mariadb-dump` bila tidak ada di PATH. |
| `SESSION_DRIVER` | `database` | Bawaan `.env.example`. |
| `SESSION_SECURE_COOKIE` | `true` | Setelah https aktif. |
| `CACHE_STORE` | `database` atau `redis` | **Wajib dibagi antarproses**, bukan `array`. Dipakai kunci per tahun (penomoran, komposisi irban), pembatas laju, dan cache angka publik. |
| `QUEUE_CONNECTION` | `database` | Email dan ekspor besar lewat antrean. |
| `DB_QUEUE_RETRY_AFTER` | `660` (atau hapus barisnya) | Harus lebih besar dari batas waktu job ekspor (600 detik). `.env` lama bernilai 90 merusak ekspor besar. |
| `MAIL_MAILER` | `smtp` | Lihat bagian "Email (SMTP)". |
| `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_SCHEME` `MAIL_FROM_ADDRESS` `MAIL_FROM_NAME` | sesuai penyedia | `MAIL_SCHEME=smtps` untuk port 465; kosongkan/`null` untuk STARTTLS di 587. |
| `TRUSTED_PROXIES` | `*` atau IP/CIDR proxy, dipisah koma | Wajib bila di belakang Nginx/load balancer; kosong berarti header `X-Forwarded-*` tidak dipercaya. |
| `BACKUP_MAIL_TO` | alamat email | Penerima laporan cadangan; kosong = tanpa notifikasi. |
| `LOG_LEVEL` | `warning` | Opsional. Dengan `MAIL_MAILER=log` pakai `debug`, kalau tidak isi email tidak tertulis di log. |

Setelah mengubah `.env`: `php artisan optimize` lalu `php artisan queue:restart`.

## Cron dan scheduler

Satu baris cron untuk pengguna aplikasi (`crontab -e`):

```
* * * * * cd /var/www/simon && php artisan schedule:run >> /dev/null 2>&1
```

Server boleh memakai zona waktu apa pun; setiap jadwal dipasang dengan zona `Asia/Jakarta`. Daftar pekerjaan (cek dengan `php artisan schedule:list`):

| Waktu (WIB) | Pekerjaan | Fungsi |
|---|---|---|
| Setiap hari 00.00 | `publik:segarkan` | Mengganti hari pada angka publik ("Hari ini", status). |
| Setiap hari 01.00 | `backup:clean` | Hapus cadangan lama sesuai retensi. |
| Setiap hari 01.30 | `backup:run` | Cadangan basis data dan berkas. |
| Setiap hari 05.00 | `ekspor:bersihkan` | Hapus file ekspor berumur > 7 hari. |
| Setiap hari 06.00 | `notifikasi:harian` | SPT Terlambat, pengingat SPT final, pengingat Perubahan PKPT. |
| Senin 07.00 | `notifikasi:ringkasan-mingguan` | Ringkasan mingguan ke Pimpinan. |
| Setiap hari 08.00 | `backup:monitor` | Memeriksa cadangan terbaru ada dan sehat. |

Tanpa cron ini cadangan tidak berjalan, angka publik tidak berganti hari, dan notifikasi harian tidak terkirim.

## Queue worker

Tanpa worker, email tidak terkirim dan ekspor besar (PDF > 200 baris, xlsx > 1.000 baris) tetap `menunggu` tanpa notifikasi. Lonceng di aplikasi tetap tertulis.

Supervisor (`/etc/supervisor/conf.d/simon-worker.conf`):

```
[program:simon-worker]
command=php /var/www/simon/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=600
directory=/var/www/simon
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/simon/storage/logs/worker.log
stopwaitsecs=3660
```

Lalu `sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl start simon-worker:*`. `--timeout=600` mengikuti batas job ekspor; `DB_QUEUE_RETRY_AFTER` (660) harus tetap lebih besar. Setiap worker ekspor dapat memakai ±512 MB (job menaikkan `memory_limit` ke 512M lewat `ini_set` dan batas waktu ke 570 detik): pastikan `ini_set` tidak ada di `disable_functions` dan RAM cukup.

Shared hosting tanpa supervisor: tambahkan cron setiap menit

```
* * * * * cd /var/www/simon && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

## Email (SMTP)

1. Isi `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` (lihat tabel). `MAIL_MAILER=log` hanya menulis email ke `storage/logs` (cocok untuk gladi; butuh `LOG_LEVEL=debug`).
2. Pastikan `APP_URL` adalah alamat publik: tombol di email dan tautan verifikasi email memakai `url()`; alamat salah membuat tanda tangan tidak cocok dan pengguna mendapat 403.
3. `php artisan optimize` dan `php artisan queue:restart`.
4. Uji: `php artisan tinker --execute="Mail::raw('uji', fn(\$m) => \$m->to('alamat@anda.id')->subject('Uji SIMON-SPT'));"`, lalu periksa kotak masuk (email aplikasi sendiri dikirim lewat antrean, jadi worker harus berjalan).
5. Pegawai tanpa alamat email tidak menerima pemberitahuan: isi email pegawai (menu Pegawai atau impor).

## Cadangan dan pemulihan

**Apa yang dicadangkan** (`spatie/laravel-backup`, `config/backup.php`): dump basis data (termasuk `activity_log`, satu-satunya jejak audit), `storage/app/private` (dokumen SPT, SK Perubahan PKPT; tanpa `ekspor/` dan `livewire-tmp/`), dan `storage/app/public` (slide, foto profil). `.env` dan kode tidak ikut: simpan `.env` (terutama `APP_KEY`) terpisah dan aman.

**Di mana:** `storage/app/backup/<APP_NAME>/simon-<tanggal>.zip` (disk `backup`, tidak publik, tidak ter-commit). Jalur di dalam zip relatif terhadap folder aplikasi (`storage/app/private/...`, `db-dumps/*.sql`).

**Kapan:** `backup:run` 01.30 WIB, `backup:clean` 01.00, `backup:monitor` 08.00 (lihat "Cron dan scheduler"). Jalankan sekali manual setelah instalasi: `php artisan backup:run`; lihat hasilnya dengan `php artisan backup:list`.

**Retensi:** semua cadangan 7 hari, satu per hari 30 hari, satu per pekan 8 pekan, satu per bulan 12 bulan; cadangan tertua dihapus bila total > 5000 MB. `activity_log` hanya bertambah (±230 B `properties` per baris), jadi pantau ukuran cadangan.

**Salin ke luar server.** Cadangan di disk yang sama tidak melindungi dari kerusakan disk. Contoh cron harian 03.00 dari mesin lain: `rsync -a simon@server:/var/www/simon/storage/app/backup/ /cadangan/simon/`, atau unggah ke penyimpanan objek.

**Laporan:** isi `BACKUP_MAIL_TO` dengan alamat valid untuk mendapat email sukses/gagal/tidak sehat (butuh `MAIL_*` yang berfungsi). Kosong = kegagalan hanya terlihat di keluaran perintah dan `storage/logs`. Alamat tidak valid membuat perintah gagal.

**Biner dump:** `DB_CONNECTION=mysql` memanggil `mysqldump`, `mariadb` memanggil `mariadb-dump`. Bila biner tidak di PATH, isi `DB_DUMP_BINARY_PATH` dengan foldernya. Di XAMPP (hanya `mysqldump`) pakai `DB_CONNECTION=mysql`.

**Pulihkan** (di server tujuan yang sudah terpasang sesuai "Instalasi pertama", atau di server yang rusak):

1. Ekstrak zip ke folder sementara (zip dari server Linux; zip buatan Windows memakai tanda `\` pada nama entri dan perlu diekstrak dengan alat yang menormalkannya): `mkdir /tmp/pulih && unzip storage/app/backup/SIMON-SPT/simon-<tanggal>.zip -d /tmp/pulih`.
2. Basis data: `mysql -u simon -p simon < /tmp/pulih/db-dumps/<berkas>.sql` (basis `simon` kosong atau yang akan ditimpa).
3. Berkas: entri zip relatif terhadap folder aplikasi, jadi salin dari akar ekstraksi ke akar aplikasi: `cp -a /tmp/pulih/storage/app/. /var/www/simon/storage/app/` (ini hanya menambahkan `private/` dan `public/`; jangan timpa `storage/app/backup`).
4. Pulihkan `.env` dari simpanan Anda, lalu `php artisan storage:link`, `php artisan optimize:clear`, `php artisan optimize`.
5. Periksa: masuk, buka satu SPT dan unduh dokumennya, buka `/jadwal`.
6. Hapus `/tmp/pulih` (berisi data pribadi).

**Uji pemulihan sekali** sebelum go-live dan berkala (mis. tiap kuartal) ke basis data sementara. Cadangan yang tidak pernah dipulihkan belum terbukti.

## Reverse proxy dan HTTPS

- Terminasi TLS di Nginx/load balancer. Set `APP_URL=https://...` dan `SESSION_SECURE_COOKIE=true`.
- Set `TRUSTED_PROXIES` ke `*` atau IP/CIDR proxy (dipisah koma). Tanpa itu `request()->ip()` dan `activity_log.ip` mencatat alamat proxy, dan aplikasi menyangka permintaan http.
- Proxy harus meneruskan `X-Forwarded-For`, `X-Forwarded-Proto`, `X-Forwarded-Host`, dan `Host`:
  ```
  proxy_set_header Host $host;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
  ```
- Aplikasi sendiri mengirim `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, dan `Permissions-Policy`; jangan digandakan dengan nilai berbeda di web server. Belum ada CSP. Tambahkan HSTS di web server setelah https stabil.
- Pembatas laju (halaman publik 120/menit per IP, ekspor 10/menit per pengguna, ganti email 3 per 10 menit, login 5 per menit per nama pengguna+IP) memakai `CACHE_STORE`; butuh `TRUSTED_PROXIES` benar agar "per IP" tidak berarti "per proxy".

## Batas PHP dan web server

| Pengaturan | Nilai | Alasan |
|---|---|---|
| `upload_max_filesize` dan `post_max_size` | minimal `13M` (minimal 12M) | Batas unggah aplikasi `upload.maks_mb` maksimal 12 MB (batas sementara Livewire); impor `.xlsx` 5 MB; gambar slide 5 MB. |
| Nginx `client_max_body_size` | `13m` | Selaras dengan di atas. |
| `memory_limit` | 256M atau lebih | Ekspor dan pemrosesan gambar menaikkannya sendiri ke 512M lewat `ini_set`; jangan blokir `ini_set` di `disable_functions` atau `php_admin_value` yang lebih rendah. |
| `max_execution_time` / `request_terminate_timeout` (PHP-FPM) / `fastcgi_read_timeout` (Nginx) | minimal 300 detik | Impor SPT data lama berjalan sinkron dalam satu permintaan (maks 2000 baris per berkas; `set_time_limit(300)`). |
| Ekstensi `gd` (WebP), `exif` | aktif | Gambar slide/profil diproses ke WebP; `exif` memutar foto ponsel sesuai orientasi. |
| `public/` | satu-satunya document root | Jangan arahkan ke folder aplikasi. |

## Memperbarui aplikasi

Dari folder aplikasi, pada jendela rilis:

1. Cadangan dulu: `php artisan backup:run` (wajib bila rilis memuat migrasi baru).
2. `php artisan down --retry=60`
3. `git pull --ff-only`
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build` (di mesin build; salin `public/build` bila perlu)
6. `php artisan migrate --force`
7. `php artisan optimize`
8. `php artisan queue:restart` (worker memuat kode baru)
9. `php artisan up`
10. Jalankan "Pemeriksaan setelah deploy".

Jangan menjalankan `migrate:rollback` atau `migrate:fresh` di produksi.

## Migrasi khusus

- **Migrasi revisi jadwal (Fase 9.2)** `2026_11_20_000020_migrasi_revisi_jadwal` mengubah isi `pkpt_item_revisi` menjadi paket Perubahan (`sumber = migrasi_revisi`) dan Usulan, lalu menghapus tabel lama. **Tidak dapat dibalik** (`down()` melempar exception). Pada database yang sudah berisi data lama: cadangkan sebelum `migrate`, jalankan di jendela rilis, jangan rollback. Pada instalasi baru tidak ada yang terpengaruh.
- **Batas unggah** `2026_12_01_000001_batasi_upload_maks_mb` menjepit nilai `upload.maks_mb` tersimpan yang lebih besar dari 12 menjadi 12 (idempoten).
- **Scheduler pertama kali pada database yang sudah berisi SPT:** `notifikasi:harian` pertama akan memberi notifikasi untuk setiap SPT yang sudah Terlambat atau lewat 3 hari tanpa SPT final. Isi dulu kolom idempotensi, sebelum cron dipasang:
  ```sql
  UPDATE spt SET terlambat_diberitahu_pada = NOW(), pengingat_final_pada = NOW()
  WHERE status_pengajuan = 'diterima' AND ditandai_selesai_pada IS NULL;
  ```
  (atau saring per kondisi bila pengingat untuk SPT tertentu tetap diinginkan). Pada instalasi baru tidak perlu.
- **Impor SPT data lama:** bila hasil impor memperingatkan `Nomor urut {n} melebihi nomor terakhir register {tahun}`, atur `Nomor awal` di Register untuk tahun berjalan sebelum menerbitkan nomor baru agar tidak bentrok. Impor berjalan sinkron, maks 2000 baris dan 5 MB per berkas `.xlsx`, tidak mengirim notifikasi.
- **Log aktivitas:** `activity_log` tidak boleh dihapus (PRD §7.12). `php artisan activitylog:clean` sengaja menolak dengan kode keluar 1. Jangan menghapus baris secara manual; pengarsipan dilakukan di luar aplikasi lewat cadangan.

## Data demo

Untuk presentasi di mesin lokal atau server demo (BUKAN produksi):

```
php artisan migrate:fresh --seed
```

`migrate:fresh --seed` **tidak pernah** dijalankan di produksi: menghapus seluruh data. Di `APP_ENV=production`, `db:seed --force` hanya menulis data rujukan (lihat Instalasi pertama).

Akun demo (kata sandi semuanya `password`, hanya dibuat di lingkungan non-produksi): `admin` (Admin), `pimpinan` (Pimpinan), `bagianumum` (Bagian Umum), `irban1` sampai `irban5` (Irban I sampai V). Ganti atau hapus bila server demo dapat dijangkau dari internet.

`KinerjaSeeder` hanya untuk pengukuran kinerja di mesin pengembang (±5.000 SPT, akun dengan kata sandi `password`); jangan dijalankan di produksi atau di server yang berisi data nyata.

Skrip presentasi 10 langkah (±15 menit):

1. Buka beranda `/` tanpa login: slide, angka ringkas, lalu `/jadwal` (linimasa dan kalender bulanan) dan `/realisasi` (grafik dan tabel). Tunjukkan bahwa tidak ada nama pegawai, NIP, atau nomor SPT.
2. Masuk sebagai `irban1`: Dasbor Irban (status SPT, penanda), lalu menu PKPT untuk melihat rencana yang sudah disahkan.
3. Masih `irban1`: Ajukan SPT. Pilih item PKPT, objek, tim; lihat peringatan kesesuaian; kirim pengajuan.
4. Keluar, masuk sebagai `bagianumum`: Kotak Masuk menampilkan pengajuan baru; Terima dan lihat nomor SPT terbit (atau Minta Revisi dengan catatan).
5. Masuk lagi sebagai `irban1`: lonceng notifikasi berisi keputusan; buka detail SPT, unggah dokumen SPT, isi kendala.
6. Masih `irban1`: Tandai Selesai dengan tanggal dan cover LHP; status berubah Selesai.
7. Masuk sebagai `pimpinan`: Dasbor Pimpinan, Monitoring SPT (filter irban, status, penanda Terlambat), lalu Rekap triwulan dan ekspor xlsx/PDF (unduhan muncul di notifikasi bila besar).
8. Masih `pimpinan`: Perubahan PKPT, buka satu paket yang diajukan, Setujui atau Tolak; kembali ke `/jadwal` untuk melihat jadwal berubah tanpa menunggu.
9. Masuk sebagai `admin`: Dasbor Admin, master data (Irban, Objek, Jenis Penugasan), Pengaturan (nomor dan tampilan publik), Slide beranda.
10. Masih `admin`: Log Aktivitas (siapa mengubah apa, dari IP mana), Pengguna, lalu Impor SPT data lama.

## Pemeriksaan setelah deploy

- [ ] `curl -sI https://alamat-publik/` mengembalikan `200`, header `X-Frame-Options` dan `X-Content-Type-Options` ada, tidak ada jejak galat di halaman.
- [ ] Halaman `/tidak-ada` menampilkan halaman 404 SIMON-SPT tanpa jejak tumpukan (`APP_DEBUG=false`).
- [ ] `/`, `/jadwal`, `/realisasi` terbuka tanpa login dan gambar slide tampil (`storage:link` benar).
- [ ] Login Admin berhasil; menu Profil terbuka dan menyimpan foto (GD/WebP/exif).
- [ ] `php artisan about` menunjukkan `Environment production`, `Debug mode OFF`, `Cache database`/`redis`, `Queue database`, `Maintenance mode OFF`.
- [ ] `php artisan schedule:list` menunjukkan tujuh pekerjaan (tabel "Cron dan scheduler"); `php artisan schedule:run` tidak galat.
- [ ] Worker berjalan: `sudo supervisorctl status simon-worker:*` RUNNING. Ekspor Register > 1.000 baris (atau PDF > 200 baris) selesai dan notifikasi `File ekspor siap` muncul.
- [ ] Email uji sampai ke kotak masuk (bagian "Email (SMTP)").
- [ ] `php artisan backup:run` sukses, `php artisan backup:list` menunjukkan `Healthy`, dan zip memuat `db-dumps/` serta `storage/app/private/`. Salinan ke luar server berjalan.
- [ ] Pemulihan diuji sekali ke basis sementara.
- [ ] Setelah login lewat proxy, baris `activity_log` terbaru berisi IP klien, bukan IP proxy (cek menu Log Aktivitas).
- [ ] Nama Objek dan Jenis Penugasan contoh sudah diganti dengan data sebenarnya.
- [ ] Tidak ada akun demo (`password`) di database produksi (menu Pengguna).
