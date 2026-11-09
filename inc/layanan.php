<?php
/*
 * Layanan simpan data: dipakai formulir dan Asisten AI,
 * supaya aturan sekolah hanya ditulis di satu tempat.
 */
declare(strict_types=1);

/* ================= BUKU ================= */

function buku_kosong(): array
{
    return [
        'judul' => '', 'sor' => '', 'pengarang' => '', 'edisi' => '', 'isbn' => '', 'penerbit' => '',
        'tempat' => '', 'tahun' => '', 'kolasi' => '', 'seri' => '', 'klasifikasi' => '', 'no_panggil' => '',
        'bahasa' => 'id', 'gmd' => (int)(nilai("SELECT gmd_id FROM mst_gmd WHERE gmd_name = 'Text'") ?: 1),
        'subjek' => '', 'catatan' => '', 'sembunyi' => 0,
        // eksemplar (hanya saat tambah)
        'prefix' => '', 'jenis' => '', 'mapel' => '', 'kelas_buku' => '', 'buku_guru' => '',
        'jumlah' => 1, 'sumber' => '', 'harga' => '', 'tgl_terima' => hari_ini(),
        'lokasi' => (string)k('lokasi_bawaan', '1'), 'titipan' => '', 'pemilik' => '',
    ];
}

function buku_dari_db(int $id): ?array
{
    $b = satu('SELECT b.*, p.publisher_name, pl.place_name FROM biblio b
        LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
        LEFT JOIN mst_place pl ON pl.place_id = b.publish_place_id WHERE b.biblio_id = ?', [$id]);
    if (!$b) return null;
    return [
        'judul' => $b['title'], 'sor' => (string)$b['sor'],
        'pengarang' => implode("\n", q('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = ? ORDER BY ba.level, a.author_name', [$id])->fetchAll(PDO::FETCH_COLUMN)),
        'edisi' => (string)$b['edition'], 'isbn' => (string)$b['isbn_issn'], 'penerbit' => (string)$b['publisher_name'],
        'tempat' => (string)$b['place_name'], 'tahun' => (string)$b['publish_year'], 'kolasi' => (string)$b['collation'],
        'seri' => (string)$b['series_title'], 'klasifikasi' => (string)$b['classification'], 'no_panggil' => (string)$b['call_number'],
        'bahasa' => (string)($b['language_id'] ?: 'id'), 'gmd' => (int)($b['gmd_id'] ?: 1),
        'subjek' => implode("\n", q('SELECT t.topic FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id WHERE bt.biblio_id = ? ORDER BY bt.level, t.topic', [$id])->fetchAll(PDO::FETCH_COLUMN)),
        'catatan' => (string)$b['notes'], 'sembunyi' => (int)$b['opac_hide'],
    ];
}

/** Ubah nilai bebas (array / teks / angka) menjadi teks rapi. */
function teks($v): string
{
    if (is_array($v)) return implode("\n", array_map('strval', $v));
    if (is_bool($v)) return $v ? '1' : '';
    return trim((string)($v ?? ''));
}

function normal_sumber($v, string $prefix): int
{
    $s = strtolower(teks($v));
    if ($s === '1' || str_contains($s, 'beli')) return 1;
    if ($s === '2' || str_contains($s, 'hibah') || str_contains($s, 'hadiah') || str_contains($s, 'bantuan')) return 2;
    return sumber_bawaan($prefix);
}

function normal_bahasa(string $v): string
{
    $v = trim($v);
    if ($v === '') return 'id';
    $id = nilai('SELECT language_id FROM mst_language WHERE language_id = ? OR LOWER(language_name) = LOWER(?) LIMIT 1', [$v, $v]);
    if ($id !== null && $id !== '') return (string)$id;
    $peta = ['indonesian' => 'id', 'bahasa indonesia' => 'id', 'inggris' => 'en', 'english' => 'en', 'arabic' => '3'];
    return $peta[strtolower($v)] ?? 'id';
}

/**
 * Simpan (tambah atau ubah) buku.
 * $masuk boleh sebagian saja saat mengubah: kolom yang tidak ada tidak diubah.
 * Hasil: ['ok'=>bool, 'galat'=>[], 'duplikat'=>[], 'biblio_id'=>?, 'kode'=>[], 'data'=>array]
 */
function layanan_simpan_buku(array $masuk, ?int $id = null, bool $paksa = false, bool $hanya_periksa = false): array
{
    $ubah = $id !== null && $id > 0;
    $lama = null;
    if ($ubah) {
        $lama = buku_dari_db($id);
        if (!$lama) return ['ok' => false, 'galat' => ['Buku #' . $id . ' tidak ditemukan.'], 'duplikat' => []];
        $d = array_merge(buku_kosong(), $lama);
        $no_panggil_lama = $lama['no_panggil'];
    } else {
        $d = buku_kosong();
    }
    foreach ($masuk as $kunci => $v) {
        if (array_key_exists($kunci, $d)) $d[$kunci] = is_int($d[$kunci]) && !is_array($v) ? (int)$v : teks($v);
    }
    // Jika klasifikasi/pengarang/judul berubah tetapi no. panggil tidak diisi, buat ulang otomatis
    if ($ubah && !array_key_exists('no_panggil', $masuk)
        && (isset($masuk['klasifikasi']) || isset($masuk['pengarang']) || isset($masuk['judul']))) {
        $d['no_panggil'] = '';
    }

    $pengarang = pisah_baris((string)$d['pengarang']);
    $subjek = pisah_baris((string)$d['subjek']);
    $galat = [];

    if (trim((string)$d['judul']) === '') $galat[] = 'Judul wajib diisi.';
    if (mb_strlen((string)$d['isbn']) > 32) $galat[] = 'ISBN terlalu panjang (maks 32 karakter). Kode produk Erlangga simpan di catatan.';
    if ($d['tahun'] !== '' && !preg_match('/^[0-9]{4}$/', (string)$d['tahun'])) $galat[] = 'Tahun terbit harus 4 angka.';
    $d['bahasa'] = normal_bahasa((string)$d['bahasa']);
    if (!satu('SELECT 1 FROM mst_gmd WHERE gmd_id = ?', [(int)$d['gmd']])) $d['gmd'] = 1;

    $prefix = null;
    $jumlah = 0;
    if (!$ubah) {
        $prefix = $d['prefix'] !== '' ? prefix_valid((string)$d['prefix']) : prefix_dari_form($d);
        $jumlah = (int)$d['jumlah'];
        if (!$prefix) $galat[] = 'Kode eksemplar belum dipilih/valid (mis. FIK, NF, CRA, AGM, REF, atau buku paket seperti INF8, MTK7G).';
        if ($jumlah < 0 || $jumlah > 100) $galat[] = 'Jumlah eksemplar 0–100.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['tgl_terima'])) $d['tgl_terima'] = hari_ini();
        if (!satu('SELECT 1 FROM mst_location WHERE location_id = ?', [$d['lokasi']])) $d['lokasi'] = (string)k('lokasi_bawaan', '1');
        if ($d['titipan'] && $d['pemilik'] === '') $galat[] = 'Isi nama pemilik buku titipan.';
    }

    $duplikat = [];
    if (!$galat && !$paksa) {
        $sql = 'SELECT biblio_id, title, isbn_issn FROM biblio WHERE (LOWER(title) = LOWER(?)';
        $par = [$d['judul']];
        if ($d['isbn'] !== '') {
            $sql .= " OR REPLACE(isbn_issn, '-', '') = ?";
            $par[] = str_replace(['-', ' '], '', (string)$d['isbn']);
        }
        $sql .= ')';
        if ($ubah) {
            $sql .= ' AND biblio_id <> ?';
            $par[] = $id;
        }
        $duplikat = semua($sql . ' LIMIT 5', $par);
    }

    $no_panggil = $d['no_panggil'] !== '' ? (string)$d['no_panggil']
        : buat_no_panggil((string)$d['klasifikasi'], $pengarang, (string)$d['judul']);
    $d['no_panggil'] = $no_panggil;
    $catatan = (string)$d['catatan'];
    if (!$ubah && $d['titipan'] && !str_starts_with($catatan, 'TITIPAN')) {
        $catatan = trim(teks_titipan((string)$d['pemilik']) . ' ' . $catatan);
    }
    $d['catatan'] = $catatan;
    if ($prefix) {
        $d['prefix'] = $prefix;
        $d['sumber_final'] = $d['titipan'] ? 2 : normal_sumber($d['sumber'], $prefix);
    }

    $hasil = ['ok' => false, 'galat' => $galat, 'duplikat' => $duplikat, 'data' => $d, 'kode' => [], 'biblio_id' => $id];
    if ($galat || $duplikat || $hanya_periksa) {
        $hasil['ok'] = !$galat && !$duplikat;
        return $hasil;
    }

    $uid = (int)(pengguna()['id'] ?? 1);
    db()->beginTransaction();
    try {
        $nilai = [
            'gmd_id' => (int)$d['gmd'],
            'title' => mb_substr(trim((string)$d['judul']), 0, 65000),
            'sor' => kosong_jadi_null(mb_substr((string)$d['sor'], 0, 200)),
            'edition' => kosong_jadi_null(mb_substr((string)$d['edisi'], 0, 50)),
            'isbn_issn' => kosong_jadi_null((string)$d['isbn']),
            'publisher_id' => id_penerbit((string)$d['penerbit']),
            'publish_year' => kosong_jadi_null((string)$d['tahun']),
            'collation' => kosong_jadi_null(mb_substr((string)$d['kolasi'], 0, 100)),
            'series_title' => kosong_jadi_null(mb_substr((string)$d['seri'], 0, 200)),
            'call_number' => kosong_jadi_null(mb_substr($no_panggil, 0, 50)),
            'language_id' => $d['bahasa'],
            'publish_place_id' => id_tempat((string)$d['tempat']),
            'classification' => kosong_jadi_null(mb_substr((string)$d['klasifikasi'], 0, 40)),
            'notes' => kosong_jadi_null($catatan),
            'opac_hide' => (int)(bool)$d['sembunyi'],
            'last_update' => sekarang(),
            'uid' => $uid,
        ];
        if ($ubah) {
            $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($nilai)));
            q("UPDATE biblio SET $set WHERE biblio_id = ?", [...array_values($nilai), $id]);
            q("UPDATE item SET call_number = ?, last_update = ? WHERE biblio_id = ? AND (call_number <=> ? OR call_number IS NULL OR call_number = '')",
                [$nilai['call_number'], sekarang(), $id, $no_panggil_lama === '' ? null : $no_panggil_lama]);
            q('DELETE FROM biblio_author WHERE biblio_id = ?', [$id]);
            q('DELETE FROM biblio_topic WHERE biblio_id = ?', [$id]);
            $bid = $id;
        } else {
            $nilai += ['frequency_id' => 0, 'promoted' => 0, 'input_date' => sekarang()];
            $kol = implode(', ', array_keys($nilai));
            q("INSERT INTO biblio ($kol) VALUES (" . implode(', ', array_fill(0, count($nilai), '?')) . ')', array_values($nilai));
            $bid = (int)db()->lastInsertId();
        }
        foreach ($pengarang as $i => $nm) {
            q('INSERT IGNORE INTO biblio_author (biblio_id, author_id, level) VALUES (?, ?, ?)', [$bid, id_pengarang(mb_substr($nm, 0, 100)), $i === 0 ? 1 : 2]);
        }
        foreach ($subjek as $i => $tp) {
            q('INSERT IGNORE INTO biblio_topic (biblio_id, topic_id, level) VALUES (?, ?, ?)', [$bid, id_subjek($tp), $i === 0 ? 1 : 2]);
        }
        $kode = [];
        if (!$ubah && $jumlah > 0) {
            $harga = ((string)$d['harga'] !== '' && (int)$d['harga'] > 0) ? (int)$d['harga'] : null;
            $kode = tambah_eksemplar($bid, $prefix, $jumlah, $d['sumber_final'], (string)$d['lokasi'], $harga, (string)$d['tgl_terima'], $no_panggil);
        }
        perbarui_indeks($bid);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    picu_gambar_otomatis();
    return ['ok' => true, 'galat' => [], 'duplikat' => [], 'biblio_id' => $bid, 'kode' => $kode, 'data' => $d];
}

