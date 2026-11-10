<?php
/* Inti aplikasi: konfigurasi, database, sesi, keamanan, fungsi bantu. */
declare(strict_types=1);

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

const VERSI = '1.11';

$GLOBALS['KONFIG'] = require __DIR__ . '/../config.php';

function k(string $kunci, $bawaan = null)
{
    return $GLOBALS['KONFIG'][$kunci] ?? $bawaan;
}

/* ---------- Database ---------- */

function konfig_db(): array
{
    $c = k('db', []);
    if (!empty($c['host']) && !empty($c['database'])) {
        return $c;
    }
    $f = rtrim((string)k('slims_path'), '/') . '/config/database.php';
    if (!is_file($f) || !is_readable($f)) {
        throw new RuntimeException('Konfigurasi SLiMS tidak ditemukan di ' . $f
            . '. Isi bagian "db" di config.php secara manual.');
    }
    try {
        $r = (static function ($f) { return include $f; })($f);
    } catch (Throwable $e) {
        throw new RuntimeException('Gagal membaca ' . $f . ' (' . $e->getMessage()
            . '). Isi bagian "db" di config.php secara manual.');
    }
    if (is_array($r) && isset($r['nodes']) && is_array($r['nodes'])) {
        $profil = $r['default_profile'] ?? 'SLiMS';
        $n = $r['nodes'][$profil] ?? reset($r['nodes']);
        return [
            'host' => $n['host'] ?? 'localhost',
            'port' => (int)($n['port'] ?? 3306),
            'database' => $n['database'] ?? '',
            'username' => $n['username'] ?? '',
            'password' => $n['password'] ?? '',
        ];
    }
    if (defined('DB_HOST')) {
        return [
            'host' => DB_HOST, 'port' => defined('DB_PORT') ? (int)DB_PORT : 3306,
            'database' => DB_NAME, 'username' => DB_USERNAME, 'password' => DB_PASSWORD,
        ];
    }
    throw new RuntimeException('Format konfigurasi SLiMS tidak dikenali. Isi bagian "db" di config.php.');
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = konfig_db();
    $dsn = 'mysql:host=' . $c['host'] . ';port=' . (int)($c['port'] ?: 3306)
        . ';dbname=' . $c['database'] . ';charset=utf8mb4';
    $pdo = new PDO($dsn, $c['username'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}

function q(string $sql, array $p = []): PDOStatement
{
    $s = db()->prepare($sql);
    $s->execute($p);
    return $s;
}

function satu(string $sql, array $p = [])
{
    $r = q($sql, $p)->fetch();
    return $r === false ? null : $r;
}

function nilai(string $sql, array $p = [])
{
    $r = q($sql, $p)->fetchColumn();
    return $r === false ? null : $r;
}

function semua(string $sql, array $p = []): array
{
    return q($sql, $p)->fetchAll();
}

/* ---------- Sesi & keamanan ---------- */

function mulai_sesi(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('KELOLAPERPUS');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 7,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 7));
    session_start();
}

function h($s): string
{
    return htmlspecialchars((string)($s ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function input_token(): string
{
    return '<input type="hidden" name="_t" value="' . h(token()) . '">';
}

function cek_token(): void
{
    $t = (string)($_POST['_t'] ?? '');
    if ($t === '' || !hash_equals(token(), $t)) {
        http_response_code(400);
        exit('Sesi formulir kedaluwarsa. Kembali, muat ulang halaman, lalu coba lagi.');
    }
}

function pengguna(): ?array
{
    return $_SESSION['pengguna'] ?? null;
}

function wajib_login(): void
{
    if (!pengguna()) {
        header('Location: ?p=masuk');
        exit;
    }
}

function pesan(string $teks, string $jenis = 'ok'): void
{
    $_SESSION['pesan'][] = [$jenis, $teks];
}

function ambil_pesan(): array
{
    $p = $_SESSION['pesan'] ?? [];
    unset($_SESSION['pesan']);
    return $p;
}

function ke(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function post(string $k, $bawaan = '')
{
    $v = $_POST[$k] ?? $bawaan;
    return is_string($v) ? trim($v) : $v;
}

function get(string $k, $bawaan = '')
{
    $v = $_GET[$k] ?? $bawaan;
    return is_string($v) ? trim($v) : $v;
}

function kosong_jadi_null(?string $s): ?string
{
    $s = trim((string)$s);
    return $s === '' ? null : $s;
}

function sekarang(): string
{
    return date('Y-m-d H:i:s');
}

function hari_ini(): string
{
    return date('Y-m-d');
}

function tgl(?string $d): string
{
    if (!$d || str_starts_with($d, '0000')) return '-';
    $bln = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $t = strtotime($d);
    return $t ? date('j', $t) . ' ' . $bln[(int)date('n', $t)] . ' ' . date('Y', $t) : '-';
}

/* ---------- Pembatas percobaan login ---------- */

function berkas_batas(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'x';
    return sys_get_temp_dir() . '/kelolaperpus_' . md5($ip . __DIR__) . '.json';
}

function login_terkunci(): int
{
    $f = berkas_batas();
    if (!is_file($f)) return 0;
    $d = json_decode((string)@file_get_contents($f), true) ?: [];
    if (($d['n'] ?? 0) >= 5 && time() - ($d['t'] ?? 0) < 600) {
        return 600 - (time() - $d['t']);
    }
    if (time() - ($d['t'] ?? 0) >= 600) @unlink($f);
    return 0;
}

function catat_gagal_login(): void
{
    $f = berkas_batas();
    $d = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    $d['n'] = ($d['n'] ?? 0) + 1;
    $d['t'] = time();
    @file_put_contents($f, json_encode($d));
}

function hapus_gagal_login(): void
{
    @unlink(berkas_batas());
}

function cocok_sandi(string $sandi, string $hash): bool
{
    if ($hash === '') return false;
    if (password_verify($sandi, $hash)) return true;
    // SLiMS lama memakai md5
    return strlen($hash) === 32 && hash_equals(strtolower($hash), md5($sandi));
}

/* ---------- Pemicu gambar otomatis ---------- */

function picu_gambar_otomatis(): void
{
    $url = (string)k('gambar_otomatis_url', '');
    if ($url === '' || !function_exists('curl_init')) return;
    $c = curl_init($url);
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'KelolaPerpustakaan/' . VERSI,
    ]);
    @curl_exec($c);
    curl_close($c);
}
