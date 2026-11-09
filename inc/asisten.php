<?php
/* Asisten AI: prompt, alat (tools), usulan perubahan yang menunggu konfirmasi. */
declare(strict_types=1);

const ALAT_TULIS = ['tambah_buku', 'ubah_buku', 'tambah_eksemplar', 'ubah_status_eksemplar', 'hapus_eksemplar',
    'tambah_anggota', 'ubah_anggota', 'perpanjang_anggota', 'isi_kelas'];

function aturan_katalog(): string
{
    $mapel = [];
    foreach (MAPEL as $k => $n) $mapel[] = "$k $n";
    return "SKEMA KODE EKSEMPLAR (wajib): buku paket/pelajaran = MAPEL+KELAS (+G untuk buku guru), mis. INF8, MTK7, PAI9G. "
        . 'Kode mapel: ' . implode(', ', $mapel) . '. Kelas hanya 7, 8, 9. '
        . 'Non-paket: FIK fiksi (novel, cerpen, komik), CRA cerita rakyat/cerita anak/buku literasi Badan Bahasa, AGM agama non-paket, '
        . 'NF nonfiksi umum, REF referensi (kamus, ensiklopedia, UUD, TAP MPR, pedoman resmi pemerintah). '
        . "Sumber: buku pelajaran pemerintah, buku bertanda 'Milik Negara Tidak Diperdagangkan', bantuan/hibah = hibah; selain itu beli. "
        . 'Kode produk Erlangga (mis. 308-370-035-0) BUKAN ISBN, taruh di catatan. '
        . "Nama pengarang perorangan ditulis 'Belakang, Depan' (mis. 'Hardiningtyas, Puji Retno'); nama satu kata, nama pena, atau nama Arab seperti 'Ummu Faris' biarkan apa adanya; lembaga ditulis apa adanya. "
        . "Judul dengan anak judul ditulis 'Judul : anak judul'. Kolasi: 'xii, 250 hlm. : ilus. ; 21 cm' (isi hanya yang terlihat). "
        . 'Klasifikasi pakai DDC (mis. 899.221 3 untuk novel Indonesia, 004 informatika, 297 Islam, 398.2 cerita rakyat). '
        . 'Subjek dalam bahasa Indonesia.';
}

function prompt_sistem(): string
{
    $tipe = implode(', ', array_column(semua('SELECT member_type_name FROM mst_member_type'), 'member_type_name'));
    return "Kamu adalah Asisten SiPustaka, aplikasi Perpustakaan SMPN 6 Keritang (SLiMS). Kamu membantu pustakawan "
        . pengguna()['nama'] . ". Hari ini " . date('Y-m-d') . ". Jawab dalam Bahasa Indonesia, singkat dan ramah, tanpa tabel markdown.\n"
        . "ATURAN KERJA:\n"
        . "- Gunakan alat untuk membaca data; jangan mengarang data perpustakaan.\n"
        . "- Sebelum tambah_buku, SELALU cari_buku dulu (judul dan ISBN). Jika buku sudah ada, usulkan tambah_eksemplar saja.\n"
        . "- Alat yang mengubah data (tambah_/ubah_/hapus_/perpanjang_/isi_kelas) TIDAK langsung menyimpan: hasilnya kartu usulan dengan tombol Simpan untuk pengguna. "
        . "Setelah membuat usulan, cukup jelaskan singkat dan minta pengguna menekan Simpan. Jangan bilang sudah tersimpan sebelum ada catatan sistem 'DISIMPAN'.\n"
        . "- Jika pengguna mengirim foto buku: baca teksnya teliti (judul, pengarang, penerbit, kota, tahun, ISBN di sampul belakang/halaman hak cipta), tebak jenis kode, lalu cari_buku dan langsung buat usulan tambah_buku (jangan hanya bertanya). Jumlah eksemplar bawaan 1 kecuali pengguna menyebut lain. Jika ada data yang tidak terbaca, kosongkan dan sebutkan.\n"
        . "- Jika pengguna minta koreksi usulan (mis. 'tahunnya 2020'), buat usulan baru yang sudah dikoreksi.\n"
        . "- Tanggal format TTTT-BB-HH. Tipe anggota: $tipe. Kelas siswa ditulis seperti 'VII A'. Masa berlaku anggota otomatis.\n"
        . "- Peminjaman/pengembalian tidak bisa lewat kamu; arahkan ke menu Sirkulasi SLiMS.\n"
        . aturan_katalog();
}

