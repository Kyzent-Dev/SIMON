# PRD dan Rencana Implementasi: Sistem Pengajuan dan Monitoring SPT
**Inspektorat Kabupaten Muara Enim**

| | |
|---|---|
| Versi | 1.0 (lingkup Semester 7) |
| Penyusun | Andhika Pratama |
| Tanggal | 18 Oktober 2026 |
| Pembaca | AI Agent pelaksana implementasi, pembimbing lapangan |
| Nama kerja aplikasi | SIMON-SPT (boleh diganti) |

> **Instruksi untuk AI Agent.** Dokumen ini menjelaskan *apa* yang harus dibangun dan *aturan bisnisnya*. Keputusan teknis detail boleh kamu ambil sendiri selama mengikuti Bagian 11 (Tech Stack) dan Bagian 13 (Konvensi). Butir di Bagian 15 (Pertanyaan Terbuka) **belum pasti**: implementasikan sebagai pengaturan yang bisa diubah admin, jangan sebagai nilai yang ditanam di kode. Kerjakan per fase (Bagian 12). Setiap fase selesai saat semua kriteria penerimaannya lulus dan test-nya hijau.

---

## 1. Ringkasan

Inspektorat memproses Surat Perintah Tugas (SPT) lewat Google Form, stambook fisik, dan dua sistem buatan pegawai (Sistem A untuk input, Sistem B untuk rekap dan monitoring). Pegawai mengetik data yang sama tiga kali, mengetik nama anggota satu per satu, dan pimpinan baru bisa memantau setelah Sistem B diperbarui manual.

Aplikasi ini menggantikan Google Form, Sistem A, dan Sistem B dengan satu website Laravel:
1. Irban mengajukan nomor SPT, Bagian Umum memutuskan, dan sistem memberi nomor otomatis dari stambook digital.
2. Status SPT berjalan otomatis berdasarkan tanggal. Irban hanya menandai Selesai dengan upload cover LHP.
3. Pimpinan memantau, memberi arahan, dan menyetujui revisi jadwal.
4. Rekap per triwulan terbentuk otomatis dan bisa diunduh ke PDF dan Excel.
5. Publik melihat jadwal PKPT dan realisasi SPT Reguler yang sudah selesai lewat landing page.

## 2. Tujuan dan Ukuran Keberhasilan

| Tujuan | Ukuran |
|---|---|
| Hapus input ganda | Data SPT diinput satu kali. Tidak ada input ulang ke sistem lain |
| Nomor SPT tanpa duplikat | 0 nomor kembar, termasuk saat dua pengajuan disetujui bersamaan |
| Monitoring real time | Pimpinan melihat status semua SPT tanpa menunggu update manual |
| Input personel cepat | Mengisi 6 personel butuh di bawah 30 detik lewat checklist tim |
| Rekap otomatis | Rekap triwulan tersedia dalam PDF dan Excel tanpa olah manual |

## 3. Ruang Lingkup

### 3.1 Masuk lingkup (Semester 7)
- Landing page publik: Home, Jadwal PKPT, Realisasi SPT.
- Autentikasi dan 4 peran internal.
- Master data: pegawai, irban, tim, objek pemeriksaan, jenis penugasan, klasifikasi SPT, konten landing page.
- PKPT per tahun beserta revisi jadwal.
- Pengajuan nomor SPT, keputusan Bagian Umum, stambook digital.
- Data SPT, personel per SPT, status otomatis, penanda (flag).
- Komentar per SPT dan persetujuan revisi jadwal oleh pimpinan.
- Notifikasi di website (ikon lonceng) dan email.
- Dashboard internal, rekap triwulan, ekspor PDF dan Excel.
- Impor data lama dari Sistem A/B lewat template Excel.
- Log aktivitas.

### 3.2 Di luar lingkup (Semester 8, jangan dibangun)
- Pembuatan dokumen SPT oleh sistem dan tanda tangan elektronik.
- OCR pembacaan SPT hasil scan.
- Sistem pendukung keputusan untuk kendala.
- Monitoring tindak lanjut rekomendasi hasil pemeriksaan.

Rancang skema data agar fitur Semester 8 bisa ditambahkan tanpa migrasi besar (lihat catatan di Bagian 8).

## 4. Glosarium

