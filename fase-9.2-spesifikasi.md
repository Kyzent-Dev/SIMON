# Fase 9.2: Ekspor Rekap, Perubahan PKPT, Profil, dan Email Pegawai

> **Spec untuk AI Agent.** Ikuti `CLAUDE.md` dan cara kerja di `docs/plans/serah-terima.md`:
> 1. Baca `serah-terima.md`. Salin "Jebakan yang sudah diketahui" ke Global Constraints plan.
> 2. Tulis plan `docs/plans/fase-9.2-ekspor-perubahan-profil.md` berdasarkan spec ini, lalu commit.
> 3. Eksekusi dengan `superpowers:subagent-driven-development` (implementer per tugas, review per tugas, review akhir, satu gelombang perbaikan).
>
> - **Branch:** `fase-9.2-ekspor-perubahan-profil` dari `fase-9.1-revisi-realisasi`.
> - **Keputusan:** buat `decisions/fase-9.2.md` (judul `# Keputusan Fase 9.2`, bagian `## Fase 9.2, Task M`) dan tambahkan satu baris di `DECISIONS.md`.
> - **UI:** baca `DESIGN.md` dan file `design/` yang relevan, lalu panggil skill `ui-ux-pro-max:ui-ux-pro-max`.
> - **Setelah selesai:** perbarui `PRD-SIMON.md` (bagian yang berubah tercantum di Task 8), `serah-terima.md`, dan graph.

---

## 0. Ringkasan keputusan pengguna (final, jangan ditanyakan ulang)

| No | Keputusan |
|---|---|
| K1 | Ekspor Rekap: pengguna memilih bagian (Rencana vs Realisasi, Per Klasifikasi, Per Jenis Penugasan, Detail Realisasi). Minimal satu bagian. |
| K2 | Excel Rekap: sheet "Gabungan" ditambah satu sheet per irban. Bagian yang dipilih disusun ke bawah di setiap sheet. Header berwarna, lebar kolom tetap. |
| K3 | PDF Rekap: grafik **batang saja** di bawah Rencana vs Realisasi, Per Klasifikasi, dan Per Jenis. Detail Realisasi tanpa grafik. |
| K4 | Alur jadwal dirombak. Hanya **Admin** yang mengubah PKPT yang sudah disahkan, lewat paket **Perubahan PKPT**. Setiap paket wajib disetujui **Pimpinan**. Fitur revisi jadwal oleh Irban dihapus dan diganti **Usulan Perubahan**. |
| K5 | Perubahan berlaku setelah disetujui Pimpinan, walaupun SK belum dilampirkan. Admin menerima pengingat harian sampai SK lengkap, dan bisa mematikannya per paket. |
| K6 | Perubahan rekap triwulan yang sudah lewat **diterima apa adanya**, dengan riwayat tercatat. Tidak ada snapshot rekap. |
| K7 | Item yang sudah dipakai SPT (status apa pun) tidak boleh dihapus. Item itu hanya boleh digeser atau diubah, dan irban serta jenis penugasannya terkunci. |
| K8 | Setiap pengguna punya halaman Profil: foto, latar profil, username, email, kata sandi. Untuk akun **Irban** (akun bersama), username dan email **hanya bisa diubah Admin**. |
| K9 | Setiap pegawai punya email (opsional). Notifikasi email juga dikirim ke pegawai yang tercatat di "Diinput oleh". |

---

## Task 1: Email pegawai

**Data**
- Migrasi: `pegawai.email` (string, nullable, unique).
- Form Pegawai (`/admin/pegawai`): field Email dengan validasi `email:rfc`. Kolom email tampil di tabel pegawai.
- Impor pegawai: kolom `email` opsional di template dan parser. Baris dengan email tidak valid atau duplikat ditolak dengan pesan per baris (pola impor yang sudah ada).
- Seeder demo: sebagian pegawai Irban I diberi email `@example.test`, sebagian dibiarkan kosong.

