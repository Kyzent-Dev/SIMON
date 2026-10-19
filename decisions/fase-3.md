# Keputusan Fase 3

## Fase 3, Task 1
- `SavePkpt` menolak perubahan tahun selama PKPT Draf masih punya item (item sudah divalidasi terhadap tahun lama); plan tidak menyebut ini.
- `SahkanPkpt::handle(Pkpt, User)`; semua aturan Draf memakai `SavePkpt::ensureDraf()` (pesan di kunci `pkpt`). `SavePkptItem::validated()` bersifat static agar dapat dipakai impor (Task 3).
- `PkptItem::revisiDisetujuiTerakhir()` berupa method query segar (bukan relation) agar tidak basi setelah keputusan revisi. `Pkpt` punya default atribut `status = draf` supaya model baru langsung berstatus Draf.
- Penguncian antar-proses (sahkan vs. tambah item bersamaan) belum ada; mengandalkan pengecekan status per aksi.

## Fase 3, Task 2
- Item PKPT diubah langsung di baris tabel (inline), bukan lewat drawer. Baris tambah dan baris ubah memakai partial sel yang sama (`livewire/admin/partials/pkpt-item-fields`); input dikirim lewat atribut HTML `form` ke `<form>` kosong di luar tabel, jadi Enter menyimpan tanpa `<form>` bersarang di tabel. Hanya satu baris yang bisa diubah sekaligus; field pertamanya langsung mendapat fokus.
- Setelah Disahkan, kolom "Jadwal" diganti "Jadwal berlaku" (rentang efektif dari `KesesuaianPkpt::rentang`, plus durasinya). Bila direvisi, jadwal asli tampil di bawahnya sebagai "semula 3–21 Februari 2026" dengan penanda garis putus-putus kecil yang meniru outline putus-putus di linimasa publik. Tidak ada kolom jadwal asli terpisah. "Riwayat revisi (N)" adalah `<details>` di sel yang sama dan hanya muncul bila item punya revisi.
- Rentang di sel tabel ditulis lengkap ("3 Maret 2026 – 21 Maret 2026", seperti popover jadwal.md); bentuk ringkas ("3–21 Februari 2026") dari `App\Support\Tanggal::rentang()` dipakai untuk "semula", riwayat revisi dan modal hapus. Tanggal tunggal tetap `translatedFormat('d F Y')`.
- Pemetaan ikon/varian `x-app.tag` ada di enum (`StatusPkpt::tag()`, `StatusRevisi::tag()`). Varian revisi memakai pasangan §3.4: Menunggu Persetujuan = accent-700 di atas putih (tepi accent-400 dekoratif), Disetujui = mint-800 di atas mint-100, Ditolak = danger-700 di atas putih.
- Galat aksi PKPT yang bukan galat kolom (status bukan Draf, SK belum diisi, PKPT tanpa item) tampil sebagai toast galat. Modal "Sahkan PKPT" juga sudah menampilkan peringatan sebelum diklik bila SK atau item belum ada.
- `x-ui.page-header` mendapat slot opsional `meta` di bawah judul (tag status + info SK).
- Halaman `admin.pkpt.impor` sementara hanya placeholder "Segera tersedia" (komponen `Admin\PkptImpor`); Task 3 menggantinya.
- Durasi pada baris input tidak dihitung langsung saat mengetik; "Durasi: N hari" tampil setelah item disimpan.
- Tidak ada breadcrumb di halaman PKPT (halaman Livewire lain juga belum memakainya); halaman editor memakai tautan "Kembali ke daftar".

## Fase 3, Task 3
- Aturan item per baris memakai `SavePkptItem::validated()` (tanpa duplikasi aturan). Pesan irban/jenis tidak ditemukan dan format tanggal diganti pesan khusus impor; sel irban/jenis kosong memberi "Irban wajib diisi." / "Jenis penugasan wajib diisi.".
- Sel angka pada kolom tanggal dianggap serial tanggal Excel (disimpan di state Livewire sebagai string digit, dikenali dari bentuknya); string hanya `dd/mm/yyyy` atau `yyyy-mm-dd` yang ketat (tanggal mustahil seperti 31/02 ditolak). `target_spt` kosong menjadi null.
- Duplikat = irban + jenis + tanggal mulai + tanggal selesai (keterangan dan target diabaikan), terhadap item yang sudah ada maupun baris sebelumnya di berkas; hanya baris tanpa galat yang dihitung. Duplikat berstatus peringatan dan dilewati bila "Lewati duplikat" dicentang (default); tombol impor menghitung sesuai.
- `ImportPkptItems` memvalidasi ulang tiap baris dan memeriksa status Draf di dalam transaksi; PKPT yang disahkan setelah pratinjau menolak impor (toast galat, tidak ada item tersimpan). Halaman PKPT Disahkan hanya menampilkan peringatan, tanpa stepper dan kontrol unggah.
- Pada baris dengan tanggal tak terbaca, kolom Jadwal pratinjau menampilkan "—" (nilai mentah tidak disimpan di data baris); pesan galatnya tetap jelas.