| Istilah | Arti |
|---|---|
| Irban | Inspektur Pembantu. Unit pengawasan (Irban I sampai V) sekaligus jabatan kepalanya |
| PPJ | Pembantu Penanggung Jawab. Dijabat kepala Irban. Tercantum di SPT pada bagian "Kepada" |
| Supervisor/Dalnis | Supervisor/Pengendali Teknis. Satu orang per tim |
| Ketua Tim, Anggota Tim | Peran personel dalam satu SPT |
| PKPT | Program Kerja Pengawasan Tahunan. Ditetapkan lewat Keputusan Bupati. Berisi jadwal penugasan per irban |
| SPT | Surat Perintah Tugas. Contoh nomor: `700/751/INSPEKTORAT-I/SPT/2024` |
| Stambook | Buku register nomor SPT. Nomor baru = nomor terakhir + 1 |
| LHP | Laporan Hasil Pemeriksaan |
| Klasifikasi SPT | REGULER, KHUSUS, DIKLAT, LUAR DAERAH, LAIN-LAIN |
| Triwulan (TW) | TW I Jan-Mar, TW II Apr-Jun, TW III Jul-Sep, TW IV Okt-Des |

## 5. Pengguna dan Hak Akses

| Peran | Akun | Hak utama |
|---|---|---|
| Publik | Tanpa login | Lihat Home, Jadwal PKPT, Realisasi (data terbatas, lihat 7.9) |
| Irban | **Satu akun per irban** (bersama) | Ajukan nomor, isi dan ubah SPT irbannya, upload dokumen, tandai Selesai, isi kendala, ajukan revisi jadwal, balas komentar, lihat rekap irbannya |
| Bagian Umum | Satu atau lebih akun | Kotak masuk pengajuan, terima/revisi/tolak dengan komentar, kelola stambook digital, catat nomor manual, batalkan nomor |
| Pimpinan | Akun per orang (Inspektur, Sekretaris) | Lihat semua data, komentar, minta penjelasan, setujui/tolak revisi jadwal, ekspor rekap. **Tidak bisa** mengubah data SPT |
| Admin | Akun per orang | Kelola user, master data, PKPT, konten landing page, pengaturan sistem, impor data |

**Aturan akun bersama Irban:**
- Setiap aksi tulis (pengajuan, ubah SPT, ubah status, upload, komentar) wajib mengisi **"Diinput oleh"** dengan memilih pegawai dari daftar anggota irban tahun berjalan. Simpan di log aktivitas.
- Setiap akun irban punya satu email notifikasi utama dan daftar email tembusan opsional.
- Admin bisa mewajibkan semua akun irban mengganti kata sandi (dipakai setiap awal tahun saat susunan anggota berubah). Akun yang diwajibkan diarahkan ke halaman ganti kata sandi setelah login.

## 6. Alur Utama

### 6.1 Pengajuan nomor sampai SPT selesai
```
Irban isi form pengajuan
   -> [Menunggu Nomor] -> notifikasi ke Bagian Umum
Bagian Umum memutuskan:
   - Perlu Revisi (komentar wajib) -> Irban perbaiki -> kembali Menunggu Nomor
   - Ditolak (komentar wajib) -> selesai
   - Diterima -> sistem ambil nomor berikutnya (terkunci) -> notifikasi ke Irban
Sistem menghitung status dari tanggal:
   [Siap Dilaksanakan] -> [Pelaksanaan] -> [Penyusunan Laporan]
Irban upload SPT final bertanda tangan (kapan saja setelah nomor terbit)
Irban upload cover LHP + nomor dan tanggal LHP -> [Selesai]
```

### 6.2 Revisi jadwal
Irban mengajukan revisi tanggal untuk satu item PKPT beserta alasan. Pimpinan menyetujui atau menolak dengan komentar. Kalau disetujui, sistem memakai jadwal revisi untuk menghitung kesesuaian. Jadwal asli tetap tersimpan dan tampil di riwayat.

### 6.3 Komentar dan minta penjelasan
Pimpinan menulis komentar di halaman detail SPT. Irban dan Bagian Umum membalas di utas yang sama. Pimpinan bisa menandai komentar sebagai **"Minta Penjelasan"**. Permintaan itu tetap terbuka sampai Irban membalas dan pimpinan menutupnya. Dashboard pimpinan menampilkan jumlah permintaan yang masih terbuka.

## 7. Kebutuhan Fungsional

### 7.1 Landing page publik

**Header** (sticky, responsif):
- Kiri: logo dan nama instansi.
- Tengah: Home, Jadwal, Realisasi.
- Kanan: ikon login. Kalau user sudah login, ikon berubah menjadi tautan ke dashboard.
- Mobile: menu tengah masuk ke menu hamburger. Logo dan ikon login tetap terlihat.

**Home:**
- Hero carousel. Setiap slide berisi gambar latar, judul, kutipan atau penjelasan singkat, dan nama/jabatan pemilik kutipan (opsional). Admin mengelola slide (tambah, ubah, urutkan, aktif/nonaktif). Carousel berganti otomatis, bisa digeser di mobile, punya tombol jeda, dan menghormati `prefers-reduced-motion`.
- Ringkasan angka tahun berjalan: jumlah item PKPT, jumlah SPT Reguler selesai, persentase realisasi.
- Tautan ke halaman Jadwal dan Realisasi.

