# Tugas Tambahan Fase 9: Detail Realisasi (Rekap) dan Kalender Jadwal PKPT

> Prompt untuk AI Agent. Ikuti `CLAUDE.md`: baca `DESIGN.md`, `design/dashboard/_shared.md`, `design/dashboard/pimpinan.md`, `design/dashboard/irban.md`, dan untuk Tugas B juga `design/public/jadwal.md`.
>
> - Catat keputusan di `decisions/fase-9.md` di bawah `## Fase 9, Task Tambahan A: Detail Realisasi` dan `## Fase 9, Task Tambahan B: Kalender Jadwal`.
> - Perbarui `docs/plans/serah-terima.md`, lalu jalankan `graphify update .` (dan `/graphify . --update` kalau dokumen desain berubah).
> - Kerjakan Tugas A dulu, lalu Tugas B. Setiap tugas selesai saat semua test-nya hijau dan test lama tetap hijau.

---

## Data acuan (dipakai kedua tugas)

Data PKPT di database saat ini masih dummy (3 item Irban I). Tambahkan seeder demo `PkptIrbanIDemo2026Seeder` berisi PKPT 2026 Irban I sesuai kisi jadwal Sistem B lama. Seeder ini menjadi fixture test dan bahan demo.

Tanggal di bawah diturunkan dari posisi minggu di kisi (Senin–Jumat). Kalau dokumen PKPT resmi tersedia, ganti dengan tanggal resminya. Kolom "Minggu" adalah hasil aturan B.2 dan **wajib sama** dengan kisi lama.

| Tanggal | Minggu (aturan B.2) | Jenis penugasan (keterangan) |
|---|---|---|
| 12–23 Jan | Jan M2–M3 | Evaluasi Risk Register |
| 26–30 Jan | Jan M4 | Evaluasi Reformasi Birokrasi dan Reviu DAK |
| 2–27 Feb | Feb M1–M4 | Pengawasan Pengelolaan Dana BOS Sekolah (1 tim 1 minggu 2 sekolah) |
| 2–6 Mar | Mar M1 | Reviu LKjIP Pemda |
| 9–13 Mar | Mar M2 | Reviu DBH |
| 23–27 Mar | Mar M4 | Reviu Tata Kelola Pajak |
| 30 Mar–3 Apr | Apr M1 | Evaluasi Reformasi Birokrasi dan Reviu DAK |
| 6–10 Apr | Apr M2 | Evaluasi SAKIP PD |
| 13 Apr–8 Mei | Apr M3–M5, Mei M1 | Pengawasan Tata Kelola Desa/Keuangan Desa (1 tim 1 minggu 2 desa) |
| 11–22 Mei | Mei M2–M3 | Pendampingan dan Evaluasi ZI PD |
| 25 Mei–5 Jun | Mei M4, Jun M1 | Audit Kinerja Program Strategis Daerah |
| 8–26 Jun | Jun M2–M4 | Audit Ketaatan Puskesmas (1 tim 1 minggu 1 puskesmas) |
| 29 Jun–3 Jul | Jul M1 | Evaluasi Reformasi Birokrasi dan Reviu DAK |
| 6–17 Jul | Jul M2–M3 | Reviu Kinerja Pelayanan Publik Sektor Kependudukan dan Pencatatan Sipil |
| 20–24 Jul | Jul M4 | Monev Program Strategis Nasional Ketahanan Pangan dan Pembangunan Tiga Juta Rumah |
| 27 Jul–14 Agu | Jul M5, Agu M1–M2 | Audit Ketaatan Kecamatan (1 tim 1 minggu 1 kecamatan) |
| 17–28 Agu | Agu M3–M4 | Audit Kinerja Program Prioritas Daerah |
| 31 Agu–25 Sep | Sep M1–M4 | Audit Ketaatan OPD (1 tim 1 minggu 1 OPD) |
| 28 Sep–2 Okt | Sep M5 | Evaluasi Pelayanan Publik Kemenpan RB |
| 5–9 Okt | Okt M1 | Evaluasi Pelayanan Publik Ombudsman |
| 12–16 Okt | Okt M2 | Audit Tujuan Tertentu Perusda/BUMD (Perumdam Lematang Enim) |
| 19 Okt–13 Nov | Okt M3–M4, Nov M1–M2 | Pengawasan Tata Kelola Desa/Keuangan Desa (1 tim 1 minggu 2 desa) |
| 16–27 Nov | Nov M3–M4 | Evaluasi Risk Register |
| 30 Nov–4 Des | Des M1 | Evaluasi Reformasi Birokrasi dan Reviu DAK |
| 7–25 Des | Des M2–M4 | Monev |

