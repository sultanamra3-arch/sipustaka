<?php
/* Scanner HP → laptop: kanal per pengguna disimpan sebagai berkas kecil di data/scan. */
declare(strict_types=1);

function scanner_folder(): string
{
    $d = ai_folder_data() . '/scan';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

/** Token rahasia milik pengguna yang sedang login (dibuat bila belum ada). */
function scanner_token(bool $baru = false): string
{
    $u = pengguna();
    $uid = (int)($u['id'] ?? 0);
    $fj = scanner_folder() . '/kunci-' . $uid . '.json';
    $ft = scanner_folder() . '/kunci-' . $uid . '.txt';
    $d = is_file($fj) ? (json_decode((string)@file_get_contents($fj), true) ?: []) : [];
    $t = (string)($d['token'] ?? (is_file($ft) ? trim((string)@file_get_contents($ft)) : ''));
    if ($baru || !preg_match('/^[a-f0-9]{24}$/', $t)) {
        if ($t !== '') @unlink(scanner_berkas_kanal($t));
        $t = bin2hex(random_bytes(12));
    }
    $nama = trim((string)($u['nama'] ?? ''));
    if (($d['token'] ?? '') !== $t || ($d['nama'] ?? '') !== $nama) {
        @file_put_contents($fj, json_encode(['token' => $t, 'nama' => $nama, 'user' => (string)($u['username'] ?? '')], JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    if (is_file($ft)) @unlink($ft);
    return $t;
}

function scanner_berkas_kanal(string $token): string
{
    return scanner_folder() . '/kanal-' . substr(hash('sha256', $token), 0, 32) . '.json';
}

/** Ubah isi kanal secara aman (dengan kunci berkas). */
function scanner_ubah(string $token, callable $fn): array
{
    $f = scanner_berkas_kanal($token);
    $h = fopen($f, 'c+');
    if (!$h) throw new RuntimeException('Tidak bisa menulis folder data/scan.');
    flock($h, LOCK_EX);
    $isi = stream_get_contents($h);
    $d = json_decode((string)$isi, true) ?: ['n' => 0, 'item' => [], 'laptop' => 0];
    $d = $fn($d);
    ftruncate($h, 0);
    rewind($h);
    fwrite($h, json_encode($d, JSON_UNESCAPED_UNICODE));
    fflush($h);
    flock($h, LOCK_UN);
    fclose($h);
    return $d;
}

function scanner_kirim(string $token, string $kode, bool $enter): array
{
    return scanner_ubah($token, function (array $d) use ($kode, $enter) {
        $d['n']++;
        $d['item'][] = ['id' => $d['n'], 'kode' => $kode, 'enter' => $enter, 't' => time()];
        $d['item'] = array_slice($d['item'], -20);
        return $d;
    });
}

function scanner_status(string $token): array
{
    $f = scanner_berkas_kanal($token);
    $d = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $terakhir = (int)($d['laptop'] ?? 0);
    return ['laptop' => $terakhir > 0 && time() - $terakhir <= 8, 'terakhir' => $terakhir, 'perangkat' => (string)($d['perangkat'] ?? '')];
}
