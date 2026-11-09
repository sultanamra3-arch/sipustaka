<?php
declare(strict_types=1);

$id = (string)get('id');
$ubah = $id !== '';
$lama = $ubah ? satu('SELECT * FROM member WHERE member_id = ?', [$id]) : null;
if ($ubah && !$lama) {
    pesan('Anggota tidak ditemukan.', 'galat');
    ke('?p=anggota');
}

$tipe_list = semua('SELECT * FROM mst_member_type ORDER BY member_type_id');

$d = [
    'member_id' => $lama['member_id'] ?? '',
    'nama' => $lama['member_name'] ?? '',
    'gender' => isset($lama['gender']) ? (string)$lama['gender'] : '',
    'tipe' => (string)($lama['member_type_id'] ?? ($tipe_list[0]['member_type_id'] ?? 1)),
    'kelas' => $lama['inst_name'] ?? '',
    'lahir' => $lama['birth_date'] ?? '',
    'telepon' => $lama['member_phone'] ?? '',
    'email' => $lama['member_email'] ?? '',
    'alamat' => $lama['member_address'] ?? '',
    'catatan' => $lama['member_notes'] ?? '',
    'berlaku' => $lama['expire_date'] ?? '',
];
$galat = [];
$duplikat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('aksi') === 'perpanjang' && $ubah) {
        $h = layanan_perpanjang_anggota($id);
        pesan('Masa berlaku diperpanjang sampai ' . h(tgl($h['berlaku'])) . '.');
        ke('?p=anggota_form&id=' . rawurlencode($id));
    }
    $masuk = [];
    foreach (array_keys($d) as $kunci) {
        if (isset($_POST[$kunci])) $masuk[$kunci] = post($kunci);
    }
    $hasil = layanan_simpan_anggota($masuk, $ubah ? $id : null, (bool)post('paksa'));
    $d = array_merge($d, $masuk);
    $galat = $hasil['galat'];
    $duplikat = $hasil['duplikat'] ?? [];
    if ($hasil['ok']) {
        if ($ubah) {
            pesan('Data anggota tersimpan.');
        } else {
            pesan('Anggota <b>' . h($hasil['data']['nama']) . '</b> terdaftar dengan nomor <b>' . h($hasil['member_id'])
                . '</b>. Sandi Area Anggota OPAC = nomor anggota. Kartu bisa dicetak di halaman kartu.');
        }
        ke('?p=anggota_form&id=' . rawurlencode($hasil['member_id']));
    }
}

