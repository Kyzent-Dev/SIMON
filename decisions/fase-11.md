# Keputusan Fase 11

## Plan
- Jawaban pengguna (2026-10-26) atas pertanyaan sebelum plan: (1) belum ada server staging, jadi syarat PRD §12 "aplikasi berjalan di server staging" diganti gladi deploy mirip produksi di mesin lokal mengikuti `DEPLOY.md` (Task 8); deploy staging nyata dilakukan pengguna nanti. (2) Target kinerja PRD §10 (< 2 detik, ±5.000 SPT) masuk lingkup untuk dasbor dan monitoring internal juga, bukan hanya cache publik (Task 3). (3) Cadangan memakai `spatie/laravel-backup` (Task 6). (4) Tiga pertanyaan terbuka Fase 10 dikerjakan: log pivot `spt_objek`/`irban_anggota`, pasangan `mint-700 | neutral-50` di DESIGN §3.4 (izin mengubah `DESIGN.md` untuk satu baris itu), dan RSUD di panel Wilayah (Task 5).
- Ruling: data kinerja = 5 tahun × 1.000 SPT (bukan 5.000 dalam satu tahun), sesuai bunyi PRD §10 "data 5 tahun (sekitar 5.000 SPT)"; monitoring bawaan menampilkan satu tahun. Biaya bila salah: target untuk 5.000 SPT setahun belum terbukti.
- Ruling: panel Wilayah publik tetap tiga kolom (design/public/jadwal.md), RSUD tetap digabung di Dinas/Badan di sana; hanya panel Irban yang mendapat daftar RSUD sendiri dan catatan yang dikoreksi. Biaya bila salah: satu pemetaan di `JadwalPublik::wilayah`.
- Ruling: `upload.maks_mb` dibatasi 12 (batas sementara Livewire) alih-alih memublikasikan config Livewire; menutup daftar tunda "bila `upload.maks_mb` > 12".
- Ruling: tanpa CSP di fase ini (Alpine/Livewire perlu ditinjau dulu); hanya empat header keamanan dasar.
- Ruling: produksi hanya menjalankan seeder referensi; admin pertama dibuat lewat `simon:buat-admin`.
