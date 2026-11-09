<?php
declare(strict_types=1);

$id = (int)get('id', 0);
$ubah = $id > 0;

if ($ubah) {
    $lama = buku_dari_db($id);
    if (!$lama) {
        pesan('Buku tidak ditemukan.', 'galat');
        ke('?p=buku');
    }
    $d = array_merge(buku_kosong(), $lama);
} else {
    $d = buku_kosong();
    // Draf dari Asisten AI atau dari foto
    $draf = (string)get('draf');
    if ($draf !== '' && isset($_SESSION['usulan'][$draf]['data'])) {
        foreach ($_SESSION['usulan'][$draf]['data'] as $kk => $vv) {
            if (array_key_exists($kk, $d)) $d[$kk] = teks($vv);
        }
        $sb = strtolower((string)$d['sumber']);
        $d['sumber'] = str_contains($sb, 'beli') || $sb === '1' ? '1' : (($sb !== '' && $sb !== '0') ? '2' : '');
        if (!empty($d['prefix'])) {
            $pr = prefix_valid((string)$d['prefix']);
            if ($pr && preg_match('/^([A-Z]{3})([7-9])(G?)$/', $pr, $m) && isset(MAPEL[$m[1]])) {
                $d['jenis'] = 'PAKET'; $d['mapel'] = $m[1]; $d['kelas_buku'] = $m[2]; $d['buku_guru'] = $m[3] ? '1' : '';
            } elseif ($pr) {
                $d['jenis'] = $pr;
            }
        }
    }
}

$galat = [];
$duplikat = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masuk = [];
    foreach (array_keys(buku_kosong()) as $kunci) {
        if ($kunci === 'prefix') continue;
        if (isset($_POST[$kunci])) $masuk[$kunci] = post($kunci);
    }
    $masuk['sembunyi'] = post('sembunyi') ? 1 : 0;
    if (!$ubah) {
        $masuk['titipan'] = post('titipan') ? '1' : '';
        $masuk['buku_guru'] = post('buku_guru') ? '1' : '';
    }
    $hasil = layanan_simpan_buku($masuk, $ubah ? $id : null, (bool)post('paksa'));
    $d = array_merge($d, array_intersect_key($masuk, $d));
    $galat = $hasil['galat'];
    $duplikat = $hasil['duplikat'];
    if ($hasil['ok']) {
        if ($ubah) {
            pesan('Perubahan buku tersimpan.');
        } else {
            pesan('Buku <b>' . h($hasil['data']['judul']) . '</b> tersimpan'
                . ($hasil['kode'] ? ' dengan kode <b>' . h(implode(', ', $hasil['kode'])) . '</b>' : ' (belum ada eksemplar)')
                . '. Tulis kode ini di buku fisiknya.');
        }
        ke('?p=buku_detail&id=' . $hasil['biblio_id']);
    }
}

$terbit = semua('SELECT publisher_name FROM mst_publisher ORDER BY publisher_name');
$kota = semua('SELECT place_name FROM mst_place ORDER BY place_name');

kepala($ubah ? 'Ubah buku' : 'Tambah buku', 'buku');
?>
<div class="judul-baris">
  <h1><?= $ubah ? 'Ubah buku' : 'Tambah buku' ?></h1>
  <a class="tombol" href="<?= $ubah ? '?p=buku_detail&amp;id=' . $id : '?p=buku' ?>">Batal</a>
</div>

<?php foreach ($galat as $g): ?><div class="pesan galat"><?= h($g) ?></div><?php endforeach; ?>