Cocokkan nama dengan master `jenis_penugasan`. Jenis yang belum ada boleh dibuat di seeder. Kolom "Lebaran" (16–20 Mar, Mar M3) **bukan** item PKPT, jadi jangan masukkan ke data.

---

## Tugas A: Tab "Detail Realisasi" di halaman Rekap

### A.1 Tujuan
Tambahkan tabel realisasi per item PKPT per irban di halaman Rekap, mengikuti format monitoring triwulan Sistem B:

| Jenis Penugasan | Periode Penugasan (PKPT) | Realisasi Periode | Kesesuaian dengan Jadwal PKPT | Status | Permasalahan/Kendala |

- Satu tabel per irban, dengan judul nama irban.
- Satu item PKPT bisa punya banyak baris SPT. Jenis Penugasan dan Periode PKPT hanya tampil di baris pertama item.
- Item PKPT yang belum punya SPT tetap tampil dengan satu baris kosong. Ini perbedaan utama dengan halaman Monitoring.

### A.2 Lokasi
- Komponen `App\Livewire\Rekap` dan view `livewire/rekap.blade.php`. Tambah nilai tab `detail` dengan label "Detail Realisasi".
  - Pimpinan: `Per Irban | Gabungan | Detail Realisasi`.
  - Irban: `Ringkasan | Detail Realisasi`. Ringkasan sama dengan tampilan Irban saat ini.
- Filter yang ada (Tahun, Triwulan, dan `irban` di URL untuk Pimpinan) berlaku juga untuk tab ini. Akun Irban tetap terkunci ke irbannya lewat `PembangunRekap::filter()`.

### A.3 Aturan data
Aturan ini **sudah final**. Pengguna sudah mengonfirmasi bahwa item April yang muncul di tabel TW I Sistem B lama adalah salah input.

Buat `App\Services\RealisasiItemPkpt`. Pakai ulang `Triwulan`, `KesesuaianPkpt`, `PenandaSpt`, dan `RekapTriwulan::rencanaItem()`. Jangan menyalin logika status, penanda, atau kesesuaian.

1. **Item yang ikut:** item dari PKPT tahun terpilih yang berstatus Disahkan. Item masuk triwulan X jika salah satu terpenuhi:
   - a. `RekapTriwulan::rencanaItem($item, $tahun)` menghasilkan TW X, atau
   - b. item punya minimal satu baris SPT (aturan 2) di TW X.

   Untuk "Semua TW", ambil semua item yang memenuhi a atau b untuk tahun tersebut.
2. **Baris SPT:** SPT `status_pengajuan = diterima`, punya `pkpt_item_id`, dan tanggal dasarnya (`Triwulan::tanggalDasar` / `batasi`) jatuh di tahun dan TW terpilih.
   - Contoh: Dana BOS direncanakan Februari dan direalisasikan April. Di TW I item tampil tanpa baris SPT. Di TW II item tampil dengan baris SPT April dan penanda Tidak Sesuai.
