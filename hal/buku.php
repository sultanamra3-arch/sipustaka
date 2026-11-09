<?php
declare(strict_types=1);

$cari = (string)get('q');
$prefix = strtoupper((string)get('prefix'));
$hal = max(1, (int)get('h', 1));
$per = 20;

$where = [];
$par = [];
if ($cari !== '') {
    $where[] = '(b.title LIKE ? OR b.isbn_issn LIKE ? OR EXISTS (SELECT 1 FROM item i2 WHERE i2.biblio_id = b.biblio_id AND i2.item_code LIKE ?)
        OR EXISTS (SELECT 1 FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id AND a.author_name LIKE ?))';
    $like = '%' . $cari . '%';
    array_push($par, $like, $like, $like, $like);
}
if ($prefix !== '' && prefix_valid($prefix)) {
    $where[] = 'EXISTS (SELECT 1 FROM item i3 WHERE i3.biblio_id = b.biblio_id AND i3.item_code LIKE ?)';
    $par[] = $prefix . '-%';
}
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)nilai("SELECT COUNT(*) FROM biblio b $w", $par);
$daftar = semua("SELECT b.biblio_id, b.title, b.publish_year, b.call_number,
    (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level SEPARATOR '; ') FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) pengarang,
    (SELECT GROUP_CONCAT(item_code ORDER BY item_code SEPARATOR ', ') FROM item WHERE biblio_id = b.biblio_id) kode,
    (SELECT COUNT(*) FROM loan l JOIN item i ON i.item_code = l.item_code WHERE i.biblio_id = b.biblio_id AND l.is_lent = 1 AND l.is_return = 0) dipinjam
    FROM biblio b $w ORDER BY b.biblio_id DESC LIMIT $per OFFSET " . (($hal - 1) * $per), $par);

$prefix_ada = q("SELECT DISTINCT SUBSTRING_INDEX(item_code, '-', 1) FROM item ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);

kepala('Buku', 'buku');
?>
<div class="judul-baris">
  <h1>Buku <small class="redup"><?= $total ?> judul</small></h1>
  <a class="tombol utama" href="?p=buku_form">＋ Tambah</a>
</div>

<form class="cari" method="get">
  <input type="hidden" name="p" value="buku">
  <input type="search" name="q" value="<?= h($cari) ?>" placeholder="Judul, pengarang, ISBN, atau kode (FIK-0003)">
  <select name="prefix" onchange="this.form.submit()">
    <option value="">Semua kode</option>
    <?php foreach ($prefix_ada as $pr): ?>
      <option value="<?= h($pr) ?>" <?= $pr === $prefix ? 'selected' : '' ?>><?= h($pr) ?></option>
    <?php endforeach; ?>
  </select>
  <button>Cari</button>
</form>

<ul class="daftar kartu">
<?php if (!$daftar): ?><li class="redup">Tidak ada buku yang cocok.</li><?php endif; ?>
<?php foreach ($daftar as $b): ?>
  <li><a href="?p=buku_detail&amp;id=<?= (int)$b['biblio_id'] ?>">
    <b><?= h($b['title']) ?></b>
    <small><?= h($b['pengarang'] ?: 'tanpa pengarang') ?><?= $b['publish_year'] ? ' · ' . h($b['publish_year']) : '' ?></small>
    <small class="kode"><?= h($b['kode'] ?: 'belum ada eksemplar') ?><?= $b['dipinjam'] ? ' · <span class="lencana">' . (int)$b['dipinjam'] . ' dipinjam</span>' : '' ?></small>
  </a></li>
<?php endforeach; ?>
</ul>
<?= halaman_nomor($total, $hal, $per, ['p' => 'buku', 'q' => $cari, 'prefix' => $prefix]) ?>
<?php
kaki();