**Pengiriman**
- Tambah `PenerimaNotifikasi::emailPegawai(?Pegawai $p, Collection $akun): ?string`. Method ini mengembalikan email pegawai hanya jika: pegawai aktif, email terisi, dan email tidak sama dengan email salah satu akun penerima yang sudah dikirimi notifikasi yang sama. Dengan begitu tidak ada email ganda.
- Kirim lewat `Notification::route('mail', $email)`. Hanya email, tanpa lonceng, dalam antrean, `afterCommit`.
- Template email memakai salam nama pegawai dan baris penutup "Anda menerima email ini karena tercatat sebagai penginput data di SIMON."

| Kejadian | Pegawai penerima |
|---|---|
| Pengajuan diterima, perlu revisi, ditolak (`PengajuanDiputuskan`) | `spt.diinput_oleh` saat pengajuan terakhir dikirim |
| Nomor dibatalkan (`NomorDibatalkan`) | `spt.diinput_oleh` |
| Usulan Perubahan diterapkan atau ditutup (Task 6) | `pkpt_usulan.diinput_oleh` |

**Test:** email terkirim ke pegawai yang benar, dilewati kalau kosong atau pegawai nonaktif, tidak ganda dengan email akun, dan impor menerima serta menolak email dengan benar.

---

## Task 2: Ekspor Rekap Excel (pilihan bagian dan workbook bergaya)

### 2.1 Pilihan bagian
- Kode bagian: `rr` (Rencana vs Realisasi), `klasifikasi`, `jenis`, `detail`. Urutan kanonik juga mengikuti urutan ini.
- Tombol Excel dan PDF di Rekap membuka modal **"Pilih isi ekspor"**:
  - Grup "Ringkasan": `rr`, `klasifikasi`, `jenis`.
  - Grup "Detail": `detail`.
  - Tombol "Unduh Excel" atau "Unduh PDF", sesuai tombol yang diklik.
  - Validasi: minimal satu bagian. Galat tampil di modal.
- Pilihan awal mengikuti tab aktif. Tab `irban` dan `gabungan` mencentang `rr`, `klasifikasi`, dan `jenis`. Tab `detail` mencentang `detail`.
- Filter ekspor mendapat kunci `bagian`: array yang divalidasi terhadap whitelist, diurutkan kanonik, dan tersimpan di job antrean. Nilai yang tidak dikenal dibuang. Kalau hasilnya kosong, sistem memakai default tab `irban`.
- Nama file: kalau semua bagian dipilih, nama tidak berubah. Kalau tidak, tambahkan sufiks kode bagian (contoh `rekap-triwulan-2026-tw2-rr-detail`).
- `PembangunRekap::jumlah()` hanya menghitung bagian yang dipilih, dikali jumlah sheet.

### 2.2 Struktur workbook
- Kelas baru `App\Exports\RekapWorkbook` (`WithMultipleSheets`) dan `App\Exports\RekapSheet` (`FromArray`, `WithTitle`, `WithStyles`, `WithColumnWidths`, `WithEvents`).
- **Jebakan:** binder teks berawalan `= + - @` dari `ArrayExport` wajib dipakai. Ekstrak binder itu ke trait (misalnya `App\Exports\Concerns\AmanFormula`) dan pakai di `ArrayExport` serta `RekapSheet`. `ArrayExport` tetap dipakai Register dan Monitoring tanpa perubahan perilaku.

| Pengguna dan filter | Sheet |
|---|---|
| Pimpinan tanpa filter irban | `Gabungan`, `Irban I` … `Irban V` (urut `irban.id`) |
| Pimpinan dengan `irban` di URL | Hanya sheet irban itu |
| Akun Irban | Hanya sheet irbannya |

