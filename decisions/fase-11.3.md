# Keputusan Fase 11.3

## Fase 11.3, Task 1: Logo resmi
- Aset dari pengguna: Lambang Kabupaten Muara Enim PNG 2000×2388 (transparan), bukan berkas Diskominfo yang disebut DESIGN §2. Berkas asli disimpan di `resources/brand/logo-asli.png` (sumber untuk membuat ulang ukuran lain); pengguna semula menaruhnya di `public/storage/logo/`, yang merupakan symlink ke `storage/app/public` (git-ignored, area unggahan) sehingga tidak akan ikut deploy.
- Turunan di `public/images/`: `logo.png` 161×192 (tinggi tampil terbesar 64px × 3, ±30 KB), `favicon-32.png` 32×32 transparan, `apple-touch-icon.png` 180×180 berlatar putih (iOS menghitamkan latar transparan). Dibuat dengan GD (`imagecopyresampled`).
- `x-app.logo` kini `<img alt="">` (dekoratif; teks di sampingnya membawa nama); pemanggil mengatur tinggi: header publik `h-9` (36px, landing.md), sidebar `h-10` (menggantikan kotak "S" mint-700 dari Fase 2; _shared.md "logo + SINTAS"), panel merek login dan halaman galat `h-12` di band <1024 (di samping nama) dan `h-16` ≥1024 (di atas nama; login.md 64px). Prop `gelap` dihapus: lambang berwarna penuh dipakai apa adanya di latar terang dan mint-950.
- Favicon lewat `partials/ikon.blade.php` di keempat layout (public, app, auth, errors). `public/favicon.ico` (0 byte, bawaan Laravel) dibiarkan.
- Tidak dikerjakan: logo di header email (_shared.md §2) — gambar email butuh URL absolut dan sering diblokir klien email; header tetap teks `SINTAS` (decisions/fase-6.md). Sampling hex kuning resmi untuk `accent-400` (DESIGN §2, §16) belum dilakukan; berkas PNG ini bisa dipakai bila pengguna menginginkannya.
- `BerandaTest::test_no_slide_shows_brand_hero` kini memeriksa ketiadaan `<img>` hanya di bagian hero (logo header juga `<img>`).
- Cek browser (`playwright-cli`): Beranda, Masuk, 404 dan dasbor Admin (drawer terbuka di 360, sidebar ciut di 1280) di 360/768/1280, `scrollWidth` = lebar viewport di semua; logo tampil 30×36 (header publik), 34×40 (sidebar), 40×48 / 54×64 (panel merek).
