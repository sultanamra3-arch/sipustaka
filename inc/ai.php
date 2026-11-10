<?php
/*
 * Mesin AI multi-penyedia (semua lewat format OpenAI-compatible).
 * Urutan dicoba dari atas; bila limit habis (429), kunci salah, model hilang,
 * atau server sibuk, otomatis pindah ke penyedia/model berikutnya.
 */
declare(strict_types=1);

const AI_PRESET = [
    'gemini' => [
        'nama' => 'Google Gemini',
        'url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
        'model' => 'gemini-3.5-flash, gemini-3.6-flash, gemini-3.5-flash-lite, gemini-3.1-flash-lite, gemini-flash-lite-latest',
        'gambar' => true,
        'daftar' => 'https://aistudio.google.com/apikey',
    ],
    'groq' => [
        'nama' => 'Groq',
        'url' => 'https://api.groq.com/openai/v1',
        'model' => 'qwen/qwen3.8-27b, openai/gpt-oss-120b, openai/gpt-oss-20b',
        'gambar' => true,
        'daftar' => 'https://console.groq.com/keys',
    ],
    'mistral' => [
        'nama' => 'Mistral',
        'url' => 'https://api.mistral.ai/v1',
        'model' => 'mistral-small-latest',
        'gambar' => true,
        'daftar' => 'https://console.mistral.ai/api-keys',
    ],
    'openrouter' => [
        'nama' => 'OpenRouter',
        'url' => 'https://openrouter.ai/api/v1',
        'model' => 'nvidia/nemotron-3.5-super-vl:free, google/gemma-4-26b-a4b-it:free, nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free, dots-studio/dots-3-note-preview:free, google/gemma-4-31b-it:free, nvidia/nemotron-3-ultra-550b-a55b:free, nvidia/nemotron-3.5-lightning:free, openrouter/free',
        'gambar' => true,
        'daftar' => 'https://openrouter.ai/keys',
    ],
    'cerebras' => [
        'nama' => 'Cerebras',
        'url' => 'https://api.cerebras.ai/v1',
        'model' => '',            // kosong = dipilih otomatis dari daftar model akun
        'gambar' => true,
        'daftar' => 'https://cloud.cerebras.ai',
    ],
    'sambanova' => [
        'nama' => 'SambaNova',
        'url' => 'https://api.sambanova.ai/v1',
        'model' => '',
        'gambar' => true,
        'daftar' => 'https://cloud.sambanova.ai/apis',
    ],
    'nvidia' => [
        'nama' => 'NVIDIA',
        'url' => 'https://integrate.api.nvidia.com/v1',
        'model' => '',
        'gambar' => true,
        'daftar' => 'https://build.nvidia.com/settings/api-keys',
    ],
];

function ai_folder_data(): string
{
    $d = __DIR__ . '/../data';
    if (!is_dir($d)) @mkdir($d, 0750, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "Require all denied\n");
    if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    return $d;
}

function ai_pengaturan(): array
{
    $f = ai_folder_data() . '/ai-kunci.php';
    $s = is_file($f) ? (include $f) : [];
    $s = is_array($s) ? $s : [];
    $hasil = [];
    foreach (AI_PRESET as $id => $p) {
        $x = $s[$id] ?? [];
        $hasil[$id] = [
            'id' => $id,
            'nama' => $p['nama'],
            'url' => (string)($x['url'] ?? $p['url']),
            'daftar' => $p['daftar'],
            'kunci' => (string)($x['kunci'] ?? ''),
            'model' => (string)($x['model'] ?? $p['model']),
            'gambar' => (bool)($x['gambar'] ?? $p['gambar']),
            'aktif' => (bool)($x['aktif'] ?? true),
            'urutan' => (int)($x['urutan'] ?? (array_search($id, array_keys(AI_PRESET), true) + 1)),
        ];
    }
    uasort($hasil, fn($a, $b) => $a['urutan'] <=> $b['urutan']);
    return $hasil;
}

