# SINTAS

**Sistem Informasi Surat Tugas Pengawasan** — Inspektorat Kabupaten Muara Enim.

SINTAS menggantikan Google Form, stambook fisik, dan dua sistem lama (input serta rekap/monitoring) dengan satu aplikasi web. Data SPT diinput satu kali, nomor SPT terbit otomatis tanpa duplikat, dan pimpinan memantau status secara langsung.

## Fitur utama

- **Pengajuan dan penomoran SPT.** Irban mengajukan nomor, Bagian Umum menerima, meminta revisi, atau menolak. Nomor terbit otomatis dari stambook digital, aman saat dua pengajuan disetujui bersamaan.
- **Status SPT otomatis** berdasarkan tanggal pelaksanaan. Irban hanya menandai Selesai dengan mengunggah cover LHP. Penanda (Tidak Sesuai PKPT, Terlambat, dan lainnya) dihitung sistem.
- **PKPT per tahun** beserta **Perubahan PKPT**: Admin menyusun paket perubahan, Irban dapat mengusulkan, Pimpinan menyetujui atau menolak.
- **Monitoring dan rekap.** Dasbor per peran, monitoring SPT, rekap triwulan dan tahunan, register nomor. Ekspor ke PDF dan Excel; ekspor besar berjalan di antrean.
- **Komentar dan permintaan penjelasan** per SPT, **notifikasi** lonceng dan email (harian dan ringkasan mingguan).
- **Landing page publik**: Beranda, Jadwal PKPT, dan Realisasi SPT. Halaman publik tidak pernah menampilkan nama pegawai, NIP, nomor SPT, objek di bawah kecamatan, kendala, komentar, atau dokumen.
- **Impor data lama** (pegawai, item PKPT, SPT) dari template Excel.
- **Log aktivitas** lengkap (siapa, apa, dari IP mana), termasuk "Diinput oleh" untuk akun Irban bersama.
- **Cadangan otomatis** basis data dan berkas, terenkripsi.

## Peran pengguna

| Peran | Hak utama |
|---|---|
| Publik | Melihat Beranda, Jadwal PKPT, dan Realisasi (data terbatas), tanpa login |
| Irban | Satu akun bersama per irban. Mengajukan nomor, mengisi dan mengubah SPT irbannya, mengunggah dokumen, menandai Selesai, mengusulkan perubahan jadwal |
| Bagian Umum | Kotak masuk pengajuan, keputusan, stambook digital, nomor manual, pembatalan nomor |
| Pimpinan | Melihat semua data, berkomentar, menyetujui atau menolak Perubahan PKPT, mengekspor rekap. Tidak mengubah data SPT secara langsung |
| Admin | Pengguna, master data, PKPT, menyusun Perubahan PKPT, konten landing page, pengaturan, impor |

## Teknologi

- PHP 8.5.12+, Laravel 13, Livewire 4, Alpine.js, Tailwind CSS v4, Chart.js, Vite 8
- `motion` (API vanilla) hanya untuk animasi halaman publik
- `barryvdh/laravel-dompdf` (PDF), `maatwebsite/excel` (Excel), `spatie/laravel-activitylog` (log), `spatie/laravel-backup` (cadangan), `blade-ui-kit/blade-heroicons` (ikon)
- MySQL 8 / MariaDB 10.6+ di produksi; SQLite untuk pengembangan lokal
- Node.js 24.22.1+ hanya untuk build aset

## Struktur kode

| Folder | Isi |
|---|---|
| `app/Models` | Model inti: `Spt`, `Pkpt`, `PkptItem`, `PkptPerubahan`, `Irban`, `Pegawai`, `Tim`, `User`, dan lainnya |
| `app/Actions` | Satu kelas per aksi tulis (Auth, Spt, Pkpt, Perubahan PKPT, Komentar, MasterData, Konten, Profil, Pengguna) |
| `app/Services` | Aturan bisnis: `Penomoran`, `PenandaSpt`, `KesesuaianPkpt`, `MonitoringSpt`, `RekapTriwulan`, `PerubahanPkpt`, `Ekspor`, `Import`, `Publik` |
| `app/Livewire` | Layar per peran: `Admin`, `BagianUmum`, `Irban`, `Pimpinan`, `Publik`, `Spt`, `Notifikasi` |
| `app/Policies`, `app/Enums` | Otorisasi per peran, status SPT dan PKPT |
| `app/Jobs`, `app/Notifications` | Ekspor di antrean, notifikasi lonceng dan email |
| `app/Console/Commands` | Perintah `sintas:buat-admin`, `notifikasi:harian`, `notifikasi:ringkasan-mingguan`, `ekspor:bersihkan` |
| `tests/Feature`, `tests/Unit` | Tes PHPUnit per modul |

## Menjalankan secara lokal

```bash
composer setup              # install, .env, key, migrate, npm install, build
php artisan migrate:fresh --seed   # data demo (hanya lokal, menghapus semua data)
composer dev                # server pengembangan
```

`.env.example` memakai SQLite, antrean `database`, dan email `log`. Ekspor besar dan email membutuhkan worker: `php artisan queue:work`.

Akun demo (kata sandi `password`, hanya di lingkungan non-produksi): `admin`, `pimpinan`, `bagianumum`, `irban1` sampai `irban5`.

## Tes

```bash
composer test
```

## Penjadwal

Pekerjaan terjadwal (zona Asia/Jakarta) terdaftar di `routes/console.php`: penyegaran cache publik, notifikasi harian dan mingguan, pembersihan ekspor, serta cadangan (`backup:clean`, `backup:run`, `backup:monitor`). Di server, jalankan `php artisan schedule:run` setiap menit lewat cron.

## Deploy

Ikuti [DEPLOY.md](DEPLOY.md): kebutuhan server, konfigurasi `.env`, cron, queue worker, SMTP, cadangan dan pemulihan, reverse proxy, mitigasi keamanan PHP, dan daftar pemeriksaan setelah deploy.

Jangan pernah menjalankan `migrate:fresh` di produksi: perintah itu menghapus semua data, termasuk log aktivitas.

## Konvensi

- Antarmuka dalam Bahasa Indonesia; tanggal `d F Y`; zona waktu Asia/Jakarta.
- Status selalu ditampilkan dengan ikon dan label (`x-app.status-badge`, `x-app.flag`), tidak hanya warna.
- Tidak ada animasi pada tabel, formulir, dan layar Bagian Umum; semua animasi menghormati `prefers-reduced-motion`.
