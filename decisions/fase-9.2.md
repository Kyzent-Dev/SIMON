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

## Fase 9.2, Task 3
- `ekspor()` dihapus dari `Rekap`; `x-app.export-buttons` mendapat prop `aksi` (default `ekspor`) dan atribut `data-ekspor` pada tombol utama sebagai target fokus kembali.
- Fokus awal modal lewat `autofocus` pada kotak centang pertama (dihormati `x-trap`); fokus kembali ke tombol Ekspor lewat `x-effect` yang membaca `$wire.pilihEkspor`. `$wire.$watch` dan `setTimeout` setelah buka tidak andal (trap menimpa fokus).
- Tombol "Unduh" tidak memakai `wire:loading.attr="disabled"` karena tombol yang disabled kehilangan fokus sehingga Esc tidak lagi sampai ke modal setelah galat validasi; diganti `opacity-60` + `aria-busy`.
- Test `DetailRealisasiTest` tab detail: `assertDontSee('Rencana vs Realisasi')` diganti `assertDontSeeHtml('id="rekap-bagian-0"')` karena modal memuat nama bagian.

## Fase 9.2, Task 4
- `App\Support\PaletGrafik` is the PHP copy of the chart palette in `resources/js/grafik.js` (DESIGN §9); the two must stay in sync. PDF charts are print output: no motion, plain HTML/CSS (tables and divs), labels always after the bar in `TEKS`.
- Bar width is relative to the chart's own maximum (paired: largest value; stacked: largest total). Deviation: the `persen` chart uses max(100, largest %) so a 33% bar is a third of the track, not stretched to the best jenis.
- Klasifikasi label format: `Berjalan n · Selesai m`; zero rows stay as empty rows with their label. Jenis: only rencana > 0, descending by %, `75% (3/4)`.
- With one irban filter the Rencana vs Realisasi chart uses TW I-IV (or the selected TW) of that irban; otherwise one pair per irban. Added `page-break-after: avoid` on `h2` in the PDF so a section title is not stranded at a page bottom.
