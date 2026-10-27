# Fase 11.2: Penyelesaian minor tertunda — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Selesaikan semua butir "minor tertunda" di `docs/plans/serah-terima.md` yang dapat diselesaikan tanpa aset, server, atau keputusan produk baru dari pengguna.

**Architecture:** Tidak ada fitur baru. Setiap tugas mengerjakan satu area (kumpulan file) dari daftar minor: perbaikan kecil kode, tes yang belum ada, dan perapian. Perilaku yang sudah disepakati di PRD tidak diubah.

**Tech Stack:** Laravel 13, Livewire 4, Alpine, Tailwind v4, Chart.js, PHPUnit (SQLite in-memory; MySQL/MariaDB XAMPP untuk uji akhir).

**Spec:** `docs/plans/serah-terima.md` bagian "Dibawa ke fase berikutnya" (daftar minor; teks butir di bawah disalin dari sana), `PRD-SIMON.md` (aturan bisnis), `DESIGN.md` + `design/**` (visual). Branch: `ganti-nama-sintas` (baseline `38ff7f4`).

## Global Constraints

- Setiap butir dari brief berakhir dalam salah satu dari dua keadaan, dicatat di `decisions/fase-11.2.md` di bawah `## Fase 11.2, Task N` (satu bullet per butir): **Selesai** (apa yang diubah, file, tes yang menjaganya) atau **Tidak dikerjakan** (alasan konkret: mengubah perilaku PRD, butuh keputusan pengguna, butuh lingkungan yang tidak ada, atau ternyata bukan cacat). Butir tidak boleh hilang diam-diam.
- Perubahan perilaku yang terlihat pengguna hanya boleh bila memperbaiki cacat yang disebut butir; jangan menambah fitur. Bila butir hanya bisa diselesaikan dengan mengubah perilaku PRD, tandai Tidak dikerjakan.
- Butir berupa celah tes diselesaikan dengan tes yang bisa gagal (punya kontrol positif); butir berupa perbaikan kode diberi tes yang gagal sebelum perbaikan bila perilakunya bisa diuji.
- Jangan edit file keputusan fase lama (`decisions/fase-0..11.1.md`) kecuali memperbaiki kesalahan faktual yang disebut butir. Jangan edit `docs/plans/serah-terima.md` (Task 11 yang memperbaruinya).
- Pakai `graphify query "<pertanyaan>"` sebelum grep/baca file untuk pertanyaan tentang kode; baca file mentah hanya pada baris yang sudah ditemukan.
- UI: sebelum mengubah apa pun yang visual, baca `DESIGN.md` penuh + file `design/**` yang sesuai (tabel di `CLAUDE.md`), dan **panggil skill `ui-ux-pro-max:ui-ux-pro-max`** (instruksi eksplisit pengguna; catat hasil auditnya di decisions). Cek 360/768/1280 tanpa gulir horizontal halaman, fokus terlihat, keyboard. Warna hanya token §13 dan pasangan §3.4. Status lewat `x-app.status-badge`/`x-app.flag`. Tanpa animasi di tabel/form dasbor. Teks UI Bahasa Indonesia.
- Stack: Blade + Livewire + Alpine + Tailwind + Chart.js. Jangan memasang paket baru.
- Commit per tugas; stage file satu per satu; jangan pernah stage `.claude/`, `.playwright/`, `graphify-out/`, `.superpowers/`. Trailer commit: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`. Jangan push.
- Jalankan `vendor/bin/pint --dirty` dan tes yang relevan sebelum commit; suite penuh (`php artisan test`) di akhir tugas. **Jangan pernah menjalankan dua `php artisan test` bersamaan** (`Storage::fake` berbagi `storage/framework/testing/disks`).
- Jebakan yang sudah diketahui (dari `serah-terima.md`, berlaku untuk semua tugas): fokus setelah aksi ke target stabil; `sr-only` di dalam kontainer gulir butuh `relative`; "Diinput oleh" di footer; aturan bisnis tidak dihitung ulang di komponen; galat non-kolom sebagai toast; lookup id di-scope ke irban; zona waktu Jakarta tanpa `->utc()`; ekspor xlsx lewat `ArrayExport`; tolak string kosong sebelum `Carbon::parse`; Livewire: nama method ≠ properti publik, id dikunci `#[Locked]`, otorisasi di `mount()` dan setiap aksi; factory `unique()` tahun; `Penomoran::ambil` di dalam `DB::transaction(..., attempts: 3)`; sticky dasbor offset ≥ `top-16`; tab basi menjadi toast lewat guard bersama + fokus ke h1; atribut field unggah baru di `lang/id/validation.php`; notifikasi dikirim dari action/command, penerima dari `PenerimaNotifikasi`, turunan `NotifikasiSintas` dengan properti `protected`; `$refs` diambil sebelum `$wire.aksi()`; teks pengguna di email Markdown di-escape, teks polos pakai `tanpaEscape()`; data publik hanya lewat `RealisasiPublik`/`JadwalPublik` + penanda `PrivasiPublikTest`; tes harus jalan di MySQL (tanpa SQL khusus SQLite, `expectException` tepat sebelum baris yang diuji, tanpa DDL di dalam `RefreshDatabase`); penulisan massal query builder melewati log aktivitas (tulis per model); tes yang menulis disk nyata memakai folder sementara + `tearDown`; `config/backup.php` harus memakai `env('DB_CONNECTION')` (dimuat sebelum `database.php`).