$pinjaman = $ubah ? semua('SELECT l.item_code, l.loan_date, l.due_date, b.title FROM loan l
    LEFT JOIN item i ON i.item_code = l.item_code LEFT JOIN biblio b ON b.biblio_id = i.biblio_id
    WHERE l.member_id = ? AND l.is_lent = 1 AND l.is_return = 0 ORDER BY l.due_date', [$id]) : [];
$kelas_list = q("SELECT DISTINCT inst_name FROM member WHERE inst_name <> '' ORDER BY inst_name")->fetchAll(PDO::FETCH_COLUMN);

kepala($ubah ? $d['nama'] : 'Tambah anggota', 'anggota');
?>
<div class="judul-baris">
  <h1><?= $ubah ? h($lama['member_name']) : 'Tambah anggota' ?></h1>
  <a class="tombol" href="?p=anggota">‹ Anggota</a>
</div>

<?php foreach ($galat as $g): ?><div class="pesan galat"><?= h($g) ?></div><?php endforeach; ?>
<?php if ($duplikat): ?><div class="pesan peringatan">Sudah ada anggota bernama sama:
  <?php foreach ($duplikat as $x): ?><a href="?p=anggota_form&amp;id=<?= h(rawurlencode($x['member_id'])) ?>" target="_blank"><?= h($x['member_name']) ?> (<?= h($x['member_id']) ?><?= $x['inst_name'] ? ', ' . h($x['inst_name']) : '' ?>)</a> <?php endforeach; ?>
  <br>Centang "tetap simpan" di bawah jika memang orang berbeda.</div><?php endif; ?>

<?php if ($ubah): $habis = $lama['expire_date'] < hari_ini(); ?>
<div class="kartu">
  <p>Nomor anggota <b class="besar"><?= h($lama['member_id']) ?></b></p>
  <p>Berlaku sampai <b><?= tgl($lama['expire_date']) ?></b> <?= $habis ? '<span class="lencana merah">sudah habis</span>' : '' ?></p>
  <form method="post" class="sebaris">
    <?= input_token() ?><input type="hidden" name="aksi" value="perpanjang">
    <button>Perpanjang ke <?= tgl(masa_berlaku_baru((int)$lama['member_type_id'])) ?></button>
  </form>
  <?php if ($pinjaman): ?>
    <h3>Sedang dipinjam</h3>
    <ul class="daftar"><?php foreach ($pinjaman as $l): ?>
      <li><b><?= h($l['title'] ?: $l['item_code']) ?></b><small><?= h($l['item_code']) ?> · kembali <?= tgl($l['due_date']) ?>
        <?= $l['due_date'] < hari_ini() ? '<span class="lencana merah">terlambat</span>' : '' ?></small></li>
    <?php endforeach; ?></ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<form method="post" class="formulir" autocomplete="off">
  <?= input_token() ?>
  <fieldset>
    <legend><?= $ubah ? 'Ubah data' : 'Data anggota' ?></legend>
    <?php if (!$ubah): ?>
    <label>Nomor anggota <small>(kosongkan = dibuat acak: siswa 10 digit, guru 9 digit)</small>
      <input name="member_id" inputmode="numeric" maxlength="20" value="<?= h($d['member_id']) ?>"></label>
    <?php endif; ?>
    <label>Nama lengkap *<input name="nama" required value="<?= h($d['nama']) ?>"></label>
    <div class="baris2">
      <label>Jenis kelamin *
        <select name="gender" required>
          <option value="">— pilih —</option>
          <option value="1" <?= $d['gender'] === '1' ? 'selected' : '' ?>>Laki-laki</option>
          <option value="0" <?= $d['gender'] === '0' ? 'selected' : '' ?>>Perempuan</option>
        </select>
      </label>
      <label>Tipe anggota<select name="tipe"><?= pilihan($tipe_list, 'member_type_id', 'member_type_name', $d['tipe']) ?></select></label>
    </div>
    <label>Kelas <small>(mis. VII A; untuk guru isi "Guru")</small><input name="kelas" list="dl-kelas" value="<?= h($d['kelas']) ?>"></label>
    <div class="baris2">
      <label>Tanggal lahir<input type="date" name="lahir" value="<?= h($d['lahir']) ?>"></label>
      <label>Telepon / WA<input name="telepon" inputmode="tel" value="<?= h($d['telepon']) ?>"></label>
    </div>
    <label>Email<input type="email" name="email" value="<?= h($d['email']) ?>"></label>
    <label>Alamat<input name="alamat" value="<?= h($d['alamat']) ?>"></label>
    <?php if ($ubah): ?>
      <label>Berlaku sampai<input type="date" name="berlaku" value="<?= h($d['berlaku']) ?>" required></label>
    <?php endif; ?>
    <label>Catatan<textarea name="catatan" rows="2"><?= h($d['catatan']) ?></textarea></label>
    <?php if (!$ubah || $duplikat): ?>
      <label class="centang"><input type="checkbox" name="paksa" value="1"> Tetap simpan walau ada nama yang sama</label>
    <?php endif; ?>
    <?php if (!$ubah): ?>
      <p class="redup kecil">Masa berlaku otomatis: <?= tgl(masa_berlaku_baru((int)$d['tipe'])) ?>.</p>
    <?php endif; ?>
  </fieldset>
  <button class="utama lebar"><?= $ubah ? 'Simpan perubahan' : 'Daftarkan anggota' ?></button>
</form>
<datalist id="dl-kelas"><?php foreach ($kelas_list as $kl): ?><option value="<?= h($kl) ?>"><?php endforeach; ?></datalist>
<?php
kaki();
