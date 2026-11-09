<?php
declare(strict_types=1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pilih = array_values(array_filter((array)($_POST['pilih'] ?? []), 'is_string'));
    $h = layanan_isi_kelas($pilih, (string)post('kelas'));
    $h['ok'] ? pesan(h((string)$h['jumlah']) . ' anggota dimasukkan ke kelas <b>' . h(trim((string)post('kelas'))) . '</b>.')
        : pesan(h(implode(' ', $h['galat'])), 'galat');
    ke('?' . http_build_query(['p' => 'anggota_kelas', 'tampil' => post('tampil'), 'q' => post('q')]));
}

$tampil = (string)get('tampil', 'kosong');
$cari = (string)get('q');
$where = ["m.member_type_id <> COALESCE((SELECT member_type_id FROM mst_member_type WHERE member_type_name LIKE '%guru%' LIMIT 1), -1)"];
$par = [];
if ($tampil === 'kosong') $where[] = "(m.inst_name IS NULL OR m.inst_name = '')";
if ($cari !== '') {
    $where[] = 'm.member_name LIKE ?';
    $par[] = '%' . $cari . '%';
}
$daftar = semua('SELECT m.member_id, m.member_name, m.gender, m.inst_name FROM member m WHERE ' . implode(' AND ', $where)
    . ' ORDER BY m.member_name LIMIT 500', $par);
$kelas_list = q("SELECT inst_name, COUNT(*) n FROM member WHERE inst_name <> '' GROUP BY inst_name ORDER BY inst_name")->fetchAll();

kepala('Isi kelas', 'anggota');
?>
<div class="judul-baris">
  <h1>Isi kelas sekaligus</h1>
  <a class="tombol" href="?p=anggota">‹ Anggota</a>
</div>
<p class="redup">Centang siswa satu kelas, tulis nama kelasnya, lalu simpan. Ulangi untuk kelas berikutnya.</p>

<form class="cari" method="get">
  <input type="hidden" name="p" value="anggota_kelas">
  <input type="search" name="q" value="<?= h($cari) ?>" placeholder="Saring nama siswa">
  <select name="tampil" onchange="this.form.submit()">
    <option value="kosong" <?= $tampil === 'kosong' ? 'selected' : '' ?>>Belum ada kelas</option>
    <option value="semua" <?= $tampil === 'semua' ? 'selected' : '' ?>>Semua siswa</option>
  </select>
  <button>Saring</button>
</form>

<?php if ($kelas_list): ?>
<p class="kecil">Kelas yang sudah ada:
  <?php foreach ($kelas_list as $k): ?><a class="lencana" href="?p=anggota&amp;kelas=<?= h(rawurlencode($k['inst_name'])) ?>"><?= h($k['inst_name']) ?> (<?= (int)$k['n'] ?>)</a> <?php endforeach; ?>
</p>
<?php endif; ?>

<form method="post" id="form-kelas">
  <?= input_token() ?>
  <input type="hidden" name="tampil" value="<?= h($tampil) ?>"><input type="hidden" name="q" value="<?= h($cari) ?>">
  <div class="kartu lengket">
    <div class="baris2">
      <label>Nama kelas<input name="kelas" list="dl-kelas" placeholder="mis. VII A" required></label>
      <div class="tombol-sebaris"><button class="utama">Simpan (<span id="jml-pilih">0</span>)</button></div>
    </div>
    <label class="centang"><input type="checkbox" id="pilih-semua"> Pilih semua yang tampil (<?= count($daftar) ?>)</label>
  </div>
  <ul class="daftar kartu centang-daftar">
    <?php if (!$daftar): ?><li class="redup">Tidak ada siswa yang perlu diisi.</li><?php endif; ?>
    <?php foreach ($daftar as $m): ?>
      <li><label><input type="checkbox" name="pilih[]" value="<?= h($m['member_id']) ?>">
        <span><b><?= h($m['member_name']) ?></b><small><?= h($m['member_id']) ?> · <?= (int)$m['gender'] === 1 ? 'L' : 'P' ?><?= $m['inst_name'] ? ' · ' . h($m['inst_name']) : '' ?></small></span></label></li>
    <?php endforeach; ?>
  </ul>
</form>
<datalist id="dl-kelas"><?php foreach ($kelas_list as $k): ?><option value="<?= h($k['inst_name']) ?>"><?php endforeach; ?>
  <?php foreach (['VII', 'VIII', 'IX'] as $t) foreach (['A', 'B', 'C', 'D'] as $r): ?><option value="<?= "$t $r" ?>"><?php endforeach; ?></datalist>
<?php
kaki();