- **Sheet Gabungan:** `rr` gabungan ditambah tabel ringkas per irban (satu baris per irban: rencana, realisasi, %), lalu `klasifikasi` dan `jenis` untuk semua irban. **Tanpa `detail`**, karena detail ada di sheet tiap irban.
- **Sheet irban:** bagian yang dipilih, dihitung dengan `RekapTriwulan::hitung($tahun, $irbanId)` dan `RealisasiItemPkpt::untuk(..., $irbanId)`.
- Nama sheet maksimal 31 karakter, tanpa `: \ / ? * [ ]`.

### 2.3 Gaya
- **Baris 1–3 setiap sheet:** judul ("Rekap Triwulanan 2026 · TW II"), nama sheet (contoh "Irban I"), dan "Dibuat: 24 Oktober 2026 09.15 WIB".
- **Per bagian:**
  - Baris judul bagian: tebal, 12pt.
  - Baris header: isi **mint-700** dengan teks putih tebal, rata tengah, wrap. Hex diambil dari token `DESIGN.md` §13 dan disimpan sebagai satu konstanta di `RekapSheet`. Catat di keputusan bahwa ini lokasi hex kedua setelah `grafik.js`.
  - Border tipis neutral-300 di seluruh tabel.
  - Baris Total tebal dengan border atas medium.
  - Angka rata kanan.
  - Satu baris kosong antarbagian.
- **Lebar kolom:** setiap bagian mendeklarasikan tipe per kolom:

  | Tipe | Lebar | Wrap |
  |---|---|---|
  | `label` | 45 | ya |
  | `teks` | 22 | ya |
  | `nomor` | 34 | tidak |
  | `tanggal` | 26 | ya |
  | `angka` | 12 | tidak |
  | `panjang` | 60 | ya |

  Lebar akhir sebuah kolom adalah nilai maksimum dari semua bagian di sheet itu. Jangan memakai `ShouldAutoSize`.
- **Detail Realisasi:** sel Jenis Penugasan dan Periode PKPT **digabung vertikal per item**, rata atas. Ini mengganti keputusan Fase 9.1 "tanpa sel gabungan", jadi catat sebagai keputusan baru.
- **Freeze pane** di baris 4 (di bawah judul sheet).
- **Pengaturan cetak:** landscape, fit to width 1 halaman, A4.

**Test:**
- Pilihan bagian dihormati di xlsx dan PDF.
- Jumlah dan nama sheet benar untuk tiga skenario pengguna.
- Header berwarna dan lebar kolom sesuai (baca balik dengan PhpSpreadsheet).
- Sel tergabung di Detail.
- Angka di setiap sheet sama dengan `RekapTriwulan` untuk irban itu.
- Ekspor antrean membawa `bagian`.
- Teks berawalan `=` tetap tersimpan sebagai string.

---

## Task 3: Grafik di PDF Rekap

- dompdf tidak menjalankan JavaScript. Grafik dibuat dengan **HTML/CSS** (tabel dan div dengan lebar persentase serta warna latar), dalam partial `resources/views/pdf/partials/grafik-batang.blade.php` yang menerima data array. Tanpa SVG, tanpa gambar, tanpa layanan luar.
- Grafik hanya muncul di bawah bagian `rr`, `klasifikasi`, dan `jenis` yang dipilih. `detail` tanpa grafik.

| Bagian | Grafik |
|---|---|
| `rr` | Batang berpasangan Rencana dan Realisasi. Sumbu kategori: per irban (Pimpinan tanpa filter irban) atau per TW (satu irban). Kalau TW tertentu dipilih, cukup per irban untuk TW itu. |
| `klasifikasi` | Batang horizontal bertumpuk Berjalan dan Selesai per klasifikasi. Klasifikasi dengan jumlah 0 tetap tampil sebagai baris kosong. |
| `jenis` | Batang horizontal **persen realisasi** per jenis yang punya rencana lebih dari 0, diurutkan menurun. Label nilai `75% (3/4)` di ujung batang. |

