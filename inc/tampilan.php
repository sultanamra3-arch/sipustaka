<?php
declare(strict_types=1);

function kepala(string $judul, string $aktif = ''): void
{
    $u = pengguna();
    ?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<script>
/* Saat dibuka sebagai aplikasi terpasang (ikon di layar HP), matikan zoom; di browser biasa zoom tetap bisa. */
(function () {
  var apl = (window.matchMedia && matchMedia('(display-mode: standalone), (display-mode: fullscreen), (display-mode: minimal-ui)').matches) || navigator.standalone;
  if (!apl) return;
  document.querySelector('meta[name=viewport]').setAttribute('content', 'width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover');
  document.documentElement.classList.add('apl');
  document.addEventListener('gesturestart', function (e) { e.preventDefault(); });
})();
</script>
<meta name="theme-color" content="#2A2C84">
<meta name="robots" content="noindex, nofollow">
<title><?= h($judul) ?> · SiPustaka</title>
<meta name="application-name" content="SiPustaka">
<meta name="apple-mobile-web-app-title" content="SiPustaka">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="manifest" href="manifest.webmanifest?v=<?= VERSI ?>">
<link rel="icon" href="aset/favicon.png?v=3">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700&display=swap">
<link rel="apple-touch-icon" href="aset/apple-touch-icon.png?v=3">
<link rel="stylesheet" href="aset/gaya.css?v=<?= VERSI ?>">
</head>
<body>
<header class="atas">
  <?php if (!empty($_SESSION['dari_siguru'])): ?><a class="ke-siguru" href="<?= h((string)k('url_siguru', 'https://guru.otomasismpn6keritang.my.id/')) ?>?p=beranda" title="Kembali ke SiGuru">‹ SiGuru</a><?php endif; ?>
  <a class="merek" href="?p=beranda"><img src="aset/logo.png" alt="Logo SMPN 6 Keritang"> <span><small>Perpustakaan SMPN 6 Keritang</small><b>SiPustaka</b></span></a>
  <?php if ($u): ?>
  <form method="post" action="?p=keluar" class="keluar"><?= input_token() ?><button title="Keluar">Keluar</button></form>
  <?php endif; ?>
</header>
<?php if ($u): ?>
<nav class="menu">
  <?php foreach (['beranda' => ['Beranda', '⌂'], 'buku' => ['Buku', '📚'], 'anggota' => ['Anggota', '👥'], 'pinjaman' => ['Pinjaman', '⇄'], 'asisten' => ['Asisten', '✨']] as $p => [$t, $i]): ?>
  <a href="?p=<?= $p ?>" class="<?= $aktif === $p ? 'aktif' : '' ?>"><span class="ik"><?= $i ?></span><?= $t ?></a>
  <?php endforeach; ?>
</nav>
<?php endif; ?>
<main>
<?php foreach (ambil_pesan() as [$j, $t]): ?>
  <div class="pesan <?= h($j) ?>"><?= $t /* sudah di-escape saat dibuat */ ?></div>
<?php endforeach;
}

function kaki(): void
{
    ?>
</main>
<footer class="bawah">SiPustaka v<?= VERSI ?> · Perpustakaan SMPN 6 Keritang · data langsung dari SLiMS</footer>
<script src="aset/app.js?v=<?= VERSI ?>"></script>
</body>
</html>
<?php
}

function pilihan(array $daftar, string $kolom_id, string $kolom_nama, $terpilih): string
{
    $o = '';
    foreach ($daftar as $r) {
        $s = ((string)$r[$kolom_id] === (string)$terpilih) ? ' selected' : '';
        $o .= '<option value="' . h($r[$kolom_id]) . '"' . $s . '>' . h($r[$kolom_nama]) . '</option>';
    }
    return $o;
}

function halaman_nomor(int $total, int $hal, int $per, array $param): string
{
    $jml = (int)ceil($total / $per);
    if ($jml <= 1) return '';
    $o = '<div class="halnav">';
    if ($hal > 1) $o .= '<a href="?' . h(http_build_query($param + ['h' => $hal - 1])) . '">‹ Sebelumnya</a>';
    $o .= '<span>Hal. ' . $hal . ' / ' . $jml . '</span>';
    if ($hal < $jml) $o .= '<a href="?' . h(http_build_query($param + ['h' => $hal + 1])) . '">Berikutnya ›</a>';
    return $o . '</div>';
}
