<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$aksi = (string)($_POST['aksi'] ?? $_GET['aksi'] ?? '');
if ($aksi === 'status') {
    echo json_encode(['ok' => true] + scanner_status(scanner_token()));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo '{"ok":false}';
    exit;
}
if ($aksi === 'kirim') {
    $kode = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string)post('kode')));
    if ($kode === '' || mb_strlen($kode) > 200) {
        echo '{"ok":false,"galat":"Kode kosong atau terlalu panjang"}';
        exit;
    }
    $t = scanner_token();
    scanner_kirim($t, $kode, post('enter') === '1');
    echo json_encode(['ok' => true, 'kode' => $kode] + scanner_status($t));
    exit;
}
echo '{"ok":false,"galat":"Aksi tidak dikenal"}';
