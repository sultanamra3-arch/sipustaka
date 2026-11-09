<?php
declare(strict_types=1);

$st = satu("SELECT
    (SELECT COUNT(*) FROM biblio) judul,
    (SELECT COUNT(*) FROM item) eksemplar,
    (SELECT COUNT(*) FROM member) anggota,
    (SELECT COUNT(*) FROM member WHERE expire_date < CURDATE()) kedaluwarsa,
    (SELECT COUNT(*) FROM member WHERE inst_name IS NULL OR inst_name = '') tanpa_kelas,
    (SELECT COUNT(*) FROM loan WHERE is_lent = 1 AND is_return = 0) dipinjam,
    (SELECT COUNT(*) FROM loan WHERE is_lent = 1 AND is_return = 0 AND due_date < CURDATE()) terlambat");

$baru = semua('SELECT b.biblio_id, b.title, b.input_date,
    (SELECT GROUP_CONCAT(item_code ORDER BY item_code SEPARATOR ", ") FROM item WHERE biblio_id = b.biblio_id) kode
    FROM biblio b ORDER BY b.biblio_id DESC LIMIT 5');

kepala('Beranda', 'beranda');
?>
<h1>Halo, <?= h(pengguna()['nama'] ?: pengguna()['username']) ?></h1>

<div class="tombol-besar">
  <a class="utama" href="?p=buku_form">＋ Tambah buku</a>
  <a href="?p=anggota_form">＋ Tambah anggota</a>
  <a class="lebar2" href="?p=asisten">✨ Asisten AI — kirim foto buku atau perintah</a>
  <a class="lebar2 scanner" href="?p=scanner">📡 Scanner — pindai barcode dengan HP, terketik di laptop</a>
</div>
<?php if (!ai_siap()): ?>
  <div class="pesan info"><a href="?p=pengaturan_ai">Aktifkan Asisten AI: isi kunci API gratis →</a></div>
<?php endif; ?>

<div class="statistik">
  <a href="?p=buku"><b><?= (int)$st['judul'] ?></b>judul</a>
  <a href="?p=buku"><b><?= (int)$st['eksemplar'] ?></b>eksemplar</a>
  <a href="?p=anggota"><b><?= (int)$st['anggota'] ?></b>anggota</a>
  <a href="?p=pinjaman"><b><?= (int)$st['dipinjam'] ?></b>dipinjam</a>
</div>

<?php if ($st['terlambat'] > 0): ?>
  <div class="pesan peringatan"><a href="?p=pinjaman&amp;f=terlambat"><?= (int)$st['terlambat'] ?> pinjaman terlambat →</a></div>
<?php endif; ?>
<?php if ($st['tanpa_kelas'] > 0): ?>
  <div class="pesan info"><a href="?p=anggota_kelas"><?= (int)$st['tanpa_kelas'] ?> anggota belum diisi kelasnya. Isi sekaligus →</a></div>
<?php endif; ?>
<?php if ($st['kedaluwarsa'] > 0): ?>
  <div class="pesan peringatan"><a href="?p=anggota&amp;f=kedaluwarsa"><?= (int)$st['kedaluwarsa'] ?> anggota sudah habis masa berlakunya →</a></div>
<?php endif; ?>

<div class="kartu">
  <h2>Buku terakhir ditambahkan</h2>
  <ul class="daftar">
  <?php foreach ($baru as $b): ?>
    <li><a href="?p=buku_detail&amp;id=<?= (int)$b['biblio_id'] ?>"><b><?= h($b['title']) ?></b>
      <small><?= h($b['kode'] ?: 'belum ada eksemplar') ?> · <?= tgl($b['input_date']) ?></small></a></li>
  <?php endforeach; ?>
  </ul>
</div>

<p class="redup kecil">Peminjaman &amp; pengembalian tetap dilakukan di menu Sirkulasi SLiMS:
  <a href="<?= h(rtrim((string)k('opac_url'), '/')) ?>/admin" target="_blank" rel="noopener">buka admin SLiMS</a>.</p>
<?php
kaki();
