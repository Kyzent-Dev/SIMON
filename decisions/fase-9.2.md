# Keputusan Fase 9.2

Spec: `fase-9.2-spesifikasi.md`. Plan: `docs/plans/fase-9.2-ekspor-perubahan-profil.md`.

## Fase 9.2, Task 1
- `App\Notifications\EmailPegawai` membungkus sebuah `NotifikasiSimon` (mail saja, `ShouldQueueAfterCommit`, properti `protected`); isi email dibangun oleh satu method `NotifikasiSimon::pesanEmail(?salam, ?penutup)` yang juga dipakai `toMail()`, jadi tidak ada dua pembangun.
- Salam `Yth. {nama_bergelar},` dan baris penutup hanya untuk email pegawai (K9); view `mail/notifikasi.blade.php` menerima `$salam`/`$penutup` opsional (di-escape seperti `catatan`). Email lama tetap tanpa salam. Komentar view "no greeting" diperbarui.
- `PenerimaNotifikasi::emailPegawai()` membandingkan dengan alamat efektif akun (`routeNotificationForMail()`: `email_notifikasi` + tembusan untuk Irban yang punya, bila tidak email akun), tanpa membedakan huruf besar/kecil. Email akun Irban yang tertimpa `email_notifikasi` bukan alamat tujuan, jadi tidak dianggap duplikat.
- Email pegawai disimpan huruf kecil dan di-trim; pencarian di halaman Pegawai juga mencakup email. Tabrakan unique di `SavePegawai` dipetakan ke `nip` bila NIP dipakai, selain itu ke `email`.
- Parser impor: validasi unik email tidak memakai aturan `unique` (NIP sama + email sama = sah); dibandingkan dengan NIP baris. Pratinjau impor mendapat kolom Email.
- Tabel Pegawai tidak punya kartu mobile (tabel gulir), jadi hanya kolom yang ditambah. Seeder: dua pegawai pertama Irban I mendapat `{slug nama}@example.test` bila email masih null.