## Di luar lingkup (tidak dikerjakan; alasan)

Dicatat sekali oleh Task 11 di `decisions/fase-11.2.md` dan tetap di `serah-terima.md`:
- Pengganti tahun di header (fitur baru, belum dijadwalkan pengguna).
- Logo resmi Diskominfo (menunggu aset).
- Suite MySQL 8 / `PenomoranKonkurensiTest` di Linux / uji `DEPLOY.md` di Linux (supervisor, crontab, `flock`, Nginx, `unzip`/`7z`) / gladi `mariadb-dump` (menunggu server).
- Kinerja impor 2000 baris, SQLite IMMEDIATE PHP ≥ 8.4, `memory_limit` 512M, target 5.000 SPT dalam satu tahun, CSP (ruling Fase 11; pemantauan atau peninjauan terpisah).
- Fase 11 Task 3 "catatan hanya median / EXPLAIN" (catatan riwayat pengukuran, bukan cacat kode).
- Fase 11 Task 6 `databases` memakai `env('DB_CONNECTION')` (disengaja; lihat jebakan `spatie/laravel-backup`); tanpa `BACKUP_MAIL_TO` kegagalan senyap (perilaku paket, sudah terdokumentasi).
- Fase 11 Task 7: seeder demo dijepit ke 2026 (`shortcut:` sudah ada), `ObjekSeeder` contoh di produksi (pengingat di `DEPLOY.md`), `LoncengNotifikasiTest` subset jenis (disengaja).
- Keputusan produk yang tercatat sebagai keputusan: tab "Belum dibaca (N)", irban menerima dua notifikasi saat persetujuan, persetujuan menimpa `komentar_putusan`, kontrol urut/slide tanpa JS, tabel + daftar seluler di DOM, grafik Status SPT pastel < 3:1 (legenda + tabel kembar), form pengajuan memuat semua pegawai (decisions/fase-4.md), keputusan Fase 6 Task 3 berbahasa Inggris (file fase lama).
- `RealisasiDemoSeeder` menambah seeding dev (pengamatan).

---

### Task 1: Keamanan, galat, dan otorisasi

Area: `tests/Feature/Keamanan/*`, `app/Http/Middleware/HeaderKeamanan.php`, `bootstrap/app.php`, `routes/web.php`, `resources/views/errors/*`, pembatas laju ekspor, aksi Revisi/Tolak.

Butir:
1. (F11 T1) Allowlist rute publik di tes otorisasi rute memakai glob luas (`password.*`, `livewire.*`, `storage.*`): ganti dengan nama rute eksak.
2. (F11 T1) Tolak Pengajuan tidak dipanggil langsung di `OtorisasiAksiTest`: tambah panggilan langsung oleh peran yang salah.
3. (F11 T1) Tidak ada tes ekspor Rekap irban lain via Livewire: tambahkan (irban A tidak bisa mengekspor rekap irban B).
4. (F11 T1) Tabel audit di `decisions/fase-11.md` tidak menyebut trait `MenampilkanOperasi`: perbaiki tabelnya (koreksi faktual di file fase lama diizinkan).
5. (F11 T1) `catch ExpectationFailedException` di `OtorisasiAksiTest` ~baris 549: ganti dengan asersi eksplisit.
6. (F11 T2) 404 rute tak cocok tanpa sesi mengarahkan tombol ke beranda walau pengguna login (E3 meminta dasbor): pakai `Route::fallback` di grup `web` (atau cara setara) agar pengguna login mendapat tombol dasbor; tes untuk tamu dan pengguna login.
7. (F11 T2) `HeaderKeamanan` berada setelah `EnsurePasswordIsChanged`, sehingga redirect paksa ganti kata sandi dan 404 tanpa header: jadikan middleware global/prepend; tes header ada pada redirect paksa ganti sandi dan 404.
8. (F11 T2) Tes xlsx tidak menguji aturan `mimetypes`: tambah tes berkas berekstensi `.xlsx` dengan isi bukan xlsx.
9. (F11 T2) Docblock `errors/layout` menyebut `beranda-aman`: perbaiki agar sesuai kode.
10. (F11 T2) Tes pembatas ekspor hanya Register/antrean: tambah satu jenis ekspor lain (Rekap atau Monitoring).
11. (F11 T2) Tes 419 hanya string: render lewat respons nyata (mis. `TokenMismatchException` dari rute uji) dan periksa status + judul.
12. (F11 T2) Batas ekspor dihitung per `minta()` termasuk unduhan langsung dan sebelum otorisasi: hitung hanya setelah otorisasi lulus; tes bahwa permintaan yang ditolak otorisasi tidak memakan kuota.
13. (Lain) Revisi/Tolak memvalidasi catatan sebelum cek peran (422, bukan 403): otorisasi dulu; tes 403 untuk peran salah dengan catatan kosong.

### Task 2: Notifikasi dan email