**Footer:** nomor telepon, email, Instagram, alamat kantor, dan peta lokasi opsional. Admin mengelola semua isinya di menu Pengaturan.

### 7.2 Halaman Jadwal PKPT (publik)
Tujuan: orang awam paham dalam beberapa detik siapa mengerjakan apa dan kapan.
- Filter: tahun, irban.
- **Tampilan Timeline (default desktop):** baris = jenis penugasan, kolom = 12 bulan dengan sub-kolom minggu 1 sampai 5. Setiap item tampil sebagai batang berwarna. Warna mengikuti jenis penugasan dan legenda tampil di atas. Batang item yang direvisi memperlihatkan jadwal asli sebagai garis putus-putus.
- **Tampilan Bulanan (default mobile):** kartu per bulan berisi daftar penugasan per minggu, mirip tabel PKPT lama tapi lebih bersih.
- Pengguna bisa berpindah antara dua tampilan.
- Klik item untuk melihat detail: jenis penugasan, irban, rentang tanggal, wilayah (level kecamatan atau nama OPD), dan progres realisasi ("3 dari 4 SPT selesai").
- Panel wilayah per irban: daftar kecamatan, dinas/badan, dan BUMD, seperti tabel wilayah lama.
- Hanya PKPT berstatus **Disahkan** yang tampil.

### 7.3 Halaman Realisasi SPT (publik)
- Data: hanya SPT klasifikasi **REGULER**, status **Selesai**, dan terhubung ke item PKPT.
- Filter: tahun, triwulan, bulan, irban, wilayah (kecamatan), jenis penugasan. Filter tersimpan di URL (query string) supaya tautan bisa dibagikan.
- Grafik (semua mengikuti filter):
  - **Garis:** jumlah SPT selesai per bulan, dengan pilihan satu garis per irban.
  - **Lingkaran (donat):** proporsi SPT selesai per irban atau per jenis penugasan. Maksimal 6 potongan. Sisanya digabung menjadi "Lainnya".
  - **Batang:** realisasi dibanding rencana PKPT per irban per triwulan.
- Tabel di bawah grafik: jenis penugasan, irban, wilayah, tanggal pelaksanaan, triwulan. Ada paginasi.
- Kolom yang **tidak boleh** tampil di publik: nama dan NIP personel, nomor SPT, nama objek di bawah level kecamatan (desa, sekolah, puskesmas), kendala, komentar, dan semua dokumen. Admin bisa mengubah visibilitas nomor SPT dan nama objek di Pengaturan, dengan default disembunyikan.

### 7.4 Master data (Admin)
- **Pegawai:** nama bergelar, NIP (18 digit, unik), pangkat, golongan, jabatan, status aktif.
- **Irban:** nama (Irban I sampai V), kode nomor (contoh `INSPEKTORAT-I`), email notifikasi dan tembusan.
- **Irban tahunan:** per tahun, PPJ (pegawai), daftar anggota irban.
- **Tim:** per irban per tahun (Tim 1, Tim 2, ...), anggota beserta peran default (Supervisor/Dalnis, Ketua, Anggota). Validasi: setiap tim punya tepat satu Supervisor/Dalnis dan satu Ketua.
- **Objek pemeriksaan:** hierarkis. Jenis: Kecamatan, Desa, Sekolah, Puskesmas, RSUD, BUMDes, Dinas/Badan, BUMD. Desa, sekolah, puskesmas, dan BUMDes punya induk kecamatan.
- **Wilayah irban per tahun:** daftar kecamatan, dinas/badan, dan BUMD yang menjadi wilayah setiap irban.
- **Jenis penugasan:** nama dan warna untuk tampilan jadwal (contoh: Evaluasi RB dan Reviu DAK, Pengawasan Dana BOS, Reviu LKjIP).
- **Klasifikasi SPT:** 5 nilai tetap beserta aturan (lihat 9.4).
- **Konten landing page:** slide carousel, kontak footer.
- Impor pegawai dari Excel.

### 7.5 PKPT
- Satu PKPT per tahun: nomor dan tanggal SK Bupati, status Draf atau Disahkan.
- Item PKPT: irban, jenis penugasan, tanggal mulai, tanggal selesai, keterangan (contoh "1 tim 1 minggu 2 sekolah"), target jumlah SPT (opsional).
- Revisi jadwal per item (alur 6.2).
- Impor item PKPT dari Excel.
- Saat PKPT masih Draf, pengajuan SPT Reguler yang merujuk PKPT itu memunculkan peringatan "PKPT belum disahkan". Admin menentukan di Pengaturan apakah peringatan ini juga memblokir pengajuan.

