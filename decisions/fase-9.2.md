# Keputusan Fase 9.2

Spec: `fase-9.2-spesifikasi.md`. Plan: `docs/plans/fase-9.2-ekspor-perubahan-profil.md`.

## Fase 9.2, Task 1
- `App\Notifications\EmailPegawai` membungkus sebuah `NotifikasiSimon` (mail saja, `ShouldQueueAfterCommit`, properti `protected`); isi email dibangun oleh satu method `NotifikasiSimon::pesanEmail(?salam, ?penutup)` yang juga dipakai `toMail()`, jadi tidak ada dua pembangun.
- Salam `Yth. {nama_bergelar},` dan baris penutup hanya untuk email pegawai (K9); view `mail/notifikasi.blade.php` menerima `$salam`/`$penutup` opsional (di-escape seperti `catatan`). Email lama tetap tanpa salam. Komentar view "no greeting" diperbarui.
- `PenerimaNotifikasi::emailPegawai()` membandingkan dengan alamat efektif akun (`routeNotificationForMail()`: `email_notifikasi` + tembusan untuk Irban yang punya, bila tidak email akun), tanpa membedakan huruf besar/kecil. Email akun Irban yang tertimpa `email_notifikasi` bukan alamat tujuan, jadi tidak dianggap duplikat.
- Email pegawai disimpan huruf kecil dan di-trim; pencarian di halaman Pegawai juga mencakup email. Tabrakan unique di `SavePegawai` dipetakan ke `nip` bila NIP dipakai, selain itu ke `email`.
- Parser impor: validasi unik email tidak memakai aturan `unique` (NIP sama + email sama = sah); dibandingkan dengan NIP baris. Pratinjau impor mendapat kolom Email.
- Tabel Pegawai tidak punya kartu mobile (tabel gulir), jadi hanya kolom yang ditambah. Seeder: dua pegawai pertama Irban I mendapat `{slug nama}@example.test` bila email masih null.

## Fase 9.2, Task 2
- Hex warna kedua di kode (setelah `resources/js/grafik.js`): `App\Exports\RekapSheet::HEADER_ISI = '17735B'` (mint-700) dan `GARIS = 'B8C4C0'` (neutral-300), dari DESIGN §13. Bila token berubah, ubah ketiga tempat.
- Detail Realisasi di xlsx kini menggabungkan sel Jenis Penugasan dan Periode Penugasan (PKPT) secara vertikal per item (rata atas). Ini mengganti keputusan Fase 9.1 (decisions/fase-9.md, Task 4) "tanpa merge"; bullet lama tetap. PDF tetap mengisi Jenis/Periode hanya di baris pertama item.
- `Pembangun::export()` kini bertipe `Maatwebsite\Excel\Concerns\Export` (bukan `object` seperti di plan): Laravel Excel 4.0.4 mensyaratkan `Excel::download/store(Export $export)`, dan `WithMultipleSheets` tidak menurunkan `Export`, jadi `RekapWorkbook` mengimplementasikan keduanya. `PembangunRegister`/`PembangunMonitoring` tetap mengembalikan `ArrayExport` (kovarian, tanpa perubahan).
- Trait `App\Exports\Concerns\AmanFormula` (binder teks berawalan `= + - @`, tab, CR) dipakai `ArrayExport` dan `RekapSheet`.
- `PembangunRekap::jumlah()` = (Σ baris bagian ringkasan terpilih untuk cakupan filter: judul + kepala + baris data dari `bagian()` tab `gabungan`/`irban`) × jumlah sheet (1 bila ada `irban`, selain itu `Irban::count() + 1`) + baris Detail Realisasi bila `detail` dipilih. Batas atas murah; tabel "Ringkasan per Irban" tidak dihitung.
- Sheet Gabungan dihilangkan bila hanya `detail` yang dipilih. Sheet Gabungan berisi `rr` gabungan + "Ringkasan per Irban" (satu baris per irban, TW terpilih atau total setahun, tanpa baris Total), lalu `klasifikasi` dan `jenis`; tidak pernah `detail`.
- Baris judul sheet: `Rekap Triwulanan {tahun} · {TW}`; bila `tw = 0` tanpa TW (`Rekap Triwulanan 2026`). Baris 3 `Dibuat: d F Y H.i WIB`.
- Nama file: sufiks kode bagian ditempel ke nama dasar yang sudah ada (tanpa `-tw2`; contoh spesifikasi dibaca ilustratif). Tanpa kunci `bagian` (tombol ekspor lama, sebelum modal Task 3) dipakai default tab `irban`, jadi nama file kini `...-rr-klasifikasi-jenis`; test lama di `HalamanRekapTest`/`DetailRealisasiTest` disesuaikan (nama file, filter berisi `bagian`, baca semua sheet, detail tanpa baris nama irban karena irban = sheet).
- Baris kosong di `RekapSheet` ditulis `[null]`: Laravel Excel membuang baris `[]` (sehingga baris 4 dan jeda antarbagian hilang).
- PDF: setiap blok tabel dibungkus `.blok { page-break-inside: avoid }`; blok Detail yang lebih tinggi dari satu halaman tetap terpecah ke halaman berikut (dites).