function definisi_alat(): array
{
    $f = static fn(string $nama, string $ket, array $prop, array $wajib = []) => [
        'type' => 'function',
        'function' => ['name' => $nama, 'description' => $ket,
            'parameters' => ['type' => 'object', 'properties' => (object)$prop, 'required' => $wajib]],
    ];
    $s = ['type' => 'string'];
    $n = ['type' => 'integer'];
    $daftar = ['type' => 'array', 'items' => ['type' => 'string']];
    $buku = [
        'judul' => $s + ['description' => "Judul lengkap, 'Judul : anak judul'"],
        'pengarang' => $daftar + ['description' => 'Pengarang utama di urutan pertama'],
        'pernyataan_tanggung_jawab' => $s + ['description' => "mis. 'penyunting, Tim Narasi ; ilustrator, EorG'"],
        'penerbit' => $s, 'tempat_terbit' => $s, 'tahun_terbit' => $s + ['description' => '4 angka'],
        'isbn' => $s, 'edisi' => $s, 'kolasi' => $s + ['description' => "mis. 'viii, 120 hlm. : ilus. ; 21 cm'"],
        'judul_seri' => $s, 'klasifikasi' => $s + ['description' => 'Nomor DDC'],
        'no_panggil' => $s + ['description' => 'Kosongkan agar dibuat otomatis'],
        'subjek' => $daftar, 'bahasa' => $s + ['description' => "'id', 'en', atau 'Arab'"], 'catatan' => $s,
    ];
    $anggota = [
        'nama' => $s, 'jenis_kelamin' => ['type' => 'string', 'enum' => ['L', 'P']],
        'tipe' => $s + ['description' => "'Siswa/i' atau 'Guru'"], 'kelas' => $s + ['description' => "mis. 'VII A', guru: 'Guru'"],
        'tanggal_lahir' => $s, 'telepon' => $s, 'email' => $s, 'alamat' => $s, 'catatan' => $s,
    ];
    return [
        $f('cari_buku', 'Cari judul berdasarkan kata pada judul/pengarang/ISBN/kode eksemplar. Hasil maks 10.', ['kata' => $s], ['kata']),
        $f('lihat_buku', 'Detail satu judul beserta semua eksemplar dan statusnya.', ['biblio_id' => $n], ['biblio_id']),
        $f('cari_anggota', 'Cari anggota berdasarkan nama/nomor, atau semua anggota satu kelas. Hasil maks 40.',
            ['kata' => $s, 'kelas' => $s + ['description' => "Saring kelas; '-' = belum diisi"]]),
        $f('statistik', 'Ringkasan jumlah judul, eksemplar, anggota, pinjaman, keterlambatan, dan jumlah per kode.', []),
        $f('pinjaman_aktif', 'Daftar buku yang sedang dipinjam.', ['hanya_terlambat' => ['type' => 'boolean'], 'member_id' => $s]),
        $f('kode_berikutnya', 'Lihat kode eksemplar berikutnya untuk sebuah prefix (mis. FIK, INF8).', ['prefix' => $s], ['prefix']),

        $f('tambah_buku', 'USULAN menambah judul baru beserta eksemplarnya (menunggu tombol Simpan pengguna).',
            $buku + [
                'kode_prefix' => $s + ['description' => 'Prefix kode eksemplar sesuai skema, mis. FIK, NF, CRA, AGM, REF, INF8, MTK7G'],
                'jumlah_eksemplar' => $n, 'sumber' => ['type' => 'string', 'enum' => ['beli', 'hibah']],
                'harga' => $n + ['description' => 'Harga per eksemplar (Rp)'],
                'titipan_pemilik' => $s + ['description' => 'Isi nama pemilik HANYA jika buku titipan pribadi'],
                'paksa' => ['type' => 'boolean', 'description' => 'true hanya jika pengguna menegaskan judulnya berbeda dari yang sudah ada'],
            ], ['judul', 'kode_prefix']),
        $f('ubah_buku', 'USULAN mengubah data judul. Isi hanya kolom yang berubah.', ['biblio_id' => $n] + $buku, ['biblio_id']),
        $f('tambah_eksemplar', 'USULAN menambah eksemplar untuk judul yang sudah ada.',
            ['biblio_id' => $n, 'jumlah' => $n, 'kode_prefix' => $s + ['description' => 'Kosongkan agar mengikuti kode yang sudah ada'],
                'sumber' => ['type' => 'string', 'enum' => ['beli', 'hibah']], 'harga' => $n], ['biblio_id', 'jumlah']),
        $f('ubah_status_eksemplar', 'USULAN mengubah status eksemplar.',
            ['kode' => $s, 'status' => ['type' => 'string', 'enum' => ['Tersedia', 'Diperbaiki', 'Tidak dipinjamkan', 'Hilang']]], ['kode', 'status']),
        $f('hapus_eksemplar', 'USULAN menghapus eksemplar yang salah input (hanya bila belum pernah dipinjam).', ['kode' => $s], ['kode']),
        $f('tambah_anggota', 'USULAN mendaftarkan anggota baru. Nomor anggota dibuat otomatis bila kosong.',
            $anggota + ['nomor_anggota' => $s, 'paksa' => ['type' => 'boolean', 'description' => 'true jika pengguna menegaskan orangnya berbeda walau namanya sama']],
            ['nama', 'jenis_kelamin']),
        $f('ubah_anggota', 'USULAN mengubah data anggota. Isi hanya kolom yang berubah.',
            ['member_id' => $s] + $anggota + ['berlaku_sampai' => $s], ['member_id']),
        $f('perpanjang_anggota', 'USULAN memperpanjang masa berlaku anggota.', ['member_id' => $s], ['member_id']),
        $f('isi_kelas', 'USULAN mengisi kelas untuk banyak anggota sekaligus.',
            ['member_ids' => $daftar, 'kelas' => $s], ['member_ids', 'kelas']),
    ];
}