function layanan_tambah_eksemplar(int $biblio_id, int $jumlah, ?string $prefix = null, $sumber = '', ?int $harga = null,
                                  ?string $tgl = null, ?string $lokasi = null, bool $hanya_periksa = false): array
{
    $b = satu('SELECT biblio_id, title, call_number FROM biblio WHERE biblio_id = ?', [$biblio_id]);
    if (!$b) return ['ok' => false, 'galat' => ['Buku #' . $biblio_id . ' tidak ditemukan.']];
    if (!$prefix) {
        $terakhir = nilai('SELECT item_code FROM item WHERE biblio_id = ? ORDER BY item_id DESC LIMIT 1', [$biblio_id]);
        $prefix = $terakhir ? prefix_dari_kode((string)$terakhir) : null;
    }
    $prefix = $prefix ? prefix_valid($prefix) : null;
    $galat = [];
    if (!$prefix) $galat[] = 'Kode eksemplar (prefix) belum diketahui. Sebutkan jenisnya, mis. FIK atau INF8.';
    if ($jumlah < 1 || $jumlah > 100) $galat[] = 'Jumlah eksemplar 1–100.';
    if ($galat) return ['ok' => false, 'galat' => $galat];
    $tgl = ($tgl && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) ? $tgl : hari_ini();
    $lokasi = ($lokasi && satu('SELECT 1 FROM mst_location WHERE location_id = ?', [$lokasi])) ? $lokasi : (string)k('lokasi_bawaan', '1');
    $s = normal_sumber($sumber, $prefix);
    $n = nomor_berikut($prefix);
    $perkiraan = [];
    for ($i = 0; $i < $jumlah; $i++) $perkiraan[] = format_kode($prefix, $n + $i);
    if ($hanya_periksa) {
        return ['ok' => true, 'galat' => [], 'prefix' => $prefix, 'kode' => $perkiraan, 'judul' => $b['title'], 'sumber' => $s];
    }
    db()->beginTransaction();
    try {
        $kode = tambah_eksemplar($biblio_id, $prefix, $jumlah, $s, $lokasi, $harga && $harga > 0 ? $harga : null, $tgl, (string)$b['call_number']);
        q('UPDATE biblio SET last_update = ? WHERE biblio_id = ?', [sekarang(), $biblio_id]);
        perbarui_indeks($biblio_id);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    return ['ok' => true, 'galat' => [], 'kode' => $kode, 'judul' => $b['title']];
}

function layanan_status_eksemplar(string $kode, string $status, bool $hanya_periksa = false): array
{
    $peta = ['tersedia' => '0', 'diperbaiki' => 'R', 'rusak' => 'R', 'repair' => 'R', 'tidak dipinjamkan' => 'NL',
        'no loan' => 'NL', 'hilang' => 'MIS', 'missing' => 'MIS'];
    $status = $peta[strtolower(trim($status))] ?? strtoupper(trim($status));
    $valid = array_column(daftar_status_eksemplar(), 'item_status_name', 'item_status_id');
    if (!isset($valid[$status])) return ['ok' => false, 'galat' => ['Status tidak dikenal. Pilihan: Tersedia, Diperbaiki, Tidak dipinjamkan, Hilang.']];
    $i = satu('SELECT item_id, biblio_id, item_code FROM item WHERE item_code = ?', [strtoupper(trim($kode))]);
    if (!$i) return ['ok' => false, 'galat' => ['Eksemplar ' . $kode . ' tidak ditemukan.']];
    if ($hanya_periksa) return ['ok' => true, 'galat' => [], 'nama_status' => $valid[$status]];
    q('UPDATE item SET item_status_id = ?, last_update = ?, uid = ? WHERE item_id = ?', [$status, sekarang(), (int)(pengguna()['id'] ?? 1), $i['item_id']]);
    return ['ok' => true, 'galat' => [], 'nama_status' => $valid[$status], 'biblio_id' => (int)$i['biblio_id']];
}

function layanan_hapus_eksemplar(string $kode, bool $hanya_periksa = false): array
{
    $i = satu('SELECT item_id, biblio_id, item_code FROM item WHERE item_code = ?', [strtoupper(trim($kode))]);
    if (!$i) return ['ok' => false, 'galat' => ['Eksemplar ' . $kode . ' tidak ditemukan.']];
    if ((int)nilai('SELECT COUNT(*) FROM loan WHERE item_code = ?', [$i['item_code']]) > 0) {
        return ['ok' => false, 'galat' => ['Eksemplar ' . $i['item_code'] . ' punya riwayat peminjaman, jadi tidak boleh dihapus. Ubah statusnya menjadi "Hilang" atau "Tidak dipinjamkan".']];
    }
    if ($hanya_periksa) return ['ok' => true, 'galat' => []];
    db()->beginTransaction();
    try {
        q('DELETE FROM item WHERE item_id = ?', [$i['item_id']]);
        perbarui_indeks((int)$i['biblio_id']);
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
    return ['ok' => true, 'galat' => [], 'biblio_id' => (int)$i['biblio_id']];
}

/* ================= ANGGOTA ================= */

function id_tipe_guru(): int
{
    return (int)(nilai("SELECT member_type_id FROM mst_member_type WHERE member_type_name LIKE '%guru%' LIMIT 1") ?: 0);
}

function masa_berlaku_baru(int $tipe): string
{
    $seragam = (string)k('masa_berlaku_anggota', '');
    if ($seragam !== '' && $seragam > hari_ini()) return $seragam;
    $hari = (int)(nilai('SELECT member_periode FROM mst_member_type WHERE member_type_id = ?', [$tipe]) ?: 365);
    return date('Y-m-d', strtotime('+' . $hari . ' days'));
}

function id_anggota_acak(int $panjang): string
{
    do {
        $s = (string)random_int(1, 9);
        for ($i = 1; $i < $panjang; $i++) $s .= (string)random_int(0, 9);
    } while (nilai('SELECT 1 FROM member WHERE member_id = ?', [$s]));
    return $s;
}

function normal_gender($v): string
{
    $s = strtolower(teks($v));
    if (in_array($s, ['1', 'l', 'laki-laki', 'laki laki', 'pria', 'male'], true)) return '1';
    if (in_array($s, ['0', 'p', 'perempuan', 'wanita', 'female'], true)) return '0';
    return '';
}

function normal_tipe_anggota($v): int
{
    $s = teks($v);
    if ($s === '') return (int)(nilai('SELECT MIN(member_type_id) FROM mst_member_type') ?: 1);
    if (ctype_digit($s)) return (int)$s;
    if (stripos($s, 'guru') !== false) return id_tipe_guru();
    return (int)(nilai('SELECT member_type_id FROM mst_member_type WHERE member_type_name LIKE ? LIMIT 1', ['%' . $s . '%'])
        ?: nilai("SELECT member_type_id FROM mst_member_type WHERE member_type_name LIKE '%siswa%' LIMIT 1") ?: 1);
}

/**
 * Tambah/ubah anggota. Kunci: member_id, nama, gender, tipe, kelas, lahir, telepon, email, alamat, catatan, berlaku.
 */
function layanan_simpan_anggota(array $masuk, ?string $id = null, bool $paksa = false, bool $hanya_periksa = false): array
{
    $ubah = $id !== null && $id !== '';
    $lama = $ubah ? satu('SELECT * FROM member WHERE member_id = ?', [$id]) : null;
    if ($ubah && !$lama) return ['ok' => false, 'galat' => ['Anggota ' . $id . ' tidak ditemukan.']];
    $d = [
        'member_id' => $lama['member_id'] ?? '',
        'nama' => $lama['member_name'] ?? '',
        'gender' => isset($lama['gender']) ? (string)$lama['gender'] : '',
        'tipe' => (string)($lama['member_type_id'] ?? ''),
        'kelas' => $lama['inst_name'] ?? '',
        'lahir' => $lama['birth_date'] ?? '',
        'telepon' => $lama['member_phone'] ?? '',
        'email' => $lama['member_email'] ?? '',
        'alamat' => $lama['member_address'] ?? '',
        'catatan' => $lama['member_notes'] ?? '',
        'berlaku' => $lama['expire_date'] ?? '',
    ];
    foreach ($masuk as $kunci => $v) {
        if (array_key_exists($kunci, $d)) $d[$kunci] = teks($v);
    }
    if ($ubah) $d['member_id'] = $lama['member_id'];
    $d['gender'] = normal_gender($d['gender']);
    $tipe = normal_tipe_anggota($d['tipe']);
    $d['tipe'] = (string)$tipe;
    $guru = $tipe === id_tipe_guru();

    $galat = [];
    if (trim($d['nama']) === '') $galat[] = 'Nama wajib diisi.';
    if ($d['gender'] === '') $galat[] = 'Jenis kelamin (L/P) wajib diisi.';
    if (!satu('SELECT 1 FROM mst_member_type WHERE member_type_id = ?', [$tipe])) $galat[] = 'Tipe anggota tidak dikenal.';
    if ($d['lahir'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['lahir'])) $galat[] = 'Tanggal lahir harus TTTT-BB-HH.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $galat[] = 'Alamat email tidak valid.';
    if ($d['kelas'] === '' && $guru) $d['kelas'] = 'Guru';

    if (!$ubah) {
        $d['member_id'] = preg_replace('/\s+/', '', (string)$d['member_id']);
        if ($d['member_id'] !== '' && !preg_match('/^[A-Za-z0-9.\-]{1,20}$/', $d['member_id'])) {
            $galat[] = 'Nomor anggota hanya huruf/angka, maks 20 karakter.';
        } elseif ($d['member_id'] !== '' && nilai('SELECT 1 FROM member WHERE member_id = ?', [$d['member_id']])) {
            $galat[] = 'Nomor anggota ' . $d['member_id'] . ' sudah dipakai.';
        }
        $d['berlaku'] = masa_berlaku_baru($tipe);
    } elseif ($d['berlaku'] === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$d['berlaku'])) {
        $galat[] = 'Tanggal berlaku harus TTTT-BB-HH.';
    }

    $duplikat = [];
    if (!$galat && !$paksa) {
        $sql = 'SELECT member_id, member_name, inst_name FROM member WHERE LOWER(member_name) = LOWER(?)';
        $par = [trim($d['nama'])];
        if ($ubah) {
            $sql .= ' AND member_id <> ?';
            $par[] = $id;
        }
        $duplikat = semua($sql . ' LIMIT 5', $par);
    }

    if ($galat || $duplikat || $hanya_periksa) {
        return ['ok' => !$galat && !$duplikat, 'galat' => $galat, 'duplikat' => $duplikat, 'data' => $d];
    }

    if (!$ubah && $d['member_id'] === '') $d['member_id'] = id_anggota_acak($guru ? 9 : 10);

    $nilai = [
        'member_name' => mb_substr(trim($d['nama']), 0, 100),
        'gender' => (int)$d['gender'],
        'birth_date' => kosong_jadi_null((string)$d['lahir']),
        'member_type_id' => $tipe,
        'member_address' => mb_substr((string)$d['alamat'], 0, 255),
        'member_email' => mb_substr((string)$d['email'], 0, 100),
        'inst_name' => mb_substr((string)$d['kelas'], 0, 100),
        'member_phone' => mb_substr((string)$d['telepon'], 0, 50),
        'member_notes' => kosong_jadi_null((string)$d['catatan']),
        'expire_date' => $d['berlaku'],
        'last_update' => hari_ini(),
    ];
    if ($ubah) {
        $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($nilai)));
        q("UPDATE member SET $set WHERE member_id = ?", [...array_values($nilai), $id]);
    } else {
        $nilai += [
            'member_id' => $d['member_id'],
            'postal_code' => '', 'member_fax' => '', 'is_new' => 0, 'is_pending' => 0,
            'member_since_date' => hari_ini(), 'register_date' => hari_ini(), 'input_date' => hari_ini(),
        ];
        $kol = implode(', ', array_keys($nilai));
        q("INSERT INTO member ($kol) VALUES (" . implode(', ', array_fill(0, count($nilai), '?')) . ')', array_values($nilai));
    }
    picu_gambar_otomatis();
    return ['ok' => true, 'galat' => [], 'duplikat' => [], 'member_id' => $d['member_id'], 'data' => $d];
}

