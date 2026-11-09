<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('aksi') === 'token_baru') {
    scanner_token(true);
    pesan('Bookmark lama dinonaktifkan. Pasang ulang bookmark baru di laptop.');
    ke('?p=scanner#laptop');
}

$token = scanner_token();
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$dasar = ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';
$bookmarklet = "javascript:(function(){if(window.__sipustakaScanner){window.__sipustakaScanner.tampil();return}"
    . "var s=document.createElement('script');s.src='" . $dasar . "aset/jembatan.js?v=" . VERSI . "#" . $token . "';"
    . "document.documentElement.appendChild(s)})();";

kepala('Scanner', 'beranda');
?>
<div class="judul-baris">
  <h1>📡 Scanner</h1>
  <a class="tombol" href="?p=beranda">‹ Beranda</a>
</div>
<p class="redup">Pindai barcode buku atau kartu anggota dengan HP. Kodenya langsung terketik di kolom web yang sedang diklik di laptop (Windows, Linux, Chromebook), misalnya di Sirkulasi SLiMS.</p>

<div class="kartu" id="hp">
  <h2>📱 Di HP: pindai</h2>
  <p class="kecil">Akun Anda: <b><?= h(pengguna()['nama'] ?: pengguna()['username']) ?></b>. Kode hanya masuk ke laptop yang <b>login SLiMS dengan akun yang sama</b>.</p>
  <div id="status-laptop" class="status-laptop">Memeriksa laptop…</div>
  <div id="pembaca" class="pembaca"></div>
  <div class="dua-tombol">
    <button type="button" class="utama" id="kamera-mulai">📷 Mulai kamera</button>
    <button type="button" id="kamera-henti" disabled>⏸ Jeda</button>
  </div>
  <label class="centang"><input type="checkbox" id="kirim-enter" checked> Tekan Enter otomatis setelah kode terketik</label>
  <form id="manual" class="cari" autocomplete="off">
    <input id="manual-kode" placeholder="atau ketik kode, mis. FIK-0012" autocapitalize="characters">
    <button>Kirim</button>
  </form>
  <ul id="riwayat-scan" class="daftar riwayat-scan"></ul>
</div>

<div class="kartu" id="laptop">
  <h2>💻 Di laptop: pasang sekali saja</h2>
  <ol class="langkah">
    <li>Buka halaman ini di <b>Chrome laptop</b>. Bookmark ini cukup dipasang sekali dan bisa dipakai semua akun: scanner otomatis mengikuti <b>akun yang sedang login di SLiMS</b> laptop itu.</li>
    <li>Tampilkan baris bookmark: tekan <kbd>Ctrl</kbd>+<kbd>Shift</kbd>+<kbd>B</kbd>.</li>
    <li><b>Seret</b> tombol di bawah ini ke baris bookmark:
      <p><a class="tombol emas bookmarklet" href="<?= h($bookmarklet) ?>" onclick="alert('Jangan diklik di sini. Seret (drag) tombol ini ke baris bookmark di atas.');return false;">📡 Scanner SiPustaka</a></p>
    </li>
    <li>Buka web yang mau diisi (mis. <b>Sirkulasi SLiMS</b>), klik bookmark <b>📡 Scanner SiPustaka</b>. Muncul kotak kecil di pojok kanan bawah berisi nama akun SLiMS yang sedang login.</li>
    <li><b>Klik kolom isian</b> (nomor anggota / kode eksemplar), lalu pindai dengan HP. Kode langsung terketik.</li>
  </ol>
  <details>
    <summary class="kecil">Tidak bisa menyeret? Buat bookmark manual</summary>
    <p class="kecil">Klik ☆ di ujung kotak alamat → Edit/Lainnya → ganti nama menjadi <b>Scanner SiPustaka</b> dan isi URL dengan teks ini:</p>
    <textarea readonly rows="4" class="kecil" onclick="this.select()"><?= h($bookmarklet) ?></textarea>
    <button type="button" class="kecil" onclick="var t=this.previousElementSibling;t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);this.textContent='Tersalin ✓'">Salin</button>
  </details>
  <form method="post" onsubmit="return confirm('Bookmark lama di semua laptop akan berhenti bekerja. Lanjutkan?')" style="margin-top:.8rem">
    <?= input_token() ?><input type="hidden" name="aksi" value="token_baru">
    <button class="kecil">Ganti kunci bookmark</button>
    <small class="redup">(bila bookmark pernah dibagikan ke orang lain)</small>
  </form>
</div>

<script src="aset/html5-qrcode.min.js?v=2.3.8"></script>
<script>window.SCANNER_TOKEN_FORM = <?= json_encode(token()) ?>;</script>
<script src="aset/scanner.js?v=<?= VERSI ?>"></script>
<?php
kaki();
