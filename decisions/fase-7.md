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
- Notifikasi (Task 2), UI (Task 3), dan `KomentarSeeder` (Task 3, bersama UI) belum dikerjakan di Task 1
