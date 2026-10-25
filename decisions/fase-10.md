# Keputusan Fase 10

Plan: `docs/plans/fase-10-impor-log.md`. Spec: `PRD-SIMON.md` §7.11, §7.12, §12 Fase 10.

## Plan
Pilihan tingkat plan yang dibuat controller tanpa bertanya (bisa diubah pengguna; biaya bila salah di akhir bullet).
- Log aktivitas memakai `spatie/laravel-activitylog` ^4.12 (PRD §11). Versi 5 butuh PHP ^8.4 sedangkan `composer.json` masih `^8.3`; 4.12 mendukung Laravel 13. Biaya bila salah: naik versi saat PHP dinaikkan.
- Kolom tambahan `activity_log.diinput_oleh` (tanpa FK, karena log harus bertahan walau pegawai dihapus) dan `ip`. "Diinput oleh" diambil dari `Context` (diisi action lewat `DiinputOleh::catat`) lalu dari atribut `diinput_oleh` subjek. Biaya: satu migrasi bila FK diinginkan.
- Model yang dicatat = daftar E1 di plan; tabel pivot (`spt_objek`, `irban_anggota`) tidak dicatat, jadi perubahan objek SPT tidak terlihat di log. Biaya: pencatatan manual di `SimpanSpt` bila diperlukan.
- Log dapat dilihat Admin (`/admin/log`) dan Pimpinan (`/pimpinan/log`, menu baru di sidebar Pimpinan) sesuai PRD §7.12; kartu "Log Aktivitas" di detail SPT hanya untuk Admin dan Pimpinan (Irban dan Bagian Umum tidak), walau `_shared.md` §7 menaruhnya di halaman bersama. Biaya: satu kondisi.
- Seeder dan factory ikut menulis log (tanpa mematikan logging) agar data demo punya "Aktivitas terbaru". Biaya: suite sedikit lebih lambat.
- Impor data lama hanya untuk SPT (Pegawai dan item PKPT sudah punya impor sendiri); halaman `/admin/impor` = menu "Impor Excel". Impor Pegawai dan PKPT ikut menulis ringkasan impor agar kartu "Impor terakhir" mencakup ketiganya.
- SPT impor membawa nomor lamanya (tidak mengambil nomor dari counter), `nomor_counter_id` null, `nomor_urut` dibaca dari segmen kedua nomor; nomor urut di tahun berjalan yang melewati counter hanya diperingatkan (Admin mengatur nomor awal). Biaya bila salah: nomor ganda saat penerbitan berikutnya menjadi galat unik (tidak diam-diam).
- Item PKPT dikaitkan otomatis bila tepat satu item aktif PKPT Disahkan (irban, jenis, tahun tanggal mulai, rentang beririsan) cocok; selain itu Non-PKPT dengan peringatan (bukan galat) untuk klasifikasi wajib item. Biaya: penanda Non-PKPT pada data lama yang ambigu.
- Status Selesai data lama: kolom `selesai` (ya/tidak) atau pasangan LHP terisi; Selesai tanpa LHP untuk klasifikasi `lhp_lengkap` hanya peringatan. SPT impor tidak pernah mendapat penanda "SPT final belum diunggah" dan kolom idempotensi notifikasi diisi saat impor agar tidak ada notifikasi massal.
- Personel opsional per baris (data Sistem B tidak memuatnya); bila ada, aturan komposisi sama dengan form (`ValidatePersonel::cek`).
