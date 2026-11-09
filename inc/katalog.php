<?php
/* Aturan katalog SMPN 6 Keritang: kode eksemplar, tipe koleksi, indeks pencarian. */
declare(strict_types=1);

const MAPEL = [
    'INF' => 'Informatika',
    'MTK' => 'Matematika',
    'BIN' => 'Bahasa Indonesia',
    'BIG' => 'Bahasa Inggris',
    'IPA' => 'IPA',
    'IPS' => 'IPS',
    'PAI' => 'PAI & Budi Pekerti',
    'PKN' => 'PPKn',
    'SBD' => 'Seni Budaya',
    'PJK' => 'PJOK',
    'PRA' => 'Prakarya',
    'MLK' => 'Muatan Lokal',
];

const JENIS_NONPAKET = [
    'FIK' => ['Fiksi (novel, cerpen, komik)', 'Fiction', 1],
    'CRA' => ['Cerita rakyat / anak / literasi Badan Bahasa', 'Fiction', 2],
    'AGM' => ['Agama (non-paket)', 'Nonfiksi', 1],
    'NF'  => ['Nonfiksi umum', 'Nonfiksi', 1],
    'REF' => ['Referensi (kamus, UUD, TAP MPR, pedoman resmi)', 'Reference', 2],
];

/** Mengembalikan prefix yang sudah divalidasi, atau null. */
function prefix_valid(string $p): ?string
{
    $p = strtoupper(trim($p));
    if (isset(JENIS_NONPAKET[$p])) return $p;
    if (preg_match('/^(' . implode('|', array_keys(MAPEL)) . ')([7-9])(G?)$/', $p)) return $p;
    return null;
}

function prefix_dari_form(array $src): ?string
{
    $jenis = strtoupper((string)($src['jenis'] ?? ''));
    if ($jenis === 'PAKET') {
        $p = strtoupper((string)($src['mapel'] ?? '')) . (string)($src['kelas_buku'] ?? '')
            . (!empty($src['buku_guru']) ? 'G' : '');
        return prefix_valid($p);
    }
    return prefix_valid($jenis);
}

function prefix_dari_kode(string $kode): string
{
    return strtoupper(explode('-', $kode)[0]);
}

function nama_tipe_koleksi(string $prefix): string
{
    return isset(JENIS_NONPAKET[$prefix]) ? JENIS_NONPAKET[$prefix][1] : 'Textbook';
}

function id_tipe_koleksi(string $prefix): int
{
    $nama = nama_tipe_koleksi($prefix);
    $id = nilai('SELECT coll_type_id FROM mst_coll_type WHERE coll_type_name = ?', [$nama]);
    if ($id) return (int)$id;
    $cadangan = ['Reference' => 1, 'Textbook' => 2, 'Fiction' => 3, 'Nonfiksi' => 4];
    return $cadangan[$nama];
}

/** Sumber bawaan per prefix: 1 = beli, 2 = hibah. Buku paket = bantuan pemerintah. */
function sumber_bawaan(string $prefix): int
{
    return isset(JENIS_NONPAKET[$prefix]) ? JENIS_NONPAKET[$prefix][2] : 2;
}

function keterangan_prefix(string $prefix): string
{
    if (isset(JENIS_NONPAKET[$prefix])) return JENIS_NONPAKET[$prefix][0];
    if (preg_match('/^([A-Z]{3})([7-9])(G?)$/', $prefix, $m)) {
        return 'Buku ' . ($m[3] ? 'guru' : 'siswa') . ' ' . (MAPEL[$m[1]] ?? $m[1]) . ' kelas ' . $m[2];
    }
    return $prefix;
}

function nomor_berikut(string $prefix): int
{
    $kode = q('SELECT item_code FROM item WHERE item_code LIKE ?', [$prefix . '-%'])->fetchAll(PDO::FETCH_COLUMN);
    $maks = 0;
    foreach ($kode as $k) {
        if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/i', $k, $m)) {
            $maks = max($maks, (int)$m[1]);
        }
    }
    return $maks + 1;
}

function format_kode(string $prefix, int $n): string
{
    return $prefix . '-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
}

/* ---------- Data induk (cari atau buat) ---------- */