## Fase 3, Task 4
- `AjukanRevisiJadwal` memeriksa status Disahkan sebelum policy `create` (yang juga mensyaratkan Disahkan), supaya Irban mendapat pesan "Revisi hanya untuk PKPT yang sudah disahkan." alih-alih 403. Pesan yang tidak ada di plan: "Tanggal mulai/selesai wajib diisi." (aturan tanggal dipakai bersama `SavePkptItem::tanggal()`), "Alasan revisi maksimal 1000 karakter.", dan untuk penginput yang bukan PPJ/anggota aktif irban itu tahun berjalan: "Penginput harus PPJ atau anggota aktif Irban ini tahun 2026.".
- `PutuskanRevisiJadwal` memeriksa "Revisi ini sudah diputuskan." (di bawah kunci baris) sebelum syarat komentar penolakan.
- Daftar dan aturan "Diinput oleh" ada di `App\Support\DiinputOleh` (pilihan, label "Nama · …1234", sesi `diinput_oleh.{irban_id}` disimpan hanya setelah tulis berhasil, prefill hanya bila pegawai itu masih valid). Tahun = `now('Asia/Jakarta')->year`, terlepas dari tahun PKPT yang dilihat.
- `x-app.diinput-oleh` di modal berada di footer modal (login.md §5), selebar penuh di atas tombol; combobox-nya membuka daftar ke atas (`drop-up`). Chip memanggil `gantiPenginput()` milik komponen Livewire induk; setelah "Ganti", combobox langsung mendapat fokus. Tombol konfirmasi: "Ajukan sebagai {nama}", atau "Ajukan revisi" selama penginput belum dipilih. Bila komposisi irban tahun berjalan belum ada, tampil peringatan agar Admin mengisi Komposisi Irban Tahunan; pengajuan tetap ditolak oleh validasi aksi.
- Setelah pengajuan berhasil, fokus pindah ke teks alasan "Masih ada revisi yang menunggu persetujuan." pada baris/kartu item itu (`tabindex=-1`, event `revisi-terkirim`), karena tombol pemicunya menjadi nonaktif.
- `x-ui.date-range` dibuat (DESIGN §8): dua input `type="date"` yang di-entangle (deferred) ke properti Livewire, durasi dihitung di browser. Tanggal baru di modal diisi awal dengan jadwal berlaku.
- `x-ui.combobox` mendapat prop opsional `required` dan `icon` untuk label. Error summary (`auth.partials.error-summary`) dipakai juga di modal; prop opsional `$class`, dan fokusnya memakai `$nextTick` agar tetap berfungsi setelah render ulang Livewire.
- Halaman Irban: tabel di ≥1024, kartu di bawahnya (termasuk 768, karena di sana kolom aksi tabel tersembunyi di dalam scroller). Item dengan revisi menunggu: tombol nonaktif + teks alasan "Masih ada revisi yang menunggu persetujuan.". Markup "jadwal berlaku/semula" dan "Riwayat revisi" diekstrak menjadi `x-app.jadwal-berlaku` dan `x-app.riwayat-revisi`, dipakai halaman admin dan irban; riwayat kini juga menampilkan "Diinput oleh **Nama** (akun …)" (login.md §5).
- Pilihan tahun = tahun yang punya PKPT plus tahun berjalan (default tahun berjalan).
- Seeder dev: `TimSeeder` kini juga membuat Komposisi Irban Tahunan Irban I tahun berjalan (PPJ + 3 anggota) supaya "Diinput oleh" punya pilihan.
- `PkptFactory`: tahun default kini 2100–2999 (tidak bertabrakan dengan tahun eksplisit di test), dan `disahkan()` membentuk nomor/tanggal SK dari tahun akhir lewat closure atribut.

## Fase 3, Task 5
- `PutuskanRevisiJadwal` membatasi komentar maksimal 1000 karakter ("Komentar maksimal 1000 karakter."), diperiksa setelah syarat alasan penolakan.
- Halaman `pimpinan.revisi-jadwal`: kartu revisi menunggu urut terlama dulu (`created_at`, lalu `id`); dua kolom di ≥1280. Baris pengaju: "Diajukan {tanggal} · Diinput oleh **{pegawai}** (akun {akun})"; bila `diinput_oleh` kosong: "oleh akun **{akun}**".
- Mini-bar (`x-app.revisi-lane`): satu lajur 12 bulan tahun PKPT; semula = outline putus-putus 2px `--type` 70% (`color-mix`) setinggi lajur, usulan = batang solid `--type` lebih tipis di atasnya supaya keduanya tetap terlihat saat tumpang tindih. Lajur `aria-hidden`; arti dinyatakan di daftar teks "Semula …/Usulan … · N hari" di bawahnya. Label bulan 3 huruf, 1 huruf di bawah 640px.
- Tombol "Tolak" memakai varian baru `danger-outline` di `x-ui.button` (danger-700 di atas putih; hover putih di atas danger-700, keduanya pasangan §3.4).
- Modal "Setujui Revisi Jadwal" (komentar opsional) dan "Tolak Revisi Jadwal" (alasan wajib) menjelaskan akibatnya. Membuka revisi yang sudah diputuskan memberi toast "Revisi ini sudah diputuskan."; id yang tidak ada = 404. Setelah keputusan, fokus pindah ke judul "Menunggu Persetujuan Anda" (`tabindex=-1`, event `revisi-diputuskan`), karena kartu dan tombol pemicunya hilang.
- Riwayat keputusan: tabel compact, filter "Tahun PKPT" (tahun PKPT item; pilihan = tahun yang punya PKPT plus tahun berjalan, seperti halaman Irban; default tahun berjalan), urut `diputuskan_pada` terbaru, paginasi 25/50/100. Sel "Semula → Baru" diberi `relative` agar span `sr-only` di dalamnya tidak melebarkan halaman di 360px.