<form method="post" class="formulir" id="form-buku" autocomplete="off" data-token="<?= h(token()) ?>">
  <?= input_token() ?>

  <?php if ($duplikat): ?>
  <div class="pesan peringatan">
    <b>Mungkin sudah ada:</b>
    <ul><?php foreach ($duplikat as $x): ?>
      <li><a href="?p=buku_detail&amp;id=<?= (int)$x['biblio_id'] ?>" target="_blank"><?= h($x['title']) ?></a> <?= $x['isbn_issn'] ? '(' . h($x['isbn_issn']) . ')' : '' ?></li>
    <?php endforeach; ?></ul>
    Jika ini buku yang sama, cukup <b>tambah eksemplar</b> di halaman buku tersebut.
    <label class="centang"><input type="checkbox" name="paksa" value="1"> Ini judul berbeda, tetap simpan</label>
  </div>
  <?php endif; ?>

  <?php if (!$ubah): ?>
  <div class="kartu foto-isi">
    <b>✨ Isi otomatis dari foto buku</b>
    <?php if (ai_siap()): ?>
    <div class="dua-tombol">
      <label class="tombol utama">📷 Kamera<input type="file" id="foto-buku-kamera" accept="image/*" capture="environment" hidden></label>
      <label class="tombol">🖼 Galeri<input type="file" id="foto-buku" accept="image/*" multiple hidden></label>
    </div>
    <div id="foto-kumpulan" class="pratinjau-foto kiri"></div>
    <button type="button" id="foto-baca" class="lebar emas" hidden>Baca dengan AI</button>
    <small class="redup">Foto 1–3 halaman: sampul depan, sampul belakang (ISBN), halaman hak cipta. Bisa memotret beberapa kali, lalu tekan "Baca dengan AI".</small>
    <?php else: ?>
    <small class="redup">Aktifkan dulu di <a href="?p=pengaturan_ai">Pengaturan AI</a>.</small>
    <?php endif; ?>
    <div id="foto-status" class="kecil"></div>
  </div>
  <fieldset>
    <legend>Kode eksemplar</legend>
    <label>Jenis buku
      <select name="jenis" id="jenis" required>
        <option value="">— pilih —</option>
        <option value="PAKET" <?= $d['jenis'] === 'PAKET' ? 'selected' : '' ?>>Buku paket / pelajaran</option>
        <?php foreach (JENIS_NONPAKET as $kd => [$ket]): ?>
          <option value="<?= $kd ?>" <?= $d['jenis'] === $kd ? 'selected' : '' ?>><?= $kd ?> · <?= h($ket) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div id="bagian-paket" class="baris3" <?= $d['jenis'] === 'PAKET' ? '' : 'hidden' ?>>
      <label>Mapel
        <select name="mapel" id="mapel">
          <option value="">— mapel —</option>
          <?php foreach (MAPEL as $kd => $nm): ?>
            <option value="<?= $kd ?>" <?= $d['mapel'] === $kd ? 'selected' : '' ?>><?= $kd ?> · <?= h($nm) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Kelas
        <select name="kelas_buku" id="kelas_buku">
          <option value="">—</option>
          <?php foreach ([7, 8, 9] as $kl): ?><option value="<?= $kl ?>" <?= (string)$d['kelas_buku'] === (string)$kl ? 'selected' : '' ?>><?= $kl ?></option><?php endforeach; ?>
        </select>
      </label>
      <label class="centang"><input type="checkbox" name="buku_guru" id="buku_guru" value="1" <?= $d['buku_guru'] ? 'checked' : '' ?>> Buku guru</label>
    </div>
    <div id="pratinjau-kode" class="pratinjau" hidden></div>
    <div class="baris3">
      <label>Jumlah eksemplar<input type="number" name="jumlah" min="0" max="100" value="<?= h($d['jumlah']) ?>"></label>
      <label>Sumber
        <select name="sumber" id="sumber">
          <option value="">Otomatis</option>
          <option value="1" <?= (string)$d['sumber'] === '1' ? 'selected' : '' ?>>Beli</option>
          <option value="2" <?= (string)$d['sumber'] === '2' ? 'selected' : '' ?>>Hibah / bantuan</option>
        </select>
      </label>
      <label>Harga per buku (Rp)<input type="number" name="harga" min="0" inputmode="numeric" value="<?= h($d['harga']) ?>"></label>
    </div>
    <div class="baris3">
      <label>Tanggal terima<input type="date" name="tgl_terima" value="<?= h($d['tgl_terima']) ?>"></label>
      <label>Lokasi<select name="lokasi"><?= pilihan(daftar_lokasi(), 'location_id', 'location_name', $d['lokasi']) ?></select></label>
    </div>
    <label class="centang"><input type="checkbox" name="titipan" id="titipan" value="1" <?= $d['titipan'] ? 'checked' : '' ?>> Buku titipan pribadi (bukan milik perpustakaan)</label>
    <label id="bagian-pemilik" <?= $d['titipan'] ? '' : 'hidden' ?>>Nama pemilik<input name="pemilik" value="<?= h($d['pemilik']) ?>"></label>
  </fieldset>
  <?php endif; ?>

  <fieldset>
    <legend>Data buku</legend>
    <label>Judul *<input name="judul" required value="<?= h($d['judul']) ?>" placeholder="Judul utama : anak judul"></label>
    <label>Pengarang <small>(satu nama per baris, pengarang utama di atas; format "Belakang, Depan")</small>
      <textarea name="pengarang" rows="2" id="pengarang"><?= h($d['pengarang']) ?></textarea></label>
    <label>Pernyataan tanggung jawab <small>(opsional, mis. "penyunting, Tim Narasi")</small><input name="sor" value="<?= h($d['sor']) ?>"></label>
    <div class="baris2">
      <label>Penerbit<input name="penerbit" list="dl-penerbit" value="<?= h($d['penerbit']) ?>"></label>
      <label>Kota terbit<input name="tempat" list="dl-kota" value="<?= h($d['tempat']) ?>"></label>
    </div>
    <div class="baris3">
      <label>Tahun<input name="tahun" inputmode="numeric" maxlength="4" value="<?= h($d['tahun']) ?>"></label>
      <label>ISBN<input name="isbn" value="<?= h($d['isbn']) ?>"></label>
      <label>Edisi / cetakan<input name="edisi" value="<?= h($d['edisi']) ?>"></label>
    </div>
    <label>Deskripsi fisik <small>(mis. "xii, 250 hlm. : ilus. ; 21 cm")</small><input name="kolasi" value="<?= h($d['kolasi']) ?>"></label>
    <label>Judul seri<input name="seri" value="<?= h($d['seri']) ?>"></label>
    <div class="baris2">
      <label>Klasifikasi (DDC)<input name="klasifikasi" id="klasifikasi" value="<?= h($d['klasifikasi']) ?>" placeholder="mis. 899.221 3"></label>
      <label>No. panggil <small>(kosong = otomatis)</small><input name="no_panggil" id="no_panggil" value="<?= h($d['no_panggil']) ?>"></label>
    </div>
    <label>Subjek <small>(satu per baris)</small><textarea name="subjek" rows="2"><?= h($d['subjek']) ?></textarea></label>
    <div class="baris2">
      <label>Bahasa<select name="bahasa"><?= pilihan(daftar_bahasa(), 'language_id', 'language_name', $d['bahasa']) ?></select></label>
      <label>Bentuk (GMD)<select name="gmd"><?= pilihan(daftar_gmd(), 'gmd_id', 'gmd_name', $d['gmd']) ?></select></label>
    </div>
    <label>Catatan<textarea name="catatan" rows="2"><?= h($d['catatan']) ?></textarea></label>
    <label class="centang"><input type="checkbox" name="sembunyi" value="1" <?= $d['sembunyi'] ? 'checked' : '' ?>> Sembunyikan dari katalog online (OPAC)</label>
  </fieldset>

  <button class="utama lebar"><?= $ubah ? 'Simpan perubahan' : 'Simpan buku' ?></button>
</form>

<datalist id="dl-penerbit"><?php foreach ($terbit as $r): ?><option value="<?= h($r['publisher_name']) ?>"><?php endforeach; ?></datalist>
<datalist id="dl-kota"><?php foreach ($kota as $r): ?><option value="<?= h($r['place_name']) ?>"><?php endforeach; ?></datalist>
<?php
kaki();