3. **SPT di luar PKPT** tidak ikut, konsisten dengan Rencana vs Realisasi.
4. **Urutan:** irban berdasarkan `id`, item berdasarkan tanggal mulai efektif (`KesesuaianPkpt::rentang`) lalu `id`, SPT berdasarkan `tanggal_mulai` lalu `id`.
5. **Isi baris SPT:**
   - Realisasi: `Tanggal::rentang(mulai, selesai)`, dengan nomor SPT sebagai tautan kecil ke `spt.show`.
   - Kesesuaian: sama dengan Monitoring.
   - Status: `x-app.status-badge`, ditambah `x-app.flag terlambat` bila ada.
   - Kendala: `kategori: uraian`, dengan pola "Selengkapnya" seperti Monitoring.
6. **Item tanpa SPT di periode itu:** satu baris. Realisasi berisi teks netral "Belum ada SPT". Kolom lain berisi "–". Jangan membuat warna atau penanda baru tanpa mencatat keputusannya.
7. **Bentuk keluaran:** `list<{irban: {id, nama}, item: list<{item_id, jenis: {nama, warna, warna_teks}, mulai, selesai, direvisi: bool, spt: list<row>}>}>`. Row memakai bentuk yang sama dengan `MonitoringSpt`. Ekstrak pembentuk row ke method bersama kalau perlu.
8. Irban tanpa item di periode itu tetap tampil dengan empty state "Tidak ada item PKPT pada periode ini."

### A.4 Tampilan
- Desktop (≥768px): satu `x-ui.table` per irban dengan density compact, dan `x-app.type-chip` di sel Jenis Penugasan. Tanpa animasi (DESIGN §7.5).
- Mobile (<768px): kartu per item dengan baris SPT bertingkat, seperti kartu di halaman Monitoring.

### A.5 Ekspor
- `PembangunRekap::export()` dan `html()`: tambahkan bagian keempat **"Detail Realisasi per Item PKPT"** setelah tiga bagian yang ada. Satu sub-tabel per irban, dengan kolom yang sama dengan layar ditambah Nomor SPT.
- Di Excel, isi Jenis dan Periode PKPT hanya di baris pertama item. Gabungkan sel bila `ArrayExport` mendukungnya. Kalau tidak, biarkan kosong dan catat keputusannya.
- Layar dan ekspor memanggil service yang sama.
- Periksa ulang `jumlah()`: tabel ini bisa panjang, jadi pertimbangkan antrean untuk ekspor besar.

### A.6 Test (`tests/Feature/Rekap/`)
1. Item tanpa SPT tampil dengan satu baris "Belum ada SPT".
2. Dana BOS (rencana TW I, realisasi TW II): kosong di TW I, dan tampil dengan baris SPT serta penanda Tidak Sesuai di TW II.
3. Item dengan beberapa SPT: urutan benar, dan Jenis/Periode hanya di baris pertama pada ekspor.
4. SPT Non-PKPT dan SPT yang belum diterima tidak muncul.
5. Revisi jadwal yang disetujui mengubah periode dan kesesuaian.
6. Akun Irban hanya melihat irbannya, termasuk saat `irban` di URL dimanipulasi. Ekspor juga terbatas.
7. Ekspor Excel dan PDF memuat bagian keempat yang sama dengan layar.

---

## Tugas B: Tampilan Kalender di halaman Jadwal PKPT Irban

### B.1 Tujuan
Halaman `Irban\Pkpt` saat ini hanya punya tabel item. Pegawai terbiasa membaca jadwal dalam bentuk kisi Sistem B: setiap bulan sebagai blok, dengan kolom Minggu 1–5, dan setiap sel berisi penugasan minggu itu. Tambahkan tampilan **Kalender** dengan bentuk itu, tanpa membuang tabel. Fitur **Ajukan Revisi Jadwal** tetap berjalan dari kedua tampilan.

### B.2 Aturan minggu (wajib, satu sumber)
Buat `App\Support\MingguPkpt` (atau service setara) sebagai satu-satunya penentu minggu:

