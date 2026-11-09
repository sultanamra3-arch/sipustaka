<?php
declare(strict_types=1);

if (pengguna()) ke('?p=beranda');

$galat = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    cek_token();
    $sisa = login_terkunci();
    if ($sisa > 0) {
        $galat = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . ceil($sisa / 60) . ' menit.';
    } else {
        $nama = post('username');
        $sandi = (string)($_POST['password'] ?? '');
        $u = satu('SELECT user_id, username, realname, passwd FROM user WHERE username = ? LIMIT 1', [$nama]);
        if ($u && cocok_sandi($sandi, (string)$u['passwd'])) {
            hapus_gagal_login();
            session_regenerate_id(true);
            $_SESSION['pengguna'] = ['id' => (int)$u['user_id'], 'username' => $u['username'], 'nama' => $u['realname']];
            ke('?p=beranda');
        }
        catat_gagal_login();
        usleep(400000);
        $galat = 'Nama pengguna atau kata sandi salah.';
    }
}

kepala('Masuk');
?>
<div class="kartu masuk">
  <img class="logo-masuk" src="aset/logo.png" alt="Logo SMPN 6 Keritang">
  <h1>SiPustaka</h1>
  <p class="redup" style="margin-top:0"><b>Kelola Perpustakaan SMPN 6 Keritang</b></p>
  <p class="redup">Pakai akun admin SLiMS yang sama (mis. <b>admin</b>, <b>Admin2</b>, <b>Admin3</b>).</p>
  <?php if ($galat): ?><div class="pesan galat"><?= h($galat) ?></div><?php endif; ?>
  <form method="post" action="?p=masuk">
    <?= input_token() ?>
    <label>Nama pengguna<input name="username" autocomplete="username" required autofocus value="<?= h(post('username')) ?>"></label>
    <label>Kata sandi<input type="password" name="password" autocomplete="current-password" required></label>
    <button class="utama lebar">Masuk</button>
  </form>
</div>
<?php
kaki();