function ai_simpan_pengaturan(array $baru): void
{
    $simpan = [];
    foreach ($baru as $id => $x) {
        if (!isset(AI_PRESET[$id])) continue;
        $simpan[$id] = [
            'kunci' => (string)$x['kunci'], 'model' => (string)$x['model'], 'gambar' => (bool)$x['gambar'],
            'aktif' => (bool)$x['aktif'], 'urutan' => (int)$x['urutan'],
        ];
        if (!empty($x['url']) && $x['url'] !== AI_PRESET[$id]['url']) $simpan[$id]['url'] = (string)$x['url'];
    }
    $isi = "<?php\n// Kunci API Asisten AI — JANGAN dibagikan.\nreturn " . var_export($simpan, true) . ";\n";
    $f = ai_folder_data() . '/ai-kunci.php';
    if (@file_put_contents($f, $isi, LOCK_EX) === false) {
        throw new RuntimeException('Tidak bisa menulis ' . $f . '. Periksa izin folder "data".');
    }
    @chmod($f, 0640);
    if (function_exists('opcache_invalidate')) @opcache_invalidate($f, true);
}

/** Daftar kandidat [penyedia, model] yang aktif dan punya kunci, sesuai urutan. */
function ai_kandidat(bool $perlu_gambar = false): array
{
    $k = [];
    foreach (ai_pengaturan() as $p) {
        if (!$p['aktif'] || $p['kunci'] === '') continue;
        if ($perlu_gambar && !$p['gambar']) continue;
        $daftar_model = trim($p['model']) !== '' ? $p['model'] : implode(',', ai_model_otomatis($p));
        foreach (array_filter(array_map('trim', explode(',', $daftar_model))) as $m) {
            if ($perlu_gambar && !ai_model_bisa_gambar($m)) continue;
            // Pengaman saldo: di OpenRouter hanya model gratis yang boleh dipakai
            if ($p['id'] === 'openrouter' && !str_ends_with($m, ':free') && $m !== 'openrouter/free') continue;
            $k[] = ['p' => $p, 'model' => $m];
        }
    }
    return $k;
}

/** Model khusus teks yang dikenal (tidak bisa membaca foto). */
function ai_model_bisa_gambar(string $model): bool
{
    if (preg_match('/vl|vision|omni|llama-4|gemma-?[34]|gemini|qwen3\.[6-9]|qwen.*-vl|pixtral|mistral-(small|medium)|dots-3|openrouter\/free/i', $model)) return true;
    return !preg_match('/gpt-oss|llama-3|allam|mixtral|gemma2|deepseek|compound|whisper|nemotron-3-ultra|nemotron-3\.5-lightning|xing|lfm|flint|north-mini|cerebras|qwen-?3-?(32b|235b|coder)|glm|kimi|minimax/i', $model);
}

/**
 * Pilih model otomatis bila kolom model dikosongkan: ambil daftar model akun (disimpan 1 hari),
 * buang yang bukan model chat, urutkan menurut model yang umumnya bagus untuk tugas ini.
 */
function ai_model_otomatis(array $p): array
{
    $f = ai_folder_data() . '/ai-model-' . $p['id'] . '.json';
    $c = is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
    if (empty($c['ids']) || ($c['t'] ?? 0) < time() - 86400) {
        if (($c['gagal'] ?? 0) > time() - 600) return $c['ids'] ?? [];
        [$kode, $isi] = ai_http($p, '/models', null, 15);
        $j = json_decode($isi, true);
        if ($kode !== 200 || empty($j['data'])) {
            $c['gagal'] = time();
            @file_put_contents($f, json_encode($c));
            return $c['ids'] ?? [];
        }
        $ids = array_values(array_filter(array_map(fn($m) => (string)($m['id'] ?? ''), $j['data']), fn($id) => $id !== ''
            && !preg_match('/embed|rerank|retriev|guard|safety|reward|whisper|tts|speech|audio|image-gen|flux|stable|sdxl|parse|ocr|clip|nv-|bge|e5-|translate|detector|classif|rank/i', $id)));
        $suka = ['llama-4-maverick', 'llama-4-scout', 'qwen3', 'gpt-oss-120b', 'nemotron.*super', 'nemotron.*ultra', 'llama-3\.3-70b',
            'mistral-(medium|small)', 'gemma-?3', 'deepseek-v3', 'gpt-oss', 'llama'];
        usort($ids, function ($a, $b) use ($suka) {
            $skor = function ($id) use ($suka) {
                foreach ($suka as $i => $pola) if (preg_match('/' . $pola . '/i', $id)) return $i;
                return 99;
            };
            return $skor($a) <=> $skor($b);
        });
        $c = ['t' => time(), 'ids' => array_slice($ids, 0, 4)];
        @file_put_contents($f, json_encode($c));
    }
    return $c['ids'] ?? [];
}

