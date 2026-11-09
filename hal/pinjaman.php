<?php
declare(strict_types=1);

$f = (string)get('f');
$w = $f === 'terlambat' ? 'AND l.due_date < CURDATE()' : '';
$daftar = semua("SELECT l.item_code, l.loan_date, l.due_date, m.member_id, m.member_name, m.inst_name, b.biblio_id, b.title
    FROM loan l LEFT JOIN member m ON m.member_id = l.member_id
    LEFT JOIN item i ON i.item_code = l.item_code LEFT JOIN biblio b ON b.biblio_id = i.biblio_id
    WHERE l.is_lent = 1 AND l.is_return = 0 $w ORDER BY l.due_date, m.member_name LIMIT 500");

kepala('Pinjaman', 'pinjaman');
?>
<div class="judul-baris">
  <h1>Sedang dipinjam <small class="redup"><?= count($daftar) ?></small></h1>
</div>
<div class="tab">
  <a href="?p=pinjaman" class="<?= $f === '' ? 'aktif' : '' ?>">Semua</a>
  <a href="?p=pinjaman&amp;f=terlambat" class="<?= $f === 'terlambat' ? 'aktif' : '' ?>">Terlambat</a>
</div>
<ul class="daftar kartu">
<?php if (!$daftar): ?><li class="redup">Tidak ada.</li><?php endif; ?>
<?php foreach ($daftar as $l): $telat = $l['due_date'] < hari_ini(); ?>
  <li>
    <b><?= h($l['title'] ?: $l['item_code']) ?></b>
    <small><?= h($l['item_code']) ?> · dipinjam <?= tgl($l['loan_date']) ?> · kembali <?= tgl($l['due_date']) ?>
      <?php if ($telat): ?><span class="lencana merah">terlambat <?= (int)((strtotime(hari_ini()) - strtotime($l['due_date'])) / 86400) ?> hari</span><?php endif; ?></small>
    <small><a href="?p=anggota_form&amp;id=<?= h(rawurlencode((string)$l['member_id'])) ?>"><?= h($l['member_name'] ?: $l['member_id']) ?></a><?= $l['inst_name'] ? ' · ' . h($l['inst_name']) : '' ?></small>
  </li>
<?php endforeach; ?>
</ul>
<p class="redup kecil">Untuk meminjamkan, mengembalikan, atau memperpanjang pinjaman, pakai menu Sirkulasi di
  <a href="<?= h(rtrim((string)k('opac_url'), '/')) ?>/admin" target="_blank" rel="noopener">admin SLiMS</a>.
  Hari terlambat di sini dihitung kalender (belum dikurangi Sabtu/Minggu); denda resmi tetap dihitung SLiMS.</p>
<?php
kaki();
