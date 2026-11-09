<?php
declare(strict_types=1);

$id = (int)get('id', 0);
$b = satu('SELECT b.*, p.publisher_name, pl.place_name, l.language_name, g.gmd_name FROM biblio b
    LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
    LEFT JOIN mst_place pl ON pl.place_id = b.publish_place_id
    LEFT JOIN mst_language l ON l.language_id = b.language_id
    LEFT JOIN mst_gmd g ON g.gmd_id = b.gmd_id
    WHERE b.biblio_id = ?', [$id]);
if (!$b) {
    pesan('Buku tidak ditemukan.', 'galat');
    ke('?p=buku');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');

    if ($aksi === 'tambah_eksemplar') {
        $prefix = prefix_dari_form($_POST) ?? prefix_valid((string)post('prefix_lama'));
        $h = layanan_tambah_eksemplar($id, (int)post('jumlah'), $prefix, post('sumber'), (int)post('harga') ?: null,
            (string)post('tgl_terima'), (string)post('lokasi'));
        $h['ok'] ? pesan('Eksemplar baru: <b>' . h(implode(', ', $h['kode'])) . '</b>') : pesan(h(implode(' ', $h['galat'])), 'galat');
    } elseif ($aksi === 'status') {
        $kode = (string)nilai('SELECT item_code FROM item WHERE item_id = ? AND biblio_id = ?', [(int)post('item_id'), $id]);
        $h = layanan_status_eksemplar($kode, (string)post('status'));
        $h['ok'] ? pesan('Status ' . h($kode) . ' menjadi ' . h($h['nama_status']) . '.') : pesan(h(implode(' ', $h['galat'])), 'galat');
    } elseif ($aksi === 'hapus_eksemplar') {
        $kode = (string)nilai('SELECT item_code FROM item WHERE item_id = ? AND biblio_id = ?', [(int)post('item_id'), $id]);
        $h = layanan_hapus_eksemplar($kode);
        $h['ok'] ? pesan('Eksemplar ' . h($kode) . ' dihapus.') : pesan(h(implode(' ', $h['galat'])), 'galat');
    }
    ke('?p=buku_detail&id=' . $id);
}

$pengarang = q('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = ? ORDER BY ba.level, a.author_name', [$id])->fetchAll(PDO::FETCH_COLUMN);
$subjek = q('SELECT t.topic FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id WHERE bt.biblio_id = ? ORDER BY bt.level', [$id])->fetchAll(PDO::FETCH_COLUMN);
$item = semua('SELECT i.*, c.coll_type_name, lo.location_name,
    (SELECT CONCAT(m.member_name, CHAR(124 USING utf8mb4), l.due_date) FROM loan l JOIN member m ON m.member_id = l.member_id
        WHERE l.item_code = i.item_code AND l.is_lent = 1 AND l.is_return = 0 ORDER BY l.loan_id DESC LIMIT 1) pinjam,
    (SELECT COUNT(*) FROM loan l2 WHERE l2.item_code = i.item_code) riwayat
    FROM item i LEFT JOIN mst_coll_type c ON c.coll_type_id = i.coll_type_id
    LEFT JOIN mst_location lo ON lo.location_id = i.location_id
    WHERE i.biblio_id = ? ORDER BY i.item_code', [$id]);
$status = daftar_status_eksemplar();
$prefix_lama = $item ? prefix_dari_kode($item[count($item) - 1]['item_code']) : '';
$gambar = $b['image'] ? rtrim((string)k('opac_url'), '/') . '/images/docs/' . rawurlencode($b['image']) : '';

kepala($b['title'], 'buku');
?>
<div class="judul-baris">
  <a class="tombol" href="?p=buku">‹ Buku</a>
  <a class="tombol" href="?p=buku_form&amp;id=<?= $id ?>">Ubah data</a>
</div>

<div class="kartu buku-detail">
  <?php if ($gambar): ?><img class="sampul" src="<?= h($gambar) ?>" alt="" loading="lazy" onerror="this.remove()"><?php endif; ?>
  <div>
    <h1><?= h($b['title']) ?></h1>
    <?php if ($b['opac_hide']): ?><span class="lencana">disembunyikan dari OPAC</span><?php endif; ?>
    <dl>
      <dt>Pengarang</dt><dd><?= h(implode('; ', $pengarang) ?: '-') ?></dd>
      <?php if ($b['sor']): ?><dt>Tanggung jawab</dt><dd><?= h($b['sor']) ?></dd><?php endif; ?>
      <dt>Terbitan</dt><dd><?= h(implode(', ', array_filter([$b['place_name'], $b['publisher_name'], $b['publish_year']])) ?: '-') ?></dd>
      <?php if ($b['edition']): ?><dt>Edisi</dt><dd><?= h($b['edition']) ?></dd><?php endif; ?>
      <dt>ISBN</dt><dd><?= h($b['isbn_issn'] ?: '-') ?></dd>
      <dt>Fisik</dt><dd><?= h($b['collation'] ?: '-') ?></dd>
      <?php if ($b['series_title']): ?><dt>Seri</dt><dd><?= h($b['series_title']) ?></dd><?php endif; ?>
      <dt>Klasifikasi</dt><dd><?= h($b['classification'] ?: '-') ?> · No. panggil <b><?= h($b['call_number'] ?: '-') ?></b></dd>
      <dt>Subjek</dt><dd><?= h(implode('; ', $subjek) ?: '-') ?></dd>
      <dt>Bahasa</dt><dd><?= h($b['language_name'] ?: '-') ?> · <?= h($b['gmd_name'] ?: '-') ?></dd>
      <?php if ($b['notes']): ?><dt>Catatan</dt><dd><?= nl2br(h($b['notes'])) ?></dd><?php endif; ?>
    </dl>
    <p class="kecil"><a href="<?= h(rtrim((string)k('opac_url'), '/')) ?>/index.php?p=show_detail&amp;id=<?= $id ?>" target="_blank" rel="noopener">Lihat di katalog online ↗</a></p>
  </div>
</div>

<div class="kartu">
  <h2>Eksemplar (<?= count($item) ?>)</h2>
  <?php if (!$item): ?><p class="redup">Belum ada eksemplar.</p><?php endif; ?>
  <ul class="daftar eksemplar">
    <?php foreach ($item as $i): ?>
      <li>
        <div class="eks-atas">
          <b class="eks-kode"><?= h($i['item_code']) ?></b>
          <small><?= h($i['coll_type_name'] ?: '-') ?> · <?= (int)$i['source'] === 1 ? 'Beli' : 'Hibah' ?><?= $i['price'] ? ' · Rp' . number_format((int)$i['price'], 0, ',', '.') : '' ?>
            · <?= h($i['location_name'] ?: '-') ?> · diterima <?= tgl($i['received_date']) ?></small>
        </div>
        <div class="eks-aksi">
          <?php if ($i['pinjam']): [$nm, $jt] = explode('|', $i['pinjam']); ?>
            <span><span class="lencana">Dipinjam</span> <small><?= h($nm) ?>, kembali <?= tgl($jt) ?></small></span>
          <?php else: ?>
          <form method="post" class="sebaris">
            <?= input_token() ?><input type="hidden" name="aksi" value="status"><input type="hidden" name="item_id" value="<?= (int)$i['item_id'] ?>">
            <select name="status" aria-label="Status <?= h($i['item_code']) ?>" onchange="this.form.submit()"><?= pilihan($status, 'item_status_id', 'item_status_name', $i['item_status_id'] ?: '0') ?></select>
          </form>
          <?php endif; ?>
          <?php if (!$i['riwayat']): ?>
          <form method="post" class="sebaris" onsubmit="return confirm('Hapus eksemplar <?= h($i['item_code']) ?>?')">
            <?= input_token() ?><input type="hidden" name="aksi" value="hapus_eksemplar"><input type="hidden" name="item_id" value="<?= (int)$i['item_id'] ?>">
            <button class="kecil bahaya">Hapus</button>
          </form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</div>

<details class="kartu" <?= $item ? '' : 'open' ?>>
  <summary><b>＋ Tambah eksemplar</b></summary>
  <form method="post" class="formulir" id="form-eksemplar">
    <?= input_token() ?>
    <input type="hidden" name="aksi" value="tambah_eksemplar">
    <input type="hidden" name="prefix_lama" value="<?= h($prefix_lama) ?>">
    <?php
    $jenis_awal = isset(JENIS_NONPAKET[$prefix_lama]) ? $prefix_lama : ($prefix_lama ? 'PAKET' : '');
    preg_match('/^([A-Z]{3})([7-9])(G?)$/', $prefix_lama, $pm);
    ?>
    <label>Jenis buku
      <select name="jenis" id="jenis" required>
        <option value="">— pilih —</option>
        <option value="PAKET" <?= $jenis_awal === 'PAKET' ? 'selected' : '' ?>>Buku paket / pelajaran</option>
        <?php foreach (JENIS_NONPAKET as $kd => [$ket]): ?>
          <option value="<?= $kd ?>" <?= $jenis_awal === $kd ? 'selected' : '' ?>><?= $kd ?> · <?= h($ket) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div id="bagian-paket" class="baris3" <?= $jenis_awal === 'PAKET' ? '' : 'hidden' ?>>
      <label>Mapel<select name="mapel" id="mapel"><option value="">— mapel —</option>
        <?php foreach (MAPEL as $kd => $nm): ?><option value="<?= $kd ?>" <?= ($pm[1] ?? '') === $kd ? 'selected' : '' ?>><?= $kd ?> · <?= h($nm) ?></option><?php endforeach; ?>
      </select></label>
      <label>Kelas<select name="kelas_buku" id="kelas_buku"><option value="">—</option>
        <?php foreach ([7, 8, 9] as $kl): ?><option value="<?= $kl ?>" <?= ($pm[2] ?? '') === (string)$kl ? 'selected' : '' ?>><?= $kl ?></option><?php endforeach; ?>
      </select></label>
      <label class="centang"><input type="checkbox" name="buku_guru" id="buku_guru" value="1" <?= !empty($pm[3]) ? 'checked' : '' ?>> Buku guru</label>
    </div>
    <div id="pratinjau-kode" class="pratinjau" hidden></div>
    <div class="baris3">
      <label>Jumlah<input type="number" name="jumlah" min="1" max="100" value="1" required></label>
      <label>Sumber<select name="sumber" id="sumber"><option value="">Otomatis</option><option value="1">Beli</option><option value="2">Hibah / bantuan</option></select></label>
      <label>Harga per buku (Rp)<input type="number" name="harga" min="0" inputmode="numeric"></label>
    </div>
    <div class="baris2">
      <label>Tanggal terima<input type="date" name="tgl_terima" value="<?= hari_ini() ?>"></label>
      <label>Lokasi<select name="lokasi"><?= pilihan(daftar_lokasi(), 'location_id', 'location_name', k('lokasi_bawaan', '1')) ?></select></label>
    </div>
    <button class="utama lebar">Tambah eksemplar</button>
  </form>
</details>
<?php
kaki();