### 7.6 Pengajuan nomor SPT (Irban)
Field form:
- Diinput oleh (wajib)
- Klasifikasi (wajib)
- Item PKPT (wajib untuk Reguler sesuai pengaturan, kosong untuk Non-PKPT)
- Jenis penugasan (otomatis dari item PKPT, atau pilih manual)
- Objek pemeriksaan (bisa lebih dari satu)
- Periode yang diperiksa (teks pendek, contoh "Tahun Anggaran 2023 s.d. saat pemeriksaan")
- Uraian "Untuk" (sistem mengusulkan teks dari field di atas, Irban bisa mengedit)
- Rencana tanggal mulai dan selesai (sistem menghitung lama hari)
- PPJ (otomatis dari irban tahunan, bisa diganti)
- **Personel:** centang satu atau lebih tim. Sistem menampilkan semua anggota tim tersebut dengan peran default. Irban bisa menghapus centang per orang, mengubah peran per orang, mengatur urutan, dan menekan "Tambahkan anggota lainnya" untuk mencari pegawai mana pun (pencarian nama atau NIP). Validasi: tepat satu Ketua Tim, maksimal satu Supervisor/Dalnis, tidak ada pegawai ganda.
- Lampiran draf SPT (PDF, opsional, maksimal 5 MB)
- Catatan untuk Bagian Umum (opsional)

Irban bisa menyimpan draf sebelum mengirim. Setelah dikirim, form terkunci kecuali Bagian Umum meminta revisi.

### 7.7 Bagian Umum dan stambook digital
- Kotak masuk pengajuan dengan filter status dan irban.
- Aksi Terima, Perlu Revisi, Tolak. Komentar wajib untuk Revisi dan Tolak.
- Saat Terima, sistem memberi nomor urut berikutnya dan menyusun nomor lengkap (lihat 9.1). Bagian Umum melihat pratinjau nomor sebelum konfirmasi.
- **Catat Nomor Manual:** untuk SPT mendesak yang terbit di luar sistem. Bagian Umum mengisi data minimum dan sistem tetap mengambil nomor berikutnya dari urutan yang sama.
- **Batalkan Nomor:** status SPT menjadi Dibatalkan dengan alasan wajib. Nomor tidak pernah dipakai ulang.
- Halaman Register (stambook digital): semua nomor per tahun beserta status. Bisa diekspor ke PDF dan Excel.
- Pengaturan awal: nomor terakhir dari stambook fisik saat sistem mulai dipakai.

### 7.8 Detail SPT dan status
- Halaman detail: semua data, personel, riwayat status, dokumen, utas komentar, log aktivitas.
- Irban bisa mengubah tanggal realisasi setelah nomor terbit. Alasan wajib diisi dan tersimpan di riwayat.
- Upload SPT final bertanda tangan (PDF).
- Tandai Selesai: upload cover LHP (PDF), nomor LHP, tanggal LHP. Kebutuhan dokumen bergantung klasifikasi (9.4).
- Isi kendala: kategori (pilih dari daftar) dan uraian. Wajib saat SPT ditandai Tidak Sesuai PKPT.
- Status dan penanda dihitung sesuai Bagian 9.2 dan 9.3.

### 7.9 Dashboard dan rekap internal
- **Dashboard Irban:** SPT irbannya per status, daftar yang butuh tindakan (revisi dari Bagian Umum, penyusunan laporan terlambat, permintaan penjelasan terbuka).
- **Dashboard Pimpinan:** kartu angka (On Process, Selesai, Terlambat, Tidak Sesuai PKPT, permintaan penjelasan terbuka, revisi jadwal menunggu persetujuan), grafik realisasi vs rencana per irban, tabel monitoring semua SPT dengan filter.
- **Dashboard Bagian Umum:** pengajuan menunggu keputusan, nomor terbit bulan ini.
- **Tabel Monitoring** (pengganti Sistem B): kolom mengikuti format lama: Jenis Penugasan, Periode Penugasan (PKPT), Realisasi Periode, Kesesuaian dengan Jadwal PKPT, Status, Permasalahan/Kendala. Baris dikelompokkan per item PKPT, dengan banyak SPT di bawah satu item.
- **Rekap Triwulan:** per irban dan gabungan, jumlah rencana vs realisasi, per klasifikasi, per jenis penugasan.
- **Ekspor:** setiap tabel monitoring dan rekap bisa diunduh ke **Excel (.xlsx)** dan **PDF**. Ekspor mengikuti filter yang aktif. Ekspor besar diproses di antrean dan user mendapat notifikasi saat file siap.

