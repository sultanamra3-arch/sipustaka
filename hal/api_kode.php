<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
$prefix = prefix_valid((string)get('prefix'));
if (!$prefix) {
    echo json_encode(['ok' => false]);
    exit;
}
$n = nomor_berikut($prefix);
echo json_encode([
    'ok' => true,
    'prefix' => $prefix,
    'kode' => format_kode($prefix, $n),
    'tipe' => nama_tipe_koleksi($prefix),
    'keterangan' => keterangan_prefix($prefix),
    'sumber' => sumber_bawaan($prefix),
]);
exit;
