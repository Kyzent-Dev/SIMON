# Authentication: Login, Password Reset, Forced Change, "Diinput oleh"

> Read `DESIGN.md` first.

## 1. Login
- **Layout ≥1024:** split 5/7.
  - **Left brand panel:** mint-950, Delapan Sungai motif in mint-200 at 15%, logo 64px, "SINTAS" display-lg white, "Sistem Informasi Surat Tugas Pengawasan — Inspektorat Daerah Kabupaten Muara Enim" in mint-200, accent-400 rule.
  - **Right:** neutral-25 bg with a centered white card, max-w 420, p-8, radius-lg, shadow-sm.
- **<1024:** a single column with a mint-950 band 120px tall (logo + name) and the card beneath, overlapping by −24px.
- **Fields:**
  - "Nama pengguna" (`autocomplete="username"`)
  - "Kata sandi" (show/hide toggle button, `aria-pressed`, label "Tampilkan kata sandi")
  - checkbox "Ingat saya"
  - primary button full width "Masuk"
  - link "Lupa kata sandi?"
- **Errors:**
  - Wrong credentials: generic alert danger "Nama pengguna atau kata sandi salah." No field-specific hint.
  - Lockout (rate limit): alert warning with `clock` icon, "Terlalu banyak percobaan masuk. Coba lagi dalam **59** detik." The countdown updates each second in an `aria-live="polite"` region announcing only every 15s. The submit button is disabled until 0.
- **Motion:** none, apart from the button loading spinner. No shake on error.

## 2. Forgot password
Same shell. Field "Email" + "Kirim tautan reset". Success alert: "Jika email terdaftar, tautan reset telah dikirim. Periksa kotak masuk Anda." Neutral wording prevents account enumeration. Include "Kembali ke halaman masuk".

## 3. Forced password change
- Shown after login when admin has forced a reset (yearly for irban accounts). A full-page card, with no navigation available.
- Title "Perbarui Kata Sandi". Info alert: "Administrator mewajibkan pembaruan kata sandi untuk tahun 2026."
- Fields: Kata sandi baru, Konfirmasi.
- Live requirement checklist, each with an icon plus text (not color only): "Minimal 8 karakter", "Huruf besar dan kecil", "Angka", "Berbeda dari kata sandi lama". There is also a 4-segment strength meter labelled "Lemah/Sedang/Kuat/Sangat kuat".
- Button "Simpan & Lanjutkan".

## 4. States and accessibility
- Autofocus the first field on desktop only.
- The error summary receives focus on failed submit.
- Inputs are 48px on mobile.
- CSRF and expired session: "Sesi berakhir, silakan masuk kembali."

## 5. The "Diinput oleh" pattern (`x-app.diinput-oleh`) — reusable for shared irban accounts
- **Where:** in the sticky footer bar of every write form and every action modal (Simpan, Ajukan, Unggah, Tandai Selesai, Balas komentar, Ajukan revisi jadwal) when the logged-in account is an Irban unit account.
- **Anatomy:**
  - A label "Diinput oleh" with a `user-circle` icon.
  - A required combobox listing only the active employees of that irban for the current year (name + NIP last 4 digits: "Budi Santoso · …0012").
  - Helper text: "Pilih pegawai yang benar-benar melakukan input ini."
- **Behavior:**
  - On the first write of the session the field is empty, and submitting without a selection triggers the error "Pilih nama penginput terlebih dahulu".
  - Afterwards it is prefilled with the last choice from the session, displayed as a mint-50 chip "Budi Santoso" with an "Ganti" link. It is never hidden.
  - The submit button text includes the person on confirm modals: "Ajukan sebagai Budi Santoso".
- **Audit display:** in history and activity logs, show "Diinput oleh **Budi Santoso** (akun Irban II)".
- **Product-owner decision:** whether prefill is allowed at all. If not, reset after every write.
- **Accessibility:** a real label; the chip is a button named "Ganti penginput, saat ini Budi Santoso".