### 7.10 Notifikasi
Setiap notifikasi masuk ke **website** (ikon lonceng dengan titik merah dan jumlah belum dibaca, daftar notifikasi, tandai sudah dibaca) dan **email**. Email dikirim lewat antrean.

| Kejadian | Penerima |
|---|---|
| Pengajuan baru dikirim | Bagian Umum |
| Pengajuan diterima (beserta nomor), perlu revisi, atau ditolak | Irban pengaju |
| Nomor dibatalkan | Irban terkait |
| Komentar baru atau minta penjelasan | Pihak dalam utas |
| Balasan atas minta penjelasan | Pimpinan peminta |
| Revisi jadwal diajukan | Pimpinan |
| Revisi jadwal disetujui atau ditolak | Irban pengaju |
| SPT masuk penanda Terlambat | Irban terkait (pimpinan lewat ringkasan) |
| Pengingat: SPT final belum diupload 3 hari setelah tanggal mulai | Irban terkait |
| Ringkasan mingguan (Senin pagi) | Pimpinan |
| File ekspor siap | Peminta |

### 7.11 Impor data lama
Admin mengunduh template Excel, mengisinya dengan data dari Sistem A/B, lalu mengunggahnya. Sistem memvalidasi setiap baris dan menampilkan pratinjau beserta daftar error sebelum menyimpan. Data impor ditandai sumber "Impor".

### 7.12 Log aktivitas
Sistem mencatat setiap perubahan data penting: siapa (akun dan "Diinput oleh"), kapan, apa, nilai lama dan baru. Admin dan Pimpinan bisa melihat log. Tidak ada yang bisa menghapus log.

## 8. Model Data (ringkas)

| Tabel | Isi pokok |
|---|---|
| `users` | nama, email, password, role, irban_id (untuk role Irban), wajib_ganti_password |
| `pegawai` | nama_bergelar, nip, pangkat, golongan, jabatan, aktif |
| `irban` | nama, kode_nomor, email_notifikasi, email_tembusan (json) |
| `irban_tahunan` | irban_id, tahun, ppj_pegawai_id |
| `irban_anggota` | irban_tahunan_id, pegawai_id |
| `tim` | irban_id, tahun, nama |
| `tim_anggota` | tim_id, pegawai_id, peran_default |
| `objek` | nama, jenis, parent_id |
| `irban_wilayah` | irban_id, tahun, objek_id |
| `jenis_penugasan` | nama, warna |
| `pkpt` | tahun, nomor_sk, tanggal_sk, status |
| `pkpt_item` | pkpt_id, irban_id, jenis_penugasan_id, tanggal_mulai, tanggal_selesai, keterangan, target_spt |
| `pkpt_item_revisi` | pkpt_item_id, tanggal baru, alasan, status persetujuan, disetujui_oleh, komentar |
| `nomor_counter` | tahun, scope, nomor_terakhir (dikunci saat ambil nomor) |
| `spt` | nomor_urut, nomor_lengkap, tahun, tanggal_spt, klasifikasi, irban_id, pkpt_item_id (nullable), jenis_penugasan_id, periode_diperiksa, uraian_untuk, tanggal_mulai, tanggal_selesai, status_pengajuan, nomor_lhp, tanggal_lhp, sumber (sistem/manual/impor), diinput_oleh |
| `spt_objek` | spt_id, objek_id |
| `spt_personel` | spt_id, pegawai_id, peran, urutan, sumber (tim/tambahan) |
| `spt_dokumen` | spt_id, jenis (draf/final/cover_lhp), path, diunggah_oleh |
| `spt_kendala` | spt_id, kategori, uraian |
| `spt_riwayat` | spt_id, kejadian, nilai lama/baru, alasan, oleh |
| `komentar` | spt_id, parent_id, user_id, diinput_oleh, isi, minta_penjelasan, status_permintaan |
| `notifications` | tabel bawaan Laravel |
| `activity_log` | log aktivitas |
| `landing_slides`, `settings` | konten landing page dan pengaturan sistem |

**Catatan untuk Semester 8:**
- Simpan semua dokumen dengan hash SHA-256 (untuk verifikasi dokumen nanti).
- `spt` menyimpan semua data yang dibutuhkan untuk menyusun dokumen SPT (personel berurutan, uraian, tanggal).
- `spt_kendala.kategori` wajib dari daftar tetap supaya datanya bisa diolah.
- Siapkan kolom `penandatangan_pegawai_id` dan `status_penandatangan` (Definitif/Plt/Plh) di `spt`, boleh kosong.

## 9. Aturan Bisnis