function id_pengarang(string $nama): int
{
    $nama = trim(preg_replace('/\s+/', ' ', $nama));
    $id = nilai('SELECT author_id FROM mst_author WHERE author_name = ? LIMIT 1', [$nama]);
    if ($id) return (int)$id;
    q("INSERT INTO mst_author (author_name, authority_type, input_date, last_update) VALUES (?, 'p', ?, ?)",
        [$nama, hari_ini(), hari_ini()]);
    return (int)db()->lastInsertId();
}

function id_penerbit(?string $nama): ?int
{
    $nama = trim((string)$nama);
    if ($nama === '') return null;
    $id = nilai('SELECT publisher_id FROM mst_publisher WHERE publisher_name = ? LIMIT 1', [$nama]);
    if ($id) return (int)$id;
    q('INSERT INTO mst_publisher (publisher_name, input_date, last_update) VALUES (?, ?, ?)',
        [mb_substr($nama, 0, 100), hari_ini(), hari_ini()]);
    return (int)db()->lastInsertId();
}

function id_tempat(?string $nama): ?int
{
    $nama = trim((string)$nama);
    if ($nama === '') return null;
    $id = nilai('SELECT place_id FROM mst_place WHERE place_name = ? LIMIT 1', [$nama]);
    if ($id) return (int)$id;
    q('INSERT INTO mst_place (place_name, input_date, last_update) VALUES (?, ?, ?)',
        [mb_substr($nama, 0, 30), hari_ini(), hari_ini()]);
    return (int)db()->lastInsertId();
}

function id_subjek(string $topik): int
{
    $topik = trim($topik);
    $id = nilai('SELECT topic_id FROM mst_topic WHERE topic = ? LIMIT 1', [$topik]);
    if ($id) return (int)$id;
    q("INSERT INTO mst_topic (topic, topic_type, classification, input_date, last_update) VALUES (?, 't', '', ?, ?)",
        [mb_substr($topik, 0, 50), hari_ini(), hari_ini()]);
    return (int)db()->lastInsertId();
}

function pisah_baris(string $teks): array
{
    $hasil = [];
    foreach (preg_split('/\r\n|\r|\n|;/', $teks) as $b) {
        $b = trim($b);
        if ($b !== '' && !in_array($b, $hasil, true)) $hasil[] = $b;
    }
    return $hasil;
}

/** No. panggil: klasifikasi + 3 huruf pengarang utama + huruf awal judul. */
function buat_no_panggil(string $klas, array $pengarang, string $judul): string
{
    $sumber = $pengarang[0] ?? $judul;
    $kata = preg_replace('/[^A-Za-z]/', '', explode(' ', str_replace([',', '.'], ' ', trim($sumber)))[0] ?? '');
    $tiga = strtoupper(substr($kata, 0, 3));
    $judul_bersih = preg_replace('/^[^A-Za-z]+/', '', $judul);
    $awal = strtolower(substr($judul_bersih, 0, 1));
    return trim(preg_replace('/\s+/', ' ', trim($klas) . ' ' . $tiga . ' ' . $awal));
}

/* ---------- Indeks pencarian OPAC (search_biblio) ---------- */

