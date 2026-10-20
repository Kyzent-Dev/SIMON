# Keputusan Fase 7

## Fase 7, Task 1
- Komentar tidak dapat diubah atau dihapus (immutable): tidak ada aksi edit/hapus, dan model tidak dipakai untuk itu. Menghindari perselisihan soal isi utas yang sudah dibaca pihak lain
- Admin hanya melihat utas: tidak punya satu pun dari `komentari`, `balasKomentar`, `tutupPermintaan`
- Komentar boleh ditulis di setiap status SPT yang bisa dilihat pengguna (termasuk Selesai, Ditolak, nomor dibatalkan) karena komentar tidak pernah mengubah data SPT. Draf tidak terlihat oleh pimpinan, Bagian Umum, dan Admin, sehingga tertutup juga
- Pimpinan mana pun boleh menutup permintaan penjelasan, tidak hanya peminta (Inspektur dan Sekretaris berbagi pengawasan)
- Menutup permintaan tidak mengirim notifikasi apa pun
- Balasan tetap boleh ditulis pada permintaan yang sudah ditutup
- `TutupPermintaan::bisa(Komentar $root): ?string` menerima root. `handle()` mencari komentar lewat `$spt->komentar()->whereKey($komentar_id)` (id dari klien selalu dibatasi pada SPT); id tidak ditemukan memberi galat `komentar`: `Permintaan penjelasan tidak ditemukan.` (untuk balasan pesannya `Komentar yang dibalas tidak ditemukan.`). Komentar yang bukan root dianggap "bukan permintaan penjelasan"
- `BalasKomentar` hanya menerima `parent_id` berupa root (`parent_id` null) pada SPT yang sama; balasan atas balasan atau id dari SPT lain ditolak. Input "Diinput oleh" diabaikan (disimpan null) untuk peran selain Irban
- Test Policy "pimpinan tidak bisa mengubah data SPT": ke-10 aksi tulis SPT dipanggil sebagai pimpinan pada SPT berstatus yang relevan, semuanya melempar `AuthorizationException` (tidak ada yang melempar `ValidationException` lebih dulu) dan baris `spt` serta jumlah riwayat, dokumen, kendala, personel tidak berubah. `CatatNomorManual` tidak menerima SPT (hanya `kelolaNomor` pada kelas), jadi dipanggil dengan data penugasan baru

## Fase 7, Task 2
- `KomentarBaru` dan `BalasanPenjelasan` berbagi `NotifikasiKomentar` (abstrak, turunan `NotifikasiSimon`) yang menyimpan nilai (penulis, konteks, url, catatan) saat dibuat, sehingga email berantrian tidak membaca ulang utas
- `PenerimaNotifikasi::utasKomentar` mengecualikan hanya penulis komentar (berdasar id); akun Irban milik SPT selalu menjadi pihak utas. Peminta hanya menerima `BalasanPenjelasan` (tidak juga `KomentarBaru`) atas balasan orang lain di utas Minta Penjelasan miliknya; balasan peminta sendiri hanya mengirim `KomentarBaru`
- `catatan` memakai `Str::limit($isi, 200)`, jadi panjang maksimal 203 karakter dengan tanda `...`
- Notifikasi dikirim dari aksi setelah komentar tersimpan (aksi tulis tidak memakai `DB::transaction`, jadi tidak ada yang terkirim bila validasi atau policy melempar)
- Properti `NotifikasiKomentar` bertipe `protected`: serialisasi antrian (`SerializesModels`) melewatkan properti `private` milik kelas induk sehingga payload kosong saat dikirim sungguhan

## Fase 7, Task 3
- Bagian Komentar adalah komponen anak `App\Livewire\Spt\Komentar` (bukan bagian dari `Detail`), dibungkus `order-6 lg:order-none` setelah Dokumen di kolom kiri. Akarnya `<section aria-labelledby="judul-komentar">`: kartu `x-ui.card` langsung sebagai akar gagal karena penanda `<!--[if BLOCK]>` di awal HTML membuat Livewire tidak menemukan tag akar
- Pemindahan fokus memakai event `fokus-ke` yang didengar oleh akar `Detail` (listener `.window`), sehingga komponen anak tidak menambah listener sendiri
- Galat `isi` dari `BalasKomentar` ditampilkan pada kolom `balasan` (id textarea balasan), agar tidak bentrok dengan kolom `isi` milik composer di halaman yang sama
- `balasUntuk` (root yang formulir balasnya terbuka) sengaja tidak `#[Locked]`: nilainya berubah dari klien; aksi `BalasKomentar` memeriksa ulang terhadap SPT ini, dan `bukaBalas()` mencarinya lewat `$spt->komentar()->whereNull('parent_id')`
- Keterangan `Menunggu balasan Irban` dan tombol `Tutup permintaan` sama-sama diturunkan dari `TutupPermintaan::bisa()` untuk root Terbuka (null = tombol untuk pimpinan; selain null = keterangan), tanpa menghitung ulang aturan di komponen
- Tombol `Balas` berfungsi sebagai toggle (`aria-expanded`): ditekan saat formulirnya terbuka sama dengan `Batal`
- Tidak ada dialog konfirmasi untuk `Tutup permintaan` (keputusan pengendali)

## Fase 7, Task 4
- `KomentarSeeder` (dev saja, dipanggil `DatabaseSeeder` setelah `SptSeeder` dan sebelum `NotifikasiSeeder`, idempoten: berhenti bila dua SPT demo sudah punya komentar) menulis baris langsung tanpa notifikasi, dengan waktu berurutan. Memakai dua SPT Diterima bernomor pertama milik Irban I: SPT pertama berisi utas biasa (pimpinan, balasan Irban dengan "Diinput oleh" anggota Irban I, balasan Bagian Umum) dan satu Minta Penjelasan Terbuka tanpa balasan Irban; SPT kedua berisi Minta Penjelasan Selesai (dibalas Irban, ditutup pimpinan)

## Fase 7, Final review
- Teks `catatan` di email (`resources/views/mail/notifikasi.blade.php`, semua jenis notifikasi) diberi backslash pada tanda baca Markdown (`\ ` * _ { } [ ] ( ) # + ! | ~ : -`) sehingga tautan dan format buatan pengguna tidak jadi aktif; `< > &` cukup lewat escape HTML Blade. Bagian teks polos dirender dari HTML, jadi tidak ada backslash yang tampak (dites). `:` ikut di-escape agar URL telanjang tidak diubah menjadi tautan otomatis (GFM autolink)
- Balasan pimpinan/Bagian Umum kedua atas permintaan penjelasan tetap mengirim `BalasanPenjelasan` ke peminta ("Balasan atas permintaan penjelasan dari {nama}") walau penulisnya bukan Irban. Perilaku ini diketahui dan dipertahankan sesuai plan (routing berdasarkan penulis balasan, bukan perannya)
- `blokirTutup` di `App\Livewire\Spt\Komentar` hanya dihitung bila pengguna boleh `tutupPermintaan` dan untuk root Terbuka; aturannya tetap di `TutupPermintaan::bisa()`
- Alur PRD §14 butir 5 dites utuh di `AlurPenjelasanTest` (kanal database dan mailer array sungguhan, antrean sinkron), ditambah penolakan menutup dengan id balasan