### 9.1 Penomoran
- Format: `{kode_arsip}/{nomor_urut}/{kode_irban}/SPT/{tahun}`. Default `kode_arsip` = `700`. Simpan format sebagai template di Pengaturan.
- Ambil nomor di dalam transaksi database dengan `lockForUpdate()` pada baris `nomor_counter`. **Jangan** memakai `MAX(nomor_urut) + 1`.
- Scope urutan (global untuk semua irban atau per irban) dan reset per tahun diatur di Pengaturan. Default: global, reset setiap tahun.
- Nomor yang sudah terbit tidak boleh berubah atau dipakai ulang. Pembatalan hanya mengubah status.
- Tahun di nomor mengikuti tanggal keputusan Bagian Umum.

### 9.2 Status (dihitung)
| Status | Kondisi |
|---|---|
| Draf | Disimpan, belum dikirim |
| Menunggu Nomor | Dikirim, belum diputuskan |
| Perlu Revisi | Bagian Umum minta revisi |
| Ditolak | Bagian Umum menolak |
| Siap Dilaksanakan | Nomor terbit dan hari ini < tanggal_mulai |
| Pelaksanaan | tanggal_mulai <= hari ini <= tanggal_selesai |
| Penyusunan Laporan | Hari ini > tanggal_selesai dan belum Selesai |
| Selesai | Syarat selesai sesuai klasifikasi terpenuhi |
| Dibatalkan | Nomor dibatalkan |

- Kelompok tampilan: **On Process** = Siap Dilaksanakan, Pelaksanaan, Penyusunan Laporan. **Selesai** = Selesai.
- Hitung status dari tanggal saat query (accessor atau scope). Jangan bergantung pada cron untuk mengubah status. Cron harian hanya untuk penanda Terlambat dan notifikasi.
- Semua tanggal memakai zona waktu `Asia/Jakarta`.

### 9.3 Penanda (flag)
- **Tidak Sesuai PKPT:** tanggal realisasi tidak berada di dalam rentang item PKPT (memakai revisi yang disetujui kalau ada). SPT tanpa item PKPT tidak dinilai (tampil "Non-PKPT").
- **Terlambat:** status Penyusunan Laporan lebih dari N hari. N di Pengaturan, default 14.
- **SPT Final Belum Diupload:** nomor terbit, tanggal mulai sudah lewat, dokumen final belum ada.

### 9.4 Klasifikasi SPT
| Klasifikasi | Wajib item PKPT | Syarat Selesai | Tampil di publik |
|---|---|---|---|
| REGULER | Ya (bisa diubah di Pengaturan) | Cover LHP + nomor + tanggal LHP | Ya, jika Selesai |
| KHUSUS | Tidak | Cover LHP + nomor + tanggal LHP | Tidak |
| DIKLAT | Tidak | Tanpa dokumen, cukup konfirmasi | Tidak |
| LUAR DAERAH | Tidak | Laporan perjalanan opsional | Tidak |
| LAIN-LAIN | Tidak | Dokumen opsional | Tidak |

Simpan aturan ini di tabel atau konfigurasi supaya admin bisa mengubahnya tanpa ubah kode.

### 9.5 Triwulan
Default: SPT masuk triwulan berdasarkan `tanggal_selesai` pelaksanaan. Pilihan lain (`tanggal_mulai` atau `tanggal_lhp`) tersedia di Pengaturan.

### 9.6 Visibilitas data publik
Query publik **wajib** lewat satu kelas atau scope khusus (contoh `PublicRealisasiQuery`) yang memfilter REGULER + Selesai + punya item PKPT dan hanya memilih kolom yang aman. Halaman publik tidak boleh memakai model atau query internal secara langsung.

## 10. Kebutuhan Non-Fungsional

- **Responsif:** semua halaman nyaman di layar 360 px sampai desktop. Tabel lebar berubah menjadi kartu atau bisa digeser horizontal di dalam kontainernya sendiri.
- **Bahasa:** seluruh antarmuka dalam Bahasa Indonesia. Format tanggal `d F Y` dalam Bahasa Indonesia.
- **Keamanan:**
  - Otorisasi lewat Policy untuk setiap model. Irban hanya bisa mengakses data irbannya.
  - Batasi percobaan login (rate limit).
  - Simpan file di disk privat dan unduh lewat route yang dicek otorisasinya.
  - Validasi upload: hanya PDF, ukuran maksimal di Pengaturan.
  - Tidak ada NIP atau nama personel di halaman publik.
- **Kinerja:** halaman publik dan dashboard memuat di bawah 2 detik untuk data 5 tahun (sekitar 5.000 SPT). Cache agregat grafik publik dan hapus cache saat SPT berubah status.
- **Aksesibilitas:** kontras warna memenuhi WCAG AA, navigasi keyboard, label form, teks alternatif gambar, grafik punya tabel data sebagai alternatif.
- **Hosting:** kompatibel dengan VPS atau shared hosting yang mendukung PHP 8.3+, cron, dan queue worker (driver antrean `database`).
- **Cadangan:** perintah backup database dan file terjadwal harian.

