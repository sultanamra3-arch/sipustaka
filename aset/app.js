(function () {
  'use strict';

  // Pasang sebagai aplikasi (PWA)
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('sw.js').catch(function () {});
    });
  }

  // Pemilih jenis / kode eksemplar
  var jenis = document.getElementById('jenis');
  if (jenis) {
    var paket = document.getElementById('bagian-paket');
    var mapel = document.getElementById('mapel');
    var kelas = document.getElementById('kelas_buku');
    var guru = document.getElementById('buku_guru');
    var pratinjau = document.getElementById('pratinjau-kode');
    var nomor = 0;

    function prefix() {
      if (jenis.value === 'PAKET') {
        if (!mapel.value || !kelas.value) return '';
        return mapel.value + kelas.value + (guru.checked ? 'G' : '');
      }
      return jenis.value;
    }

    function segarkan() {
      paket.hidden = jenis.value !== 'PAKET';
      mapel.required = kelas.required = jenis.value === 'PAKET';
      var p = prefix();
      if (!p) { pratinjau.hidden = true; return; }
      var ini = ++nomor;
      fetch('?p=api_kode&prefix=' + encodeURIComponent(p), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (ini !== nomor || !d.ok) return;
          pratinjau.hidden = false;
          pratinjau.innerHTML = '';
          var t = document.createElement('div');
          t.append('Kode berikutnya: ');
          var b = document.createElement('b'); b.textContent = d.kode; t.append(b);
          var s = document.createElement('div'); s.className = 'kecil';
          s.textContent = d.keterangan + ' · tipe koleksi ' + d.tipe + ' · sumber otomatis: ' + (d.sumber === 1 ? 'beli' : 'hibah');
          pratinjau.append(t, s);
        })
        .catch(function () { pratinjau.hidden = true; });
    }

    [jenis, mapel, kelas, guru].forEach(function (el) { el && el.addEventListener('change', segarkan); });
    segarkan();
  }

  // Buku titipan
  var titipan = document.getElementById('titipan');
  if (titipan) {
    var pemilik = document.getElementById('bagian-pemilik');
    titipan.addEventListener('change', function () { pemilik.hidden = !titipan.checked; });
  }

  // Isi kelas massal
  var semua = document.getElementById('pilih-semua');
  if (semua) {
    var kotak = document.querySelectorAll('input[name="pilih[]"]');
    var jml = document.getElementById('jml-pilih');
    function hitung() {
      var n = 0; kotak.forEach(function (k) { if (k.checked) n++; });
      jml.textContent = n;
    }
    semua.addEventListener('change', function () { kotak.forEach(function (k) { k.checked = semua.checked; }); hitung(); });
    kotak.forEach(function (k) { k.addEventListener('change', hitung); });
  }

  // Cegah klik ganda saat menyimpan (hosting kadang lambat)
  document.querySelectorAll('form[method="post"]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      if (f.dataset.terkirim) { e.preventDefault(); return; }
      f.dataset.terkirim = '1';
      f.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
      setTimeout(function () { delete f.dataset.terkirim; f.querySelectorAll('button').forEach(function (b) { b.disabled = false; }); }, 15000);
    });
  });

  /* ===================== AI ===================== */

  // Perkecil foto di HP sebelum dikirim (hemat kuota & lebih cepat)
  function kecilkanFoto(berkas, maks) {
    maks = maks || 1600;
    return new Promise(function (ok, gagal) {
      var url = URL.createObjectURL(berkas);
      var img = new Image();
      img.onload = function () {
        var s = Math.min(1, maks / Math.max(img.width, img.height));
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        URL.revokeObjectURL(url);
        ok(c.toDataURL('image/jpeg', 0.82));
      };
      img.onerror = function () { URL.revokeObjectURL(url); gagal(new Error('Foto tidak bisa dibuka')); };
      img.src = url;
    });
  }

  function kirimAI(token, data) {
    var fd = new FormData();
    fd.append('_t', token);
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (Array.isArray(v)) v.forEach(function (x) { fd.append(k + '[]', x); });
      else fd.append(k, v);
    });
    return fetch('?p=api_ai', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        try { return JSON.parse(t); }
        catch (e) { return { ok: false, galat: t.indexOf('Sesi formulir') >= 0 ? 'Sesi kedaluwarsa, muat ulang halaman.' : 'Server membalas tidak wajar (mungkin 503/sibuk). Coba lagi.' }; }
      })
      .catch(function () { return { ok: false, galat: 'Tidak tersambung ke server. Periksa internet.' }; });
  }

  function el(tag, kelas, teks) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (teks != null) e.textContent = teks;
    return e;
  }

  // Markdown sederhana: **tebal**, baris baru, daftar "- "
  function md(teks) {
    var d = document.createElement('div');
    d.textContent = teks;
    var h = d.innerHTML
      .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
      .replace(/`([^`\n]+)`/g, '<code>$1</code>')
      .replace(/^\s*[-*] /gm, '• ')
      .replace(/\n/g, '<br>');
    return h;
  }

  /* ----- Isi formulir buku dari foto ----- */
  var fotoBuku = document.getElementById('foto-buku');
  if (fotoBuku) {
    var form = document.getElementById('form-buku');
    var st = document.getElementById('foto-status');
    var kumpulan = document.getElementById('foto-kumpulan');
    var tombolBaca = document.getElementById('foto-baca');
    var fotoForm = [];

    function gambarKumpulan() {
      kumpulan.innerHTML = '';
      fotoForm.forEach(function (f, i) {
        var w = el('span', 'mini');
        var im = document.createElement('img'); im.src = f;
        var x = el('button', null, '×'); x.type = 'button';
        x.onclick = function () { fotoForm.splice(i, 1); gambarKumpulan(); };
        w.append(im, x); kumpulan.append(w);
      });
      tombolBaca.hidden = !fotoForm.length;
      tombolBaca.textContent = 'Baca ' + fotoForm.length + ' foto dengan AI';
    }

    function tambahFoto(input) {
      var berkas = Array.prototype.slice.call(input.files, 0, 3 - fotoForm.length);
      input.value = '';
      if (!berkas.length) { if (fotoForm.length >= 3) st.textContent = 'Maksimal 3 foto.'; return; }
      st.textContent = '⏳ Menyiapkan foto…';
      Promise.all(berkas.map(function (b) { return kecilkanFoto(b); }))
        .then(function (f) { fotoForm = fotoForm.concat(f); st.textContent = ''; gambarKumpulan(); })
        .catch(function (e) { st.textContent = '❌ ' + e.message; });
    }
    fotoBuku.addEventListener('change', function () { tambahFoto(fotoBuku); });
    var kamera = document.getElementById('foto-buku-kamera');
    if (kamera) kamera.addEventListener('change', function () { tambahFoto(kamera); });

    tombolBaca.addEventListener('click', function () {
      if (!fotoForm.length) return;
      tombolBaca.disabled = true;
      st.textContent = '⏳ AI sedang membaca foto (5–40 detik)…';
      kirimAI(form.dataset.token, { aksi: 'baca_foto', foto: fotoForm })
        .then(function (r) {
          tombolBaca.disabled = false;
          st.innerHTML = '';
          if (!r.ok) {
            st.append(el('div', 'pesan galat', r.galat || 'Gagal membaca foto.'));
            (r.rincian || []).forEach(function (x) { st.append(el('div', 'redup kecil', x)); });
            return;
          }
          fotoForm = []; gambarKumpulan();
          var d = r.data, isi = function (nama, nilai) {
            var f = form.elements[nama];
            if (f && nilai !== undefined && nilai !== '') f.value = nilai;
          };
          ['judul', 'pengarang', 'sor', 'penerbit', 'tempat', 'tahun', 'isbn', 'edisi', 'kolasi', 'seri', 'klasifikasi', 'subjek', 'catatan', 'sumber']
            .forEach(function (k) { isi(k, d[k]); });
          if (d.bahasa && form.elements.bahasa) form.elements.bahasa.value = d.bahasa;
          if (d.prefix) {
            var m = d.prefix.match(/^([A-Z]{3})([7-9])(G?)$/);
            var jenis = document.getElementById('jenis');
            if (m && document.querySelector('#mapel option[value="' + m[1] + '"]')) {
              jenis.value = 'PAKET';
              document.getElementById('mapel').value = m[1];
              document.getElementById('kelas_buku').value = m[2];
              document.getElementById('buku_guru').checked = !!m[3];
            } else if (document.querySelector('#jenis option[value="' + d.prefix + '"]')) {
              jenis.value = d.prefix;
            }
            jenis.dispatchEvent(new Event('change'));
          }
          var ok = el('div', 'pesan ok');
          ok.append('✅ Kolom terisi (oleh ' + r.oleh + '). Periksa lagi, terutama ejaan nama dan ISBN, lalu tekan Simpan.');
          if (d.tidak_terbaca) ok.append(el('div', 'kecil', 'Perlu dicek: ' + d.tidak_terbaca));
          st.append(ok);
          if (r.mirip && r.mirip.length) {
            var w = el('div', 'pesan peringatan');
            w.append('Mungkin sudah ada di perpustakaan: ');
            r.mirip.forEach(function (b) {
              var a = el('a', null, b.judul + (b.kode ? ' (' + b.kode + ')' : ''));
              a.href = '?p=buku_detail&id=' + b.biblio_id; a.target = '_blank';
              w.append(a, ' ');
            });
            st.append(w);
          }
        });
    });
  }

  /* ----- Asisten chat ----- */
  var chat = document.getElementById('chat');
  if (chat) {
    var token = chat.dataset.token;
    var formChat = document.getElementById('chat-form');
    var teks = document.getElementById('chat-teks');
    var tombol = document.getElementById('chat-kirim');
    var inputFoto = document.getElementById('chat-foto');
    var prat = document.getElementById('pratinjau-foto');
    var fotoSiap = [];

    chat.querySelectorAll('[data-md]').forEach(function (g) { g.innerHTML = md(g.textContent); });
    function gulir() { window.scrollTo(0, document.body.scrollHeight); }
    gulir();

    teks.addEventListener('input', function () {
      teks.style.height = 'auto';
      teks.style.height = Math.min(teks.scrollHeight, 160) + 'px';
    });
    teks.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && window.matchMedia('(pointer:fine)').matches) { e.preventDefault(); formChat.requestSubmit(); }
    });

    function gambarPratinjau() {
      prat.innerHTML = '';
      fotoSiap.forEach(function (f, i) {
        var w = el('span', 'mini');
        var im = document.createElement('img'); im.src = f;
        var x = el('button', null, '×'); x.type = 'button';
        x.onclick = function () { fotoSiap.splice(i, 1); gambarPratinjau(); };
        w.append(im, x); prat.append(w);
      });
    }
    function ambilFoto(input) {
      var b = Array.prototype.slice.call(input.files, 0, 4 - fotoSiap.length);
      input.value = '';
      Promise.all(b.map(function (x) { return kecilkanFoto(x); })).then(function (f) {
        fotoSiap = fotoSiap.concat(f); gambarPratinjau();
      });
    }
    inputFoto.addEventListener('change', function () { ambilFoto(inputFoto); });
    var kameraChat = document.getElementById('chat-kamera');
    if (kameraChat) kameraChat.addEventListener('change', function () { ambilFoto(kameraChat); });

    function kartuUsulan(u) {
      var k = el('div', 'usulan');
      k.append(el('div', 'usulan-judul', '📝 ' + u.judul));
      var dl = el('dl');
      u.baris.forEach(function (b) { dl.append(el('dt', null, b[0]), el('dd', null, b[1])); });
      k.append(dl);
      var aksi = el('div', 'usulan-aksi');
      var simpan = el('button', 'utama', 'Simpan'); simpan.type = 'button';
      var batal = el('button', null, 'Batal'); batal.type = 'button';
      aksi.append(simpan);
      if (u.formulir) { var f = el('a', 'tombol', 'Ubah di formulir'); f.href = u.formulir; aksi.append(f); }
      aksi.append(batal);
      k.append(aksi);
      var hasil = el('div', 'usulan-hasil');
      k.append(hasil);
      simpan.onclick = function () {
        simpan.disabled = batal.disabled = true; simpan.textContent = 'Menyimpan…';
        kirimAI(token, { aksi: 'simpan', id: u.id }).then(function (r) {
          if (r.ok) {
            aksi.remove();
            hasil.className = 'usulan-hasil ok';
            hasil.textContent = '✅ ' + r.teks + ' ';
            if (r.tautan) { var a = el('a', null, 'Lihat →'); a.href = r.tautan; hasil.append(a); }
          } else {
            simpan.disabled = batal.disabled = false; simpan.textContent = 'Simpan';
            hasil.className = 'usulan-hasil galat'; hasil.textContent = r.teks || r.galat;
          }
        });
      };
      batal.onclick = function () {
        kirimAI(token, { aksi: 'batal', id: u.id });
        aksi.remove(); hasil.className = 'usulan-hasil'; hasil.textContent = 'Dibatalkan.';
      };
      return k;
    }

    formChat.addEventListener('submit', function (e) {
      e.preventDefault();
      var isi = teks.value.trim();
      if (!isi && !fotoSiap.length) return;
      var g = el('div', 'gelembung saya');
      if (isi) g.append(el('div', null, isi));
      fotoSiap.forEach(function (f) { var im = document.createElement('img'); im.src = f; im.className = 'foto-chat'; g.append(im); });
      chat.append(g);
      var tunggu = el('div', 'gelembung ai menunggu', 'Sedang berpikir…');
      chat.append(tunggu); gulir();
      var foto = fotoSiap; fotoSiap = []; gambarPratinjau();
      teks.value = ''; teks.style.height = 'auto';
      tombol.disabled = true;
      kirimAI(token, { aksi: 'chat', pesan: isi, foto: foto }).then(function (r) {
        tunggu.remove();
        var b = el('div', 'gelembung ai' + (r.ok ? '' : ' galat'));
        b.innerHTML = md(r.teks || r.galat || 'Maaf, terjadi kesalahan.');
        if (r.oleh) b.append(el('small', 'oleh', r.oleh));
        if (!r.ok && r.rincian && r.rincian.length) {
          var det = document.createElement('details'); det.append(el('summary', null, 'Rincian'));
          r.rincian.forEach(function (x) { det.append(el('div', 'kecil', x)); }); b.append(det);
        }
        chat.append(b);
        (r.usulan || []).forEach(function (u) { chat.append(kartuUsulan(u)); });
        tombol.disabled = false; gulir();
      });
    });

    document.getElementById('chat-baru').addEventListener('click', function () {
      if (!confirm('Mulai percakapan baru? Usulan yang belum disimpan akan hilang.')) return;
      kirimAI(token, { aksi: 'baru' }).then(function () { location.reload(); });
    });
  }
})();