Area: `app/Notifications/*`, `resources/views/mail/*`, `resources/views/vendor/mail/*`, `app/Services/PenerimaNotifikasi.php`, action pengirim email pegawai, halaman `/notifikasi`, `x-app.notifikasi-teks`, tes `tests/Feature/Notifikasi/*`.

Butir:
1. (F11 T3b) Kelas karakter regex `aman()`/`tanpaEscape()` diduplikasi: jadikan satu konstanta bersama.
2. (F6) Entri `email_tembusan` tidak di-trim: trim (dan buang entri kosong) di tempat daftar dibaca/disimpan; tes.
3. (F6) CTA `Buka SINTAS` hanya dites untuk `ringkasan_mingguan`: tes untuk notifikasi lain yang punya `url`.
4. (F6) Tes rollback di `NotifikasiPengajuanRevisiTest` tidak membedakan afterCommit: buat tes yang gagal bila `afterCommit` dihapus.
5. (F6) `toMail` hanya diperiksa untuk `PengajuanDiputuskan`: tes isi `SptTerlambat` dan `PengingatSptFinal`.
6. (F6) Baris `dan {k} lainnya.` belum dites: tes.
7. (F6) `RingkasanMingguan` menyimpan `CarbonImmutable` di payload antrean: simpan sebagai string tanggal (atau tipe skalar) dan bentuk ulang saat render; tes.
8. (F6) SPT dengan `tanggal_mulai` null atau yang tak pernah mengunggah final dipindai ulang setiap hari: batasi query pemindaian agar baris yang tidak mungkin memicu tidak diambil, tanpa mengubah siapa yang menerima notifikasi; tes. Bila tidak bisa tanpa mengubah perilaku, Tidak dikerjakan.
9. (F6) Kolom idempotensi tetap diisi walau irban tak punya akun: nilai perilaku saat ini terhadap PRD; perbaiki hanya bila jelas cacat (notifikasi hilang ketika akun dibuat kemudian), selain itu Tidak dikerjakan dengan alasan.
10. (F9.2 T1) Blok kirim (penerima + `emailPegawai` + route) berulang di `BatalkanNomor`/`PutuskanPengajuan` dan pemanggil ketiga: ekstrak satu helper; tes yang ada harus tetap hijau.
11. (F9.2 T1) Glyph sel kosong `–` vs `—` di tabel Pegawai: samakan dengan glyph yang dipakai tabel lain.
12. (F9.2 T1) `EmailPegawaiTest` hanya menegaskan tidak ada `<a href>`: tegaskan teks ter-escape dan pengiriman afterCommit.
13. (Notifikasi) `/notifikasi` di 360px: nomor SPT di keterangan terpotong di tanda hubung: cegah pemenggalan di dalam nomor (komponen `x-app.notifikasi-teks`) tanpa melebarkan halaman. UI: lihat Global Constraints.
14. (Notifikasi) `updated()` + `resetPage` rapuh; belum ada tes potongan halaman 2, pengosongan pilihan saat ganti halaman, clamp `perPage`, penangan klik-dengan-modifier: tambah tes; perbaiki kerapuhan bila tes membuktikannya.

### Task 3: Kinerja, cache publik, log pivot, Settings

Area: `tests/Feature/Kinerja*`, `database/seeders/KinerjaSeeder.php`, `app/Support/CachePublik.php`, `MenyegarkanCachePublik`, `app/Actions/Spt/SimpanSpt.php`, `app/Actions/Spt/ImporSpt.php`, `app/Services/WilayahPengawasan.php`, `app/Services/Settings.php`, trait log aktivitas.

Butir:
1. (F11 T3) `KinerjaDasborTest` query-konstan bisa semu bila halaman memakai cache: kosongkan/lewati cache dalam tes agar jumlah query nyata yang diukur.
2. (F11 T3) Nilai tersemat `test_hasil_sama` bergantung urutan RNG seeder: beri komentar satu baris.
3. (F11 T4) Tidak ada tes khusus penyegaran cache publik setelah `sync` pivot di `SimpanSpt`/`ImporSpt`: tambahkan.
4. (F11 T4) Filter `'2026'` vs `2026` membuat kunci cache berbeda: normalkan tahun ke int sebelum membentuk kunci; tes.
5. (F11 T4) `afterCommit` di `RefreshDatabase` MySQL belum diuji: tes yang membuktikan penyegaran terjadi setelah commit (jalan di SQLite dan MySQL); bila tidak mungkin diuji tanpa DDL/transaksi bersarang, Tidak dikerjakan dengan alasan.
6. (F11 T5) Daftar Dinas/Badan publik diurut ulang natural di PHP, komentar "as before" tidak tepat: perbaiki komentar.
7. (F11 T5) Tidak ada tes rollback log pivot: tes bahwa transaksi yang gagal tidak meninggalkan baris log pivot.
8. (F11 T5) `pluck` ekstra per simpan SPT: hilangkan query ekstra bila nilai lama sudah tersedia (atau ambil hanya bila pivot berubah).
9. (F10 T1) `Settings::set` mencatat di luar transaksi pemanggil: catat di dalam transaksi yang sama (atau `afterCommit`); tes rollback.
10. (F10 T1) `untukSpt` memfilter `properties->old->spt_id` tanpa indeks: nilai; bila indeks JSON tidak portabel SQLite/MySQL tanpa kolom baru, Tidak dikerjakan dengan alasan.
11. (F10 T1) Simpan ulang draf SPT/tim mencatat hapus+buat untuk baris yang tidak berubah: hanya hapus/buat baris yang benar-benar berubah; tes jumlah baris log.