function perbarui_indeks(int $biblio_id): void
{
    $b = satu('SELECT b.*, g.gmd_name, p.publisher_name, pl.place_name, l.language_name
        FROM biblio b
        LEFT JOIN mst_gmd g ON g.gmd_id = b.gmd_id
        LEFT JOIN mst_publisher p ON p.publisher_id = b.publisher_id
        LEFT JOIN mst_place pl ON pl.place_id = b.publish_place_id
        LEFT JOIN mst_language l ON l.language_id = b.language_id
        WHERE b.biblio_id = ?', [$biblio_id]);
    if (!$b) {
        q('DELETE FROM search_biblio WHERE biblio_id = ?', [$biblio_id]);
        return;
    }
    $gabung = static fn(array $a) => implode(' - ', array_values(array_unique(array_filter($a, 'strlen'))));
    $pengarang = q('SELECT a.author_name FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id
        WHERE ba.biblio_id = ? ORDER BY ba.level, a.author_name', [$biblio_id])->fetchAll(PDO::FETCH_COLUMN);
    $topik = q('SELECT t.topic FROM biblio_topic bt JOIN mst_topic t ON t.topic_id = bt.topic_id
        WHERE bt.biblio_id = ? ORDER BY bt.level, t.topic', [$biblio_id])->fetchAll(PDO::FETCH_COLUMN);
    $item = semua('SELECT i.item_code, c.coll_type_name, lo.location_name FROM item i
        LEFT JOIN mst_coll_type c ON c.coll_type_id = i.coll_type_id
        LEFT JOIN mst_location lo ON lo.location_id = i.location_id
        WHERE i.biblio_id = ? ORDER BY i.item_code', [$biblio_id]);

    q('REPLACE INTO search_biblio (biblio_id, title, edition, isbn_issn, author, topic, gmd, publisher,
        publish_place, language, classification, spec_detail_info, carrier_type, content_type, media_type,
        location, publish_year, notes, series_title, items, collection_types, call_number, opac_hide,
        promoted, labels, collation, image, input_date, last_update)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [
        $biblio_id, $b['title'], (string)$b['edition'], $b['isbn_issn'],
        $gabung($pengarang), $gabung($topik), $b['gmd_name'], $b['publisher_name'],
        $b['place_name'], $b['language_name'], $b['classification'], (string)$b['spec_detail_info'],
        '', '', '',
        $gabung(array_column($item, 'location_name')), $b['publish_year'], $b['notes'], $b['series_title'],
        $gabung(array_column($item, 'item_code')), $gabung(array_column($item, 'coll_type_name')),
        $b['call_number'], (int)$b['opac_hide'], (int)$b['promoted'], $b['labels'], $b['collation'],
        (string)$b['image'], $b['input_date'], $b['last_update'],
    ]);
}

/* ---------- Eksemplar ---------- */

/** Membuat $jumlah eksemplar baru, mengembalikan daftar kode. Panggil di dalam transaksi. */
function tambah_eksemplar(int $biblio_id, string $prefix, int $jumlah, int $sumber, string $lokasi,
                          ?int $harga, string $tgl_terima, string $no_panggil): array
{
    $kunci = 'kelolaperpus_kode_' . $prefix;
    nilai('SELECT GET_LOCK(?, 10)', [$kunci]);
    try {
        $n = nomor_berikut($prefix);
        $tipe = id_tipe_koleksi($prefix);
        $kode = [];
        for ($i = 0; $i < $jumlah; $i++) {
            $kd = format_kode($prefix, $n + $i);
            q("INSERT INTO item (biblio_id, call_number, coll_type_id, item_code, received_date, location_id,
                item_status_id, source, price, price_currency, input_date, last_update, uid)
                VALUES (?, ?, ?, ?, ?, ?, '0', ?, ?, ?, ?, ?, ?)", [
                $biblio_id, $no_panggil, $tipe, $kd, $tgl_terima, $lokasi, $sumber,
                $harga, $harga ? 'Rp' : null, sekarang(), sekarang(), (int)(pengguna()['id'] ?? 1),
            ]);
            $kode[] = $kd;
        }
        return $kode;
    } finally {
        nilai('SELECT RELEASE_LOCK(?)', [$kunci]);
    }
}

function teks_titipan(string $pemilik): string
{
    return 'TITIPAN - milik pribadi ' . $pemilik
        . ', hanya dititipkan ke perpustakaan (bukan hibah). Kembalikan ke pemilik bila diminta.';
}

/* ---------- Pilihan formulir ---------- */

function daftar_bahasa(): array
{
    return semua("SELECT language_id, language_name FROM mst_language
        WHERE language_id <> '' AND language_name <> 'language_name' ORDER BY language_name");
}

function daftar_gmd(): array
{
    return semua("SELECT gmd_id, gmd_name FROM mst_gmd WHERE gmd_name <> 'gmd_name' ORDER BY gmd_name");
}

function daftar_lokasi(): array
{
    return semua('SELECT location_id, location_name FROM mst_location ORDER BY location_name');
}

function daftar_status_eksemplar(): array
{
    $s = [['item_status_id' => '0', 'item_status_name' => 'Tersedia']];
    $nama = ['R' => 'Diperbaiki', 'NL' => 'Tidak dipinjamkan', 'MIS' => 'Hilang'];
    foreach (semua('SELECT item_status_id, item_status_name FROM mst_item_status ORDER BY item_status_id') as $r) {
        $r['item_status_name'] = $nama[$r['item_status_id']] ?? $r['item_status_name'];
        $s[] = $r;
    }
    return $s;
}
