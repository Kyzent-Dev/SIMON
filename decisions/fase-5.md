# Keputusan Fase 5

## Fase 5, Task 1
- Kolom Selesai dan tabel `spt_kendala` ada di migration baru `2026_10_21_000011_add_selesai_and_kendala_to_spt.php`; migration Fase 4 tidak diubah. `Spt` juga mendapat relasi `ditandaiSelesaiOleh()` (belongsTo users).
- `Spt::status()` kini mengembalikan Pelaksanaan bila `tanggal_selesai` kosong setelah mulai (sebelumnya Penyusunan Laporan), sehingga sama dengan `scopeBerstatus`. Kedua sisi diuji per kasus lewat data provider di `StatusSptTest`.
- `PenandaSpt` adalah service instance (dependensi `KesesuaianPkpt` dan `Settings` lewat constructor), dipanggil `app(PenandaSpt::class)->untuk($spt)`, mengikuti pola `KesesuaianPkpt`; notasi `PenandaSpt::untuk` di plan dibaca sebagai nama metode, bukan metode statis.
- `terlambat` memakai `status()`, jadi SPT Selesai tidak pernah Terlambat. `spt_final_belum_diupload` diterapkan persis seperti Exact values (diterima, hari ini > mulai, tanpa dokumen `final`), termasuk untuk SPT yang sudah Selesai.
- `SptFactory::selesai()` = `diterima()` + `ditandai_selesai_pada` now + `ditandai_selesai_oleh` user irban. Tidak ada state tanggal tambahan; test mengisi tanggal langsung.