### Task 4: Log aktivitas dan impor

Area: `app/Livewire/**/LogAktivitas*`, view log, `app/Livewire/**/*Impor*`, `app/Services/Import/*`, `app/Actions/Spt/ImporSpt.php`, `app/Actions/MasterData/ImportPegawai.php`, `app/Actions/Pkpt/ImportPkptItems.php`, tes impor/log.

Butir:
1. (F10 T2) `aksi=lainnya` (`whereNotIn`) melewatkan `event` NULL: sertakan NULL; tes.
2. (F10 T2) `terapkan()` mengubah properti di `render()`: pindahkan normalisasi keluar dari `render()`.
3. (F10 T2) Field filter dirender dua kali (inline + drawer) dan select per halaman satu opsi: hilangkan duplikasi id/field bila bisa tanpa mengubah tata letak; select per halaman satu opsi diganti teks statis atau diberi opsi yang dipakai halaman log lain. UI.
4. (F10 T2) Tes jumlah query hanya satu jenis subjek; belum ada tes Gate di `aturUlang`/`updated`, tautan Admin di kartu SPT, chip `spt`, render baris impor: tambahkan tes.
5. (F10 T2) Format waktu ganda di dua komponen baris: satukan.
6. (F10 T2) Kolom kunci diff terpotong tengah kata di 360: pemenggalan di batas kata. UI.
7. (F10 T3) Cek "sudah ada" peka huruf di SQLite saja: bandingkan tanpa peka huruf di semua driver; tes.
8. (F10 T3) Ringkasan impor Pegawai/PKPT memakai `auth()->user()`: terima pengguna sebagai parameter seperti impor SPT.
9. (F10 T4) `impor()` tanpa penjaga server untuk 0 baris: tolak di server dengan toast; tes.
10. (F10 T4) Raw rusak berkunci hilang di jalur lama `PkptImpor` bisa 500: tangani sebagai galat baris; tes.
11. (F10 T4) Belum ada tes berkas kosong / semua galat + lewati: tambahkan.
12. (F10 T4) Tes toast balapan memakai mock: ganti dengan skenario nyata bila mungkin, selain itu Tidak dikerjakan dengan alasan.
13. (F10 T4) Templat `PegawaiImpor` hanya memformat teks pada baris yang ada (NIP bisa jadi angka): format kolom NIP sebagai teks untuk seluruh rentang isian templat.
14. (F10 T4) `array_search` dua kali per kolom di `unduhTemplat`: sekali.
15. (F10 T5) `FileContohTest` meninggalkan berkas `tempnam`; baris baik hanya ditegaskan bukan galat; bergantung pada Tim 1 punya ≥ 4 anggota: bersihkan berkas, tegaskan baris baik positif, buat data tes sendiri.
16. (F10 review akhir) `TemporaryUploadedFile::delete()` meninggalkan sidecar `.json`: hapus sidecar juga.
17. (Impor item PKPT) `analyze()` menjalankan ±2 query per baris setiap render: muat irban/jenis sekali; tes jumlah query.

### Task 5: Seeder dan cadangan

Area: `database/seeders/*`, `tests/Feature/Cadangan/CadanganTest.php`, `tests/Feature/*Seeder*`.

Butir:
1. (Seeder demo) `migrate:fresh --seed` meninggalkan PDF yatim di `storage/app/private/spt`: setelah seeding non-produksi, hapus berkas di `spt/` yang tidak dirujuk baris dokumen mana pun (jangan hapus berkas yang dirujuk); tes dengan disk palsu.
2. (Seeder demo) PDF tulisan tangan tanpa tabel xref: hasilkan PDF valid (dengan xref) memakai helper yang sudah ada di repo bila ada (mis. `tests/Support/PdfUji.php` atau dompdf), tanpa paket baru.
3. (Seeder demo) Personel = dua pegawai pertama (bukan anggota Irban I), tanpa PPJ: ambil personel dari anggota Irban pemilik SPT dengan peran sesuai aturan `ValidatePersonel`, termasuk PPJ bila aturan memintanya.
4. (F11 T7) `CadanganTest` menulis berkas nyata di `storage/app/private` (dihapus di `tearDown`): pakai folder sementara di luar `storage/app/private` bila paket mengizinkan; selain itu Tidak dikerjakan dengan alasan.
5. (F11 T6) Tes cadangan hanya `--only-files`: tambah tes dump DB bila biner dump tersedia di lingkungan tes (lewati dengan `markTestSkipped` bila tidak), atau Tidak dikerjakan dengan alasan.
6. (F7 T4) `KomentarSeederTest` belum memeriksa `diinput_oleh` anggota Irban I, `ditutup_oleh`/`ditutup_pada` pada root Selesai, root Terbuka tanpa balasan Irban, dan urutan waktu: tambahkan.
7. (F7 T4) `KomentarSeeder` membandingkan `role->value` dengan string: bandingkan dengan kasus enum `Role`.
8. (F9.1) Swatch seeder demo berdasar `count()`: pakai kunci stabil.

