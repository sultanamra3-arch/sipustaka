# SiPustaka (Kelola Perpustakaan SMPN 6 Keritang) — panduan pasang

Aplikasi web (bisa dipasang di HP seperti aplikasi) untuk menambah buku, eksemplar, dan anggota
SLiMS **tanpa Claude**. Login memakai akun admin SLiMS yang sama.

## Pasang (sekali saja, ±10 menit)

1. **Buat subdomain** di cPanel → Domains (atau Subdomains):
   `kelola.otomasismpn6keritang.my.id`, folder (document root):
   `/home/bzydmpxm/kelola.otomasismpn6keritang.my.id`
2. **Upload zip**: File Manager → buka folder **home** `/home/bzydmpxm`
   (BUKAN `public_html`!). Cek kotak alamat File Manager dulu.
   Upload `kelola.zip`, klik kanan → Extract. Isinya otomatis masuk ke folder
   `kelola.otomasismpn6keritang.my.id`.
3. **Versi PHP** subdomain: cPanel → MultiPHP Manager (atau Select PHP Version) → pilih 8.2,
   sama seperti domain utama.
4. **SSL**: cPanel → SSL/TLS Status → Run AutoSSL (agar https aktif dan bisa dipasang di HP).
5. Buka https://kelola.otomasismpn6keritang.my.id → login dengan akun admin SLiMS.

Jika muncul "Database belum tersambung": buka `config.php` di folder subdomain, isi bagian
`db` (host `localhost`, database `bzydmpxm_amra`, user & sandi database yang dipakai SLiMS;
lihat di `public_html/config/database.php`).

Opsional: isi `gambar_otomatis_url` di `config.php` dengan alamat yang sama seperti di tugas cron
gambar-otomatis, supaya sampul/foto langsung dibuat setelah menyimpan (tanpa itu, cron membuatnya
dalam 10 menit).

## Pasang di HP

- Android (Chrome): buka alamatnya → menu ⋮ → **Instal aplikasi** / Tambahkan ke layar utama.
- iPhone (Safari): tombol Bagikan → **Tambah ke Layar Utama**.

## Fitur

- Tambah buku + eksemplar. Kode dibuat otomatis sesuai skema (INF8-0001, MTK7G-0001, FIK-0011, …),
  tipe koleksi & sumber mengikuti jenisnya, no. panggil otomatis, cek judul/ISBN dobel,
  opsi buku titipan pribadi.
- Ubah data buku, tambah eksemplar, ubah status (Tersedia/Diperbaiki/Tidak dipinjamkan/Hilang),
  hapus eksemplar yang belum pernah dipinjam.
- Tambah & ubah anggota: nomor acak (siswa 10 digit, guru 9 digit), masa berlaku 27-09-2027,
  tombol perpanjang, cek nama dobel.
- **Isi kelas sekaligus**: centang siswa satu kelas → tulis kelasnya → simpan.
- Daftar pinjaman aktif & terlambat (baca saja).

Peminjaman/pengembalian tetap di menu Sirkulasi admin SLiMS.

## Asisten AI (v1.1)

- **📷 Isi otomatis dari foto** di halaman Tambah buku: foto sampul/halaman hak cipta → kolom terisi.
- **✨ Asisten** (menu bawah): chat seperti Claude. Bisa kirim foto, minta tambah/ubah buku,
  eksemplar, anggota, isi kelas, tanya pinjaman terlambat. Setiap perubahan tampil sebagai kartu;
  baru tersimpan setelah tombol **Simpan** ditekan.
- Kunci Gemini dan Groq sudah terpasang di zip ini (`data/ai-kunci.php`). Cek di menu Asisten → ⚙ →
  Tes semua AI. Kunci baru: Gemini https://aistudio.google.com/apikey, Groq https://console.groq.com/keys.
- Model teruji 7 Nov 2026: Gemini `gemini-3.5-flash, gemini-3.5-flash-lite, gemini-flash-lite-latest`;
  Groq `qwen/qwen3.8-27b` (baca foto) dan `openai/gpt-oss-120b` (teks saja).
- AI dicoba berurutan; bila limit habis atau model hilang, otomatis pindah ke berikutnya.
  Limit harian Gemini direset sekitar pukul 14.00–15.00 WIB.
- Kunci disimpan di folder `data/` (terkunci dari internet). Saat memperbarui aplikasi dengan zip
  baru, folder `data/` tidak ditimpa sehingga kunci tetap ada.
- Nama model bisa berubah dari waktu ke waktu. Jika tes menunjukkan "404 model not found",
  ganti nama model di Pengaturan AI sesuai daftar model penyedia.

## Scanner HP → laptop (v1.6)

Pindai barcode buku/kartu anggota dengan kamera HP; kodenya terketik di kolom web yang sedang diklik
di laptop (Windows, Linux, Chromebook) + Enter otomatis. Tanpa Bluetooth, tanpa aplikasi tambahan.
1. Laptop (sekali saja): buka SiPustaka di Chrome → Beranda → 📡 Scanner → seret tombol
   "📡 Scanner SiPustaka" ke baris bookmark (Ctrl+Shift+B).
2. Buka Sirkulasi SLiMS (area admin) → klik bookmark itu → muncul kotak "Scanner SiPustaka".
3. Klik kolom isian → di HP buka SiPustaka → 📡 Scanner → Mulai kamera → pindai.
HP dan laptop harus login dengan akun yang sama. Butuh internet (±1 detik). Bookmark tidak jalan di
halaman OPAC publik (diblokir aturan keamanan SLiMS), tetapi jalan di area admin.

## Memperbarui dari versi lama

Upload zip baru ke `/home/bzydmpxm` lalu Extract dan pilih timpa (overwrite). Jika dulu Anda mengisi
bagian `db` di `config.php` secara manual, isi lagi setelah menimpa.

## Aman dari update SLiMS?

Ya. Aplikasi ada di folder subdomain sendiri dan hanya membaca/menulis database.
Indeks pencarian OPAC (`search_biblio`) ikut diperbarui setiap menyimpan buku.
