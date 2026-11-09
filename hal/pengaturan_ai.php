<?php
declare(strict_types=1);

set_time_limit(180);
$set = ai_pengaturan();
$hasil_tes = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = post('aksi');
    if ($aksi === 'simpan') {
        $baru = [];
        foreach ($set as $id => $p) {
            $kunci = trim((string)($_POST['kunci'][$id] ?? ''));
            if (!empty($_POST['hapus'][$id])) $kunci = '';
            elseif ($kunci === '') $kunci = $p['kunci']; // kosong = tetap pakai kunci lama
            $baru[$id] = [
                'kunci' => $kunci,
                'model' => trim(preg_replace('/\s*,\s*/', ', ', (string)($_POST['model'][$id] ?? ''))),
                'gambar' => !empty($_POST['gambar'][$id]),
                'aktif' => !empty($_POST['aktif'][$id]),
                'urutan' => (int)($_POST['urutan'][$id] ?? 9),
                'url' => $p['url'],
            ];
        }
        ai_simpan_pengaturan($baru);
        @unlink(ai_folder_data() . '/ai-status.json');
        pesan('Pengaturan AI tersimpan. Tekan "Tes semua" untuk memastikan kuncinya berfungsi.');
        ke('?p=pengaturan_ai');
    }
    if ($aksi === 'tes') {
        foreach (ai_kandidat() as $k) {
            $mulai = microtime(true);
            [$kode, $isi, $gc] = ai_http($k['p'], '/chat/completions', [
                'model' => $k['model'],
                'messages' => [['role' => 'user', 'content' => 'Balas hanya dengan kata: SIAP']],
                'max_tokens' => 20,
            ], 40);
            $j = json_decode($isi, true);
            $ok = $kode === 200 && isset($j['choices'][0]['message']);
            $hasil_tes[] = [
                'nama' => $k['p']['nama'] . ' · ' . $k['model'],
                'ok' => $ok,
                'teks' => $ok ? 'Berfungsi (' . round(microtime(true) - $mulai, 1) . ' detik)' : ai_pesan_galat($kode, $isi, $gc),
            ];
            if ($ok) ai_status_hapus($k['p']['id'] . '|' . $k['model']);
        }
        if (!$hasil_tes) pesan('Belum ada penyedia aktif yang punya kunci.', 'galat');
    }
    $set = ai_pengaturan();
}

$status = ai_status_baca();
kepala('Pengaturan AI', 'asisten');
?>
<div class="judul-baris">
  <h1>Pengaturan AI</h1>
  <a class="tombol" href="?p=asisten">‹ Asisten</a>
</div>

<div class="kartu kecil">
  <p><b>Cara dapat kunci gratis</b> (cukup login akun Google/email, tanpa kartu kredit):</p>
  <ol>
    <li><b>Gemini</b> (paling disarankan, bisa baca foto): buka <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener">aistudio.google.com/apikey</a> → <i>Create API key</i> → salin.</li>
    <li><b>Groq</b> (cadangan, cepat): <a href="https://console.groq.com/keys" target="_blank" rel="noopener">console.groq.com/keys</a> → <i>Create API Key</i>.</li>
    <li><b>OpenRouter</b> (cadangan): hanya model berakhiran <code>:free</code> yang dipakai, jadi saldo tidak pernah terpotong.</li>
    <li><b>Cerebras</b>, <b>SambaNova</b>, <b>NVIDIA</b>, <b>Mistral</b>: cadangan tambahan, gratis. Cukup tempel kunci; kolom Model boleh dikosongkan, nanti dipilih otomatis.</li>
  </ol>
  <p class="redup">AI dicoba berurutan dari nomor terkecil. Jika limit satu AI habis, otomatis pindah ke berikutnya. Cukup satu akun per layanan.</p>
</div>

<?php if ($hasil_tes): ?>
<div class="kartu">
  <h2>Hasil tes</h2>
  <ul class="daftar">
    <?php foreach ($hasil_tes as $t): ?>
      <li><b><?= $t['ok'] ? '✅' : '❌' ?> <?= h($t['nama']) ?></b><small><?= h($t['teks']) ?></small></li>
    <?php endforeach; ?>
  </ul>
  <p class="redup kecil">Jika "HTTP 404 / model not found": nama model sudah diganti penyedianya. Buka halaman model penyedia itu dan salin nama model yang baru ke kolom Model.</p>
</div>
<?php endif; ?>

<form method="post" class="formulir" autocomplete="off">
  <?= input_token() ?>
  <input type="hidden" name="aksi" value="simpan">
  <?php foreach ($set as $id => $p): ?>
  <fieldset>
    <legend><?= h($p['nama']) ?></legend>
    <div class="baris2">
      <label class="centang"><input type="checkbox" name="aktif[<?= $id ?>]" value="1" <?= $p['aktif'] ? 'checked' : '' ?>> Aktif</label>
      <label>Urutan<input type="number" name="urutan[<?= $id ?>]" min="1" max="9" value="<?= (int)$p['urutan'] ?>"></label>
    </div>
    <label>Kunci API
      <small><?= $p['kunci'] !== '' ? '(tersimpan: ••••' . h(substr($p['kunci'], -4)) . ' — kosongkan bila tidak diganti)' : '(belum diisi)' ?>
        · <a href="<?= h($p['daftar']) ?>" target="_blank" rel="noopener">ambil kunci ↗</a></small>
      <input type="password" name="kunci[<?= $id ?>]" autocomplete="off" spellcheck="false" placeholder="<?= $p['kunci'] !== '' ? 'tersimpan' : 'tempel kunci di sini' ?>">
    </label>
    <?php if ($p['kunci'] !== ''): ?><label class="centang"><input type="checkbox" name="hapus[<?= $id ?>]" value="1"> Hapus kunci ini</label><?php endif; ?>
    <label>Model <small>(boleh lebih dari satu, pisahkan koma; dicoba berurutan<?= $p['model'] === '' ? '; kosong = otomatis' : '' ?>)</small>
      <input name="model[<?= $id ?>]" value="<?= h($p['model']) ?>" spellcheck="false" placeholder="otomatis"></label>
    <?php if ($p['model'] === '' && $p['kunci'] !== ''): $oto = ai_model_otomatis($p); ?>
      <p class="kecil redup">Model otomatis: <?= $oto ? h(implode(', ', $oto)) : 'belum bisa diambil (cek kunci)' ?></p>
    <?php endif; ?>
    <label class="centang"><input type="checkbox" name="gambar[<?= $id ?>]" value="1" <?= $p['gambar'] ? 'checked' : '' ?>> Model ini bisa membaca foto</label>
    <?php foreach (array_filter(array_map('trim', explode(',', $p['model']))) as $m):
        $st = $status[$id . '|' . $m] ?? null; if ($st && $st['sampai'] > time()): ?>
      <p class="kecil redup">⏸ <?= h($m) ?> istirahat ±<?= ceil(($st['sampai'] - time()) / 60) ?> menit: <?= h($st['pesan']) ?></p>
    <?php endif; endforeach; ?>
  </fieldset>
  <?php endforeach; ?>
  <button class="utama lebar">Simpan pengaturan</button>
</form>

<form method="post" style="margin-top:.8rem">
  <?= input_token() ?><input type="hidden" name="aksi" value="tes">
  <button class="lebar">Tes semua AI</button>
</form>
<p class="redup kecil">Kunci disimpan di server sekolah (folder <code>data</code>, tidak bisa dibuka dari internet), tidak pernah dikirim ke HP.</p>
<?php
kaki();