/* ---------- Alat baca ---------- */

function alat_baca(string $nama, array $a): array
{
    switch ($nama) {
        case 'cari_buku':
            $kata = trim((string)($a['kata'] ?? ''));
            $like = '%' . $kata . '%';
            $isbn = str_replace(['-', ' '], '', $kata);
            $r = semua("SELECT b.biblio_id, b.title judul, b.publish_year tahun, b.isbn_issn isbn,
                (SELECT GROUP_CONCAT(a.author_name ORDER BY ba.level SEPARATOR '; ') FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id) pengarang,
                (SELECT GROUP_CONCAT(item_code ORDER BY item_code SEPARATOR ', ') FROM item WHERE biblio_id = b.biblio_id) kode
                FROM biblio b WHERE b.title LIKE ? OR (? <> '' AND REPLACE(b.isbn_issn, '-', '') = ?)
                OR EXISTS (SELECT 1 FROM item i WHERE i.biblio_id = b.biblio_id AND i.item_code LIKE ?)
                OR EXISTS (SELECT 1 FROM biblio_author ba JOIN mst_author a ON a.author_id = ba.author_id WHERE ba.biblio_id = b.biblio_id AND a.author_name LIKE ?)
                ORDER BY b.biblio_id DESC LIMIT 10", [$like, $isbn, $isbn, $like, $like]);
            return ['jumlah' => count($r), 'hasil' => $r];

        case 'lihat_buku':
            $id = (int)($a['biblio_id'] ?? 0);
            $d = buku_dari_db($id);
            if (!$d) return ['galat' => 'Buku tidak ditemukan'];
            $d['biblio_id'] = $id;
            $d['eksemplar'] = semua("SELECT i.item_code kode, c.coll_type_name tipe, IF(i.source = 1, 'beli', 'hibah') sumber,
                i.item_status_id status,
                (SELECT CONCAT(l.member_id, ' sampai ', l.due_date) FROM loan l WHERE l.item_code = i.item_code AND l.is_lent = 1 AND l.is_return = 0 LIMIT 1) dipinjam
                FROM item i LEFT JOIN mst_coll_type c ON c.coll_type_id = i.coll_type_id WHERE i.biblio_id = ? ORDER BY i.item_code", [$id]);
            return $d;

        case 'cari_anggota':
            $w = [];
            $p = [];
            $kata = trim((string)($a['kata'] ?? ''));
            if ($kata !== '') {
                $w[] = '(m.member_name LIKE ? OR m.member_id LIKE ?)';
                array_push($p, '%' . $kata . '%', $kata . '%');
            }
            $kelas = trim((string)($a['kelas'] ?? ''));
            if ($kelas === '-') $w[] = "(m.inst_name IS NULL OR m.inst_name = '')";
            elseif ($kelas !== '') {
                $w[] = 'm.inst_name = ?';
                $p[] = $kelas;
            }
            $r = semua("SELECT m.member_id, m.member_name nama, IF(m.gender = 1, 'L', 'P') jk, t.member_type_name tipe,
                m.inst_name kelas, m.expire_date berlaku_sampai,
                (SELECT COUNT(*) FROM loan l WHERE l.member_id = m.member_id AND l.is_lent = 1 AND l.is_return = 0) sedang_pinjam
                FROM member m LEFT JOIN mst_member_type t ON t.member_type_id = m.member_type_id "
                . ($w ? 'WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY m.member_name LIMIT 40', $p);
            return ['jumlah' => count($r), 'hasil' => $r];

        case 'statistik':
            $s = satu("SELECT (SELECT COUNT(*) FROM biblio) judul, (SELECT COUNT(*) FROM item) eksemplar,
                (SELECT COUNT(*) FROM member) anggota,
                (SELECT COUNT(*) FROM member WHERE inst_name IS NULL OR inst_name = '') anggota_tanpa_kelas,
                (SELECT COUNT(*) FROM member WHERE expire_date < CURDATE()) anggota_kedaluwarsa,
                (SELECT COUNT(*) FROM loan WHERE is_lent = 1 AND is_return = 0) dipinjam,
                (SELECT COUNT(*) FROM loan WHERE is_lent = 1 AND is_return = 0 AND due_date < CURDATE()) terlambat");
            $s['per_kode'] = semua("SELECT SUBSTRING_INDEX(item_code, '-', 1) prefix, COUNT(*) eksemplar FROM item GROUP BY prefix ORDER BY prefix");
            return $s;

        case 'pinjaman_aktif':
            $w = 'l.is_lent = 1 AND l.is_return = 0';
            $p = [];
            if (!empty($a['hanya_terlambat'])) $w .= ' AND l.due_date < CURDATE()';
            if (!empty($a['member_id'])) {
                $w .= ' AND l.member_id = ?';
                $p[] = (string)$a['member_id'];
            }
            $r = semua("SELECT l.item_code kode, b.title judul, m.member_id, m.member_name nama, m.inst_name kelas, l.loan_date dipinjam, l.due_date kembali
                FROM loan l LEFT JOIN member m ON m.member_id = l.member_id LEFT JOIN item i ON i.item_code = l.item_code
                LEFT JOIN biblio b ON b.biblio_id = i.biblio_id WHERE $w ORDER BY l.due_date LIMIT 50", $p);
            return ['jumlah' => count($r), 'hasil' => $r];

        case 'kode_berikutnya':
            $pr = prefix_valid((string)($a['prefix'] ?? ''));
            if (!$pr) return ['galat' => 'Prefix tidak sesuai skema'];
            return ['prefix' => $pr, 'kode_berikutnya' => format_kode($pr, nomor_berikut($pr)),
                'tipe_koleksi' => nama_tipe_koleksi($pr), 'keterangan' => keterangan_prefix($pr)];
    }
    return ['galat' => 'Alat tidak dikenal: ' . $nama];
}

/* ---------- Alat tulis → usulan ---------- */

function arg_ke_buku(array $a): array
{
    $peta = ['judul' => 'judul', 'pengarang' => 'pengarang', 'pernyataan_tanggung_jawab' => 'sor', 'penerbit' => 'penerbit',
        'tempat_terbit' => 'tempat', 'tahun_terbit' => 'tahun', 'isbn' => 'isbn', 'edisi' => 'edisi', 'kolasi' => 'kolasi',
        'judul_seri' => 'seri', 'klasifikasi' => 'klasifikasi', 'no_panggil' => 'no_panggil', 'subjek' => 'subjek',
        'bahasa' => 'bahasa', 'catatan' => 'catatan', 'kode_prefix' => 'prefix', 'jumlah_eksemplar' => 'jumlah',
        'sumber' => 'sumber', 'harga' => 'harga'];
    $d = [];
    foreach ($peta as $dari => $ke) {
        if (array_key_exists($dari, $a) && $a[$dari] !== null) $d[$ke] = $a[$dari];
    }
    if (!empty($a['titipan_pemilik'])) {
        $d['titipan'] = '1';
        $d['pemilik'] = (string)$a['titipan_pemilik'];
    }
    return $d;
}

function arg_ke_anggota(array $a): array
{
    $peta = ['nama' => 'nama', 'jenis_kelamin' => 'gender', 'tipe' => 'tipe', 'kelas' => 'kelas', 'tanggal_lahir' => 'lahir',
        'telepon' => 'telepon', 'email' => 'email', 'alamat' => 'alamat', 'catatan' => 'catatan', 'nomor_anggota' => 'member_id',
        'berlaku_sampai' => 'berlaku'];
    $d = [];
    foreach ($peta as $dari => $ke) {
        if (array_key_exists($dari, $a) && $a[$dari] !== null) $d[$ke] = $a[$dari];
    }
    return $d;
}

/** Jalankan alat tulis. $simpan=false hanya memeriksa (untuk membuat usulan). */
function jalankan_tulis(string $nama, array $a, bool $simpan): array
{
    $periksa = !$simpan;
    $paksa = !empty($a['paksa']);
    switch ($nama) {
        case 'tambah_buku':
            return layanan_simpan_buku(arg_ke_buku($a), null, $paksa, $periksa);
        case 'ubah_buku':
            return layanan_simpan_buku(arg_ke_buku($a), (int)($a['biblio_id'] ?? 0), true, $periksa);
        case 'tambah_eksemplar':
            return layanan_tambah_eksemplar((int)($a['biblio_id'] ?? 0), (int)($a['jumlah'] ?? 1),
                !empty($a['kode_prefix']) ? (string)$a['kode_prefix'] : null, $a['sumber'] ?? '', isset($a['harga']) ? (int)$a['harga'] : null,
                null, null, $periksa);
        case 'ubah_status_eksemplar':
            return layanan_status_eksemplar((string)($a['kode'] ?? ''), (string)($a['status'] ?? ''), $periksa);
        case 'hapus_eksemplar':
            return layanan_hapus_eksemplar((string)($a['kode'] ?? ''), $periksa);
        case 'tambah_anggota':
            return layanan_simpan_anggota(arg_ke_anggota($a), null, $paksa, $periksa);
        case 'ubah_anggota':
            return layanan_simpan_anggota(arg_ke_anggota($a), (string)($a['member_id'] ?? ''), true, $periksa);
        case 'perpanjang_anggota':
            return layanan_perpanjang_anggota((string)($a['member_id'] ?? ''), $periksa);
        case 'isi_kelas':
            return layanan_isi_kelas((array)($a['member_ids'] ?? []), (string)($a['kelas'] ?? ''), $periksa);
    }
    return ['ok' => false, 'galat' => ['Alat tidak dikenal']];
}

/** Baris ringkasan [label, nilai] untuk kartu usulan. */
function ringkasan_usulan(string $nama, array $a, array $cek): array
{
    $r = [];
    $tambah = static function (string $l, $v) use (&$r) {
        $v = teks($v);
        if ($v !== '') $r[] = [$l, str_replace("\n", '; ', $v)];
    };
    switch ($nama) {
        case 'tambah_buku':
        case 'ubah_buku':
            $d = $cek['data'] ?? [];
            if ($nama === 'ubah_buku') {
                $lama = buku_dari_db((int)$a['biblio_id']) ?? [];
                $tambah('Buku', '#' . (int)$a['biblio_id'] . ' ' . ($lama['judul'] ?? ''));
                foreach (arg_ke_buku($a) as $k => $v) {
                    $sebelum = teks($lama[$k] ?? '');
                    $label = ['judul' => 'Judul', 'pengarang' => 'Pengarang', 'sor' => 'Tanggung jawab', 'penerbit' => 'Penerbit',
                        'tempat' => 'Kota', 'tahun' => 'Tahun', 'isbn' => 'ISBN', 'edisi' => 'Edisi', 'kolasi' => 'Fisik', 'seri' => 'Seri',
                        'klasifikasi' => 'Klasifikasi', 'no_panggil' => 'No. panggil', 'subjek' => 'Subjek', 'bahasa' => 'Bahasa', 'catatan' => 'Catatan'][$k] ?? $k;
                    $tambah($label, ($sebelum !== '' ? $sebelum . ' → ' : '') . teks($v));
                }
                if (!array_key_exists('no_panggil', arg_ke_buku($a)) && isset($d['no_panggil'])) $tambah('No. panggil baru', $d['no_panggil']);
                break;
            }
            $tambah('Judul', $d['judul'] ?? '');
            $tambah('Pengarang', $d['pengarang'] ?? '');
            $tambah('Tanggung jawab', $d['sor'] ?? '');
            $tambah('Terbitan', implode(', ', array_filter([teks($d['tempat'] ?? ''), teks($d['penerbit'] ?? ''), teks($d['tahun'] ?? '')])));
            $tambah('ISBN', $d['isbn'] ?? '');
            $tambah('Edisi', $d['edisi'] ?? '');
            $tambah('Fisik', $d['kolasi'] ?? '');
            $tambah('Seri', $d['seri'] ?? '');
            $tambah('Klasifikasi', trim(teks($d['klasifikasi'] ?? '') . ' · no. panggil ' . teks($d['no_panggil'] ?? ''), ' ·'));
            $tambah('Subjek', $d['subjek'] ?? '');
            if (!empty($d['prefix'])) {
                $n = (int)($d['jumlah'] ?? 1);
                $mulai = nomor_berikut($d['prefix']);
                $kd = format_kode($d['prefix'], $mulai) . ($n > 1 ? ' s.d. ' . format_kode($d['prefix'], $mulai + $n - 1) : '');
                $tambah('Eksemplar', $n > 0 ? $n . ' buku: ' . $kd : 'belum ada');
                $tambah('Jenis', keterangan_prefix($d['prefix']) . ' · ' . nama_tipe_koleksi($d['prefix']) . ' · ' . (($d['sumber_final'] ?? 1) === 1 ? 'beli' : 'hibah'));
            }
            if (!empty($d['harga'])) $tambah('Harga', 'Rp' . number_format((int)$d['harga'], 0, ',', '.'));
            $tambah('Catatan', $d['catatan'] ?? '');
            break;
        case 'tambah_eksemplar':
            $tambah('Buku', '#' . (int)$a['biblio_id'] . ' ' . ($cek['judul'] ?? ''));
            $tambah('Kode baru', implode(', ', $cek['kode'] ?? []));
            $tambah('Sumber', ($cek['sumber'] ?? 1) === 1 ? 'beli' : 'hibah');
            if (!empty($a['harga'])) $tambah('Harga', 'Rp' . number_format((int)$a['harga'], 0, ',', '.'));
            break;
        case 'ubah_status_eksemplar':
            $tambah('Eksemplar', strtoupper((string)$a['kode']));
            $tambah('Status baru', $cek['nama_status'] ?? $a['status']);
            break;
        case 'hapus_eksemplar':
            $tambah('Hapus eksemplar', strtoupper((string)$a['kode']));
            break;
        case 'tambah_anggota':
        case 'ubah_anggota':
            $d = $cek['data'] ?? [];
            if ($nama === 'ubah_anggota') $tambah('Anggota', (string)$a['member_id']);
            $tambah('Nama', $d['nama'] ?? '');
            $tambah('Nomor', ($d['member_id'] ?? '') !== '' ? $d['member_id'] : 'dibuat otomatis');
            $tambah('Jenis kelamin', ($d['gender'] ?? '') === '1' ? 'Laki-laki' : 'Perempuan');
            $tambah('Tipe', nilai('SELECT member_type_name FROM mst_member_type WHERE member_type_id = ?', [(int)($d['tipe'] ?? 0)]));
            $tambah('Kelas', $d['kelas'] ?? '');
            $tambah('Tanggal lahir', $d['lahir'] ?? '');
            $tambah('Telepon', $d['telepon'] ?? '');
            $tambah('Email', $d['email'] ?? '');
            $tambah('Berlaku sampai', $d['berlaku'] ?? '');
            break;
        case 'perpanjang_anggota':
            $tambah('Anggota', ($cek['nama'] ?? '') . ' (' . $a['member_id'] . ')');
            $tambah('Berlaku sampai', $cek['berlaku'] ?? '');
            break;
        case 'isi_kelas':
            $tambah('Kelas', $a['kelas']);
            $tambah('Anggota (' . count($cek['anggota'] ?? []) . ')', implode(', ', array_column($cek['anggota'] ?? [], 'member_name')));
            break;
    }
    return $r;
}

function judul_usulan(string $nama): string
{
    return [
        'tambah_buku' => 'Tambah buku', 'ubah_buku' => 'Ubah data buku', 'tambah_eksemplar' => 'Tambah eksemplar',
        'ubah_status_eksemplar' => 'Ubah status eksemplar', 'hapus_eksemplar' => 'Hapus eksemplar',
        'tambah_anggota' => 'Tambah anggota', 'ubah_anggota' => 'Ubah data anggota',
        'perpanjang_anggota' => 'Perpanjang anggota', 'isi_kelas' => 'Isi kelas',
    ][$nama] ?? $nama;
}

/** Buat usulan; mengembalikan [hasil untuk AI, usulan untuk layar|null]. */
function buat_usulan(string $nama, array $a): array
{
    $cek = jalankan_tulis($nama, $a, false);
    if (!$cek['ok']) {
        $info = ['status' => 'DITOLAK', 'galat' => $cek['galat'] ?? []];
        if (!empty($cek['duplikat'])) {
            $info['duplikat'] = $cek['duplikat'];
            $info['saran'] = 'Data serupa sudah ada. Jika barangnya sama, gunakan tambah_eksemplar / ubah. Jika pengguna menegaskan berbeda, ulangi dengan paksa=true.';
        }
        return [$info, null];
    }
    $id = bin2hex(random_bytes(4));
    $u = ['id' => $id, 'alat' => $nama, 'arg' => $a, 'judul' => judul_usulan($nama),
        'baris' => ringkasan_usulan($nama, $a, $cek), 'waktu' => time(), 'data' => $cek['data'] ?? null];
    $_SESSION['usulan'][$id] = $u;
    // simpan maksimal 30 usulan terakhir
    if (count($_SESSION['usulan']) > 30) $_SESSION['usulan'] = array_slice($_SESSION['usulan'], -30, null, true);
    return [['status' => 'MENUNGGU_KONFIRMASI', 'usulan_id' => $id,
        'pesan' => 'Kartu usulan sudah tampil di layar pengguna dengan tombol Simpan. Jangan memanggil alat ini lagi untuk data yang sama.'], $u];
}

/** Jalankan usulan setelah pengguna menekan Simpan. */
function simpan_usulan(string $id): array
{
    $u = $_SESSION['usulan'][$id] ?? null;
    if (!$u) return ['ok' => false, 'teks' => 'Usulan sudah kedaluwarsa atau sudah diproses.'];
    if (!empty($u['selesai'])) return ['ok' => false, 'teks' => 'Usulan ini sudah diproses.'];
    $h = jalankan_tulis($u['alat'], $u['arg'], true);
    if (!$h['ok']) {
        $msg = implode(' ', $h['galat'] ?? []);
        if (!empty($h['duplikat'])) $msg .= ' Ada data serupa: ' . implode('; ', array_map(fn($x) => $x['title'] ?? $x['member_name'] ?? '', $h['duplikat']));
        return ['ok' => false, 'teks' => 'Gagal: ' . $msg];
    }
    $_SESSION['usulan'][$id]['selesai'] = true;
    $tautan = null;
    switch ($u['alat']) {
        case 'tambah_buku':
            $teks = 'Buku tersimpan' . ($h['kode'] ? ' dengan kode ' . implode(', ', $h['kode']) . '. Tulis kode ini di bukunya.' : '.');
            $tautan = '?p=buku_detail&id=' . $h['biblio_id'];
            $catat = 'biblio_id ' . $h['biblio_id'] . ', kode ' . implode(', ', $h['kode']);
            break;
        case 'ubah_buku':
            $teks = 'Data buku diperbarui.';
            $tautan = '?p=buku_detail&id=' . $h['biblio_id'];
            $catat = 'biblio_id ' . $h['biblio_id'];
            break;
        case 'tambah_eksemplar':
            $teks = 'Eksemplar baru: ' . implode(', ', $h['kode']) . '.';
            $tautan = '?p=buku_detail&id=' . (int)$u['arg']['biblio_id'];
            $catat = 'kode ' . implode(', ', $h['kode']);
            break;
        case 'ubah_status_eksemplar':
            $teks = 'Status ' . strtoupper((string)$u['arg']['kode']) . ' menjadi ' . $h['nama_status'] . '.';
            $tautan = '?p=buku_detail&id=' . $h['biblio_id'];
            $catat = $teks;
            break;
        case 'hapus_eksemplar':
            $teks = 'Eksemplar ' . strtoupper((string)$u['arg']['kode']) . ' dihapus.';
            $tautan = '?p=buku_detail&id=' . $h['biblio_id'];
            $catat = $teks;
            break;
        case 'tambah_anggota':
        case 'ubah_anggota':
            $teks = ($u['alat'] === 'tambah_anggota' ? 'Anggota terdaftar dengan nomor ' : 'Data anggota diperbarui: ') . $h['member_id'] . '.';
            $tautan = '?p=anggota_form&id=' . rawurlencode($h['member_id']);
            $catat = 'member_id ' . $h['member_id'];
            break;
        case 'perpanjang_anggota':
            $teks = 'Masa berlaku diperpanjang sampai ' . tgl($h['berlaku']) . '.';
            $tautan = '?p=anggota_form&id=' . rawurlencode((string)$u['arg']['member_id']);
            $catat = $teks;
            break;
        case 'isi_kelas':
            $teks = $h['jumlah'] . ' anggota dimasukkan ke kelas ' . $u['arg']['kelas'] . '.';
            $catat = $teks;
            break;
        default:
            $teks = 'Tersimpan.';
            $catat = '';
    }
    riwayat_tambah('assistant', '[Catatan sistem] Usulan ' . $id . ' (' . $u['alat'] . ') DISIMPAN oleh pengguna: ' . $catat);
    return ['ok' => true, 'teks' => $teks, 'tautan' => $tautan];
}

/* ---------- Riwayat percakapan (di sesi, hanya teks) ---------- */

function riwayat(): array
{
    return $_SESSION['asisten'] ?? [];
}

function riwayat_tambah(string $peran, string $isi): void
{
    $_SESSION['asisten'][] = ['role' => $peran, 'content' => $isi];
    if (count($_SESSION['asisten']) > 40) $_SESSION['asisten'] = array_slice($_SESSION['asisten'], -40);
}

/**
 * Satu giliran percakapan. Hasil: ['ok', 'teks', 'usulan'=>[], 'oleh', 'rincian'].
 */
function asisten_giliran(string $teks, array $foto): array
{
    $bagian_foto = ai_bagian_foto($foto);
    $isi_pengguna = $teks !== '' ? $teks : 'Ini foto bukunya. Tolong tambahkan ke perpustakaan.';
    $pesan_pengguna = $bagian_foto
        ? ['role' => 'user', 'content' => array_merge([['type' => 'text', 'text' => $isi_pengguna]], $bagian_foto)]
        : ['role' => 'user', 'content' => $isi_pengguna];

    $pesan = array_merge([['role' => 'system', 'content' => prompt_sistem()]], riwayat(), [$pesan_pengguna]);
    $alat = definisi_alat();
    $usulan = [];
    $oleh = '';
    $rincian = [];
    $jawab = '';

    for ($putaran = 0; $putaran < 7; $putaran++) {
        $r = ai_minta($pesan, ['alat' => $alat, 'gambar' => (bool)$bagian_foto]);
        $rincian = array_values(array_unique(array_merge($rincian, $r['rincian'] ?? [])));
        if (!$r['ok']) {
            return ['ok' => false, 'teks' => $r['galat'], 'usulan' => $usulan, 'rincian' => $rincian];
        }
        $oleh = $r['oleh'];
        $m = $r['pesan'];
        if (empty($m['tool_calls'])) {
            $jawab = $m['content'];
            break;
        }
        $pesan[] = ['role' => 'assistant', 'content' => ($m['content'] ?? '') !== '' ? $m['content'] : null, 'tool_calls' => $m['tool_calls']];
        foreach ($m['tool_calls'] as $tc) {
            $nama = $tc['function']['name'];
            $arg = json_decode($tc['function']['arguments'] ?: '{}', true);
            if (!is_array($arg)) $arg = [];
            try {
                if (in_array($nama, ALAT_TULIS, true)) {
                    [$hasil, $u] = buat_usulan($nama, $arg);
                    if ($u) $usulan[] = $u;
                } else {
                    $hasil = alat_baca($nama, $arg);
                }
            } catch (Throwable $e) {
                $hasil = ['galat' => 'Kesalahan server: ' . $e->getMessage()];
            }
            $pesan[] = ['role' => 'tool', 'tool_call_id' => $tc['id'],
                'content' => json_encode($hasil, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)];
        }
    }
    if (!$usulan && preg_match('/tekan (tombol )?\**simpan/i', $jawab)) {
        $jawab .= "\n\n(Catatan: kartu usulan belum dibuat oleh AI. Ulangi permintaannya, misalnya: \"buat usulannya\".)";
    }
    if ($jawab === '') {
        $jawab = $usulan ? 'Silakan periksa usulan di bawah, lalu tekan Simpan.' : 'Maaf, saya belum bisa menyelesaikan permintaan itu. Coba jelaskan dengan cara lain.';
    }

    // Simpan ke riwayat (teks saja; foto diganti keterangan, usulan dicatat lengkap agar bisa dikoreksi)
    riwayat_tambah('user', $isi_pengguna . ($bagian_foto ? ' [melampirkan ' . count($bagian_foto) . ' foto]' : ''));
    $catatan = '';
    foreach ($usulan as $u) {
        $catatan .= "\n[Usulan " . $u['id'] . ' ' . $u['alat'] . ' menunggu tombol Simpan: '
            . json_encode($u['arg'], JSON_UNESCAPED_UNICODE) . ']';
    }
    riwayat_tambah('assistant', $jawab . $catatan);

    return ['ok' => true, 'teks' => $jawab, 'usulan' => $usulan, 'oleh' => $oleh, 'rincian' => $rincian];
}

/** Baca foto buku menjadi isian formulir. */
function baca_foto_buku(array $foto): array
{
    $bagian = ai_bagian_foto($foto);
    if (!$bagian) return ['ok' => false, 'galat' => 'Foto tidak terbaca. Coba ambil ulang.'];
    $perintah = "Baca foto buku ini (bisa sampul depan, sampul belakang, atau halaman hak cipta) dan isi data katalognya.\n"
        . aturan_katalog() . "\n"
        . "Balas HANYA dengan satu objek JSON, tanpa penjelasan, dengan kunci:\n"
        . '{"judul":"","pengarang":["..."],"sor":"","penerbit":"","tempat":"","tahun":"","isbn":"","edisi":"","kolasi":"",'
        . '"seri":"","klasifikasi":"","subjek":["..."],"bahasa":"id","prefix":"","sumber":"beli|hibah","catatan":"","tidak_terbaca":""}' . "\n"
        . "Kosongkan yang tidak terlihat; jangan mengarang ISBN atau tahun. 'prefix' = tebakan kode eksemplar sesuai skema. "
        . "'tidak_terbaca' = daftar singkat data yang perlu dicek manual.";
    $r = ai_minta([['role' => 'user', 'content' => array_merge([['type' => 'text', 'text' => $perintah]], $bagian)]],
        ['gambar' => true, 'maks_token' => 2500]);
    if (!$r['ok']) return ['ok' => false, 'galat' => $r['galat'], 'rincian' => $r['rincian']];
    $j = ai_ambil_json((string)$r['pesan']['content']);
    if (!$j) return ['ok' => false, 'galat' => 'AI membalas dengan format yang tidak dikenali. Coba lagi.', 'rincian' => [$r['oleh']]];
    $hasil = [];
    foreach (['judul', 'pengarang', 'sor', 'penerbit', 'tempat', 'tahun', 'isbn', 'edisi', 'kolasi', 'seri', 'klasifikasi',
                 'subjek', 'bahasa', 'prefix', 'sumber', 'catatan', 'tidak_terbaca'] as $k) {
        $hasil[$k] = teks($j[$k] ?? '');
    }
    $hasil['bahasa'] = normal_bahasa($hasil['bahasa']);
    $hasil['prefix'] = prefix_valid($hasil['prefix']) ?? '';
    if (!preg_match('/^\d{4}$/', $hasil['tahun'])) $hasil['tahun'] = preg_match('/\b(19|20)\d{2}\b/', $hasil['tahun'], $m) ? $m[0] : '';
    $hasil['sumber'] = str_contains(strtolower($hasil['sumber']), 'hibah') ? '2' : (str_contains(strtolower($hasil['sumber']), 'beli') ? '1' : '');
    // Peringatan bila sudah ada
    $dup = alat_baca('cari_buku', ['kata' => $hasil['isbn'] !== '' ? $hasil['isbn'] : $hasil['judul']]);
    return ['ok' => true, 'data' => $hasil, 'oleh' => $r['oleh'], 'mirip' => array_slice($dup['hasil'] ?? [], 0, 3)];
}