### Task 6: PKPT, Perubahan PKPT, Usulan

Area: `app/Actions/Pkpt/**`, `app/Livewire/**/Pkpt*`, `app/Livewire/**/PerubahanPkpt*`, `app/Livewire/**/Usulan*`, `app/Models/PkptPerubahan.php`, policy, `app/Services/PerubahanPkpt.php`, `SimpanSpt::kunciItem`, `app/Services/Publik/*` (select kolom), tes PKPT.

Butir:
1. (F9.2 T5) Tes yang ditulis ulang memakai update inline, bukan `disahkanDari()`: pakai helper/factory state yang benar.
2. (F9.2 T5) Asersi `/jadwal` bergantung markup: tegaskan lewat data (viewData/service) atau penanda stabil.
3. (F9.2 T5) Isolasi MySQL `MigrasiRevisiJadwalTest` bergantung pada tes berikutnya memakai `RefreshDatabase`: buat tes itu memulihkan keadaannya sendiri.
4. (F9.2 T6) Policy/`PenerimaNotifikasi` memakai `app()` locator: injeksi dependensi bila kerangka mendukung, selain itu Tidak dikerjakan dengan alasan.
5. (F9.2 T6) `SimpanSpt::kunciItem` menyalin string pesan dari `validasi()` dan mengambil kunci PKPT pada setiap simpan draf berisi item: satu sumber pesan; ambil kunci hanya bila item berubah, tanpa melanggar jebakan "penulis wajib mengambil kunci yang sama".
6. (F9.2 T7) Paket dihapus di tab lain memberi 404, bukan toast: toast + fokus h1 (jebakan tab basi).
7. (F9.2 T7) `TIDAK_BERUBAH` dideteksi dari string pesan: pakai kode/konstanta, bukan teks.
8. (F9.2 T7) Kondisi `$bisaUbah/Buang` diulang di view: satu sumber (method model/komponen).
9. (F9.2 T7) Ajukan tidak atomik (menyimpan jenis/alasan lebih dulu): bungkus dalam satu transaksi.
10. (F9.2 T7) Cabang h1 ditolak belum dites: tambahkan.
11. (F9.2 T7) `perluSk` memakai `sk_lengkap_pada` null, bukan field `skLengkap()`: pakai satu predikat.
12. (F9.2 T8) Galat komentar Tolak kosong tidak memfokuskan textarea: fokus ke textarea. UI.
13. (F9.2 T8) `Pimpinan\PerubahanPkpt::$perPage` tanpa `#[Locked]`/clamp: clamp ke opsi yang sah; tes.
14. (F9.2 T8) Flag dampak dipetakan dari teks label: petakan dari kode/enum.
15. (F9.2 T8) Tolak oleh non-Pimpinan lewat panggilan langsung belum dites: tambahkan.
16. (F9.2 T8) Pimpinan bisa melihat detail paket draf: nilai terhadap PRD §6.2; tutup bila PRD tidak memberi hak itu (tes), selain itu Tidak dikerjakan dengan alasan.
17. (F9.2 T9) Admin `bukaTutup` membuka modal untuk usulan yang sudah ditampung/ditutup: jangan buka modal, beri toast.
18. (F9.2 T9) Tautan lonceng ke paket yang dibuang memberi 404: arahkan ke halaman dengan toast (tab basi).
19. (F9.2 T9) `ditambahkan_perubahan_id` dipilih di query publik: keluarkan dari select bila tidak dipakai; `PrivasiPublikTest` tetap hijau.
20. (Lain) `PkptDetail` menghitung `rentang()` per item yang direvisi (N+1 kecil): hitung sekali/eager-load.
21. (Lain) `{klasifikasi}` di template nomor hanya dihapus bersama `/` di sebelahnya; validasi template di Pengaturan (cegah tabrakan `nomor_lengkap` bila scope/template diganti di tengah tahun): tambah validasi template di Pengaturan (placeholder wajib/sah) dan perbaiki penghapusan `{klasifikasi}`; tes. Pencegahan tabrakan di tengah tahun: bila butuh keputusan aturan bisnis, Tidak dikerjakan dengan alasan.

### Task 7: Pengguna dan Profil

Area: `app/Actions/Pengguna/*`, `app/Actions/Profil/*`, `app/Livewire/**/Pengguna*`, `app/Livewire/**/Profil*`, view terkait, `tests/Feature/**/Pengguna*`, `tests/Feature/**/Profil*`.

