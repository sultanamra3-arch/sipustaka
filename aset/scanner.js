/* Scanner SiPustaka (sisi HP): pindai barcode lalu kirim ke laptop. */
(function () {
  'use strict';
  var token = window.SCANNER_TOKEN_FORM;
  var statusEl = document.getElementById('status-laptop');
  var riwayat = document.getElementById('riwayat-scan');
  var btnMulai = document.getElementById('kamera-mulai');
  var btnHenti = document.getElementById('kamera-henti');
  var cekEnter = document.getElementById('kirim-enter');
  var pembaca = null, berjalan = false, terakhir = '', waktuTerakhir = 0, audio = null;

  try { var simpan = localStorage.getItem('sipustaka_enter'); if (simpan !== null) cekEnter.checked = simpan === '1'; } catch (e) {}
  cekEnter.addEventListener('change', function () { try { localStorage.setItem('sipustaka_enter', cekEnter.checked ? '1' : '0'); } catch (e) {} });

  // Di laptop/PC, tampilkan bagian laptop lebih dulu
  if (!window.matchMedia('(pointer:coarse)').matches) {
    var hp = document.getElementById('hp'), lp = document.getElementById('laptop');
    hp.parentNode.insertBefore(lp, hp);
  }

  function bunyi() {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain();
      o.frequency.value = 1400; g.gain.value = 0.08;
      o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + 0.09);
    } catch (e) {}
    if (navigator.vibrate) navigator.vibrate(60);
  }

  function post(data) {
    var fd = new FormData();
    fd.append('_t', token);
    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
    return fetch('?p=api_scan', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .catch(function () { return { ok: false, galat: 'Tidak tersambung ke server' }; });
  }

  function tampilStatus(s) {
    if (s && s.laptop) {
      statusEl.className = 'status-laptop ok';
      statusEl.textContent = '✅ Laptop tersambung' + (s.perangkat ? ' (' + s.perangkat + ')' : '') + ' — klik kolom isian di laptop, lalu pindai.';
    } else {
      statusEl.className = 'status-laptop';
      statusEl.textContent = '⚠️ Laptop belum tersambung. Di laptop, buka web tujuan lalu klik bookmark "📡 Scanner SiPustaka".';
    }
  }
  function cekStatus() {
    fetch('?p=api_scan&aksi=status', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); }).then(tampilStatus).catch(function () {});
  }
  cekStatus();
  setInterval(function () { if (!document.hidden) cekStatus(); }, 4000);

  function kirim(kode) {
    kode = String(kode || '').trim();
    if (!kode) return;
    var li = document.createElement('li');
    var b = document.createElement('b'); b.textContent = kode;
    var st = document.createElement('small'); st.textContent = 'mengirim…';
    li.append(b, st);
    riwayat.prepend(li);
    while (riwayat.children.length > 8) riwayat.lastChild.remove();
    post({ aksi: 'kirim', kode: kode, enter: cekEnter.checked ? '1' : '0' }).then(function (r) {
      if (r.ok) {
        st.textContent = r.laptop ? '✓ terkirim ke laptop' : '✓ terkirim (laptop belum tersambung)';
        li.className = 'ok';
        tampilStatus(r);
      } else {
        st.textContent = '✗ ' + (r.galat || 'gagal');
        li.className = 'galat';
      }
    });
  }

  function terbaca(teks) {
    var kini = Date.now();
    if (teks === terakhir && kini - waktuTerakhir < 2500) return; // abaikan bacaan ganda
    terakhir = teks; waktuTerakhir = kini;
    bunyi();
    kirim(teks);
  }

  function mulai() {
    if (berjalan) return;
    if (typeof Html5Qrcode === 'undefined') { statusEl.textContent = 'Pustaka pemindai gagal dimuat. Muat ulang halaman.'; return; }
    var F = Html5QrcodeSupportedFormats;
    pembaca = pembaca || new Html5Qrcode('pembaca', {
      formatsToSupport: [F.CODE_128, F.CODE_39, F.CODE_93, F.EAN_13, F.EAN_8, F.UPC_A, F.UPC_E, F.ITF, F.CODABAR, F.QR_CODE],
      experimentalFeatures: { useBarCodeDetectorIfSupported: true },
      verbose: false
    });
    btnMulai.disabled = true; btnMulai.textContent = 'Membuka kamera…';
    pembaca.start({ facingMode: 'environment' }, {
      fps: 12,
      qrbox: function (w, h) { return { width: Math.floor(w * 0.88), height: Math.floor(Math.min(h * 0.55, w * 0.5)) }; },
      aspectRatio: 1.333
    }, terbaca, function () {})
      .then(function () {
        berjalan = true; btnHenti.disabled = false; btnMulai.textContent = '📷 Kamera aktif';
      })
      .catch(function (e) {
        btnMulai.disabled = false; btnMulai.textContent = '📷 Mulai kamera';
        statusEl.className = 'status-laptop';
        statusEl.textContent = '❌ Kamera tidak bisa dibuka: ' + (e && e.message ? e.message : e) + '. Izinkan akses kamera untuk situs ini.';
      });
  }
  function henti() {
    if (!berjalan || !pembaca) return Promise.resolve();
    return pembaca.stop().then(function () {
      berjalan = false; btnHenti.disabled = true; btnMulai.disabled = false; btnMulai.textContent = '📷 Mulai kamera';
    }).catch(function () {});
  }
  btnMulai.addEventListener('click', mulai);
  btnHenti.addEventListener('click', henti);
  // Hemat baterai: matikan kamera saat aplikasi ditinggal
  document.addEventListener('visibilitychange', function () {
    if (document.hidden && berjalan) { henti().then(function () { btnMulai.dataset.lanjut = '1'; }); }
    else if (!document.hidden && btnMulai.dataset.lanjut) { delete btnMulai.dataset.lanjut; mulai(); }
  });

  document.getElementById('manual').addEventListener('submit', function (e) {
    e.preventDefault();
    var i = document.getElementById('manual-kode');
    kirim(i.value); i.value = '';
  });
})();
