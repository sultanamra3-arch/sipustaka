<?php
/* Masuk tanpa login ulang dari SiGuru (khusus admin SiGuru) — v1.12.
 * SiGuru mengirim ?p=sso&u=<nama pengguna SLiMS>&t=<waktu>&n=<acak>&s=<HMAC-SHA256>. Kunci bersama: data/sso-kunci.php
 * (dibuat otomatis oleh SiGuru atau SiPustaka). Berlaku 60 detik dan hanya sekali pakai. */
declare(strict_types=1);

$u = (string)($_GET['u'] ?? '');
$t = (int)($_GET['t'] ?? 0);
$n = (string)($_GET['n'] ?? '');
$s = (string)($_GET['s'] ?? '');
$ke = in_array($_GET['ke'] ?? '', ['pinjaman', 'buku', 'anggota'], true) ? (string)$_GET['ke'] : 'beranda';
$kunci = sso_kunci();
$ok = $kunci && preg_match('/^[a-f0-9]{16}$/', $n) && preg_match('/^[a-f0-9]{64}$/', $s) && abs(time() - $t) <= 60
    && hash_equals(hash_hmac('sha256', $u . '|' . $t . '|' . $n, $kunci), $s) && sso_nonce_baru($n);
$a = $ok ? satu('SELECT user_id, username, realname FROM user WHERE username = ? LIMIT 1', [$u]) : null;
if (!$a) {
    pesan('Tidak bisa masuk otomatis dari SiGuru (tautan kedaluwarsa atau akun tidak cocok). Silakan masuk dengan kata sandi.', 'galat');
    ke('?p=' . (pengguna() ? $ke : 'masuk'));
}
session_regenerate_id(true);
$_SESSION['dari_siguru'] = true;
$_SESSION['pengguna'] = ['id' => (int)$a['user_id'], 'username' => $a['username'], 'nama' => $a['realname']];
ke('?p=' . $ke);