- Setiap batang diberi label angka. Warna tidak boleh menjadi satu-satunya pembawa makna. Sertakan legenda teks.
- Warna dari palet `grafik.js`, disalin sebagai konstanta PHP di satu tempat (`App\Support\PaletGrafik`). Catat di keputusan.
- `page-break-inside: avoid` untuk setiap blok tabel dan grafik.
- **Test:** PDF HTML memuat grafik hanya untuk bagian ringkasan yang dipilih, lebar batang sesuai proporsi (cek atribut gaya), dan render dompdf tidak error untuk data kosong.

---

## Task 4: Perubahan PKPT: model data, layanan, dan migrasi revisi lama

### 4.1 Aturan inti
- PKPT **Draf** tetap bebas diubah Admin seperti sekarang. Pengesahan awal tetap dilakukan Admin dengan SK, tanpa persetujuan Pimpinan. Catat keputusan ini.
- PKPT **Disahkan** hanya bisa diubah lewat **Perubahan PKPT** (satu paket berisi banyak operasi).
- Hanya **satu paket terbuka** (`draf`, `diajukan`, atau `ditolak`) per PKPT.
- **Operasi:**

  | Aksi | Isi |
  |---|---|
  | `tambah` | Item baru: irban, jenis, tanggal, keterangan, target |
  | `ubah` | Mengubah tanggal (geser), keterangan, target. Irban dan jenis juga bisa diubah, kecuali item sudah punya SPT |
  | `hapus` | Item **tanpa SPT apa pun** (cek `spt.pkpt_item_id`, semua status) ditandai `dihapus` (soft). Item tetap ada untuk riwayat dan dikecualikan dari semua tampilan dan hitungan |

- **Status paket:** `draf` → `diajukan` → `disetujui` atau `ditolak`. Paket `ditolak` bisa diedit Admin lalu diajukan lagi. Paket `draf` atau `ditolak` yang belum pernah disetujui boleh dibuang.
- **Penerapan saat disetujui:**
  - Dalam satu transaksi dengan `lockForUpdate` pada baris `pkpt`, **validasi ulang setiap operasi** dengan aturan `SavePkptItem::validated` (tanggal dalam tahun PKPT, dan seterusnya) dan aturan K7.
  - Kalau satu operasi tidak sah, seluruh persetujuan gagal dan paket tetap `diajukan`. Sistem menampilkan daftar alasan, dan Pimpinan bisa Tolak dengan komentar.
  - `data_lama` direkam **saat penerapan**, bukan saat draf disusun.
  - Paket mendapat nomor urut "Perubahan ke-N" per PKPT saat disetujui.
- **SK** (nomor, tanggal, PDF) boleh diisi sejak draf dan bisa dilengkapi atau diubah setelah disetujui. Hanya kolom SK yang boleh berubah setelah persetujuan, dan setiap perubahan tercatat di riwayat paket. SK dianggap lengkap kalau ketiganya terisi.
- File SK disimpan di disk privat dengan hash SHA-256. Unduhnya lewat route berotorisasi (Admin, Pimpinan, dan Irban yang itemnya terdampak). File SK **tidak pernah** tampil di halaman publik.

### 4.2 Skema
- `pkpt_item`: tambah kolom
  - `status` (`aktif` | `dihapus`, default `aktif`)
  - `tanggal_mulai_disahkan`, `tanggal_selesai_disahkan` (snapshot saat pengesahan, untuk "jadwal semula")
  - `ditambahkan_perubahan_id` (nullable)
  - `dihapus_perubahan_id` (nullable)
  - `SahkanPkpt` mengisi kolom snapshot untuk semua item.
