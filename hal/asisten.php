<?php
declare(strict_types=1);

$siap = ai_siap();
$riwayat = array_values(array_filter(riwayat(), fn($m) => !str_starts_with((string)$m['content'], '[Catatan sistem]')));

kepala('Asisten', 'asisten');
?>
<div class="judul-baris">
  <h1>✨ Asisten</h1>
  <span>
    <button type="button" class="kecil" id="chat-baru" title="Mulai percakapan baru">Baru</button>
    <a class="tombol kecil" href="?p=pengaturan_ai" title="Pengaturan AI">⚙</a>
  </span>
</div>

<?php if (!$siap): ?>
<div class="pesan info">Asisten belum aktif. Isi dulu kunci API gratis di <a href="?p=pengaturan_ai">Pengaturan AI</a> (±3 menit).</div>
<?php endif; ?>

<div id="chat" class="chat" data-token="<?= h(token()) ?>">
  <div class="gelembung ai">
    Halo! Saya bisa membantu:
    <br>• 📷 kirim foto sampul/halaman hak cipta buku → saya siapkan datanya
    <br>• “tambah 3 eksemplar buku Madilog”, “ubah tahun terbit buku X jadi 2020”
    <br>• “daftarkan siswa Ahmad Fauzi, laki-laki, kelas VII A”
    <br>• “siapa saja yang terlambat mengembalikan?”
    <br><small>Setiap perubahan muncul sebagai kartu; data baru tersimpan setelah Anda menekan <b>Simpan</b>.</small>
  </div>
  <?php foreach ($riwayat as $m):
      $isi = preg_replace('/\n?\[Usulan [0-9a-f]+ .*$/s', '', (string)$m['content']); ?>
    <div class="gelembung <?= $m['role'] === 'user' ? 'saya' : 'ai' ?>" data-md="1"><?= h($isi) ?></div>
  <?php endforeach; ?>
</div>

<form id="chat-form" class="chat-form" autocomplete="off">
  <div id="pratinjau-foto" class="pratinjau-foto"></div>
  <div class="chat-baris">
    <label class="tombol tombol-foto" title="Ambil foto dengan kamera">📷<input type="file" id="chat-kamera" accept="image/*" capture="environment" hidden></label>
    <label class="tombol tombol-foto" title="Pilih dari galeri">🖼<input type="file" id="chat-foto" accept="image/*" multiple hidden></label>
    <textarea id="chat-teks" rows="1" placeholder="Tulis pesan…" <?= $siap ? '' : 'disabled' ?>></textarea>
    <button class="utama" id="chat-kirim" <?= $siap ? '' : 'disabled' ?>>Kirim</button>
  </div>
</form>
<?php
kaki();
