<?php
/*
 * Pengaturan aplikasi "Kelola Perpustakaan" SMPN 6 Keritang.
 * Biasanya tidak perlu diubah: koneksi database dibaca otomatis
 * dari konfigurasi SLiMS di public_html.
 */
return [
    // Folder SLiMS (tempat config/database.php berada)
    'slims_path' => '/home/bzydmpxm/public_html',

    // Isi HANYA jika pembacaan otomatis gagal (lihat pesan di layar)
    'db' => [
        'host'     => '',          // biasanya: localhost
        'port'     => 3306,
        'database' => '',          // bzydmpxm_amra
        'username' => '',
        'password' => '',
    ],

    // Masa berlaku anggota yang diseragamkan
    'masa_berlaku_anggota' => '2027-09-27',

    // Lokasi bawaan eksemplar (ID di tabel mst_location): 1 = Rak Perpustakaan
    'lokasi_bawaan' => '1',

    // Alamat pemicu gambar otomatis (sampul & foto). Salin dari tugas cron,
    // mis. https://otomasismpn6keritang.my.id/gambar-otomatis/?key=XXXX
    // Biarkan kosong: gambar tetap dibuat oleh cron dalam 10 menit.
    'gambar_otomatis_url' => '',

    // Alamat OPAC untuk tautan "lihat di website"
    'opac_url' => 'https://otomasismpn6keritang.my.id',
];