- `pkpt_perubahan`:
  - `pkpt_id`, `nomor_urut` (nullable sampai disetujui), `jenis` (`perubahan` | `koreksi_input`), `alasan` (text, wajib), `status`, `sumber` (`admin` | `migrasi_revisi`)
  - `dibuat_oleh`, `diajukan_pada`, `diputuskan_oleh`, `diputuskan_pada`, `komentar_putusan`
  - `nomor_sk`, `tanggal_sk`, `berkas_sk`, `hash_sk`, `sk_lengkap_pada`
  - `pengingat_sk_dimatikan_oleh`, `pengingat_sk_dimatikan_pada`
  - `pengingat_terakhir_pada` (idempotensi cron), `timestamps`
- `pkpt_perubahan_item`: `perubahan_id`, `aksi`, `pkpt_item_id` (nullable untuk `tambah` sampai diterapkan), `data_baru` (json), `data_lama` (json, nullable), `usulan_id` (nullable), `urutan`.
- `pkpt_perubahan_riwayat`: `perubahan_id`, `kejadian` (dibuat, diajukan, disetujui, ditolak, sk_diubah, pengingat_dimatikan, pengingat_dinyalakan), `oleh` (user), `catatan`, `created_at`.

### 4.3 Layanan dan action
- `app/Actions/Pkpt/Perubahan/`: `BuatPerubahan`, `SimpanOperasi`, `HapusOperasi`, `AjukanPerubahan`, `PutuskanPerubahan` (setujui/tolak), `BuangPerubahan`, `SimpanSkPerubahan`, `AturPengingatSk`.
- `App\Services\PerubahanPkpt::cek(Perubahan): list<string>` sebagai validasi non-throwing. UI dan `PutuskanPerubahan` memakai method yang sama (jebakan "aturan bisnis tidak dihitung ulang di komponen").
- **Policy:**
  - Admin membuat, mengubah, mengajukan, membuang, dan mengelola SK.
  - Pimpinan memutuskan.
  - Irban hanya melihat paket yang menyentuh irbannya.

### 4.4 Ganti sumber tanggal
- `KesesuaianPkpt::rentang()` membaca tanggal item langsung. Tidak ada lagi `revisiDisetujuiTerakhir()`.
- "Direvisi" artinya tanggal item berbeda dari snapshot `*_disahkan`. Perbarui `JadwalPublik` (`semula`), `KalenderPkpt`, `RealisasiItemPkpt` (`direvisi`), `MonitoringSpt`, `PenandaSpt`, `RekapTriwulan`, `RealisasiPublik`, dan `RingkasanMingguan`.
- Semua query item (rencana rekap, kalender, publik, combobox item di form SPT, Detail Realisasi, impor) mengecualikan `status = dihapus`. Buat scope `PkptItem::aktif()` dan pakai di semua tempat (`grep` semua pemakaian `PkptItem`/`pkpt_item`).

### 4.5 Migrasi data revisi lama (satu migrasi, teruji)
1. Isi snapshot `*_disahkan` semua item dari tanggal item sekarang. Saat ini item masih menyimpan tanggal asli, karena revisi tersimpan terpisah.
2. Untuk setiap revisi `disetujui`, urut `diputuskan_pada`:
   - Buat `pkpt_perubahan` historis dengan `sumber = migrasi_revisi`, `status = disetujui`, `jenis = perubahan`, `alasan` dari revisi, dan pemutus dari revisi.
   - Tandai pengingat SK dimatikan oleh sistem, karena revisi lama memang tidak punya SK.
   - Isi operasi `ubah` dengan `data_lama` dan `data_baru`.
   - Perbarui tanggal item ke revisi terakhir yang disetujui.
3. Revisi `menunggu` menjadi `pkpt_usulan` berstatus `terbuka`. Revisi `ditolak` menjadi usulan berstatus `ditutup` dengan komentar sebagai catatan.
4. Hapus tabel `pkpt_item_revisi`, model `PkptItemRevisi`, policy, factory, `AjukanRevisiJadwal`, `PutuskanRevisiJadwal`, notifikasi `RevisiJadwalDiajukan` dan `RevisiJadwalDiputuskan` (tambahkan pemetaan jenis lama di `JenisNotifikasi` supaya notifikasi lama di lonceng tetap tampil), serta halaman `/pimpinan/revisi-jadwal`. Migrasi ini tidak bisa dibalik. `down()` cukup melempar exception yang jelas.