- **Minggu dimulai Senin.** Satu minggu Senin–Minggu menjadi milik bulan tempat **hari Rabu**-nya jatuh. Artinya, minggu itu milik bulan yang memegang mayoritas hari kerjanya (Senin–Jumat).
- Minggu ke-n adalah urutan minggu tersebut di bulan itu. Satu bulan punya 4 atau 5 minggu.
- Item tampil di **setiap** minggu yang beririsan dengan rentang efektifnya (`KesesuaianPkpt::rentang`), bukan hanya di minggu mulai.
- **Batas tahun:** minggu yang dimiliki tahun lain tidak ditampilkan. Contohnya, 1–2 Jan 2026 milik Desember 2025 M5. Kalau seluruh rentang item jatuh di minggu milik tahun lain, tampilkan item di minggu pertama tahun ini (atau minggu terakhir untuk ujung Desember) supaya item tidak hilang.
- Rentang tanggal setiap minggu tersedia untuk label, contoh "Minggu 2 · 6–10 Apr" (tampilkan Senin–Jumat).

Aturan ini sudah diuji terhadap **seluruh** kisi Sistem B 2026 dan cocok di semua sel (lihat kolom Minggu di tabel Data Acuan). Contoh yang menentukan:
- 30 Mar–3 Apr: Rabu-nya 1 April, jadi Apr M1 dan Maret M5 kosong (sesuai kisi).
- 28 Sep–2 Okt: Rabu-nya 30 September, jadi Sep M5 (sesuai kisi).
- 29 Des 2025–2 Jan 2026: Rabu-nya 31 Desember 2025, jadi Januari 2026 dimulai dari 5 Januari (sesuai kisi: Jan M1 kosong, Evaluasi RB 26–30 Jan di M4).

Aturan lain yang sudah dicoba dan **gagal**:
- Aturan ISO (hari Kamis): Januari bergeser satu minggu.
- Minggu milik bulan hari Senin-nya: April dan Juli bergeser.
- `ceil(day / 7)` di `JadwalPublik::minggu()`: SAKIP 6–10 Apr jatuh di M1–M2, dan Evaluasi RB muncul di Maret M5 sekaligus April M1.

**Ganti `JadwalPublik::minggu()` dan `kolom()` agar memakai `MingguPkpt`**, supaya nomor minggu di halaman publik dan internal selalu sama.
- Linimasa publik tetap 60 kolom (12 × 5). Bulan yang hanya punya 4 minggu menampilkan kolom ke-5 kosong dan diarsir.
- Tampilan Bulanan publik ikut aturan "tampil di setiap minggu yang beririsan".
- Sesuaikan test publik yang bergantung pada aturan lama, dan catat perubahannya di `decisions/fase-9.md`.

### B.3 Tampilan
Tambahkan `x-ui.segmented` "Kalender | Tabel" di header halaman. Default Kalender. Simpan pilihan di query `?tampilan=`.

**Kalender, ≥1024px:**
- Kisi 2 kolom blok bulan (Jan–Feb, Mar–Apr, dan seterusnya), mengikuti tata letak Sistem B.
- Setiap blok berisi:
  - judul bulan
  - baris kepala "Minggu 1…5" dengan rentang tanggal kecil (neutral-500)
  - satu baris sel isi
- Isi sel:
  - Kartu kecil per item: batang kiri 4px `var(--type)`, nama jenis penugasan, dan keterangan item (contoh "1 tim 1 minggu 2 sekolah") dalam teks kecil.
  - Lebih dari satu item di minggu yang sama ditumpuk.
  - Sel kosong berisi "–".
  - Minggu ke-5 yang tidak ada di bulan itu tampil diarsir dengan `aria-hidden`.
