<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
set_time_limit(180);

function balas(array $d): never
{
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') balas(['ok' => false, 'galat' => 'Metode tidak didukung']);

$aksi = (string)post('aksi');
$foto = array_values(array_filter((array)($_POST['foto'] ?? []), 'is_string'));

try {
    switch ($aksi) {
        case 'baca_foto':
            balas(baca_foto_buku($foto));

        case 'chat':
            $teks = mb_substr((string)post('pesan'), 0, 4000);
            if ($teks === '' && !$foto) balas(['ok' => false, 'teks' => 'Tulis pesan atau lampirkan foto.']);
            $h = asisten_giliran($teks, $foto);
            $h['usulan'] = array_map(fn($u) => [
                'id' => $u['id'], 'alat' => $u['alat'], 'judul' => $u['judul'], 'baris' => $u['baris'],
                'formulir' => $u['alat'] === 'tambah_buku' ? '?p=buku_form&draf=' . $u['id'] : null,
            ], $h['usulan']);
            // tutup sesi agar permintaan lain tidak menunggu
            balas($h);

        case 'simpan':
            balas(simpan_usulan((string)post('id')));

        case 'batal':
            $id = (string)post('id');
            if (isset($_SESSION['usulan'][$id]) && empty($_SESSION['usulan'][$id]['selesai'])) {
                $_SESSION['usulan'][$id]['selesai'] = true;
                riwayat_tambah('assistant', '[Catatan sistem] Usulan ' . $id . ' DIBATALKAN oleh pengguna.');
            }
            balas(['ok' => true]);

        case 'baru':
            $_SESSION['asisten'] = [];
            $_SESSION['usulan'] = [];
            balas(['ok' => true]);
    }
    balas(['ok' => false, 'galat' => 'Aksi tidak dikenal']);
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('[kelola-ai] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    balas(['ok' => false, 'galat' => 'Kesalahan server: ' . $e->getMessage(), 'teks' => 'Kesalahan server: ' . $e->getMessage()]);
}