**Test:**
- Penerapan setiap aksi.
- Validasi ulang gagal kalau SPT dibuat setelah paket diajukan.
- Kunci irban dan jenis untuk item ber-SPT.
- Hanya satu paket terbuka.
- Item dihapus hilang dari rencana, kalender, publik, dan form SPT.
- Kesesuaian SPT berubah setelah penggeseran disetujui.
- Migrasi revisi lama (fixture: satu disetujui ganda, satu menunggu, satu ditolak).
- Uji konkurensi dua persetujuan sekaligus.

---

## Task 5: Perubahan PKPT: layar Admin dan Pimpinan

**Admin, `/admin/pkpt/{pkpt}` (PKPT Disahkan):**
- Tab "Item": daftar item read-only dengan riwayat perubahan per item. Item dihapus disembunyikan secara default, dengan pilihan "Tampilkan yang dihapus".
- Tab "Perubahan":
  - Daftar paket dengan status (`x-app.tag`), nomor urut, jumlah operasi, dan penanda **"SK belum dilengkapi"**.
  - Tombol "Buat Perubahan", nonaktif dengan alasan tertulis kalau sudah ada paket terbuka.
- Halaman detail paket `/admin/pkpt/{pkpt}/perubahan/{perubahan}`:
  - Form alasan dan jenis (Perubahan / Koreksi input).
  - Daftar operasi dengan tampilan sebelum dan sesudah. Tombol "Tambah item", "Ubah item", "Hapus item". Pilih item lewat combobox yang menampilkan chip jenis, irban, dan jadwal.
  - Strip validasi dari `PerubahanPkpt::cek()`.
  - Panel "Usulan dari Irban" (Task 6) untuk menampung usulan ke paket.
  - Blok SK: nomor, tanggal, unggah PDF.
  - Tombol "Ajukan ke Pimpinan".
  - Komentar penolakan tampil di atas form kalau paket ditolak.
  - Setelah disetujui: "Matikan pengingat SK" atau "Nyalakan pengingat SK" (konfirmasi, tercatat).
- Kartu dasbor Admin: "Perubahan menunggu persetujuan" dan "SK belum dilengkapi".

**Pimpinan, `/pimpinan/perubahan-pkpt`** (menggantikan Revisi Jadwal; perbarui navigasi dan kartu dasbor):
- Daftar paket `diajukan` dan riwayat.
- Detail paket menampilkan operasi sebelum dan sesudah serta dampaknya: jumlah item per irban yang berubah, dan SPT yang kesesuaiannya akan berubah.
- Tombol **Setujui** dan **Tolak** (komentar wajib saat menolak).
- Kalau validasi ulang gagal, daftar alasan tampil sebagai alert dan paket tetap `diajukan`.

**Notifikasi** (pakai `NotifikasiSimon`, penerima lewat `PenerimaNotifikasi`; tambahkan `admin()`):

| Kejadian | Penerima |
|---|---|
| Paket diajukan | Semua Pimpinan |
| Paket disetujui | Admin pembuat, Pimpinan lain, akun Irban yang itemnya terdampak |
| Paket ditolak | Admin pembuat |

**Pengingat** (di command `notifikasi:harian` 06.00, idempotensi per tanggal lewat `pengingat_terakhir_pada`):
- Ke Pimpinan: setiap paket `diajukan`, setiap hari sampai diputuskan. Tidak bisa dimatikan.
- Ke semua Admin: setiap paket `disetujui` yang SK-nya belum lengkap dan pengingatnya tidak dimatikan.

**Test:** otorisasi setiap layar dan aksi (termasuk endpoint update Livewire), penerima notifikasi, pengingat tidak ganda dalam satu hari, pengingat berhenti setelah SK lengkap atau dimatikan, dan fokus pindah ke target stabil setelah setujui, tolak, atau ajukan.

