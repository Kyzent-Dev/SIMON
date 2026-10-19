# Keputusan Fase 1

## Fase 1, Task 1
- Kolom baru `users` (`username`, `role`, `irban_id`, `wajib_ganti_password`) ditambahkan lewat migrasi baru; kolom bawaan `name`/`email`/`password` dipertahankan. `irban_id` tanpa foreign key (tabel `irban` datang di Fase 2). `role` punya default `irban` di DB.
- `/masuk` (route `login`) di Task 1 hanya stub berisi heading "Masuk" agar redirect tamu bisa dites; Task 2 menggantinya dengan login nyata.
- Route `/dashboard` Fase 0 (placeholder) diganti redirect ke dasbor per role; view `placeholder/app` dihapus. Guard ganti-kata-sandi-wajib belum ada (Task 2).
- `lang/id/` hanya berisi kunci yang dipakai Fase 1 (`auth`, `passwords`, `validation`: required, email, confirmed, min.string, unique, password.*, attributes). Kunci lain jatuh ke `en` (fallback_locale). Pesan `passwords.user` sengaja sama dengan `passwords.sent` agar tidak membocorkan keberadaan email.

## Fase 1, Task 2
- Logo (64px di panel merek, di band mobile) dihilangkan karena aset logo belum ada; panel hanya berisi teks "SIMON-SPT", subjudul dan garis accent-400. Motif Delapan Sungai digambar sebagai SVG inline dekoratif (8 garis bergelombang, mint-200, opacity 15%, `aria-hidden`).
- `blade-ui-kit/blade-heroicons` 2.7 dipasang: Packagist menunjukkan `illuminate/support ^13.0` (blade-icons 1.10 juga mendukung 13). Ikon dirender lewat helper `svg()`; SVG Heroicons sudah membawa `aria-hidden="true"`.
- Galat kredensial dan lockout disimpan di kunci error `login` (bukan `username`) agar tampil sebagai satu alert umum tanpa petunjuk per field (login.md §1). Galat validasi biasa (kolom kosong) tetap inline + ringkasan galat (DESIGN §10).
- Kartu auth memakai padding dan elevasi DESIGN §5 (`p-4 lg:p-5`, elevasi 1: `border border-neutral-200 shadow-xs`), menggantikan `p-8`/`shadow-sm` dari login.md §1, karena DESIGN.md menang atas file halaman (CLAUDE.md); diputuskan controller pada review Task 2.
- `x-ui.alert`: tidak ada token latar semantik (mis. danger-50) di §13, jadi alert memakai permukaan putih + tepi kiri 4px dan ikon berwarna (info-800, accent-700, danger-700, success-700); teks neutral-900 di atas putih. Makna ada di teks dan ikon, bukan warna.
- Tombol nonaktif memakai neutral-100 + neutral-400 persis seperti DESIGN §8 (kontrol nonaktif dikecualikan dari WCAG 1.4.3).
- Cincin fokus global `:focus-visible` (mint-600, 2px, offset 2px) ditambahkan di `app.css` (`@layer base`); input/checkbox memakai ring mint-600 milik utilitas Tailwind karena plugin forms menimpa outline.
- Salinan tambahan yang tidak ada di login.md: judul "Lupa Kata Sandi", halaman "Atur Ulang Kata Sandi" (helper "Minimal 8 karakter, huruf besar dan kecil, serta angka.", tombol "Simpan kata sandi"), label "Kekuatan:", pengumuman akhir lockout "Silakan coba masuk kembali.", teks sr-only "(terpenuhi)/(belum terpenuhi)/(diperiksa saat disimpan)", judul ringkasan "Periksa N isian berikut" (DESIGN §10), dan tombol ghost "Keluar" di halaman wajib ganti (satu-satunya jalan keluar; bukan navigasi).
- Syarat "Berbeda dari kata sandi lama" tidak bisa dicek di browser (server tidak mengirim kata sandi lama): tampil netral, berubah ke ikon gagal bila server menolak. Meter kekuatan: skor = jumlah 3 syarat + 1 bila ≥12 karakter atau ada simbol (1 Lemah … 4 Sangat kuat), heuristik sederhana.
- Atur ulang kata sandi lewat email tidak menghapus `wajib_ganti_password`; pengguna yang ditandai tetap harus melewati halaman wajib ganti.
- Aturan kata sandi didefinisikan sekali lewat `Password::defaults()` di `AppServiceProvider` dan dipakai oleh reset dan wajib ganti.
- Font belum di-self-host (tetap stack token §13).

## Fase 1, Task 3
- Login memakai `username` (design/auth/login.md), bukan kolom `email` seperti PRD §8; `email` tetap ada untuk reset kata sandi. Kolom bawaan `name` dipertahankan (tidak diganti nama Indonesia).
- Header dasbor minimal (`layouts/app.blade.php`): 64px, putih, border-b; kanan berisi avatar inisial (mint-100/mint-800), nama akun, caption role, dan tombol "Keluar" (POST `logout`). Sidebar, lonceng notifikasi, breadcrumbs, pengganti tahun, menu pengguna ("Profil", "Ubah kata sandi") dan hamburger ditunda ke fase yang membutuhkannya.
- Akun seeder hanya untuk pengembangan: kata sandi `password` untuk `admin`, `pimpinan`, `irban1`, `bagianumum`; jangan dijalankan di produksi.
- 419 (sesi/CSRF berakhir) sudah ditangani di `bootstrap/app.php` dan dites di `LoginTest::test_expired_csrf_redirects_to_login_with_message`; tidak diduplikasi.
- Setelah wajib ganti kata sandi berhasil, `remember_token` diganti baru; saat Admin menandai semua Irban (`RequireIrbanPasswordChange`), `remember_token` mereka dikosongkan, sehingga sesi "ingat saya" lama tidak lolos. Middleware `auth.session` sengaja tidak dipasang.
- Email reset kata sandi diterjemahkan lewat `lang/id.json` (kunci JSON bawaan Laravel: subjek, isi, tombol, salam, penutup, subcopy, hak cipta); kunci bahasa Inggris dipertahankan persis agar terjemahan terpakai.
- `UserSeeder` (akun dev berkata sandi `password`) langsung berhenti bila `app()->isProduction()`.