function ai_siap(): bool
{
    return (bool)ai_kandidat();
}

/* ---------- Catatan jeda (cooldown) penyedia ---------- */

function ai_status_baca(): array
{
    $f = ai_folder_data() . '/ai-status.json';
    return is_file($f) ? (json_decode((string)@file_get_contents($f), true) ?: []) : [];
}

function ai_status_tulis(string $kunci, int $jeda_detik, string $pesan): void
{
    $f = ai_folder_data() . '/ai-status.json';
    $s = ai_status_baca();
    $s[$kunci] = ['sampai' => time() + $jeda_detik, 'pesan' => mb_substr($pesan, 0, 300), 'waktu' => time()];
    @file_put_contents($f, json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

function ai_status_hapus(string $kunci): void
{
    $s = ai_status_baca();
    if (!isset($s[$kunci])) return;
    unset($s[$kunci]);
    @file_put_contents(ai_folder_data() . '/ai-status.json', json_encode($s, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
}

/* ---------- Panggilan HTTP ---------- */

function ai_http(array $p, string $jalur, ?array $badan, int $batas_waktu = 70): array
{
    $c = curl_init(rtrim($p['url'], '/') . $jalur);
    $kepala = ['Authorization: Bearer ' . $p['kunci'], 'Content-Type: application/json'];
    if ($p['id'] === 'openrouter') {
        $kepala[] = 'HTTP-Referer: https://kelola.otomasismpn6keritang.my.id';
        $kepala[] = 'X-Title: SiPustaka SMPN 6 Keritang';
    }
    curl_setopt_array($c, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $kepala,
        CURLOPT_TIMEOUT => $batas_waktu,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'SiPustaka/' . VERSI,
    ]);
    if ($badan !== null) {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($badan, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }
    $isi = curl_exec($c);
    $kode = (int)curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $galat_curl = curl_error($c);
    curl_close($c);
    return [$kode, $isi === false ? '' : (string)$isi, $galat_curl];
}

function ai_pesan_galat(int $kode, string $isi, string $galat_curl): string
{
    if ($galat_curl !== '') return 'koneksi: ' . $galat_curl;
    $j = json_decode($isi, true);
    $m = $j['error']['message'] ?? ($j[0]['error']['message'] ?? ($j['message'] ?? ''));
    if (is_array($m)) $m = json_encode($m);
    return 'HTTP ' . $kode . ($m ? ': ' . mb_substr((string)$m, 0, 220) : '');
}

/**
 * Kirim percakapan ke AI. $opsi: alat (tools), gambar (bool), suhu, maks_token.
 * Hasil: ['ok'=>true, 'pesan'=>[role, content, tool_calls?], 'oleh'=>'Nama · model'] atau ['ok'=>false, 'galat'=>..., 'rincian'=>[]]
 */
function ai_minta(array $pesan, array $opsi = []): array
{
    $kandidat = ai_kandidat((bool)($opsi['gambar'] ?? false));
    if (!$kandidat) {
        return ['ok' => false, 'galat' => !empty($opsi['gambar'])
            ? 'Belum ada penyedia AI yang bisa membaca foto. Isi kunci API di Pengaturan AI.'
            : 'Belum ada kunci API. Isi dulu di Pengaturan AI.', 'rincian' => []];
    }
    $status = ai_status_baca();
    $rincian = [];
    $semua_jeda = true;
    foreach ($kandidat as $k) {
        $p = $k['p'];
        $kunci_status = $p['id'] . '|' . $k['model'];
        if (($status[$kunci_status]['sampai'] ?? 0) > time()) {
            $rincian[] = $p['nama'] . ' · ' . $k['model'] . ': dilewati (' . $status[$kunci_status]['pesan'] . ')';
            continue;
        }
        $semua_jeda = false;
        $badan = [
            'model' => $k['model'],
            'messages' => ai_sesuaikan_pesan($pesan, $p['id']),
            'temperature' => $opsi['suhu'] ?? 0.2,
            'max_tokens' => $opsi['maks_token'] ?? 4000,
        ];
        if (!empty($opsi['alat'])) {
            $badan['tools'] = $opsi['alat'];
            $badan['tool_choice'] = 'auto';
        }
        [$kode, $isi, $gc] = ai_http($p, '/chat/completions', $badan);
        $j = json_decode($isi, true);
        $m = $j['choices'][0]['message'] ?? null;
        if ($kode === 200 && is_array($m) && !isset($j['error'])) {
            ai_status_hapus($kunci_status);
            return ['ok' => true, 'pesan' => ai_rapikan_pesan($m), 'oleh' => $p['nama'] . ' · ' . $k['model'], 'rincian' => $rincian];
        }
        $teks = ai_pesan_galat($kode, $isi, $gc);
        $rincian[] = $p['nama'] . ' · ' . $k['model'] . ': ' . $teks;
        // Tentukan lama jeda sebelum dicoba lagi
        $jeda = match (true) {
            $kode === 429 => ai_jeda_429($isi),
            $kode === 401 || $kode === 403 => 3600,
            $kode === 404 => 6 * 3600,
            $kode === 400 => 0,          // permintaan ditolak (mis. model tak dukung alat/foto): coba berikutnya, tanpa jeda
            $kode >= 500 || $kode === 0 => 30,
            default => 30,
        };
        // Model tidak mendukung foto/alat: lewati tanpa jeda agar tetap dipakai untuk chat teks
        if (preg_match('/image|vision|multimodal|content must be a string|tool|function/i', $teks) && $kode >= 400 && $kode < 500 && $kode !== 429) $jeda = 0;
        if ($jeda > 0) ai_status_tulis($kunci_status, $jeda, $teks);
    }
    $g = $semua_jeda
        ? 'Semua AI sedang istirahat karena limitnya habis. Coba lagi beberapa menit lagi (limit harian direset pukul 14.00–15.00 WIB).'
        : 'Semua AI gagal dihubungi. Rincian di bawah.';
    return ['ok' => false, 'galat' => $g, 'rincian' => $rincian];
}

/**
 * Sesuaikan riwayat untuk penyedia tertentu: Gemini butuh thought_signature pada setiap
 * panggilan alat (pakai nilai lewati-validasi bila panggilan dibuat AI lain);
 * penyedia lain tidak mengenal kolom tambahan Google sehingga dibuang.
 */
function ai_sesuaikan_pesan(array $pesan, string $penyedia): array
{
    foreach ($pesan as &$m) {
        if (empty($m['tool_calls'])) continue;
        foreach ($m['tool_calls'] as &$t) {
            if ($penyedia === 'gemini') {
                if (empty($t['extra_content']['google']['thought_signature'])) {
                    $t['extra_content'] = ['google' => ['thought_signature' => 'skip_thought_signature_validator']];
                }
            } else {
                unset($t['extra_content']);
            }
        }
        unset($t);
    }
    unset($m);
    return $pesan;
}

/** Lama istirahat setelah 429: ikuti saran server; limit harian = 3 jam; selain itu ±1 menit. */
function ai_jeda_429(string $isi): int
{
    if (preg_match('/retryDelay"?\s*:\s*"?(\d+)(?:\.\d+)?s/i', $isi, $m)
        || preg_match('/(?:retry|try again) in (\d+)(?:\.\d+)?\s*s/i', $isi, $m)) {
        $d = (int)$m[1] + 2;
        if ($d >= 15) return min($d, 3 * 3600);
    }
    if (preg_match('/per ?day|PerDay|daily|RPD|TPD/i', $isi)) return 3 * 3600;
    return 65;
}

/** Samakan bentuk balasan antar-penyedia. */
function ai_rapikan_pesan(array $m): array
{
    $isi = $m['content'] ?? '';
    if (is_array($isi)) {
        $isi = implode("\n", array_map(fn($b) => is_array($b) ? (string)($b['text'] ?? '') : (string)$b, $isi));
    }
    $isi = trim(preg_replace('/<think>.*?<\/think>/s', '', (string)$isi));
    $hasil = ['role' => 'assistant', 'content' => $isi];
    if (!empty($m['tool_calls']) && is_array($m['tool_calls'])) {
        $tc = [];
        foreach ($m['tool_calls'] as $i => $t) {
            if (empty($t['function']['name'])) continue;
            $arg = $t['function']['arguments'] ?? '{}';
            if (is_array($arg)) $arg = json_encode($arg, JSON_UNESCAPED_UNICODE);
            $satu = [
                'id' => (string)($t['id'] ?? '') !== '' ? (string)$t['id'] : 'panggil_' . $i . '_' . bin2hex(random_bytes(3)),
                'type' => 'function',
                'function' => ['name' => preg_replace('/^.*[.:]/', '', (string)$t['function']['name']), 'arguments' => (string)$arg],
            ];
            // Gemini 3 mewajibkan thought_signature dikirim balik apa adanya
            if (!empty($t['extra_content']) && is_array($t['extra_content'])) $satu['extra_content'] = $t['extra_content'];
            $tc[] = $satu;
        }
        if ($tc) $hasil['tool_calls'] = $tc;
    }
    return $hasil;
}

/** Ambil objek JSON pertama dari teks balasan AI. */
function ai_ambil_json(string $teks): ?array
{
    $teks = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($teks));
    $j = json_decode($teks, true);
    if (is_array($j)) return $j;
    $a = strpos($teks, '{');
    $b = strrpos($teks, '}');
    if ($a === false || $b === false || $b <= $a) return null;
    $j = json_decode(substr($teks, $a, $b - $a + 1), true);
    return is_array($j) ? $j : null;
}

/** Ubah daftar data-URL foto dari peramban menjadi bagian pesan; tolak yang bukan gambar. */
function ai_bagian_foto(array $foto, int $maks = 4): array
{
    $bagian = [];
    foreach (array_slice($foto, 0, $maks) as $f) {
        $f = (string)$f;
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+$#', $f)) continue;
        if (strlen($f) > 4_500_000) continue;
        $bagian[] = ['type' => 'image_url', 'image_url' => ['url' => $f]];
    }
    return $bagian;
}

/* ---------- masuk dari SiGuru (v1.12) ---------- */
/** Kunci bersama SiGuru ↔ SiPustaka (data/sso-kunci.php), dibuat bila belum ada. */
function sso_kunci(): ?string
{
    $f = ai_folder_data() . '/sso-kunci.php';
    if (!is_file($f)) {
        @file_put_contents($f, "<?php return '" . bin2hex(random_bytes(32)) . "';\n", LOCK_EX);
        @chmod($f, 0640);
    }
    $k = @include $f;
    return is_string($k) && strlen($k) >= 32 ? $k : null;
}

/** Tanda sekali pakai: true bila belum pernah dipakai (disimpan 5 menit). */
function sso_nonce_baru(string $n): bool
{
    $f = ai_folder_data() . '/sso-pakai.json';
    $fh = @fopen($f, 'c+');
    if (!$fh) return false;
    flock($fh, LOCK_EX);
    $isi = json_decode((string)stream_get_contents($fh), true) ?: [];
    $isi = array_filter($isi, fn($w) => $w > time() - 300);
    $baru = !isset($isi[$n]);
    $isi[$n] = time();
    ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($isi));
    flock($fh, LOCK_UN); fclose($fh);
    return $baru;
}
