/*
 * Jembatan Scanner SiPustaka (sisi laptop).
 * Dimuat lewat bookmark di halaman web mana pun (mis. Sirkulasi SLiMS).
 * Mengambil kode hasil pindaian HP lalu mengetikkannya ke kolom yang sedang diklik.
 */
(function () {
  'use strict';
  if (window.__sipustakaScanner) { window.__sipustakaScanner.tampil(); return; }

  var src = (document.currentScript && document.currentScript.src) || '';
  if (!src) {
    var semua = document.querySelectorAll('script[src*="jembatan.js"]');
    src = semua.length ? semua[semua.length - 1].src : '';
  }
  var token = (src.split('#')[1] || '').trim();
  var dasar = src.split('?')[0].replace(/aset\/jembatan\.js$/, '');
  var titik = dasar + 'scan.php';

  var namaTerakhir = null;
  function namaSlims() {
    // Nama akun yang sedang login di halaman admin SLiMS
    var el = document.querySelector('.s-user-name');
    try { if (!el && window.top !== window) el = window.top.document.querySelector('.s-user-name'); } catch (e) {}
    return el ? el.textContent.replace(/\s+/g, ' ').trim() : '';
  }
  var terakhir = -1, aktif = true, gagalBeruntun = 0, timer = null, kolomTerakhir = null, audio = null;

  /* ---------- Kotak status (Shadow DOM agar gaya web tidak bentrok) ---------- */
  var host = document.createElement('div');
  host.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:2147483647';
  var akar = host.attachShadow ? host.attachShadow({ mode: 'open' }) : host;
  akar.innerHTML =
    '<style>' +
    '.k{font:600 14px/1.35 Quicksand,"Segoe UI",system-ui,sans-serif;background:linear-gradient(135deg,#2A2C84,#1B1D5E);color:#fff;' +
    'border-radius:16px;padding:10px 12px;box-shadow:0 6px 24px rgba(0,0,0,.28);min-width:230px;max-width:300px;border-bottom:4px solid #F2C230}' +
    '.a{display:flex;align-items:center;gap:8px}.j{flex:1;color:#F2C230;font-size:11px;letter-spacing:.1em;text-transform:uppercase}' +
    'button{all:unset;cursor:pointer;width:24px;height:24px;border-radius:50%;text-align:center;line-height:24px;background:rgba(255,255,255,.14)}' +
    'button:hover{background:rgba(255,255,255,.28)}.s{margin-top:6px;font-size:13px;opacity:.95}' +
    '.kode{margin-top:6px;background:#F2C230;color:#1B1D5E;border-radius:10px;padding:6px 8px;font-size:16px;font-weight:700;word-break:break-all;display:none}' +
    '.titik{width:9px;height:9px;border-radius:50%;background:#2E9E48;box-shadow:0 0 0 3px rgba(46,158,72,.3)}' +
    '.akun{margin-top:4px;font-size:12px;color:#A0D9F6}.akun:empty{display:none}' +
    '.titik.mati{background:#aaa;box-shadow:none}.titik.galat{background:#e05555;box-shadow:0 0 0 3px rgba(224,85,85,.3)}' +
    '</style>' +
    '<div class="k"><div class="a"><span class="titik"></span><span class="j">Scanner SiPustaka</span>' +
    '<button class="jeda" title="Jeda">⏸</button><button class="tutup" title="Tutup">✕</button></div>' +
    '<div class="akun"></div><div class="s">Klik kolom isian, lalu pindai dengan HP.</div><div class="kode"></div></div>';
  document.documentElement.appendChild(host);
  var elAkun = akar.querySelector('.akun');
  var elStatus = akar.querySelector('.s'), elKode = akar.querySelector('.kode'), elTitik = akar.querySelector('.titik');
  var btnJeda = akar.querySelector('.jeda');

  function status(teks, jenis) {
    elStatus.textContent = teks;
    elTitik.className = 'titik' + (jenis ? ' ' + jenis : '');
  }

  /* ---------- Melacak kolom yang terakhir diklik (termasuk di dalam iframe) ---------- */
  function bisaDiisi(el) {
    if (!el || !el.tagName) return false;
    if (el.isContentEditable) return true;
    if (el.tagName === 'TEXTAREA') return !el.readOnly && !el.disabled;
    if (el.tagName !== 'INPUT') return false;
    var t = (el.type || 'text').toLowerCase();
    return ['text', 'search', 'number', 'tel', 'email', 'url', ''].indexOf(t) >= 0 && !el.readOnly && !el.disabled;
  }
  function pasangPelacak(doc) {
    if (!doc || doc.__sipustakaPelacak) return;
    doc.__sipustakaPelacak = true;
    doc.addEventListener('focusin', function (e) { if (bisaDiisi(e.target)) kolomTerakhir = e.target; }, true);
  }
  function telusuriIframe(doc) {
    pasangPelacak(doc);
    var f = doc.querySelectorAll('iframe,frame');
    for (var i = 0; i < f.length; i++) {
      try { if (f[i].contentDocument) telusuriIframe(f[i].contentDocument); } catch (e) { /* lintas domain */ }
    }
  }
  telusuriIframe(document);
  var pelacakTimer = setInterval(function () { telusuriIframe(document); }, 2000);

  function kolomAktif(doc) {
    var el = doc.activeElement;
    if (el && (el.tagName === 'IFRAME' || el.tagName === 'FRAME')) {
      try { return kolomAktif(el.contentDocument); } catch (e) { return null; }
    }
    return bisaDiisi(el) ? el : null;
  }

  /* ---------- Mengetik kode ke kolom ---------- */
  function setNilai(el, nilai) {
    var proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    var desk = Object.getOwnPropertyDescriptor(proto, 'value');
    if (desk && desk.set) desk.set.call(el, nilai); else el.value = nilai;
  }
  function ketik(el, kode) {
    el.focus();
    if (el.isContentEditable) {
      el.ownerDocument.execCommand('insertText', false, kode);
      return;
    }
    var awal = null, akhir = null;
    try { awal = el.selectionStart; akhir = el.selectionEnd; } catch (e) {}
    if (awal !== null && awal !== undefined) {
      var v = el.value;
      setNilai(el, v.slice(0, awal) + kode + v.slice(akhir));
      try { el.setSelectionRange(awal + kode.length, awal + kode.length); } catch (e) {}
    } else {
      setNilai(el, (el.value || '') + kode);
    }
    var Ev = el.ownerDocument.defaultView;
    el.dispatchEvent(new Ev.Event('input', { bubbles: true }));
    el.dispatchEvent(new Ev.Event('change', { bubbles: true }));
  }
  function tombolEnter(el) {
    var win = el.ownerDocument.defaultView;
    function kirimKey(tipe) {
      var ev = new win.KeyboardEvent(tipe, { key: 'Enter', code: 'Enter', bubbles: true, cancelable: true });
      try {
        Object.defineProperty(ev, 'keyCode', { get: function () { return 13; } });
        Object.defineProperty(ev, 'which', { get: function () { return 13; } });
        Object.defineProperty(ev, 'charCode', { get: function () { return tipe === 'keypress' ? 13 : 0; } });
      } catch (e) {}
      return el.dispatchEvent(ev);
    }
    var lanjut = kirimKey('keydown');
    if (lanjut) lanjut = kirimKey('keypress');
    // Perilaku bawaan Enter pada kolom formulir = kirim formulir
    if (lanjut && el.form && el.tagName === 'INPUT') {
      if (typeof el.form.requestSubmit === 'function') el.form.requestSubmit();
      else {
        var b = el.form.querySelector('[type=submit]');
        if (b) b.click(); else el.form.submit();
      }
    }
    kirimKey('keyup');
  }

  function bunyi() {
    try {
      audio = audio || new (window.AudioContext || window.webkitAudioContext)();
      var o = audio.createOscillator(), g = audio.createGain();
      o.frequency.value = 1200; g.gain.value = 0.05;
      o.connect(g); g.connect(audio.destination); o.start(); o.stop(audio.currentTime + 0.08);
    } catch (e) {}
  }

  function terima(item) {
    elKode.style.display = 'block';
    elKode.textContent = item.kode;
    var el = kolomAktif(document) || (kolomTerakhir && kolomTerakhir.isConnected ? kolomTerakhir : null);
    if (!el) {
      status('Kode diterima, tapi belum ada kolom yang diklik. Klik kolomnya lalu pindai lagi.', 'galat');
      return;
    }
    ketik(el, item.kode);
    if (item.enter) tombolEnter(el);
    bunyi();
    status('✓ Terketik' + (item.enter ? ' + Enter' : '') + '. Siap pindai berikutnya.');
  }

  /* ---------- Mengambil kode dari server ---------- */
  function ambil() {
    if (!aktif) return;
    var ctrl = window.AbortController ? new AbortController() : null;
    var batas = setTimeout(function () { if (ctrl) ctrl.abort(); }, 8000);
    var nama = namaSlims();
    if (nama !== namaTerakhir) { namaTerakhir = nama; terakhir = -1; }
    fetch(titik + '?t=' + encodeURIComponent(token) + '&s=' + terakhir + (nama ? '&n=' + encodeURIComponent(nama) : ''), { cache: 'no-store', signal: ctrl ? ctrl.signal : undefined })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        clearTimeout(batas);
        if (!d.ok) { status(d.galat || 'Ditolak server.', 'galat'); aktif = false; return; }
        elAkun.textContent = d.akun ? 'Akun: ' + d.akun : '';
        if (d.menunggu) { status(d.pesan, 'galat'); terakhir = -1; return; }
        if (gagalBeruntun) status('Tersambung lagi. Klik kolom isian, lalu pindai dengan HP.');
        gagalBeruntun = 0;
        if (terakhir < 0) { terakhir = d.n; status('Aktif. Klik kolom isian, lalu pindai dengan HP' + (d.akun ? ' (login SiPustaka sebagai ' + d.akun + ')' : '') + '.'); }
        (d.kode || []).forEach(function (it) { if (it.id > terakhir) { terakhir = it.id; terima(it); } });
        if (d.n > terakhir) terakhir = d.n;
      })
      .catch(function () {
        clearTimeout(batas);
        gagalBeruntun++;
        if (gagalBeruntun >= 3) status('Koneksi ke server terputus, mencoba lagi…', 'galat');
      })
      .then(jadwal);
  }
  function jadwal() {
    if (!aktif) return;
    var jeda = document.hidden ? 3000 : (gagalBeruntun ? Math.min(2000 * gagalBeruntun, 10000) : 900);
    timer = setTimeout(ambil, jeda);
  }

  btnJeda.addEventListener('click', function () {
    aktif = !aktif;
    btnJeda.textContent = aktif ? '⏸' : '▶';
    if (aktif) { status('Aktif lagi. Klik kolom isian, lalu pindai.'); terakhir = -1; ambil(); }
    else { clearTimeout(timer); status('Dijeda. Tekan ▶ untuk melanjutkan.', 'mati'); }
  });
  akar.querySelector('.tutup').addEventListener('click', function () {
    aktif = false; clearTimeout(timer); clearInterval(pelacakTimer);
    host.remove(); delete window.__sipustakaScanner;
  });

  window.__sipustakaScanner = {
    tampil: function () { host.style.display = ''; if (!aktif) btnJeda.click(); }
  };

  if (!/^[a-f0-9]{24}$/.test(token)) { status('Bookmark rusak. Pasang ulang dari halaman Scanner SiPustaka.', 'galat'); return; }
  status('Menyambungkan…', 'mati');
  ambil();
})();