## 11. Tech Stack

| Lapisan | Pilihan |
|---|---|
| Framework | Laravel 13 (PHP 8.3 atau lebih baru) |
| Frontend | Blade + Livewire + Alpine.js + Tailwind CSS |
| Database | MySQL 8 atau MariaDB 10.6+ |
| Grafik | Chart.js |
| Ekspor Excel | `maatwebsite/excel` 4.x (mendukung Laravel 13) |
| Ekspor PDF | `barryvdh/laravel-dompdf` |
| Log aktivitas | `spatie/laravel-activitylog` |
| Antrean | driver `database` + `php artisan queue:work` |
| Email | SMTP lewat konfigurasi `.env` |
| Test | Pest atau PHPUnit |

> AI Agent: sebelum memasang paket, cek kompatibilitas versinya dengan Laravel 13 di Packagist. Kalau ada paket yang tidak kompatibel, pilih alternatif setara dan catat alasannya di `DECISIONS.md`.

**Arah desain:**
- Kesan resmi tapi modern. Warna utama diambil dari logo Pemkab Muara Enim, dengan satu warna aksen.
- Satu skema warna jenis penugasan dipakai konsisten di jadwal, grafik, dan badge.
- Warna status tetap: abu (Draf), kuning (Menunggu), oranye (Perlu Revisi), merah (Ditolak/Terlambat), biru (On Process), hijau (Selesai).
- Mode terang wajib, mode gelap opsional.

## 12. Langkah Implementasi

Kerjakan berurutan. Setiap fase diakhiri dengan test hijau dan commit.

**Fase 0: Fondasi**
- Buat proyek Laravel 13, pasang Tailwind, Livewire, Alpine, dan paket di Bagian 11.
- Set `APP_TIMEZONE=Asia/Jakarta` dan `APP_LOCALE=id`.
- Buat layout publik dan layout internal (sidebar, header dengan lonceng).
- *Selesai jika:* aplikasi berjalan, halaman kosong publik dan internal tampil responsif.

**Fase 1: Autentikasi dan peran**
- Login, logout, lupa kata sandi, wajib ganti kata sandi.
- Role enum (Admin, Pimpinan, Irban, Bagian Umum) dan Policy dasar.
- Seeder satu akun per role.
- *Selesai jika:* setiap role diarahkan ke dashboard-nya, dan akses lintas role ditolak (ada test).

**Fase 2: Master data**
- CRUD pegawai (dengan impor Excel), irban, irban tahunan, tim beserta anggota, objek hierarkis, wilayah irban, jenis penugasan, klasifikasi, pengaturan.
- Seeder: 5 irban, objek contoh dari tabel wilayah Irban I (5 kecamatan, 9 dinas/badan, Perumdam Lematang Enim).
- *Selesai jika:* validasi tim (satu Supervisor/Dalnis, satu Ketua) berjalan dan ada test-nya.

**Fase 3: PKPT**
- CRUD PKPT dan item, impor Excel, status Draf/Disahkan.
- Alur revisi jadwal dengan persetujuan pimpinan.
- *Selesai jika:* revisi disetujui mengubah rentang yang dipakai untuk kesesuaian, dan jadwal asli tetap tersimpan.

**Fase 4: Pengajuan dan penomoran**
- Form pengajuan dengan checklist tim (Livewire).
- Kotak masuk Bagian Umum, keputusan, nomor otomatis terkunci, catat nomor manual, batalkan nomor, halaman register.
- *Selesai jika:* test konkurensi membuktikan tidak ada nomor ganda (contoh: 20 proses mengambil nomor bersamaan menghasilkan 20 nomor unik berurutan), dan nomor batal tidak dipakai ulang.

**Fase 5: SPT, status, dan penanda**
- Halaman detail SPT, ubah tanggal dengan alasan, upload dokumen, tandai Selesai sesuai klasifikasi, kendala.
- Perhitungan status dan penanda (9.2 dan 9.3), riwayat.
- *Selesai jika:* test status untuk setiap kombinasi tanggal lulus (gunakan `travelTo`), dan syarat Selesai per klasifikasi berjalan.

**Fase 6: Notifikasi**
- Notification Laravel dengan channel `database` dan `mail`, komponen lonceng, halaman daftar notifikasi.
- Semua kejadian di 7.10, ringkasan mingguan, pengingat harian lewat scheduler.
- *Selesai jika:* setiap kejadian mengirim notifikasi ke penerima yang benar (test dengan `Notification::fake()`).