- Minggu berjalan diberi tint mint-50 dan label "Minggu ini".
- Item yang direvisi memakai ikon kecil dengan teks sr-only "Jadwal direvisi".
- Klik kartu item membuka detail (popover di desktop, drawer di bawah 768px) yang berisi:
  - jenis penugasan
  - jadwal berlaku
  - jadwal semula, kalau item direvisi
  - keterangan
  - progres "x dari y SPT selesai"
  - tombol **Ajukan Revisi Jadwal**, memakai modal yang sudah ada. Tombol nonaktif kalau masih ada revisi menunggu, dengan alasan tertulis.

**Kalender, 768–1023px:** satu blok bulan per baris. Lima kolom minggu tetap tampil.

**Kalender, <768px:**
- Kartu per bulan dengan accordion. Bulan berjalan terbuka.
- Isinya bagian "Minggu 1 · 5–11 Jan" dan seterusnya, tersusun vertikal. Minggu kosong cukup satu baris "–".
- Tidak ada kisi 5 kolom di layar 360px.

**Panel Wilayah Pengawasan** di atas kalender, mengikuti tabel wilayah Sistem B:
- Tiga daftar untuk irban ini: Kecamatan, Dinas/Badan, Perusda/BUMD.
- Catatan: "RSUD, Puskesmas, Desa, BUMDes, dan Sekolah mengikuti wilayah kecamatan."
- Pakai ulang `JadwalPublik::wilayah()` atau ekstrak query-nya ke service bersama yang menerima `irban_id`.
- Panel bisa dilipat. Di mobile, panel tertutup secara default.

**Legenda:** chip `x-app.type-chip` untuk jenis penugasan yang ada, diletakkan di atas kalender.

Ikuti DESIGN §7.5: tidak ada animasi di halaman dashboard selain transisi modal atau drawer bawaan.

### B.4 Struktur kode
- Data kalender dibangun di service, misalnya `KalenderPkpt::untuk(int $tahun, int $irbanId)`, dengan keluaran `list<{bulan, nama, minggu: array<1..5, {ada: bool, mulai, selesai, item: list<...>}>}>`. Komponen dan view hanya memformat.
- Komponen Blade kalender dibuat bisa dipakai ulang (`x-app.kalender-pkpt`), supaya nanti Admin (`PkptDetail`) dan Pimpinan bisa memakainya tanpa kode baru. Jangan pasang di halaman lain dalam tugas ini.

### B.5 Test
1. Uji unit `MingguPkpt` dengan semua baris tabel Data Acuan (data provider), ditambah batas tahun:
   - 1 Jan 2026 milik Desember 2025 M5.
   - 5 Jan 2026 adalah Jan M1.
   - 31 Des 2026 adalah Des M5.
   - Item 1–2 Jan 2026 tetap tampil di Jan M1 tahun 2026.
2. Dengan `PkptIrbanIDemo2026Seeder`, kalender Irban I menghasilkan isi sel yang sama dengan tabel Data Acuan.
3. Item yang direvisi tampil di minggu jadwal berlaku, bukan jadwal semula.
4. Irban hanya melihat item dan wilayah irbannya.
5. Tombol Ajukan Revisi dari detail kalender membuka modal yang sama dan tetap mematuhi aturan `AjukanRevisiJadwal`.
6. Test halaman publik Jadwal tetap hijau setelah disesuaikan dengan aturan minggu baru.
7. Cek manual di lebar 360, 768, dan 1280px: tidak ada scroll horizontal di level halaman.

---

## Di luar lingkup
- Grafik batang 3D Sistem B. Kalau dibutuhkan, buat grafik batang 2D Chart.js dari data "Per Jenis Penugasan" sebagai tugas terpisah.
- Agenda non-penugasan seperti "Lebaran". Kalau nanti dibutuhkan, buat tabel agenda terpisah yang tidak ikut dihitung sebagai rencana. Jangan simpan agenda sebagai item PKPT.
- Pemasangan kalender di halaman Admin dan Pimpinan.
