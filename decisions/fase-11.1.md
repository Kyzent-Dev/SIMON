# Keputusan Fase 11.1

Ganti nama aplikasi SIMON-SPT menjadi SINTAS (Sistem Informasi Surat Tugas Pengawasan) dan pindah basis data lokal ke MySQL. Atas permintaan pengguna, 2026-10-27.

## Fase 11.1, Task 1

- Nama tampil `SIMON-SPT` menjadi `SINTAS` di layout, halaman galat, email (subjek `[SINTAS] ...`, header, footer, CTA `Buka SINTAS`) dan teks notifikasi; tagline panel merek menjadi "Sistem Informasi Surat Tugas Pengawasan — Inspektorat Daerah Kabupaten Muara Enim". `APP_NAME="SINTAS"` di `.env.example`.
- Pengenal teknis ikut diganti: kelas `App\Notifications\NotifikasiSimon` → `NotifikasiSintas`, tema email `simon` → `sintas` (`resources/views/vendor/mail/html/themes/sintas.css`), perintah `simon:buat-admin` → `sintas:buat-admin`, awalan zip cadangan `simon-` → `sintas-`, domain akun demo `@simon-spt.test` → `@sintas.test`. Tidak ada alias untuk nama lama: aplikasi belum pernah dideploy, jadi tidak ada cron, skrip, atau data produksi yang memakai nama lama.
- Folder cadangan mengikuti `APP_NAME`, jadi cadangan baru ada di `storage/app/backup/SINTAS/`; cadangan lama di `storage/app/backup/SIMON-SPT/` tidak dipindah.
- Yang sengaja tidak diganti: nama berkas `PRD-SIMON.md` (dirujuk di banyak dokumen; isinya sudah memakai nama baru), nama repo/remote GitHub `Kyzent-Dev/SIMON` (diganti pengguna sendiri), dan dokumen riwayat fase 0–11 (`docs/plans/fase-*.md`, `decisions/fase-0..11.md`) yang mencatat keadaan saat itu.
- Dokumen yang masih dipakai diperbarui: `DEPLOY.md` (jalur `/var/www/sintas`, basis/pengguna `sintas`, worker `sintas-worker`), `DESIGN.md`, `design/auth/login.md`, `design/dashboard/_shared.md`, `docs/plans/serah-terima.md`, `decisions/catatan-deploy.md`.

## Fase 11.1, Task 2

- `.env` lokal: `DB_CONNECTION=mysql`, basis `sintas` (utf8mb4 / `utf8mb4_unicode_ci`) di XAMPP, `DB_DUMP_BINARY_PATH="C:/xampp/mysql/bin"` agar `backup:run` menemukan `mysqldump`. `phpunit.xml` tetap SQLite in-memory.
- `HalamanLogAktivitasTest::test_diff_ditampilkan` gagal acak bila Faker memberi nama berapostrof (`D'Amore`), karena Blade menulis `&#039;`. Tes sekarang membandingkan dengan `e($u->name)`; kode aplikasi tidak berubah.