---

## Task 6: Usulan Perubahan oleh Irban dan dampak tampilan

**Usulan:**
- Tabel `pkpt_usulan`:
  - `pkpt_id`, `irban_id`, `pkpt_item_id` (nullable untuk usulan item baru)
  - `aksi_usulan` (`geser` | `hapus` | `tambah` | `lainnya`)
  - `tanggal_mulai`, `tanggal_selesai` (nullable), `jenis_penugasan_id` (nullable, untuk `tambah`), `alasan` (wajib)
  - `diajukan_oleh` (user), `diinput_oleh` (pegawai, wajib)
  - `status` (`terbuka` | `ditampung` | `diterapkan` | `ditutup`), `perubahan_id`, `ditutup_oleh`, `catatan_penutupan`, `timestamps`
- Halaman Jadwal PKPT Irban: tombol "Ajukan Revisi Jadwal" diganti **"Usulkan Perubahan"**, baik di tabel maupun di detail kalender. Ada juga tombol umum "Usulkan item baru". Modal memakai pola yang sama, dengan "Diinput oleh" di footer.
- Daftar "Usulan saya" berisi status tiap usulan.
- **Admin:**
  - Panel usulan terbuka di halaman Perubahan.
  - "Tampung ke perubahan" membuat operasi terisi dari usulan dan mengubah status usulan menjadi `ditampung`.
  - "Tutup usulan" mewajibkan catatan.
  - Saat paket disetujui, usulan yang tertampung menjadi `diterapkan`.
  - Kalau operasi asal usulan dihapus dari paket, usulan kembali `terbuka`.
- **Notifikasi:**

  | Kejadian | Penerima |
  |---|---|
  | Usulan baru | Semua Admin |
  | Usulan diterapkan atau ditutup | Akun irban dan email pegawai `diinput_oleh` (Task 1) |

**Dampak tampilan:**
- **Kalender Irban dan publik:** item tambahan diberi label "Tambahan · Perubahan ke-N". Item yang tanggalnya beda dari snapshot memakai penanda direvisi, dengan "Jadwal semula" dari snapshot. Item dihapus tidak tampil.
- **Riwayat per item** (Irban dan Admin): daftar perubahan dengan nomor dan tanggal SK kalau ada, plus tautan unduh SK untuk pengguna berhak.
- **Publik:** hanya jadwal berlaku dan jadwal semula. Tanpa SK, tanpa alasan, tanpa usulan. Perbarui `PrivasiPublikTest` dengan penanda untuk alasan perubahan, nomor SK, dan alasan usulan.

**Test:** alur usulan penuh, scope irban pada semua lookup, usulan kembali terbuka saat operasi dihapus, dan privasi publik.

---

## Task 7: Halaman Pengguna (Admin) dan Profil

### 7.1 Admin, `/admin/pengguna` (celah PRD 7.4 yang belum dibangun)
- Daftar pengguna dengan filter peran.
- Buat pengguna: nama, username, email, peran, irban (wajib untuk peran Irban).
- Ubah pengguna: kolom yang sama.
- **"Atur ulang kata sandi":** Admin menetapkan kata sandi sementara, dan pengguna wajib menggantinya saat login berikutnya. Pakai mekanisme `wajib_ganti_password` yang sudah ada.
- Validasi username: huruf kecil, 3 sampai 30 karakter, `a-z0-9._`, unik. Email unik.
- Admin tidak bisa mengubah perannya sendiri atau menghapus admin terakhir. Tidak ada fitur hapus pengguna di fase ini.

### 7.2 Profil, `/profil` (semua peran, tautan di menu pengguna header)

