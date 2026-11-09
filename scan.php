<?php
/*
 * Titik ambil kode untuk laptop (dipanggil jembatan.js setiap ±1 detik).
 * Sengaja ringan: tanpa sesi & tanpa database; hanya token rahasia.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') exit;

$t = (string)($_GET['t'] ?? '');
$s = (int)($_GET['s'] ?? -1);
if (!preg_match('/^[a-f0-9]{24}$/', $t)) {
    http_response_code(403);
    exit('{"ok":false,"galat":"Token tidak valid"}');
}
$folder = __DIR__ . '/data/scan';
$akun = [];            // token => nama lengkap
foreach (glob($folder . '/kunci-*.json') ?: [] as $k) {
    $j = json_decode((string)@file_get_contents($k), true);
    if (!empty($j['token'])) $akun[(string)$j['token']] = (string)($j['nama'] ?? '');
}
foreach (glob($folder . '/kunci-*.txt') ?: [] as $k) {   // format lama
    $akun[trim((string)@file_get_contents($k))] = '';
}
$sah = false;
foreach (array_keys($akun) as $tk) {
    if (hash_equals((string)$tk, $t)) { $sah = true; break; }
}
if (!$sah) {
    http_response_code(403);
    exit('{"ok":false,"galat":"Bookmark scanner sudah tidak berlaku. Pasang ulang dari halaman Scanner SiPustaka."}');
}

// Kanal mengikuti akun yang sedang login di SLiMS laptop (dikirim jembatan.js sebagai n=)
$nama = trim((string)($_GET['n'] ?? ''));
$nama_akun = $akun[$t];
if ($nama !== '') {
    $cocok = null;
    foreach ($akun as $tk => $nm) {
        if ($nm !== '' && mb_strtolower(trim($nm)) === mb_strtolower($nama)) { $cocok = (string)$tk; $nama_akun = $nm; break; }
    }
    if ($cocok === null) {
        echo json_encode(['ok' => true, 'menunggu' => true, 'akun' => $nama, 'n' => 0, 'kode' => [],
            'pesan' => 'Akun "' . $nama . '" belum mengaktifkan Scanner. Di HP, login SiPustaka dengan akun ini lalu buka menu Scanner.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $t = $cocok;
}

$f = $folder . '/kanal-' . substr(hash('sha256', $t), 0, 32) . '.json';
$h = fopen($f, 'c+');
if (!$h) exit('{"ok":false,"galat":"Server tidak bisa menulis data"}');
flock($h, LOCK_EX);
$d = json_decode((string)stream_get_contents($h), true) ?: ['n' => 0, 'item' => [], 'laptop' => 0];
$d['laptop'] = time();
$ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$d['perangkat'] = str_contains($ua, 'CrOS') ? 'Chromebook' : (str_contains($ua, 'Windows') ? 'Windows' : (str_contains($ua, 'Linux') ? 'Linux' : (str_contains($ua, 'Mac') ? 'Mac' : 'Laptop')));
ftruncate($h, 0);
rewind($h);
fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE));
fflush($h);
flock($h, LOCK_UN);
fclose($h);

$baru = [];
if ($s >= 0) {
    foreach ($d['item'] as $i) {
        if ($i['id'] > $s && time() - (int)$i['t'] <= 120) $baru[] = ['id' => $i['id'], 'kode' => $i['kode'], 'enter' => (bool)$i['enter']];
    }
}
echo json_encode(['ok' => true, 'akun' => $nama_akun, 'n' => (int)$d['n'], 'kode' => $baru], JSON_UNESCAPED_UNICODE);
