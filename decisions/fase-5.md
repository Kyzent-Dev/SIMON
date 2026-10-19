# Keputusan Fase 5

## Fase 5, Task 1
- Kolom Selesai dan tabel `spt_kendala` ada di migration baru `2026_10_21_000011_add_selesai_and_kendala_to_spt.php`; migration Fase 4 tidak diubah. `Spt` juga mendapat relasi `ditandaiSelesaiOleh()` (belongsTo users).
- `Spt::status()` kini mengembalikan Pelaksanaan bila `tanggal_selesai` kosong setelah mulai (sebelumnya Penyusunan Laporan), sehingga sama dengan `scopeBerstatus`. Kedua sisi diuji per kasus lewat data provider di `StatusSptTest`.
- `PenandaSpt` adalah service instance (dependensi `KesesuaianPkpt` dan `Settings` lewat constructor), dipanggil `app(PenandaSpt::class)->untuk($spt)`, mengikuti pola `KesesuaianPkpt`; notasi `PenandaSpt::untuk` di plan dibaca sebagai nama metode, bukan metode statis.
- `terlambat` memakai `status()`, jadi SPT Selesai tidak pernah Terlambat. `spt_final_belum_diupload` diterapkan persis seperti Exact values (diterima, hari ini > mulai, tanpa dokumen `final`), termasuk untuk SPT yang sudah Selesai.
- `SptFactory::selesai()` = `diterima()` + `ditandai_selesai_pada` now + `ditandai_selesai_oleh` user irban. Tidak ada state tanggal tambahan; test mengisi tanggal langsung.

## Fase 5, Task 2
- `SptPolicy::laksanakan` = Irban pemilik + `diterima` + belum Selesai. Pesan `SPT ini sudah Selesai dan tidak dapat diubah.` (key `spt`) dilempar oleh trait `Actions\Spt\Concerns\MelaksanakanSpt` sebelum authorize, hanya untuk Irban pemilik, dan berlaku untuk keempat aksi (bukan hanya UbahTanggalRealisasi) agar UI bisa menampilkannya sebagai toast; pihak lain tetap 403.
- Setiap aksi mengunci baris (`lockForUpdate`) dan memeriksa ulang `laksanakan` di dalam transaksi. `TandaiSelesai` membaca `syarat_selesai` dan memvalidasi isian di dalam transaksi setelah kunci, sehingga perubahan Admin berlaku pada panggilan itu juga; dokumen yang sudah tertulis dihapus bila transaksi gagal.
- Logika bersama diekstrak tanpa mengubah perilaku `SimpanSpt`: `DiinputOleh::galat()` (aturan + pesan "Diinput oleh") dan `Support\BerkasSpt` (cek PDF + simpan `spt/{id}/{uuid}.pdf` + sha256 + baris `spt_dokumen`). Lampiran draf tetap 5 MB di `SimpanSpt`; aksi baru memakai `upload.maks_mb`.
- Kunci error: `berkas` (file), `konfirmasi`, `nomor_lhp`, `tanggal_lhp`, `diinput_oleh`, `spt` (diblokir/Selesai, `bisa()`), `tanggal_mulai` untuk "Tanggal realisasi tidak berubah.". Pesan tambahan di luar daftar plan: `Pilih berkas PDF.` (unggah tanpa file), `Isi tanggal mulai.`/`Isi tanggal selesai.`, `Alasan maksimal 1000 karakter.`, `Uraian kendala maksimal 2000 karakter.`, `Nomor LHP maksimal 100 karakter.`.
- `UnggahDokumenSpt::handle(..., string $jenis = 'final')` melempar `InvalidArgumentException` untuk jenis lain; cover LHP dan laporan hanya disimpan lewat `TandaiSelesai`. Kendala diisi pada SPT yang sudah Selesai ditolak (ikut `laksanakan`).
- `TandaiSelesai::syarat()` membaca klasifikasi dari DB (bukan relasi yang ter-cache) agar edit Admin langsung terbaca. Pada `konfirmasi`, berkas/nomor/tanggal LHP yang dikirim diabaikan.
