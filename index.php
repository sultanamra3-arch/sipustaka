<?php
declare(strict_types=1);

require __DIR__ . '/inc/inti.php';
require __DIR__ . '/inc/katalog.php';
require __DIR__ . '/inc/layanan.php';
require __DIR__ . '/inc/ai.php';
require __DIR__ . '/inc/asisten.php';
require __DIR__ . '/inc/scanner.php';
require __DIR__ . '/inc/tampilan.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

mulai_sesi();

$halaman = [
    'masuk', 'keluar', 'beranda',
    'buku', 'buku_form', 'buku_detail', 'api_kode',
    'anggota', 'anggota_form', 'anggota_kelas',
    'pinjaman', 'asisten', 'api_ai', 'pengaturan_ai', 'scanner', 'api_scan', 'sso',
];
$p = (string)($_GET['p'] ?? '');
if (!in_array($p, $halaman, true)) {
    $p = pengguna() ? 'beranda' : 'masuk';
}

try {
    db();
} catch (Throwable $e) {
    http_response_code(500);
    kepala('Gagal tersambung');
    echo '<div class="kartu"><h2>Database belum tersambung</h2><p>' . h($e->getMessage())
        . '</p><p>Periksa <code>config.php</code> di folder aplikasi ini.</p></div>';
    kaki();
    exit;
}

if ($p !== 'masuk' && $p !== 'sso') wajib_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') cek_token();

try {
    require __DIR__ . '/hal/' . $p . '.php';
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log('[kelola] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if ($p === 'api_ai' || $p === 'api_kode' || $p === 'api_scan') {
        echo json_encode(['ok' => false, 'galat' => $e->getMessage(), 'teks' => 'Kesalahan server: ' . $e->getMessage()]);
        exit;
    }
    if (!headers_sent()) {
        kepala('Terjadi kesalahan');
    }
    echo '<div class="pesan galat">Terjadi kesalahan: ' . h($e->getMessage())
        . '<br>Data belum tersimpan. Jika server sempat membalas 503/Bad Gateway, cek dulu apakah data sudah masuk sebelum mengulang.</div>';
    kaki();
}