Butir:
1. (F9.2 T10) Email dicek unik sebelum lowercase: lowercase dulu; tes dengan huruf besar.
2. (F9.2 T10) Cabang peran-diri ganda: satukan.
3. (F9.2 T10) Cek admin terakhir tanpa kunci: kunci baris (`lockForUpdate`) dalam transaksi.
4. (F9.2 T10) Username lama dipaksa regex baru: validasi regex hanya bila username berubah; tes.
5. (F9.2 T10) Tes rotasi `remember_token`, peran dirusak, dan admin terakhir lewat Livewire belum ada: tambahkan.
6. (F9.2 T10) Daftar `KOLOM` x3 dan regex username x2 terduplikasi: satu sumber.
7. (F9.2 T11) `ProfilTest` `assertDontSeeHtml('data-avatar src')` tak pernah bisa gagal: ganti dengan asersi yang bisa gagal.
8. (F9.2 T11) `RuntimeException` storage berlabel "Gambar tidak dapat dibaca": pesan yang sesuai (gagal menyimpan).
9. (F9.2 T11) Injeksi `Settings` tak terpakai di `SimpanGambarProfil`: hapus.
10. (F9.2 T11) `sandiOpen` bisa diatur klien (hanya UX): Tidak dikerjakan kecuali `#[Locked]` tidak merusak alur.
11. (F9.2 T11) Tanpa kirim ulang/throttle email verifikasi: tambah throttle (RateLimiter) pada permintaan ganti email; kirim ulang hanya bila UI sudah punya tempat alami, selain itu Tidak dikerjakan.
12. (F9.2 T11) X→Y→X membuat hash lama valid: tautan lama harus tidak berlaku setelah permintaan baru; tes.
13. (F9.2 T11) Titik hilang di pemberitahuan Irban: tambah.
14. (F9.2 T11) `simpanAkun` tidak menyegarkan nama di header: dispatch event/penyegaran; tes.
15. (F9.2 T11) PNG transparan menjadi hitam: isi latar putih sebelum konversi; tes (juga butir yang sama di slide, Task 9 butir 7 memakai helper yang sama bila ada).
16. (F9.2 T11) Tautan email kedaluwarsa/dirusak memberi 403 umum: tampilkan pesan yang jelas (halaman/flash); tes.
17. (F9.2 T11) Cek email gagal meninggalkan nilai yang diketik: perilaku form standar; nilai sesuai DESIGN, perbaiki atau Tidak dikerjakan dengan alasan.
18. (F9.2 T11) Admin yang mengatur ulang kata sandi diri sendiri ter-logout: cegah atur ulang untuk diri sendiri (arahkan ke Profil) atau pertahankan sesi; pilih yang tidak mengubah PRD; tes.
19. (F9.2 T3) Tes hanya memeriksa 403 saat mount, belum Gate di `bukaEkspor`/`unduhEkspor` dan default Irban: tambahkan.
20. (F9.2 T3) `x-effect` pengembalian fokus memakai `setTimeout(80)` + `querySelector` global: pola `$refs`/`$nextTick` (jebakan `$refs`).

### Task 8: Ekspor, Rekap, Monitoring, grafik

Area: `app/Services/Ekspor*`, `app/Services/Ekspor/*`, `app/Jobs/*Ekspor*`, `app/Services/RekapTriwulan.php`, `app/Services/MonitoringSpt.php`, `app/Services/RealisasiItemPkpt.php`, PDF grafik, `resources/js/*chart*`, Livewire Rekap/Monitoring, tes terkait.

Butir:
1. (F9.2 T2) Judul sheet tidak dibuat unik setelah sanitasi/pemotongan: buat unik; tes dengan irban "Gabungan" dan nama bentrok.
2. (F9.2 T2) `jumlah()` dan `export()` menjalankan `hitung()` dua kali (juga 9.1 ekspor rekap): hitung sekali per permintaan.
3. (F9.2 T2) Tes (e) memeriksa angka per irban, bukan per jenis: tambah asersi per jenis.
4. (F9.2 T2) Judul cetak xlsx (baris berulang) belum diatur: atur baris judul berulang lewat binder/`ArrayExport` yang ada.
5. (F9.2 T4) Lebar skala 100 grafik jenis dan segmen bertumpuk belum ditegaskan di `GrafikPdfRekapTest`: tambahkan.
6. (F9.2 T4) `PaletGrafik::TEKS/TEKS_SEKUNDER` tak terpakai (view menulis hex di CSS): pakai konstanta di view atau hapus.
7. (F9.2 T4) Batang nol menggambar sliver border 1px: jangan gambar batang bernilai nol.
8. (F8 Ekspor) Kegagalan meninggalkan file yatim (`path` null sehingga `ekspor:bersihkan` melewatkannya): hapus file parsial saat gagal atau simpan path sebelum menulis; tes.
9. (F8 Ekspor) Jalur gagal memanggil `halaman()` di dalam `catch`: pindahkan agar galat kedua tidak menutupi yang pertama.
10. (F8 Ekspor) Jalur time-out hanya dites lewat panggilan langsung `failed()`: nilai; tambah tes lewat job bila praktis, selain itu Tidak dikerjakan.
11. (F8 Rekap) Belum ada tes item rekap yang pindah tahun karena revisi, baris `Tanpa jenis penugasan`, dan dasar `tanggal_mulai` lewat `hitung()`: tambahkan.
12. (F8 Rekap) `untukBanyak` meninggalkan atribut `final_ada` pada model pemanggil: jangan mengubah model pemanggil.
13. (F8 Rekap) `RekapTriwulan` mengindeks langsung per id klasifikasi/jenis: pakai pencarian aman (default bila id tak ada).
14. (F8 Rekap) Tabel per jenis menampilkan baris nol: nilai terhadap PRD/design; sembunyikan hanya bila design tidak mensyaratkan baris lengkap.
15. (F8 Monitoring) Filter TW tetap memakai tahun terpilih bila dipakai bersama chip `Terlambat (semua tahun)`: perbaiki kombinasi; tes.
16. (F8 Monitoring) Hitungan `{n} SPT` ikut tergulir ke samping: keluarkan dari kontainer gulir. UI.
17. (F8 Monitoring) `Selengkapnya` berdasar > 70 karakter, bukan overflow terukur: nilai; ukur overflow lewat Alpine bila sederhana, selain itu Tidak dikerjakan. UI.
18. (F8 Monitoring) Kolom Kendala tergulir di 1280 dengan sidebar terbuka: atur lebar kolom. UI.
19. (F8 Rekap) Daftar Tahun Rekap Saya memuat tahun milik irban lain: batasi ke tahun irban itu; tes.
20. (F8 Rekap) Pimpinan hanya dapat menyaring irban lewat URL: tambah kontrol select irban sesuai pola filter yang ada. UI.
21. (F8 Grafik) `chartJs.defaults.animation = false` global berlebihan: hapus bila setiap grafik sudah mengatur animasinya.
22. (F9.1) `MingguPkpt::untukRentang` menghitung tumpang tindih Sabtu/Minggu saja sebagai minggu itu; `daftar()` memakai zona waktu default; tes argumen Carbon mutable/rentang akhir pekan: perbaiki + tes.
23. (F9.1) Tab Detail menjalankan `RekapTriwulan::hitung` hanya untuk alert; `Irban\Pkpt` memuat relasi tabel juga di tampilan kalender: kurangi kerja yang tidak dipakai.
24. (F9.1) `MonitoringSpt::bentukBaris` memanggil `now()` per baris; `barisSpt` mengandalkan eager-load pemanggil: ambil `now()` sekali; pastikan eager-load di dalam.
25. (F9.1) Label kesesuaian/status ekspor ditulis ulang di `PembangunRekap` (fallback `Non-PKPT` mati): pakai sumber label yang ada.