| Kolom | Akun pribadi (Admin, Pimpinan, Bagian Umum) | Akun Irban |
|---|---|---|
| Nama tampilan | Bisa | Hanya Admin |
| Foto profil | Bisa | Bisa |
| Latar profil | Bisa | Bisa |
| Username | Bisa | Hanya Admin (tampil read-only + teks "Hubungi Admin untuk mengubah") |
| Email | Bisa, lewat verifikasi | Hanya Admin |
| Kata sandi | Bisa | Bisa |

- **Gambar:** pakai pola `GambarSlide` (GD, orientasi EXIF, batas dimensi), dengan disk `public` folder `profil/`.
  - Foto: dipotong persegi di tengah, WebP 256×256.
  - Latar: WebP 1600×400, dipotong di tengah.
  - File lama dihapus saat diganti.
  - Bisa "Hapus foto" atau "Hapus latar".
  - Field unggah baru diberi atribut di `lang/id/validation.php`.
- **Avatar** tampil di menu pengguna header dan halaman profil. Kalau kosong, tampilkan inisial nama. Latar hanya tampil di halaman profil. Jangan tambahkan avatar ke komentar atau tempat lain di fase ini.
- **Ganti email (akun pribadi):**
  - Simpan `email_baru` dan kirim tautan verifikasi bertanda tangan (berlaku 60 menit) ke email baru. Email lama tetap aktif sampai tautan diklik.
  - Email lama menerima pemberitahuan setelah perubahan berhasil.
- **Ganti kata sandi:**
  - Wajib memasukkan kata sandi lama. Aturan kekuatan sama dengan ganti kata sandi wajib.
  - Setelah berhasil, sesi lain dikeluarkan (`Auth::logoutOtherDevices`). Pastikan middleware `AuthenticateSession` aktif. Untuk akun Irban, layar konfirmasi memberi tahu bahwa anggota lain akan keluar.
  - Email akun menerima pemberitahuan "Kata sandi diubah".
- **Akun Irban:** setiap aksi tulis di Profil (foto, latar, kata sandi) wajib "Diinput oleh" di footer form dan tercatat di log. Pemberitahuan kata sandi menyebut nama pegawai penginputnya.
- **Test:**
  - Pembatasan akun Irban, termasuk percobaan lewat endpoint Livewire.
  - Verifikasi email: tautan sah, kedaluwarsa, dan dipakai ulang.
  - Logout sesi lain.
  - Validasi gambar dan hasil WebP.
  - Admin mengatur ulang kata sandi lalu login memaksa ganti.

---

## Task 8: Penutup fase
- Perbarui `PRD-SIMON.md`:
  - §5: hak akses Admin, Pimpinan, dan Irban untuk jadwal; halaman Pengguna; Profil.
  - §6.2: alur revisi diganti alur Perubahan PKPT dan Usulan.
  - §7.4: email pegawai.
  - §7.5: Perubahan PKPT.
  - §7.9: ekspor Rekap.
  - §7.10: notifikasi baru dan pengingat.
  - §8: tabel baru.
- Uji di MariaDB/MySQL (`serah-terima.md`, "Cara kerja").
- Cek browser di 360, 768, dan 1280px:
  - modal pilih isi ekspor
  - layar Perubahan Admin dan Pimpinan
  - modal Usulan
  - Profil (unggah, hapus, ganti kata sandi)
  - halaman Pengguna
- Buka file xlsx dan PDF hasil ekspor dan periksa secara visual. Simpan screenshot ke scratchpad.
- Perbarui `serah-terima.md` dan graph (`graphify update .` dan `/graphify . --update`).

---

## Di luar lingkup
- Snapshot rekap per triwulan (K6).
- Persetujuan Pimpinan untuk pengesahan awal PKPT.
- Hapus atau nonaktifkan pengguna.
- Avatar di komentar.
- Kalender di halaman Admin dan Pimpinan.
- Pertanyaan terbuka dari Fase 9.1 (RSUD di panel Wilayah) tetap menunggu jawaban pengguna. Jangan ubah perilakunya di fase ini.