**Fase 7: Interaksi pimpinan**
- Utas komentar, minta penjelasan, tutup permintaan.
- *Selesai jika:* pimpinan bisa komentar tetapi tidak bisa mengubah data SPT (test Policy).

**Fase 8: Dashboard, monitoring, rekap, ekspor**
- Dashboard per role, tabel monitoring format lama, rekap triwulan.
- Ekspor Excel dan PDF mengikuti filter, ekspor besar lewat antrean.
- *Selesai jika:* angka rekap cocok dengan data uji yang dihitung manual, dan file Excel/PDF terbuka dengan benar.

**Fase 9: Landing page publik**
- Home (carousel dari CMS, ringkasan angka), Jadwal (timeline dan bulanan), Realisasi (filter, 3 grafik, tabel), footer.
- Kelola slide dan kontak di menu Admin.
- *Selesai jika:* test memastikan SPT non-Reguler, belum Selesai, atau tanpa item PKPT tidak muncul di publik, dan kolom terlarang (9.6) tidak ada di respons HTML.

**Fase 10: Impor data lama dan log**
- Template impor SPT, pratinjau, laporan error. Log aktivitas di semua model penting.
- *Selesai jika:* impor file contoh berhasil dan baris bermasalah ditolak dengan pesan jelas.

**Fase 11: Pengerasan dan deploy**
- Audit Policy, rate limit, validasi upload, indeks database, cache publik, backup terjadwal.
- Panduan deploy (`DEPLOY.md`): kebutuhan server, cron, queue worker, SMTP.
- Data demo untuk presentasi.
- *Selesai jika:* semua test hijau, dan aplikasi berjalan di server staging.

## 13. Konvensi untuk AI Agent

- Nama tabel, kolom, dan model memakai Bahasa Indonesia sesuai Bagian 8. Kode lain mengikuti konvensi Laravel.
- Logika bisnis (penomoran, status, kesesuaian, visibilitas publik) ditaruh di kelas service atau action, bukan di controller atau komponen Livewire.
- Setiap aturan di Bagian 9 punya test.
- Catat keputusan teknis yang menyimpang dari dokumen ini di `DECISIONS.md`.
- Jangan menebak jawaban Bagian 15. Pakai default yang tertulis dan sediakan pengaturannya.

## 14. Pengujian Penerimaan (ringkas)

1. Irban I mengajukan SPT Reguler dengan dua tim, menghapus satu anggota, dan menambah satu pegawai dari Irban II. Data personel tersimpan dengan peran dan urutan yang benar.
2. Bagian Umum menerima dua pengajuan dalam waktu bersamaan. Kedua nomor berurutan dan tidak kembar.
3. SPT dengan tanggal realisasi di luar jadwal PKPT ditandai Tidak Sesuai dan meminta kendala. Setelah revisi jadwal disetujui, penandanya hilang.
4. SPT yang 15 hari di Penyusunan Laporan ditandai Terlambat dan muncul di dashboard pimpinan.
5. Pimpinan meminta penjelasan. Irban menerima notifikasi di lonceng dan email, lalu membalas. Pimpinan menutup permintaan.
6. Rekap TW I diekspor ke Excel dan angkanya sama dengan tabel di layar.
7. Pengunjung tanpa login hanya melihat SPT Reguler Selesai, tanpa nama personel dan nomor SPT.

## 15. Pertanyaan Terbuka (jadikan pengaturan)

| No | Pertanyaan | Default sementara |
|---|---|---|
| 1 | Urutan nomor global semua irban atau per irban? | Global |
| 2 | Nomor reset setiap tahun? | Ya |
| 3 | Kode unit untuk SPT Sekretariat? | `INSPEKTORAT` |
| 4 | Apakah klasifikasi tercantum di nomor SPT? | Tidak |
| 5 | Lama penugasan dihitung hari kalender atau hari kerja? | Hari kalender |
| 6 | Batas hari sebelum Terlambat? | 14 |
| 7 | Dasar penentuan triwulan? | tanggal_selesai |
| 8 | Apakah SPT Reguler selalu terhubung ke item PKPT? | Ya |
| 9 | Syarat Selesai untuk DIKLAT, LUAR DAERAH, LAIN-LAIN? | Lihat 9.4 |
| 10 | Apakah pimpinan menyetujui data realisasi tampil publik, dan sampai level apa? | Level kecamatan, tanpa nomor SPT |
| 11 | Apakah PKPT Draf memblokir pengajuan Reguler? | Hanya peringatan |
| 12 | Daftar kategori kendala | Jadwal PKPT belum disahkan, Bentrok penugasan lain, Personel tidak tersedia, Objek tidak siap, Lainnya |
