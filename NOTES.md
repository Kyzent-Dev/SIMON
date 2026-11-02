# Catatan untuk masa depan

## CVE-2026-115560 (PHP session upload progress): tunggu rilis PHP yang menambal

Status per 2026-11-01: **belum ada versi PHP yang menambalnya.** PHP 8.5.12 (21 Oktober 2026) hanya menambal CVE-2026-109618/109619; perbaikan CVE ini direncanakan di rilis reguler berikutnya.

Mitigasi yang sedang berlaku (versi 1.1.5):
- `public/.user.ini`: `session.upload_progress.enabled=0` (PHP-FPM/CGI).
- `public/.htaccess`: blok `<Files ".user.ini"> Require all denied </Files>` agar berkas itu tidak bisa diunduh di Apache.
- `DEPLOY.md` bagian "Mitigasi keamanan PHP": pengaturan `php.ini` server dan cara ceknya.
- Mesin dev (XAMPP): `C:\xampp\php\php.ini` sudah `session.upload_progress.enabled = 0` (2026-11-01).

Risiko SINTAS rendah: eksploitasi butuh serializer sesi `php_binary`/`php_serialize` dan kode yang mengganti handler sesi lewat `ini_set()`; SINTAS memakai serializer `php` bawaan dan sesi Laravel.

### Yang harus dilakukan setelah PHP merilis tambalan
1. Pantau https://www.php.net (ChangeLog/rilis keamanan) untuk versi 8.5.x yang menyebut CVE-2026-115560.
2. Perbarui PHP di mesin dev (XAMPP) dan server ke versi itu.
3. Naikkan `require.php` di `composer.json` ke versi itu (mis. `^8.5.13`), jalankan `composer update --lock`, pastikan `vendor/composer/platform_check.php` memeriksa versi baru, lalu jalankan suite tes.
4. Hapus `public/.user.ini` dan blok `<Files ".user.ini">` di `public/.htaccess`.
5. Perbarui `DEPLOY.md`: syarat PHP minimum baru, hapus bagian "Mitigasi keamanan PHP" (atau tandai tidak lagi diperlukan).
6. Opsional: kembalikan `session.upload_progress.enabled` di `php.ini` dev/server (SINTAS tidak memakai fitur ini; membiarkannya `0` juga aman).
7. Catat di `decisions/fase-N.md` versi yang mengerjakannya dan perbarui `docs/plans/serah-terima.md`.