### Task 9: Halaman publik dan komponen kalender/slide

Area: `app/Services/Publik/*`, `app/Livewire/Publik/*`, `resources/views/livewire/publik/*`, `resources/views/components/app/kalender-pkpt*`, `app/Actions/Konten/*`, `resources/js/motion.js`/`public.js` (hanya bila perlu), tes publik/slide. Baca `design/public/*.md`.

Butir:
1. (F9 T1) `basis()` publik bisa disalahgunakan: tandai `@internal`.
2. (F9 T1) `jenis.nama` null lewat left join belum dites; urutan seri `proporsi` dan `Tanpa jenis penugasan` belum dites: tambahkan.
3. (F9 T1) `JadwalPublik` ~baris 98 `startOfDay` pada Carbon model: salin dulu (immutable) agar model tidak berubah.
4. (F9 T1) `KinerjaPublikTest` memakai batas 2000 ms jam dinding: ganti dengan asersi jumlah query (atau longgarkan dengan alasan).
5. (F9 T1) Gaya penugasan-dalam-negasi di `RekapTriwulan` ~baris 136; `opsi()` memberi tahun berjalan bila tak ada PKPT Disahkan; SPT tanpa objek tampil `Belum ditentukan`: rapikan; dua yang terakhir nilai terhadap design, perbaiki atau Tidak dikerjakan dengan alasan.
6. (F9 T2) `Tampilkan peta` adalah `<a>` yang ditingkatkan menjadi tombol: elemen semantik yang benar. UI.
7. (F9 T2) PNG transparan menjadi hitam; ternary bersarang `SimpanSlide` ~baris 103; logika tukar urutan di komponen, bukan action; `Storage::fake` ganda di `SlideTest`: perbaiki.
8. (F9 T2) Jalur gagal dan pembersihan variasi belum dites; `ValidationException` `SimpanSlide` lewat `save()` jalur GD belum dites; label aria `x-ui.switch` belum terbukti tes: tambahkan.
9. (F9 T2) Rotasi EXIF berjalan setelah cek lebar minimum: putar dulu, lalu cek (jebakan gambar unggahan). Bila ekstensi exif tidak ada lokal, uji jalur dengan stub/fungsi terpisah.
10. (F9 T2) `decisions/fase-9.md` ~baris 250 menyebut tes exif yang dilewati padahal tidak ada: koreksi faktual.
11. (F9 T2) Teks pratinjau kecil di drawer sempit. UI.
12. (F9 T3) h1 slide pertama ikut `aria-hidden` saat slide lain aktif; `keydown.left/right` aktif bersama Alt; jeda `focus-within` bertahan setelah klik mouse; deteksi nol PKPT lewat `item === 0`: perbaiki. UI.
13. (F9 T4) Ikon segmented Linimasa/Bulanan; label dalam bar memakai aturan ≥ 8 kolom, bukan ukuran terukur ≥ 96 px; kilatan kedua tampilan sebelum Alpine siap (`x-cloak`); `Pilih tahun lain` memakai `setTimeout(300)`; celah tes (posisi `Hari ini`, label vs title, `aria-pressed` legenda, drawer filter); bottom sheet tanpa kunci scroll; `aria-modal` pada popover desktop: perbaiki/tes. UI.
14. (F9 T5) Bar filter sticky membungkus dua baris di 768–1023; label nilai batang belum diverifikasi; tes dispatch hanya memeriksa 4 dari ±10 masukan grafik; label tombol urut menampilkan keadaan, bukan aksi: perbaiki/tes. UI.
15. (F9.1) `x-app.kalender-pkpt`: id statis bentrok bila dua instans; popover `aria-modal` tanpa `inert`; handler `revisi-terkirim` bergantung pada `data-kartu`; arsir minggu ke-5 memakai `var(--color-neutral-200)` arbitrer; hover baris Detail Realisasi tidak menyorot sel `rowspan`; kartu mobile selalu terbuka; tautan nomor SPT 24px; judul minggu Bulanan publik tanpa rentang tanggal: perbaiki yang tidak mengubah design; sisanya Tidak dikerjakan dengan alasan. UI.
16. (F9.1) `KalenderPkpt` memakai `RealisasiPublik::itemDisahkan`: pastikan penanda `MK-KETERANGAN-item` tetap menjaga; tidak ada perubahan bila sudah aman (catat).

