<?php
declare(strict_types=1);

$cari = (string)get('q');
$tipe = (int)get('tipe', 0);
$kelas = (string)get('kelas');
$f = (string)get('f');
$hal = max(1, (int)get('h', 1));
$per = 25;

$where = [];
$par = [];
if ($cari !== '') {
    $where[] = '(m.member_name LIKE ? OR m.member_id LIKE ?)';
    array_push($par, '%' . $cari . '%', $cari . '%');
}
if ($tipe) {
    $where[] = 'm.member_type_id = ?';
    $par[] = $tipe;
}
if ($kelas === '-') {
    $where[] = "(m.inst_name IS NULL OR m.inst_name = '')";
} elseif ($kelas !== '') {
    $where[] = 'm.inst_name = ?';
    $par[] = $kelas;
}
if ($f === 'kedaluwarsa') $where[] = 'm.expire_date < CURDATE()';
$w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = (int)nilai("SELECT COUNT(*) FROM member m $w", $par);
$daftar = semua("SELECT m.member_id, m.member_name, m.gender, m.inst_name, m.expire_date, t.member_type_name,
    (SELECT COUNT(*) FROM loan l WHERE l.member_id = m.member_id AND l.is_lent = 1 AND l.is_return = 0) pinjam
    FROM member m LEFT JOIN mst_member_type t ON t.member_type_id = m.member_type_id
    $w ORDER BY m.member_name LIMIT $per OFFSET " . (($hal - 1) * $per), $par);

$tipe_list = semua('SELECT member_type_id, member_type_name FROM mst_member_type ORDER BY member_type_id');
$kelas_list = q("SELECT DISTINCT inst_name FROM member WHERE inst_name <> '' ORDER BY inst_name")->fetchAll(PDO::FETCH_COLUMN);

kepala('Anggota', 'anggota');
?>
<div class="judul-baris">
  <h1>Anggota <small class="redup"><?= $total ?> orang</small></h1>
  <a class="tombol utama" href="?p=anggota_form">＋ Tambah</a>
</div>

<form class="cari" method="get">
  <input type="hidden" name="p" value="anggota">
  <input type="search" name="q" value="<?= h($cari) ?>" placeholder="Nama atau nomor anggota">
  <select name="tipe" onchange="this.form.submit()">
    <option value="0">Semua tipe</option>
    <?= pilihan($tipe_list, 'member_type_id', 'member_type_name', $tipe) ?>
  </select>
  <select name="kelas" onchange="this.form.submit()">
    <option value="">Semua kelas</option>
    <option value="-" <?= $kelas === '-' ? 'selected' : '' ?>>(belum diisi)</option>
    <?php foreach ($kelas_list as $kl): ?><option <?= $kl === $kelas ? 'selected' : '' ?>><?= h($kl) ?></option><?php endforeach; ?>
  </select>
  <?php if ($f): ?><input type="hidden" name="f" value="<?= h($f) ?>"><?php endif; ?>
  <button>Cari</button>
</form>
<p class="kecil"><a href="?p=anggota_kelas">Isi kelas banyak anggota sekaligus →</a></p>

<ul class="daftar kartu">
<?php if (!$daftar): ?><li class="redup">Tidak ada anggota yang cocok.</li><?php endif; ?>
<?php foreach ($daftar as $m): $habis = $m['expire_date'] < hari_ini(); ?>
  <li><a href="?p=anggota_form&amp;id=<?= h(rawurlencode($m['member_id'])) ?>">
    <b><?= h($m['member_name']) ?></b>
    <small><?= h($m['member_id']) ?> · <?= h($m['member_type_name'] ?: '-') ?> · <?= (int)$m['gender'] === 1 ? 'L' : 'P' ?> · <?= h($m['inst_name'] ?: 'kelas belum diisi') ?></small>
    <small><?= $habis ? '<span class="lencana merah">habis ' . tgl($m['expire_date']) . '</span>' : 'berlaku s.d. ' . tgl($m['expire_date']) ?>
      <?= $m['pinjam'] ? ' · <span class="lencana">' . (int)$m['pinjam'] . ' dipinjam</span>' : '' ?></small>
  </a></li>
<?php endforeach; ?>
</ul>
<?= halaman_nomor($total, $hal, $per, ['p' => 'anggota', 'q' => $cari, 'tipe' => $tipe, 'kelas' => $kelas, 'f' => $f]) ?>
<?php
kaki();
