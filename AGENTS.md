# Panduan untuk agen AI (Freebuff, Claude, dll.) — SiPustaka

## 1. Bahasa & cara berkomunikasi (WAJIB)
- **Selalu jawab dalam Bahasa Indonesia** yang sederhana dan mudah dipahami orang awam. Pengguna (Pak Sultan Amra, SMP Negeri 6 Keritang) bukan programmer.
- Jangan memakai istilah teknis bahasa Inggris tanpa penjelasan singkat. Contoh: tulis "kirim ke GitHub (push)", bukan hanya "push".
- Sebelum mengubah file, **jelaskan dulu dengan singkat apa yang akan diubah dan kenapa**. Untuk perubahan besar (fitur baru, perubahan database, data buku/anggota), **tanya dulu dan tunggu persetujuan** ("buat").
- Setelah selesai, laporkan singkat: apa yang berubah, cara mencobanya, dan langkah memasang di website.
- Jawaban singkat dan jelas; pengguna sering membaca di HP.

## 2. Tentang aplikasi
SiPustaka ("Kelola Perpustakaan") = aplikasi web (PWA) untuk menambah buku, eksemplar, dan anggota perpustakaan **SLiMS 9.8** tanpa membuka admin SLiMS. PHP 8.2 + PDO MySQL, tanpa framework, tanpa Composer/npm.
Dipasang di subdomain `kelola.otomasismpn6keritang.my.id` (folder `/home/bzydmpxm/kelola.otomasismpn6keritang.my.id`). Login memakai akun admin SLiMS (tabel `user`).

Aplikasi ini **membaca dan menulis langsung ke database SLiMS** (`bzydmpxm_amra`): `biblio`, `item`, `member`, `loan`, dll. Hati-hati: kesalahan di sini merusak data perpustakaan yang asli.

Struktur:
- `index.php` — router (`?p=nama_halaman`) dan daftar halaman yang diizinkan.
- `hal/` — satu file per halaman, termasuk API (`hal/api_*.php`).
- `inc/` — fungsi bersama: `inti.php` (VERSI, database, helper `q()`/`satu()`/`semua()`/`h()`/token), `katalog.php` (buku & eksemplar), `layanan.php`, `ai.php` & `asisten.php` (AI), `scanner.php` (scanner HP → laptop), `tampilan.php`.
- `aset/` — CSS (`gaya.css`) dan JavaScript.
- `config.contoh.php` — contoh pengaturan. `config.php` asli **hanya ada di server**.
- `data/` — berkas di server (kunci AI `data/ai-kunci.php`, kanal scanner `data/scan`). **Tidak ikut Git.**

## 3. Aturan wajib setiap perubahan
1. **Mulai dengan `git pull`.**
2. **Jangan pernah commit kunci API, sandi database, `config.php`, atau isi folder `data/`.**
3. **Jangan pernah mengubah SLiMS itu sendiri** (`/home/bzydmpxm/public_html`) — termasuk `index.php` dan `.htaccess` di sana. Repo ini hanya untuk SiPustaka.
4. **Jangan mengubah struktur tabel SLiMS** (tambah/hapus kolom, ubah tipe). Kalau benar-benar perlu, tanya dulu pengguna.
5. **Setiap perubahan yang dikirim ke GitHub: naikkan versi di SEMUA tempat sekaligus** (angkanya harus sama):
   - `inc/inti.php` → `const VERSI = '1.xx';`
   - `sw.js` → `const CACHE = 'kelola-v1.xx';` dan `?v=1.xx` di daftar `ASET`
   Kalau `sw.js` lupa dinaikkan, HP yang memasang aplikasi bisa terus menampilkan halaman lama.
6. Database berjalan dalam **mode strict**: kolom angka jangan diisi teks kosong (isi `0`), tanggal kosong pakai `NULL`.
7. Keamanan: pakai `q()` / `satu()` / `semua()` dengan parameter `?` (jangan menempel input ke SQL), keluaran HTML lewat `h()`, formulir POST wajib `input_token()` (CSRF). Halaman selain `masuk` wajib login.
8. Periksa sebelum push: `php -l` untuk file PHP yang diubah, `node --check` untuk JS yang diubah.
9. Kalau menambah file atau folder baru di luar `aset/ hal/ inc/`, **tambahkan juga ke `.cpanel.yml`** supaya ikut tersalin ke website.
10. Pesan commit dalam Bahasa Indonesia, diawali versi, mis. `v1.11: ...`.

## 4. Aturan perpustakaan yang sudah disepakati
- **Kode eksemplar**: `PREFIX-0001`, nomor urut terpisah per prefix.
  - Buku paket: kode mapel + kelas (+`G` untuk buku guru), mis. `INF8-0001`, `MTK7-0001`, `PAI9G-0001`.
  - Kode mapel: INF Informatika, MTK Matematika, BIN B. Indonesia, BIG B. Inggris, IPA, IPS, PAI PAI & Budi Pekerti, PKN PPKn, SBD Seni Budaya, PJK PJOK, PRA Prakarya, MLK Muatan Lokal.
  - Non-paket: FIK fiksi, CRA cerita rakyat/anak, AGM agama non-paket, NF nonfiksi umum, REF referensi.
  - Tipe koleksi: FIK & CRA → Fiction; NF & AGM → Nonfiksi; REF → Reference; paket → Textbook.
- Masa berlaku semua anggota: **27-09-2027** (dari `config.php`). ID anggota acak: siswa 10 digit, guru 9 digit. Kelas siswa disimpan di `member.inst_name`.
- Sirkulasi (pinjam/kembali) tetap dilakukan di SLiMS; SiPustaka hanya menampilkan pinjaman aktif.
- Sabtu & Minggu hari libur perpustakaan.

## 5. Gaya tampilan
- Semua teks untuk pengguna dalam Bahasa Indonesia.
- **Harus rapi di HP** (lebar 320–412 px): tidak boleh ada halaman yang bisa digeser ke samping. Saat dipasang sebagai aplikasi, tampilan tidak bisa di-zoom.
- Ikon di dalam aplikasi pakai ikon garis (Lucide), bukan emoji di menu/tombol utama.
- Warna: navy `#2A2C84`, navy gelap `#1B1D5E`, sky `#A0D9F6`, emas `#F2C230`, hijau `#2E9E48`. Font Quicksand.

## 6. AI
- Penyedia: Gemini → Groq → OpenRouter → preset lain. **OpenRouter hanya model `:free`** (jangan sampai memakai kredit).
- Kunci AI hanya di server (`data/ai-kunci.php`), dipakai bersama dengan SiGuru. Kunci baru diuji dulu sebelum disimpan.

## 6b. Masuk dari SiGuru (v1.12)
- `?p=sso` (boleh tanpa login): menerima `u`, `t`, `n`, `s` dari SiGuru (admin). Tanda tangan HMAC-SHA256 atas `u|t|n` dengan kunci bersama `data/sso-kunci.php` (dibuat otomatis, tidak ikut Git), berlaku 60 detik, sekali pakai (`data/sso-pakai.json`), akun harus ada di tabel `user` SLiMS. Opsi `ke` = pinjaman/buku/anggota. Jangan longgarkan pemeriksaan ini.

## 7. Cara memasang perubahan di website
Setelah commit & push ke GitHub: cPanel → **Git Version Control** → Manage `sipustaka` → **Pull or Deploy** →
1. **Update from Remote** (ambil kode terbaru dari GitHub), lalu
2. **Deploy HEAD Commit** (salin kode ke folder subdomain kelola — diatur oleh `.cpanel.yml`).

Cek tulisan versi di bagian bawah halaman SiPustaka.