### Task 10: SPT, pengajuan, kotak masuk, detail, komentar

Area: `app/Actions/Spt/*`, `app/Livewire/Spt/*`, `app/Livewire/**/KotakMasuk*`, `resources/views/livewire/spt/*`, `resources/views/components/ui/file-upload*`, `app/Actions/Komentar/*`, `app/Models/Komentar.php`, tes SPT/komentar.

Butir:
1. (F10+) SPT Saya: kolom Ubah terdorong keluar layar di 1280: atur kolom agar Ubah terlihat tanpa gulir di 1280. UI.
2. (F10+) Ringkasan Kesesuaian di detail dihitung untuk semua status, penanda hanya untuk diterima: samakan dengan aturan PRD (tampilkan hanya bila relevan) atau catat alasan.
3. (F10+) Tes status: baris untuk SPT non-diterima dengan `ditandai_selesai_pada` lama: tambahkan.
4. (Kotak masuk) Penghitung karakter catatan `aria-live` terlalu sering bicara: umumkan hanya di ambang (mis. sisa ≤ 20) atau debounce. Tes pintasan keyboard hanya memeriksa string sumber JS: Tidak dikerjakan bila tanpa tes browser.
5. (Lain) Halaman Ubah SPT tidak menyorot item sidebar: sorot item yang benar.
6. (Lain) `x-ui.file-upload`: input file tanpa nama aksesibel layak dan tanpa penanda wajib (Cover LHP): perbaiki di komponen bersama. UI.
7. (F5) Tes: binding `x-ui.date-range` di modal Ubah Tanggal; `assertSee` Tidak Sesuai PKPT ambigu; ketiadaan alert perlu-kendala; normalisasi `tanggal_lhp` dengan input non-kanonis: tambahkan/perjelas. `TandaiSelesai` mem-parse `tanggal_lhp` dua kali: sekali.
8. (Detail SPT) Tautan nomor di Register tanpa ukuran target sentuh (≥ 24px, DESIGN §6); `Spt\Detail` memakai trait `MelaksanakanSpt` hanya untuk `izinkan`: pindahkan `izinkan` ke tempat bersama tanpa membawa method privat tak terpakai. UI.
9. (F7) `PimpinanPolicyTest` assert label-tidak-ada tanpa kontrol positif; Irban pemilik boleh `balasKomentar` pada draf miliknya (tes perilaku saat ini); `Komentar` `fillable` memuat `status_permintaan`/`ditutup_*` (keluarkan bila tidak diisi massal dari input); `trim()` tidak membuang NBSP saja (tolak komentar hanya-spasi Unicode); `utasKomentar` tanpa docblock `@return` dan query ekstra; balasan pada permintaan yang sudah ditutup mengirim `BalasanPenjelasan` (tes perilaku saat ini); `konteks` bisa `''` bukan null; id `diinput_oleh` ganda bila modal Detail dibuka di atas form balas; aksi Komentar tanpa `Gate::authorize` sendiri (tambahkan); Komentar bergantung pada listener `fokus-ke` milik `Detail`; `assertDontSee('Diinput oleh')` untuk BU lemah: perbaiki/tes satu per satu.

### Task 11: Penutup

- [ ] Perbarui `docs/plans/serah-terima.md`: hapus butir minor yang Selesai (lihat `decisions/fase-11.2.md`), sisakan yang Tidak dikerjakan dengan alasannya (dan bagian "Di luar lingkup" di atas), perbarui status repo/branch, tambah jebakan baru bila ada yang memakan putaran perbaikan.
- [ ] Tambah bagian "Di luar lingkup" ke `decisions/fase-11.2.md`.
- [ ] Suite penuh SQLite + suite penuh MySQL (`sintas_uji`, lalu hapus database itu); pint.
- [ ] `graphify update .` dan ekstraksi ulang dokumen yang berubah (`scripts/rebuild_graph.py`).
- [ ] Commit.