function layanan_perpanjang_anggota(string $id, bool $hanya_periksa = false): array
{
    $m = satu('SELECT member_id, member_name, member_type_id FROM member WHERE member_id = ?', [$id]);
    if (!$m) return ['ok' => false, 'galat' => ['Anggota ' . $id . ' tidak ditemukan.']];
    $baru = masa_berlaku_baru((int)$m['member_type_id']);
    if (!$hanya_periksa) q('UPDATE member SET expire_date = ?, last_update = ? WHERE member_id = ?', [$baru, hari_ini(), $id]);
    return ['ok' => true, 'galat' => [], 'berlaku' => $baru, 'nama' => $m['member_name']];
}

function layanan_isi_kelas(array $ids, string $kelas, bool $hanya_periksa = false): array
{
    $kelas = mb_substr(trim($kelas), 0, 100);
    $ids = array_values(array_unique(array_filter(array_map('teks', $ids), 'strlen')));
    if ($kelas === '' || !$ids) return ['ok' => false, 'galat' => ['Pilih anggota dan isi nama kelasnya.']];
    $tanda = implode(',', array_fill(0, count($ids), '?'));
    $ada = semua("SELECT member_id, member_name FROM member WHERE member_id IN ($tanda)", $ids);
    if (!$ada) return ['ok' => false, 'galat' => ['Nomor anggota tidak ditemukan.']];
    if ($hanya_periksa) return ['ok' => true, 'galat' => [], 'anggota' => $ada];
    $n = q("UPDATE member SET inst_name = ?, last_update = ? WHERE member_id IN ($tanda)", [$kelas, hari_ini(), ...$ids])->rowCount();
    return ['ok' => true, 'galat' => [], 'jumlah' => $n, 'anggota' => $ada];
}
